<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;

class FuturesAnalysisService
{
    public function __construct(
        protected FuturesKlinesClient $client,
        protected FvgAnalyzer $fvg,
        protected SwingStructureAnalyzer $swings,
        protected OrderBlockAnalyzer $orderBlocks,
        protected LiquiditySweepAnalyzer $liquiditySweeps,
        protected OteFibonacciAnalyzer $oteFib,
        protected TradeSetupAnalyzer $tradeSetup,
        protected MarketContextAnalyzer $marketContext,
    ) {}

    public static function make(): self
    {
        $order = config('crypto.auto_provider_order');
        $order = is_array($order) ? $order : [];

        $client = match (config('crypto.data_provider')) {
            'auto' => new FallbackFuturesClient(
                FuturesClientFactory::makeChain($order),
                $order,
            ),
            'binance', 'bybit', 'okx', 'cryptocompare' => FuturesClientFactory::make(config('crypto.data_provider')),
            default => new FallbackFuturesClient(
                FuturesClientFactory::makeChain($order),
                $order,
            ),
        };

        return new self(
            $client,
            new FvgAnalyzer,
            new SwingStructureAnalyzer,
            new OrderBlockAnalyzer,
            new LiquiditySweepAnalyzer,
            new OteFibonacciAnalyzer,
            new TradeSetupAnalyzer,
            new MarketContextAnalyzer,
        );
    }

    /**
     * Hanya fetch kline + chart_candles — untuk polling cepat (tanpa FVG/analisis).
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function chartSnapshot(string $symbol, string $interval, int $limit): array
    {
        $symbol = strtoupper(trim($symbol));
        $raw = $this->client->klines($symbol, $interval, $limit);
        $candles = Candle::collectionFromRaw($raw);

        $chartCandles = [];
        foreach ($candles as $c) {
            $chartCandles[] = [
                'time' => (int) floor(((int) $c['open_time']) / 1000),
                'open' => (float) $c['open'],
                'high' => (float) $c['high'],
                'low' => (float) $c['low'],
                'close' => (float) $c['close'],
            ];
        }

        return [
            'symbol' => $symbol,
            'interval' => $interval,
            'candles_count' => count($candles),
            'last_candle' => $candles !== [] ? end($candles) : null,
            'chart_candles' => $chartCandles,
            'chart_only' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function analyze(string $symbol, string $interval, int $limit): array
    {
        $symbol = strtoupper(trim($symbol));
        $raw = $this->client->klines($symbol, $interval, $limit);
        $candles = Candle::collectionFromRaw($raw);

        $fvgs = $this->fvg->detect($candles);
        $structure = $this->swings->analyze($candles);
        $obs = $this->orderBlocks->detect($candles);

        $liquidity = $this->liquiditySweeps->detect(
            $candles,
            $structure['swing_highs'] ?? [],
            $structure['swing_lows'] ?? [],
        );

        $ote = $this->oteFib->analyze($structure, $fvgs);

        $lastClose = $candles !== [] ? (float) $candles[array_key_last($candles)]['close'] : 0.0;
        $levels = $this->tradeSetup->build($structure, $fvgs, $obs, $ote, $lastClose);

        $setupForContext = ! empty($levels['has_setup']) ? $levels : null;
        $marketContext = $this->marketContext->analyze($candles, $structure, $setupForContext, $lastClose);

        $recentFvgs = array_values(array_filter($fvgs, fn ($f) => ($f['fill_status'] ?? '') !== 'filled'));
        $recentFvgs = array_slice($recentFvgs, -15);

        $narrative = $this->buildNarrative($symbol, $interval, $structure, $recentFvgs, $obs, $liquidity, $ote, $levels, $marketContext);

        $signalCard = '';
        if (! empty($levels['has_setup'])) {
            $signalCard = SignalCardFormatter::format([
                'symbol' => $symbol,
                'trade_setup' => $levels,
                'structure' => $structure,
                'ote_fibonacci' => $ote,
                'liquidity_sweeps' => $liquidity,
            ], $interval, (int) config('crypto.default_leverage', 10));
        }

        $chartCandles = [];
        foreach ($candles as $c) {
            $chartCandles[] = [
                'time' => (int) floor(((int) $c['open_time']) / 1000),
                'open' => (float) $c['open'],
                'high' => (float) $c['high'],
                'low' => (float) $c['low'],
                'close' => (float) $c['close'],
            ];
        }

        $tz = (string) config('app.timezone', 'UTC');
        $analyzedAt = Carbon::now();
        $lastCandleRow = $candles !== [] ? end($candles) : null;
        $lastCandleOpenAt = null;
        if (is_array($lastCandleRow) && isset($lastCandleRow['open_time'])) {
            $lastCandleOpenAt = Carbon::createFromTimestampMs((int) $lastCandleRow['open_time'])
                ->timezone($tz)
                ->format('Y-m-d H:i:s');
        }

        return [
            'symbol' => $symbol,
            'interval' => $interval,
            'candles_count' => count($candles),
            'last_candle' => $lastCandleRow,
            'analyzed_at' => $analyzedAt->format('Y-m-d H:i:s'),
            'analyzed_timezone' => $tz,
            'last_candle_open_at' => $lastCandleOpenAt,
            'chart_candles' => $chartCandles,
            'fvgs' => array_slice($fvgs, -20),
            'fvgs_active' => $recentFvgs,
            'structure' => $structure,
            'order_blocks' => $obs,
            'liquidity_sweeps' => $liquidity,
            'ote_fibonacci' => $ote,
            'trade_setup' => $levels,
            'market_context' => $marketContext,
            'signal_card' => $signalCard,
            'narrative' => $narrative,
        ];
    }

    /**
     * @param  array<string, mixed>  $structure
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     * @param  list<array<string, mixed>>  $liquidity
     * @param  array<string, mixed>  $ote
     * @param  array<string, mixed>  $tradeSetup
     * @param  array<string, mixed>  $marketContext
     * @return list<string>
     */
    private function buildNarrative(
        string $symbol,
        string $interval,
        array $structure,
        array $fvgs,
        array $obs,
        array $liquidity,
        array $ote,
        array $tradeSetup,
        array $marketContext,
    ): array {
        $lines = [];
        $lines[] = 'Ringkasan otomatis SMC/price action (bukan sinyal finansial): '.$symbol.' @ '.$interval.'.';

        $bos = $structure['bos'] ?? null;
        if ($bos) {
            $dir = $bos['direction'] === 'bullish' ? 'naik' : 'turun';
            $lines[] = 'BOS: penutupan terakhir menembus swing terakhir ke arah '.$dir.'.';
        } else {
            $lines[] = 'BOS: belum terdeteksi vs swing fractal terakhir.';
        }

        $cm = $structure['choch_mss'] ?? null;
        if (is_array($cm)) {
            $lines[] = 'MSS/ChoCh: '.$cm['note'];
        } else {
            $lines[] = 'MSS/ChoCh: tidak terdeteksi pola break higher-low / lower-high sederhana pada swing terakhir.';
        }

        $activeBull = array_values(array_filter($fvgs, fn ($f) => $f['direction'] === 'bullish'));
        $activeBear = array_values(array_filter($fvgs, fn ($f) => $f['direction'] === 'bearish'));

        if ($activeBull !== []) {
            $last = $activeBull[array_key_last($activeBull)];
            $lines[] = sprintf(
                'FVG bullish aktif terakhir: zona %.8f – %.8f (status %s).',
                $last['zone_low'],
                $last['zone_high'],
                $last['fill_status'] ?? 'unknown'
            );
        }
        if ($activeBear !== []) {
            $last = $activeBear[array_key_last($activeBear)];
            $lines[] = sprintf(
                'FVG bearish aktif terakhir: zona %.8f – %.8f (status %s).',
                $last['zone_low'],
                $last['zone_high'],
                $last['fill_status'] ?? 'unknown'
            );
        }

        $oteConf = $ote['fvg_ote_confluence'] ?? [];
        if ($oteConf !== []) {
            $lines[] = 'Konfluensi FVG × zona Fibonacci OTE (62–79% / golden pocket): terdeteksi '.count($oteConf).' potongan zona yang berhimpit.';
        } elseif (($ote['impulse'] ?? null) !== null) {
            $lines[] = 'Fibonacci OTE: impuls terakhir terbaca; belum ada FVG aktif yang berhimpit jelas dengan zona OTE.';
        }

        if ($obs !== []) {
            $lastOb = $obs[array_key_last($obs)];
            $t = $lastOb['type'] === 'bullish_ob' ? 'Bullish OB' : 'Bearish OB';
            $lines[] = sprintf(
                'Order block: %s terakhir zona %.8f – %.8f (lilin lawan sebelum displacement).',
                $t,
                $lastOb['zone_low'],
                $lastOb['zone_high']
            );
        }

        if ($liquidity !== []) {
            $lastSweep = $liquidity[array_key_last($liquidity)];
            $lines[] = 'Liquidity sweep terakhir: '.($lastSweep['type'] === 'sweep_high' ? 'ambil likuiditas di atas' : 'ambil likuiditas di bawah').' (wick lewati level lalu close kembali).';
        } else {
            $lines[] = 'Liquidity sweep: tidak ada pola wick vs swing terakhir pada candle terbaru (rentang deteksi terbatas).';
        }

        if (! empty($tradeSetup['has_setup'])) {
            $lines[] = sprintf(
                'Level struktural (%s): zona entry %.8f – %.8f · SL %.8f · TP1 %.8f · TP2 %.8f · TP3 %.8f (RR TP1 ≈ %.2f).',
                $tradeSetup['basis'] ?? 'setup',
                $tradeSetup['entry_zone_low'],
                $tradeSetup['entry_zone_high'],
                $tradeSetup['stop_loss'],
                $tradeSetup['take_profit_1'],
                $tradeSetup['take_profit_2'],
                $tradeSetup['take_profit_3'] ?? 0,
                $tradeSetup['rr_tp1'] ?? 0
            );
        } elseif (isset($tradeSetup['reason'])) {
            $lines[] = 'Tidak ada setup entry/TP/SL otomatis: '.$tradeSetup['reason'];
        }

        $atr = $marketContext['atr'] ?? [];
        if (isset($atr['period_14']) && $atr['period_14'] !== null) {
            $lines[] = sprintf(
                'ATR(14) ≈ %.8f (%s%% dari harga) — bandingkan dengan SL/TP alternatif berbasis ATR di panel konteks.',
                $atr['period_14'],
                $atr['pct_of_price_14'] ?? '—'
            );
        }

        $pd = $marketContext['premium_discount'] ?? [];
        if (! empty($pd['available'])) {
            $lines[] = 'Premium/Discount (swing terakhir): '.$pd['note'];
        }

        $lines[] = 'Konfirmasi manual (HTF, konteks berita) tetap diperlukan — tidak ada indikator yang selalu benar.';

        return $lines;
    }
}

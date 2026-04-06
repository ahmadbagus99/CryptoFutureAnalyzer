<?php

namespace App\Services;

/**
 * Konteks tambahan: ATR, premium/discount, level kunci, SL/TP alternatif berbasis volatilitas.
 */
final class MarketContextAnalyzer
{
    /**
     * @param  list<array<string, float|int>>  $candles
     * @param  array<string, mixed>  $structure
     * @param  array<string, mixed>|null  $tradeSetup
     * @return array<string, mixed>
     */
    public function analyze(array $candles, array $structure, ?array $tradeSetup, float $lastClose): array
    {
        $atr14 = $this->atr($candles, 14);
        $atr7 = $this->atr($candles, 7);

        $pd = $this->premiumDiscount($structure, $lastClose);

        $levels = $this->keyLevels($structure, $lastClose);

        $vol = $this->volumeContext($candles);

        $atrTrade = $this->atrAdjustedLevels($tradeSetup, $atr14);

        return [
            'atr' => [
                'period_14' => $atr14,
                'period_7' => $atr7,
                'pct_of_price_14' => $atr14 !== null && $lastClose > 0
                    ? round($atr14 / $lastClose * 100, 4)
                    : null,
                'note' => 'ATR = kisaran gerak rata-rata; dipakai untuk jarak SL/TP relatif terhadap volatilitas.',
            ],
            'premium_discount' => $pd,
            'key_levels' => $levels,
            'volume' => $vol,
            'atr_trade_levels' => $atrTrade,
        ];
    }

    /**
     * @param  list<array<string, float|int>>  $candles
     */
    private function atr(array $candles, int $period): ?float
    {
        $n = count($candles);
        if ($n < $period + 1) {
            return null;
        }

        $trs = [];
        for ($i = 1; $i < $n; $i++) {
            $h = (float) $candles[$i]['high'];
            $l = (float) $candles[$i]['low'];
            $pc = (float) $candles[$i - 1]['close'];
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        $slice = array_slice($trs, -$period);
        if ($slice === []) {
            return null;
        }

        return array_sum($slice) / count($slice);
    }

    /**
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    private function premiumDiscount(array $structure, float $lastClose): array
    {
        $sh = $structure['last_swing_high'] ?? null;
        $sl = $structure['last_swing_low'] ?? null;
        if (! is_array($sh) || ! is_array($sl)) {
            return [
                'available' => false,
                'note' => 'Butuh swing high & low terakhir untuk range PD.',
            ];
        }

        $hi = (float) $sh['price'];
        $lo = (float) $sl['price'];
        $range = abs($hi - $lo);
        if ($range < 1e-12) {
            return ['available' => false, 'note' => 'Range swing nol.'];
        }

        $low = min($hi, $lo);
        $high = max($hi, $lo);
        $eq = ($low + $high) / 2.0;
        $pos = ($lastClose - $low) / $range * 100.0;
        $pos = max(0.0, min(100.0, $pos));

        $zone = 'equilibrium';
        if ($pos >= 62.0) {
            $zone = 'premium';
        } elseif ($pos <= 38.0) {
            $zone = 'discount';
        }

        return [
            'available' => true,
            'range_low' => $low,
            'range_high' => $high,
            'equilibrium' => $eq,
            'close_position_pct' => round($pos, 2),
            'zone' => $zone,
            'note' => match ($zone) {
                'premium' => 'Harga di atas ~62% range swing terakhir (area premium / supply).',
                'discount' => 'Harga di bawah ~38% range swing terakhir (area diskon / demand).',
                default => 'Harga di sekitar equilibrium (50–62% / 38–50% band).',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    private function keyLevels(array $structure, float $lastClose): array
    {
        $highs = $structure['swing_highs'] ?? [];
        $lows = $structure['swing_lows'] ?? [];
        if (! is_array($highs)) {
            $highs = [];
        }
        if (! is_array($lows)) {
            $lows = [];
        }

        $res = [];
        foreach ($highs as $h) {
            $p = (float) ($h['price'] ?? 0);
            if ($p > $lastClose) {
                $res[] = [
                    'price' => $p,
                    'distance_pct' => round(($p - $lastClose) / $lastClose * 100, 4),
                    'kind' => 'resistance',
                ];
            }
        }
        usort($res, fn ($a, $b) => $a['price'] <=> $b['price']);

        $sup = [];
        foreach ($lows as $l) {
            $p = (float) ($l['price'] ?? 0);
            if ($p < $lastClose) {
                $sup[] = [
                    'price' => $p,
                    'distance_pct' => round(($lastClose - $p) / $lastClose * 100, 4),
                    'kind' => 'support',
                ];
            }
        }
        usort($sup, fn ($a, $b) => $b['price'] <=> $a['price']);

        return [
            'resistances_nearest' => array_slice($res, 0, 4),
            'supports_nearest' => array_slice($sup, 0, 4),
            'note' => 'Dari swing fractal; resistance di atas harga, support di bawah.',
        ];
    }

    /**
     * @param  list<array<string, float|int>>  $candles
     * @return array<string, mixed>
     */
    private function volumeContext(array $candles): array
    {
        $n = count($candles);
        if ($n < 5) {
            return ['available' => false];
        }

        $vols = [];
        foreach ($candles as $c) {
            $vols[] = (float) ($c['volume'] ?? 0);
        }
        $last = $vols[array_key_last($vols)];
        $look = array_slice($vols, -21, -1);
        if ($look === []) {
            return ['available' => false];
        }
        $avg = array_sum($look) / count($look);
        $ratio = $avg > 0 ? $last / $avg : null;

        return [
            'available' => true,
            'last_volume' => $last,
            'sma20_volume' => round($avg, 8),
            'ratio_vs_sma' => $ratio !== null ? round($ratio, 3) : null,
            'note' => $ratio !== null && $ratio >= 1.2
                ? 'Volume candle terakhir di atas rata-rata 20 bar (aktivitas relatif tinggi).'
                : ($ratio !== null && $ratio <= 0.8
                    ? 'Volume candle terakhir di bawah rata-rata 20 bar (aktivitas relatif rendah).'
                    : 'Volume sejalan rata-rata terbaru.'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $tradeSetup
     * @return array<string, mixed>
     */
    private function atrAdjustedLevels(?array $tradeSetup, ?float $atr): array
    {
        if ($atr === null || $atr <= 0 || ! is_array($tradeSetup) || empty($tradeSetup['has_setup'])) {
            return [
                'available' => false,
                'note' => 'Perlu ATR terhitung dan setup entry aktif.',
            ];
        }

        $ref = (float) $tradeSetup['entry_reference'];
        $side = $tradeSetup['side'] ?? '';

        if ($side === 'LONG') {
            return [
                'available' => true,
                'basis' => 'ATR(14) sebagai jarak dinamis dari entry reference',
                'stop_loss_1_0_atr' => $ref - 1.0 * $atr,
                'stop_loss_1_5_atr' => $ref - 1.5 * $atr,
                'take_profit_2_0_atr' => $ref + 2.0 * $atr,
                'take_profit_3_0_atr' => $ref + 3.0 * $atr,
                'take_profit_4_5_atr' => $ref + 4.5 * $atr,
                'note' => 'Bandingkan dengan SL struktural di atas. Pilih satu metode konsisten; jangan double-count risiko.',
            ];
        }

        if ($side === 'SHORT') {
            return [
                'available' => true,
                'basis' => 'ATR(14) sebagai jarak dinamis dari entry reference',
                'stop_loss_1_0_atr' => $ref + 1.0 * $atr,
                'stop_loss_1_5_atr' => $ref + 1.5 * $atr,
                'take_profit_2_0_atr' => $ref - 2.0 * $atr,
                'take_profit_3_0_atr' => $ref - 3.0 * $atr,
                'take_profit_4_5_atr' => $ref - 4.5 * $atr,
                'note' => 'Bandingkan dengan SL struktural; sesuaikan leverage & margin.',
            ];
        }

        return ['available' => false];
    }
}

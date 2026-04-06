<?php

namespace App\Services;

/**
 * Format kartu sinyal (gaya pesan) untuk salin ke clipboard / Telegram.
 */
final class SignalCardFormatter
{
    /**
     * @param  array<string, mixed>  $analysis
     */
    public static function format(array $analysis, string $interval, int $leverage): string
    {
        $ts = $analysis['trade_setup'] ?? [];
        if (empty($ts['has_setup'])) {
            return '';
        }

        $symbol = (string) ($analysis['symbol'] ?? '');
        $iv = strtoupper($interval);

        $term = self::termLabel($interval);
        $typeLine = self::typeLine($interval);

        $side = (string) ($ts['side'] ?? 'LONG');
        $isShort = $side === 'SHORT';
        $dirEmoji = $isShort ? '📉' : '📈';
        $tradeWord = $isShort ? 'Short' : 'Long';

        $accuracy = self::accuracyLabel($analysis);

        $entry = self::fmtPrice((float) ($ts['entry_reference'] ?? 0));
        $sl = self::fmtPrice((float) ($ts['stop_loss'] ?? 0));
        $tp1 = self::fmtPrice((float) ($ts['take_profit_1'] ?? 0));
        $tp2 = self::fmtPrice((float) ($ts['take_profit_2'] ?? 0));
        $tp3 = self::fmtPrice((float) ($ts['take_profit_3'] ?? 0));

        $trend = self::trendConfirmed($analysis) ? 'Confirmed' : 'Unconfirmed';

        $lines = [
            "📩 {$symbol} {$iv} | {$term}",
            "{$dirEmoji} Trade Type: {$tradeWord}",
            ' - Strategy Accuracy: '.$accuracy,
            '',
            '🎯Entry Orders: '.$entry,
            '❌Stop-loss: '.$sl,
            '',
            '⏳- Signal details::',
            'Target 1: '.$tp1,
            'Target 2: '.$tp2,
            'Target 3: '.$tp3,
            '',
            '⏳Leverage- Cross : '.$leverage.'x',
            '',
            '🧲Trend-Line: '.$trend,
            '📈Type :'.$typeLine,
            '💡After reaching the first target you can put the rest of the position to breakeven',
        ];

        return implode("\n", $lines);
    }

    private static function termLabel(string $interval): string
    {
        $i = strtolower($interval);

        return match (true) {
            in_array($i, ['1m', '3m', '5m', '15m'], true) => 'Scalping',
            in_array($i, ['30m', '1h', '2h'], true) => 'Mid-Term',
            in_array($i, ['4h', '6h', '12h'], true) => 'Swing',
            $i === '1d' => 'Long-Term',
            default => 'Active',
        };
    }

    private static function typeLine(string $interval): string
    {
        $i = strtolower($interval);

        if (in_array($i, ['1m', '3m', '5m', '15m'], true)) {
            return 'Scalping ( Very Small SL ) ';
        }
        if (in_array($i, ['30m', '1h', '2h'], true)) {
            return 'Mid-Term ( Structured SL ) ';
        }
        if (in_array($i, ['4h', '6h', '12h', '1d'], true)) {
            return 'Swing / Position ( Wider SL ) ';
        }

        return 'Structured ';
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    public static function accuracyLabel(array $analysis): string
    {
        $ts = $analysis['trade_setup'] ?? [];
        if (empty($ts['has_setup'])) {
            return 'N/A';
        }

        $ote = $analysis['ote_fibonacci'] ?? [];
        $hasOte = ! empty($ote['fvg_ote_confluence']);

        $liq = $analysis['liquidity_sweeps'] ?? [];
        $hasLiq = is_array($liq) && $liq !== [];

        $s = $analysis['structure'] ?? [];
        $hasBos = ! empty($s['bos']);
        $hasChoch = ! empty($s['choch_mss']);

        if ($hasOte || ($hasLiq && ($hasBos || $hasChoch))) {
            return 'High';
        }
        if ($hasBos || $hasChoch) {
            return 'Medium';
        }

        return 'Low';
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private static function trendConfirmed(array $analysis): bool
    {
        $s = $analysis['structure'] ?? [];

        return ! empty($s['bos']) || ! empty($s['choch_mss']);
    }

    private static function fmtPrice(float $p): string
    {
        if ($p >= 1000) {
            return number_format($p, 2, '.', '');
        }
        if ($p >= 1) {
            return rtrim(rtrim(number_format($p, 4, '.', ''), '0'), '.');
        }
        if ($p >= 0.0001) {
            return rtrim(rtrim(number_format($p, 6, '.', ''), '0'), '.');
        }

        return rtrim(rtrim(number_format($p, 8, '.', ''), '0'), '.');
    }
}

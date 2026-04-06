<?php

namespace App\Services;

/**
 * Likuiditas di atas swing high / di bawah swing low yang diambil wick lalu ditutup kembali (sweep sederhana).
 */
final class LiquiditySweepAnalyzer
{
    /**
     * @param  list<array<string, float|int>>  $candles
     * @param  list<array<string, mixed>>  $swingHighs
     * @param  list<array<string, mixed>>  $swingLows
     * @return list<array<string, mixed>>
     */
    public function detect(array $candles, array $swingHighs, array $swingLows): array
    {
        $n = count($candles);
        if ($n < 5) {
            return [];
        }

        $sweeps = [];
        $from = max(0, $n - 80);

        for ($i = $from; $i < $n; $i++) {
            $c = $candles[$i];

            foreach ($swingHighs as $sh) {
                if (($sh['index'] ?? 0) >= $i) {
                    continue;
                }
                $level = (float) $sh['price'];
                if ($c['high'] > $level && $c['close'] < $level) {
                    $sweeps[] = [
                        'type' => 'sweep_high',
                        'detail' => 'Wick di atas swing high lalu close kembali di bawah (likuiditas atas diambil).',
                        'swept_level' => $level,
                        'candle_index' => $i,
                        'open_time' => $c['open_time'],
                    ];
                }
            }

            foreach ($swingLows as $sl) {
                if (($sl['index'] ?? 0) >= $i) {
                    continue;
                }
                $level = (float) $sl['price'];
                if ($c['low'] < $level && $c['close'] > $level) {
                    $sweeps[] = [
                        'type' => 'sweep_low',
                        'detail' => 'Wick di bawah swing low lalu close kembali di atas (likuiditas bawah diambil).',
                        'swept_level' => $level,
                        'candle_index' => $i,
                        'open_time' => $c['open_time'],
                    ];
                }
            }
        }

        return array_slice($sweeps, -15);
    }
}

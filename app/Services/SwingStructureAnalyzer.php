<?php

namespace App\Services;

/**
 * Fractal swing highs / lows and simple BOS vs last swings.
 */
final class SwingStructureAnalyzer
{
    /**
     * @param  list<array<string, float|int>>  $candles
     * @return array<string, mixed>
     */
    public function analyze(array $candles): array
    {
        $n = count($candles);
        if ($n < 3) {
            return [
                'swing_highs' => [],
                'swing_lows' => [],
                'last_close' => null,
                'bos' => null,
                'choch_mss' => null,
            ];
        }

        $swingHighs = [];
        $swingLows = [];

        for ($i = 1; $i < $n - 1; $i++) {
            $h = $candles[$i]['high'];
            if ($h > $candles[$i - 1]['high'] && $h > $candles[$i + 1]['high']) {
                $swingHighs[] = [
                    'index' => $i,
                    'price' => (float) $h,
                    'open_time' => $candles[$i]['open_time'],
                ];
            }
            $l = $candles[$i]['low'];
            if ($l < $candles[$i - 1]['low'] && $l < $candles[$i + 1]['low']) {
                $swingLows[] = [
                    'index' => $i,
                    'price' => (float) $l,
                    'open_time' => $candles[$i]['open_time'],
                ];
            }
        }

        $last = $candles[$n - 1];
        $lastClose = (float) $last['close'];

        $lastSwingHigh = $swingHighs !== [] ? $swingHighs[array_key_last($swingHighs)] : null;
        $lastSwingLow = $swingLows !== [] ? $swingLows[array_key_last($swingLows)] : null;

        $bos = null;
        if ($lastSwingHigh && $lastClose > $lastSwingHigh['price']) {
            $bos = [
                'direction' => 'bullish',
                'broken_level' => $lastSwingHigh['price'],
                'reference_index' => $lastSwingHigh['index'],
            ];
        } elseif ($lastSwingLow && $lastClose < $lastSwingLow['price']) {
            $bos = [
                'direction' => 'bearish',
                'broken_level' => $lastSwingLow['price'],
                'reference_index' => $lastSwingLow['index'],
            ];
        }

        $chochMss = null;
        if (count($swingLows) >= 2) {
            $prevL = $swingLows[count($swingLows) - 2];
            $lastL = $swingLows[count($swingLows) - 1];
            if ($lastL['price'] > $prevL['price'] && $lastClose < $lastL['price']) {
                $chochMss = [
                    'direction' => 'bearish',
                    'kind' => 'choch_mss',
                    'broken_level' => $lastL['price'],
                    'reference_index' => $lastL['index'],
                    'note' => 'Gagal lanjut HH: penutupan di bawah higher low terakhir (MSS/ChoCh bearish).',
                ];
            }
        }
        if ($chochMss === null && count($swingHighs) >= 2) {
            $prevH = $swingHighs[count($swingHighs) - 2];
            $lastH = $swingHighs[count($swingHighs) - 1];
            if ($lastH['price'] < $prevH['price'] && $lastClose > $lastH['price']) {
                $chochMss = [
                    'direction' => 'bullish',
                    'kind' => 'choch_mss',
                    'broken_level' => $lastH['price'],
                    'reference_index' => $lastH['index'],
                    'note' => 'Gagal lanjut LL: penutupan di atas lower high terakhir (MSS/ChoCh bullish).',
                ];
            }
        }

        return [
            'swing_highs' => array_slice($swingHighs, -8),
            'swing_lows' => array_slice($swingLows, -8),
            'last_close' => $lastClose,
            'last_swing_high' => $lastSwingHigh,
            'last_swing_low' => $lastSwingLow,
            'bos' => $bos,
            'choch_mss' => $chochMss,
        ];
    }
}

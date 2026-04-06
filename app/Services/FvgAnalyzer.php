<?php

namespace App\Services;

/**
 * Fair Value Gap (3-candle imbalance): gap between candle i-2 and candle i.
 */
final class FvgAnalyzer
{
    /**
     * @param  list<array<string, float|int>>  $candles
     * @return list<array<string, mixed>>
     */
    public function detect(array $candles): array
    {
        $n = count($candles);
        if ($n < 3) {
            return [];
        }

        $fvgs = [];
        for ($i = 2; $i < $n; $i++) {
            $left = $candles[$i - 2];
            $mid = $candles[$i - 1];
            $right = $candles[$i];

            // Bullish FVG: low of right > high of left
            if ($right['low'] > $left['high']) {
                $zoneLow = $left['high'];
                $zoneHigh = $right['low'];
                $fvgs[] = $this->finalizeFvg([
                    'direction' => 'bullish',
                    'zone_low' => $zoneLow,
                    'zone_high' => $zoneHigh,
                    'formed_index' => $i,
                    'left_index' => $i - 2,
                    'mid_index' => $i - 1,
                    'right_index' => $i,
                    'open_time' => $right['open_time'],
                ], $candles, $i);
            }

            // Bearish FVG: high of right < low of left
            if ($right['high'] < $left['low']) {
                $zoneLow = $right['high'];
                $zoneHigh = $left['low'];
                $fvgs[] = $this->finalizeFvg([
                    'direction' => 'bearish',
                    'zone_low' => $zoneLow,
                    'zone_high' => $zoneHigh,
                    'formed_index' => $i,
                    'left_index' => $i - 2,
                    'mid_index' => $i - 1,
                    'right_index' => $i,
                    'open_time' => $right['open_time'],
                ], $candles, $i);
            }
        }

        return $fvgs;
    }

    /**
     * @param  list<array<string, float|int>>  $candles
     * @param  array<string, mixed>  $fvg
     * @return array<string, mixed>
     */
    private function finalizeFvg(array $fvg, array $candles, int $formedIndex): array
    {
        $last = $candles[array_key_last($candles)];
        $lastClose = (float) $last['close'];

        $status = 'open';
        $direction = $fvg['direction'];
        $low = (float) $fvg['zone_low'];
        $high = (float) $fvg['zone_high'];

        for ($j = $formedIndex + 1; $j < count($candles); $j++) {
            $c = $candles[$j];
            if ($direction === 'bullish') {
                if ($c['low'] <= $low) {
                    $status = 'filled';
                    break;
                }
                if ($c['low'] < $high && $c['low'] > $low) {
                    $status = 'partial';
                }
            } else {
                if ($c['high'] >= $high) {
                    $status = 'filled';
                    break;
                }
                if ($c['high'] > $low && $c['high'] < $high) {
                    $status = 'partial';
                }
            }
        }

        $mid = ($low + $high) / 2;
        $fvg['fill_status'] = $status;
        $fvg['size'] = abs($high - $low);
        $fvg['distance_from_last_close_pct'] = $mid > 0
            ? abs($lastClose - $mid) / $mid * 100
            : null;

        return $fvg;
    }
}

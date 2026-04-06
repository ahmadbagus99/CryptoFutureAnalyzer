<?php

namespace App\Services;

/**
 * Simplified order blocks: last opposite candle before a displacement leg.
 */
final class OrderBlockAnalyzer
{
    /**
     * @param  list<array<string, float|int>>  $candles
     * @return list<array<string, mixed>>
     */
    public function detect(array $candles): array
    {
        $n = count($candles);
        if ($n < 4) {
            return [];
        }

        $blocks = [];

        for ($i = 3; $i < $n; $i++) {
            $a = $candles[$i - 3];
            $b = $candles[$i - 2];
            $c = $candles[$i - 1];
            $d = $candles[$i];

            // Bullish displacement: three higher closes
            if (
                $d['close'] > $c['close']
                && $c['close'] > $b['close']
                && $b['close'] > $a['close']
                && Candle::isBearish($b)
            ) {
                $blocks[] = [
                    'type' => 'bullish_ob',
                    'zone_low' => min((float) $b['open'], (float) $b['close']),
                    'zone_high' => max((float) $b['open'], (float) $b['close']),
                    'formed_index' => $i,
                    'ob_candle_index' => $i - 2,
                    'open_time' => $b['open_time'],
                ];
            }

            // Bearish displacement: three lower closes
            if (
                $d['close'] < $c['close']
                && $c['close'] < $b['close']
                && $b['close'] < $a['close']
                && Candle::isBullish($b)
            ) {
                $blocks[] = [
                    'type' => 'bearish_ob',
                    'zone_low' => min((float) $b['open'], (float) $b['close']),
                    'zone_high' => max((float) $b['open'], (float) $b['close']),
                    'formed_index' => $i,
                    'ob_candle_index' => $i - 2,
                    'open_time' => $b['open_time'],
                ];
            }
        }

        return array_slice($blocks, -12);
    }
}

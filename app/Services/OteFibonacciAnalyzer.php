<?php

namespace App\Services;

/**
 * Fibonacci retracement & zona OTE (62%–79%) untuk konfluensi dengan FVG.
 */
final class OteFibonacciAnalyzer
{
    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @return array<string, mixed>
     */
    public function analyze(
        array $structure,
        array $fvgs,
    ): array {
        $lastHigh = $structure['last_swing_high'] ?? null;
        $lastLow = $structure['last_swing_low'] ?? null;

        if (! is_array($lastHigh) || ! is_array($lastLow)) {
            return [
                'impulse' => null,
                'leg_low' => null,
                'leg_high' => null,
                'levels' => null,
                'ote_zone' => null,
                'fvg_ote_confluence' => [],
            ];
        }

        $hi = (float) $lastHigh['price'];
        $lo = (float) $lastLow['price'];
        $hiIdx = (int) $lastHigh['index'];
        $loIdx = (int) $lastLow['index'];

        if ($hi <= $lo) {
            return [
                'impulse' => null,
                'leg_low' => null,
                'leg_high' => null,
                'levels' => null,
                'ote_zone' => null,
                'fvg_ote_confluence' => [],
            ];
        }

        $range = $hi - $lo;

        if ($hiIdx > $loIdx) {
            $impulse = 'bullish';
            $p618 = $hi - $range * 0.618;
            $p705 = $hi - $range * 0.705;
            $p786 = $hi - $range * 0.786;
            $oteLow = min($p618, $p705, $p786);
            $oteHigh = max($p618, $p705, $p786);
            $goldenPocketLow = min($p705, $p618);
            $goldenPocketHigh = max($p705, $p618);
        } else {
            $impulse = 'bearish';
            $p618 = $lo + $range * 0.618;
            $p705 = $lo + $range * 0.705;
            $p786 = $lo + $range * 0.786;
            $oteLow = min($p618, $p705, $p786);
            $oteHigh = max($p618, $p705, $p786);
            $goldenPocketLow = min($p618, $p705);
            $goldenPocketHigh = max($p618, $p705);
        }

        $levels = [
            '0.618' => $p618,
            '0.705' => $p705,
            '0.786' => $p786,
        ];

        $confluence = [];
        foreach ($fvgs as $f) {
            if (($f['fill_status'] ?? '') === 'filled') {
                continue;
            }
            $zL = (float) $f['zone_low'];
            $zH = (float) $f['zone_high'];
            $overlapOte = max($zL, $oteLow) <= min($zH, $oteHigh);
            $overlapGolden = max($zL, $goldenPocketLow) <= min($zH, $goldenPocketHigh);
            if ($overlapOte || $overlapGolden) {
                $confluence[] = [
                    'fvg_direction' => $f['direction'],
                    'zone_low' => $zL,
                    'zone_high' => $zH,
                    'formed_index' => (int) ($f['formed_index'] ?? 0),
                    'matches_ote_band' => $overlapOte,
                    'matches_golden_pocket_618_705' => $overlapGolden,
                ];
            }
        }

        return [
            'impulse' => $impulse,
            'leg_low' => $lo,
            'leg_high' => $hi,
            'levels' => $levels,
            'ote_zone' => [
                'low' => $oteLow,
                'high' => $oteHigh,
                'note' => 'Zona retracement 61.8%–78.6% dari impuls terakhir (swing terakhir vs swing terakhir).',
            ],
            'golden_pocket' => [
                'low' => $goldenPocketLow,
                'high' => $goldenPocketHigh,
                'note' => 'Sering dipakai SMC: area 0.618–0.705 untuk OTE.',
            ],
            'fvg_ote_confluence' => array_slice($confluence, -10),
        ];
    }
}

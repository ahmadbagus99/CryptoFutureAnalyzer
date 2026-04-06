<?php

namespace App\Services;

/**
 * Menyusun level entry / SL / TP sederhana dari output SMC (bukan rekomendasi investasi).
 */
final class TradeSetupAnalyzer
{
    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     * @param  array<string, mixed>  $ote
     * @return array<string, mixed>
     */
    public function build(
        array $structure,
        array $fvgs,
        array $obs,
        array $ote,
        float $lastClose,
    ): array {
        $bias = $this->inferBias($structure);

        if ($bias === 'neutral') {
            return [
                'has_setup' => false,
                'bias' => 'neutral',
                'reason' => 'BOS + MSS/ChoCh tidak searah atau tidak ada — arah otomatis dibatalkan.',
                'disclaimer' => 'Hanya ilustrasi dari data terakhir; selalu verifikasi di chart & HTF.',
            ];
        }

        if ($bias === 'long') {
            return $this->buildLong($structure, $fvgs, $obs, $ote, $lastClose);
        }

        return $this->buildShort($structure, $fvgs, $obs, $ote, $lastClose);
    }

    private function inferBias(array $structure): string
    {
        $score = 0;
        $bos = $structure['bos'] ?? null;
        if (is_array($bos)) {
            $score += $bos['direction'] === 'bullish' ? 2 : -2;
        }
        $cm = $structure['choch_mss'] ?? null;
        if (is_array($cm)) {
            $score += $cm['direction'] === 'bullish' ? 3 : -3;
        }

        if ($score > 0) {
            return 'long';
        }
        if ($score < 0) {
            return 'short';
        }

        return 'neutral';
    }

    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     */
    private function buildLong(
        array $structure,
        array $fvgs,
        array $obs,
        array $ote,
        float $lastClose,
    ): array {
        $zone = $this->findLongEntryZone($fvgs, $obs, $ote, $lastClose);
        if ($zone === null) {
            return [
                'has_setup' => false,
                'bias' => 'long',
                'reason' => 'Bias long terbaca, tetapi tidak ada FVG bullish / OB bullish / konfluensi OTE yang dipakai.',
                'disclaimer' => 'Hanya ilustrasi; bukan sinyal.',
            ];
        }

        $lastHigh = $structure['last_swing_high'] ?? null;
        $lastLow = $structure['last_swing_low'] ?? null;

        $zL = (float) $zone['zone_low'];
        $zH = (float) $zone['zone_high'];
        $entryMid = ($zL + $zH) / 2.0;

        $buf = $this->slBuffer($lastClose, $zH - $zL);

        $sl = $zL - $buf;
        if (is_array($lastLow) && (float) $lastLow['price'] < $zL) {
            $sl = min($sl, (float) $lastLow['price'] - $buf);
        }
        if ($sl >= $entryMid) {
            $sl = $zL - $buf * 2;
        }

        $risk = $entryMid - $sl;
        if ($risk <= 0) {
            return [
                'has_setup' => false,
                'bias' => 'long',
                'reason' => 'Zona entry terlalu sempit vs SL yang wajar.',
                'disclaimer' => 'Hanya ilustrasi; bukan sinyal.',
            ];
        }

        $tp1 = null;
        $tp1Label = 'TP1 (swing high terdekat)';
        if (is_array($lastHigh) && (float) $lastHigh['price'] > $entryMid) {
            $tp1 = (float) $lastHigh['price'];
        } else {
            $tp1 = $entryMid + $risk * 1.5;
            $tp1Label = 'TP1 (proyeksi 1.5R, swing tidak di atas entry)';
        }

        $tp2 = $entryMid + 2.0 * $risk;
        $tp3 = $entryMid + 3.0 * $risk;
        $rr1 = ($tp1 - $entryMid) / $risk;

        return [
            'has_setup' => true,
            'bias' => 'long',
            'side' => 'LONG',
            'basis' => $zone['basis'],
            'entry_zone_low' => $zL,
            'entry_zone_high' => $zH,
            'entry_reference' => $entryMid,
            'stop_loss' => $sl,
            'take_profit_1' => $tp1,
            'take_profit_1_label' => $tp1Label,
            'take_profit_2' => $tp2,
            'take_profit_2_label' => 'TP2 (2R struktural)',
            'take_profit_3' => $tp3,
            'take_profit_3_label' => 'TP3 (3R struktural)',
            'risk_amount' => $risk,
            'rr_tp1' => round($rr1, 2),
            'notes' => [
                'Entry: pertimbangkan limit di zona atau konfirmasi di TF lebih rendah.',
                'SL: di bawah zona / swing; sesuaikan dengan volatilitas & exchange.',
            ],
            'disclaimer' => 'Bukan saran investasi. Futures berisiko likuidasi; uji di demo.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     */
    private function buildShort(
        array $structure,
        array $fvgs,
        array $obs,
        array $ote,
        float $lastClose,
    ): array {
        $zone = $this->findShortEntryZone($fvgs, $obs, $ote, $lastClose);
        if ($zone === null) {
            return [
                'has_setup' => false,
                'bias' => 'short',
                'reason' => 'Bias short terbaca, tetapi tidak ada FVG bearish / OB bearish / konfluensi OTE yang dipakai.',
                'disclaimer' => 'Hanya ilustrasi; bukan sinyal.',
            ];
        }

        $lastHigh = $structure['last_swing_high'] ?? null;
        $lastLow = $structure['last_swing_low'] ?? null;

        $zL = (float) $zone['zone_low'];
        $zH = (float) $zone['zone_high'];
        $entryMid = ($zL + $zH) / 2.0;

        $buf = $this->slBuffer($lastClose, $zH - $zL);

        $sl = $zH + $buf;
        if (is_array($lastHigh) && (float) $lastHigh['price'] > $zH) {
            $sl = max($sl, (float) $lastHigh['price'] + $buf);
        }
        if ($sl <= $entryMid) {
            $sl = $zH + $buf * 2;
        }

        $risk = $sl - $entryMid;
        if ($risk <= 0) {
            return [
                'has_setup' => false,
                'bias' => 'short',
                'reason' => 'Zona entry terlalu sempit vs SL yang wajar.',
                'disclaimer' => 'Hanya ilustrasi; bukan sinyal.',
            ];
        }

        $tp1 = null;
        $tp1Label = 'TP1 (swing low terdekat)';
        if (is_array($lastLow) && (float) $lastLow['price'] < $entryMid) {
            $tp1 = (float) $lastLow['price'];
        } else {
            $tp1 = $entryMid - $risk * 1.5;
            $tp1Label = 'TP1 (proyeksi 1.5R, swing tidak di bawah entry)';
        }

        $tp2 = $entryMid - 2.0 * $risk;
        $tp3 = $entryMid - 3.0 * $risk;
        $rr1 = ($entryMid - $tp1) / $risk;

        return [
            'has_setup' => true,
            'bias' => 'short',
            'side' => 'SHORT',
            'basis' => $zone['basis'],
            'entry_zone_low' => $zL,
            'entry_zone_high' => $zH,
            'entry_reference' => $entryMid,
            'stop_loss' => $sl,
            'take_profit_1' => $tp1,
            'take_profit_1_label' => $tp1Label,
            'take_profit_2' => $tp2,
            'take_profit_2_label' => 'TP2 (2R struktural)',
            'take_profit_3' => $tp3,
            'take_profit_3_label' => 'TP3 (3R struktural)',
            'risk_amount' => $risk,
            'rr_tp1' => round($rr1, 2),
            'notes' => [
                'Entry: pertimbangkan limit di zona atau konfirmasi di TF lebih rendah.',
                'SL: di atas zona / swing; sesuaikan fee & spread.',
            ],
            'disclaimer' => 'Bukan saran investasi. Futures berisiko likuidasi; uji di demo.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     * @return array{basis: string, zone_low: float, zone_high: float}|null
     */
    private function findLongEntryZone(array $fvgs, array $obs, array $ote, float $lastClose): ?array
    {
        $activeBull = array_values(array_filter(
            $fvgs,
            fn ($f) => $f['direction'] === 'bullish' && ($f['fill_status'] ?? '') !== 'filled'
        ));
        if ($activeBull !== []) {
            $f = $this->pickNearestZoneToPrice($activeBull, $lastClose);
            if ($f === null) {
                return null;
            }

            return [
                'basis' => 'FVG bullish (diskon)',
                'zone_low' => (float) $f['zone_low'],
                'zone_high' => (float) $f['zone_high'],
            ];
        }

        $bullOb = array_values(array_filter($obs, fn ($o) => ($o['type'] ?? '') === 'bullish_ob'));
        if ($bullOb !== []) {
            $o = $this->pickNearestZoneToPrice($bullOb, $lastClose);
            if ($o === null) {
                return null;
            }

            return [
                'basis' => 'Order block bullish',
                'zone_low' => (float) $o['zone_low'],
                'zone_high' => (float) $o['zone_high'],
            ];
        }

        $conf = array_values(array_filter(
            $ote['fvg_ote_confluence'] ?? [],
            fn ($c) => ($c['fvg_direction'] ?? '') === 'bullish'
        ));
        $c = $this->pickNearestZoneToPrice($conf, $lastClose);
        if ($c !== null) {
            return [
                'basis' => 'Konfluensi FVG bullish × OTE',
                'zone_low' => (float) $c['zone_low'],
                'zone_high' => (float) $c['zone_high'],
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $fvgs
     * @param  list<array<string, mixed>>  $obs
     * @return array{basis: string, zone_low: float, zone_high: float}|null
     */
    private function findShortEntryZone(array $fvgs, array $obs, array $ote, float $lastClose): ?array
    {
        $activeBear = array_values(array_filter(
            $fvgs,
            fn ($f) => $f['direction'] === 'bearish' && ($f['fill_status'] ?? '') !== 'filled'
        ));
        if ($activeBear !== []) {
            $f = $this->pickNearestZoneToPrice($activeBear, $lastClose);
            if ($f === null) {
                return null;
            }

            return [
                'basis' => 'FVG bearish (premium)',
                'zone_low' => (float) $f['zone_low'],
                'zone_high' => (float) $f['zone_high'],
            ];
        }

        $bearOb = array_values(array_filter($obs, fn ($o) => ($o['type'] ?? '') === 'bearish_ob'));
        if ($bearOb !== []) {
            $o = $this->pickNearestZoneToPrice($bearOb, $lastClose);
            if ($o === null) {
                return null;
            }

            return [
                'basis' => 'Order block bearish',
                'zone_low' => (float) $o['zone_low'],
                'zone_high' => (float) $o['zone_high'],
            ];
        }

        $conf = array_values(array_filter(
            $ote['fvg_ote_confluence'] ?? [],
            fn ($c) => ($c['fvg_direction'] ?? '') === 'bearish'
        ));
        $c = $this->pickNearestZoneToPrice($conf, $lastClose);
        if ($c !== null) {
            return [
                'basis' => 'Konfluensi FVG bearish × OTE',
                'zone_low' => (float) $c['zone_low'],
                'zone_high' => (float) $c['zone_high'],
            ];
        }

        return null;
    }

    /**
     * Zona terdekat ke close terakhir (0 jika di dalam zona). Jarak sama → ambil FVG/OB lebih baru (formed_index).
     *
     * @param  list<array<string, mixed>>  $items  zone_low, zone_high, formed_index
     */
    private function pickNearestZoneToPrice(array $items, float $lastClose): ?array
    {
        if ($items === []) {
            return null;
        }

        $best = null;
        $bestDist = INF;
        $bestFormed = -1;
        $eps = max(1e-15, abs($lastClose) * 1e-12);

        foreach ($items as $item) {
            $zL = (float) $item['zone_low'];
            $zH = (float) $item['zone_high'];
            $dist = $this->distancePointToInterval($lastClose, $zL, $zH);
            $formed = (int) ($item['formed_index'] ?? -1);

            if ($dist < $bestDist - $eps) {
                $best = $item;
                $bestDist = $dist;
                $bestFormed = $formed;
            } elseif (abs($dist - $bestDist) <= $eps && $formed > $bestFormed) {
                $best = $item;
                $bestFormed = $formed;
            }
        }

        return $best;
    }

    private function distancePointToInterval(float $p, float $lo, float $hi): float
    {
        if ($p >= $lo && $p <= $hi) {
            return 0.0;
        }
        if ($p < $lo) {
            return $lo - $p;
        }

        return $p - $hi;
    }

    private function slBuffer(float $lastClose, float $zoneWidth): float
    {
        return max($lastClose * 0.0005, $zoneWidth * 0.05, 1e-12);
    }
}

<?php

namespace App\Services;

final class SetupScannerService
{
    /**
     * @return array{signals: list<array<string, mixed>>, errors: list<array<string, string>>}
     */
    public function scan(string $interval, int $limit): array
    {
        $symbols = config('crypto.scan_symbols');
        if (! is_array($symbols) || $symbols === []) {
            $symbols = config('crypto.quick_symbols', []);
        }

        $service = FuturesAnalysisService::make();
        $leverage = (int) config('crypto.default_leverage', 10);

        $signals = [];
        $errors = [];

        foreach ($symbols as $symbol) {
            $symbol = strtoupper(trim((string) $symbol));
            if ($symbol === '') {
                continue;
            }
            try {
                $data = $service->analyze($symbol, $interval, $limit);
                if (empty($data['trade_setup']['has_setup'])) {
                    continue;
                }
                $text = SignalCardFormatter::format($data, $interval, $leverage);
                if ($text === '') {
                    continue;
                }
                $signals[] = [
                    'symbol' => $data['symbol'],
                    'side' => $data['trade_setup']['side'] ?? '',
                    'accuracy' => SignalCardFormatter::accuracyLabel($data),
                    'card' => $text,
                ];
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $errors[] = [
                    'symbol' => $symbol,
                    'message' => $msg,
                ];
            }
        }

        return ['signals' => $signals, 'errors' => $errors];
    }
}

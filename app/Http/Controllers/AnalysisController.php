<?php

namespace App\Http\Controllers;

use App\Services\FuturesAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $maxSeconds = (int) config('crypto.analysis_max_seconds', 120);
        if ($maxSeconds > 0) {
            set_time_limit($maxSeconds);
        }

        $symbol = (string) $request->query('symbol', config('crypto.default_symbol'));
        $interval = (string) $request->query('interval', config('crypto.default_interval'));
        $limit = (int) $request->query('limit', (int) config('crypto.default_kline_limit'));

        $limit = min(max($limit, 50), 1500);

        $providerLabel = $this->resolveProviderLabel();

        $service = FuturesAnalysisService::make();

        $wantsJson = $request->wantsJson() || $request->query('format') === 'json';
        if ($wantsJson && $request->boolean('chart_only')) {
            try {
                $snap = $service->chartSnapshot($symbol, $interval, $limit);

                return response()->json([
                    'ok' => true,
                    'data' => $snap,
                ]);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Could not connect to server')) {
                    $msg .= ' — Tidak bisa terhubung ke API data. Coba VPN atau CRYPTO_HTTP_PROXY.';
                }

                return response()->json([
                    'ok' => false,
                    'error' => $msg,
                ], 422);
            }
        }

        try {
            $data = $service->analyze($symbol, $interval, $limit);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Could not connect to server')) {
                $msg .= ' — Tidak bisa terhubung ke API data. Coba VPN, ganti FUTURES_DATA_PROVIDER di .env, atau set CRYPTO_HTTP_PROXY (mis. http://127.0.0.1:7890).';
            }

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'error' => $msg,
                ], 422);
            }

            return view('analysis.index', [
                'error' => 'Gagal mengambil data: '.$msg,
                'result' => null,
                'symbol' => strtoupper($symbol),
                'interval' => $interval,
                'limit' => $limit,
                'providerLabel' => $providerLabel,
                'quickSymbols' => config('crypto.quick_symbols'),
                'intervals' => config('crypto.intervals'),
            ]);
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'data' => $data,
            ]);
        }

        return view('analysis.index', [
            'error' => null,
            'result' => $data,
            'symbol' => $data['symbol'],
            'interval' => $interval,
            'limit' => $limit,
            'providerLabel' => $providerLabel,
            'quickSymbols' => config('crypto.quick_symbols'),
            'intervals' => config('crypto.intervals'),
        ]);
    }

    private function resolveProviderLabel(): string
    {
        $dp = (string) config('crypto.data_provider');

        if ($dp === 'auto') {
            $order = config('crypto.auto_provider_order');
            $order = is_array($order) ? $order : [];
            $bits = array_map(
                fn (string $n) => (string) config('crypto.provider_labels.'.$n, $n),
                $order
            );

            return $bits !== [] ? 'Otomatis: '.implode(' → ', $bits) : 'Otomatis';
        }

        return (string) config('crypto.provider_labels.'.$dp, $dp !== '' ? $dp : 'Futures');
    }
}

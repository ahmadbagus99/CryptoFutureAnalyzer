<?php

namespace App\Http\Controllers;

use App\Services\SetupScannerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request, SetupScannerService $scanner): View
    {
        $maxSeconds = (int) config('crypto.scan_max_seconds', 300);
        if ($maxSeconds > 0) {
            set_time_limit($maxSeconds);
        }

        $interval = (string) $request->query('interval', config('crypto.default_interval'));
        $limit = (int) $request->query('limit', (int) config('crypto.default_kline_limit'));
        $limit = min(max($limit, 50), 1500);

        $run = $request->boolean('run');

        $signals = [];
        $errors = [];
        if ($run) {
            ['signals' => $signals, 'errors' => $errors] = $scanner->scan($interval, $limit);
        }

        return view('scan.index', [
            'interval' => $interval,
            'limit' => $limit,
            'signals' => $signals,
            'errors' => $errors,
            'ran' => $run,
            'scanSymbols' => config('crypto.scan_symbols', config('crypto.quick_symbols', [])),
            'intervals' => config('crypto.intervals'),
            'leverage' => (int) config('crypto.default_leverage', 10),
        ]);
    }
}

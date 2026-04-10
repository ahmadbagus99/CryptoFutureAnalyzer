<?php

return [
    /**
     * Sumber data: auto (default) | cryptocompare | okx | bybit | binance
     */
    'data_provider' => env('FUTURES_DATA_PROVIDER', 'auto'),

    /**
     * Urutan jika auto — cryptocompare dulu (domain terpisah, sering tidak ikut diblokir bersama exchange).
     */
    'auto_provider_order' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FUTURES_AUTO_ORDER', 'cryptocompare,okx,bybit'))
    ))),

    'provider_labels' => [
        'binance' => 'Binance USDT-M',
        'bybit' => 'Bybit USDT linear',
        'okx' => 'OKX USDT swap',
        'cryptocompare' => 'CryptoCompare (spot)',
        'auto' => 'Otomatis',
    ],

    'cryptocompare_api_base' => env('CRYPTOCOMPARE_API_BASE', 'https://min-api.cryptocompare.com'),

    'binance_futures_base' => env('BINANCE_FUTURES_BASE', 'https://fapi.binance.com'),

    'bybit_api_base' => env('BYBIT_API_BASE', 'https://api.bybit.com'),

    'okx_api_base' => env('OKX_API_BASE', 'https://www.okx.com'),

    /** HTTP(S) proxy untuk semua provider (mis. http://127.0.0.1:7890) */
    'http_proxy' => env('CRYPTO_HTTP_PROXY', env('BINANCE_HTTP_PROXY')),

    /** Timeout Guzzle per request (detik) — kecilkan jika sering mentok max_execution_time PHP */
    'http_timeout' => (int) env('CRYPTO_HTTP_TIMEOUT', 12),

    'http_connect_timeout' => (int) env('CRYPTO_HTTP_CONNECT_TIMEOUT', 6),

    /** Batas waktu PHP untuk satu request analisis (fetch kline + hitung) */
    'analysis_max_seconds' => (int) env('ANALYSIS_MAX_SECONDS', 120),

    /** Interval polling chart (detik), min 1 — pakai endpoint chart_only agar ringan */
    'chart_poll_seconds' => max(1, (int) env('CHART_POLL_SECONDS', 15)),

    'default_symbol' => 'BTCUSDT',

    'default_interval' => '15m',

    'default_kline_limit' => 500,

    'quick_symbols' => [
        'BTCUSDT',
        'ETHUSDT',
        'SOLUSDT',
        'BNBUSDT',
        'XRPUSDT',
        'NOMUSDT',
    ],

    /** Simbol yang di-scan (CSV di .env SCAN_SYMBOLS) */
    'scan_symbols' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SCAN_SYMBOLS', 'BTCUSDT,ETHUSDT,SOLUSDT,BNBUSDT,XRPUSDT,NOMUSDT'))
    ))),

    /** Leverage default untuk teks sinyal (bukan eksekusi otomatis) */
    'default_leverage' => (int) env('DEFAULT_LEVERAGE', 10),

    /** Batas waktu PHP untuk satu kali scan semua simbol */
    'scan_max_seconds' => (int) env('SCAN_MAX_SECONDS', 300),

    /** Integrasi Claude untuk analisis tambahan setelah setup ditemukan */
    'claude_api_key' => env('ANTHROPIC_API_KEY', ''),
    'claude_model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
    'claude_max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 350),
    'claude_timeout' => (int) env('ANTHROPIC_TIMEOUT', 20),

    'intervals' => [
        '1m', '3m', '5m', '15m', '30m',
        '1h', '2h', '4h', '6h', '12h',
        '1d',
    ],
];

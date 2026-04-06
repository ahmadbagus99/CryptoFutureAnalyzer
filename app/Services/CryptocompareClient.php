<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * CryptoCompare aggregate OHLC (spot/index; bukan kontrak futures).
 * Domain min-api.cryptocompare.com sering masih terjangkau saat API bursa diblokir.
 */
class CryptocompareClient implements FuturesKlinesClient
{
    /** @var array<string, array{0: string, 1: int}> path v2 + aggregate */
    private const INTERVAL_ROUTES = [
        '1m' => ['histominute', 1],
        '3m' => ['histominute', 3],
        '5m' => ['histominute', 5],
        '15m' => ['histominute', 15],
        '30m' => ['histominute', 30],
        '1h' => ['histohour', 1],
        '2h' => ['histohour', 2],
        '4h' => ['histohour', 4],
        '6h' => ['histohour', 6],
        '12h' => ['histohour', 12],
        '1d' => ['histoday', 1],
    ];

    public function __construct(
        protected string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('crypto.cryptocompare_api_base'), '/'));
    }

    /**
     * @return list<array<int, float|int>>
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array
    {
        $intervalKey = strtolower($interval);
        if (! isset(self::INTERVAL_ROUTES[$intervalKey])) {
            throw new InvalidArgumentException('Interval tidak didukung untuk CryptoCompare: '.$interval);
        }

        [$path, $aggregate] = self::INTERVAL_ROUTES[$intervalKey];
        [$fsym, $tsym] = self::parseSymbol($symbol);

        $limit = min(max($limit, 10), 2000);

        $query = [
            'fsym' => $fsym,
            'tsym' => $tsym,
            'limit' => $limit,
            'aggregate' => $aggregate,
        ];

        $url = $this->baseUrl.'/data/v2/'.$path;

        $response = Http::withOptions(HttpClientOptions::forOutbound())
            ->withHeaders([
                'User-Agent' => 'crypto-futures-analyzer/1.0 (Laravel)',
                'Accept' => 'application/json',
            ])
            ->acceptJson()
            ->get($url, $query);

        $response->throw();

        $json = $response->json();
        if (! is_array($json) || ($json['Response'] ?? '') !== 'Success') {
            $msg = is_array($json) ? (string) ($json['Message'] ?? 'error') : 'response tidak valid';

            throw new \RuntimeException('CryptoCompare: '.$msg);
        }

        $data = $json['Data']['Data'] ?? null;
        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $c) {
            if (! is_array($c)) {
                continue;
            }
            $t = isset($c['time']) ? (int) $c['time'] : 0;
            if ($t === 0) {
                continue;
            }
            $out[] = [
                $t * 1000,
                (float) ($c['open'] ?? 0),
                (float) ($c['high'] ?? 0),
                (float) ($c['low'] ?? 0),
                (float) ($c['close'] ?? 0),
                (float) ($c['volumefrom'] ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => $a[0] <=> $b[0]);

        return $out;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseSymbol(string $symbol): array
    {
        $s = strtoupper(trim($symbol));
        if (! str_ends_with($s, 'USDT')) {
            throw new InvalidArgumentException('Symbol harus berakhiran USDT: '.$symbol);
        }

        $base = substr($s, 0, -4);
        if ($base === '') {
            throw new InvalidArgumentException('Symbol tidak valid: '.$symbol);
        }

        return [$base, 'USDT'];
    }
}

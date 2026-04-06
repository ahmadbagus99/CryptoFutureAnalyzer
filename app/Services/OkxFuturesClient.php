<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * OKX USDT perpetual swap (public REST, tidak perlu API key untuk candles).
 */
class OkxFuturesClient implements FuturesKlinesClient
{
    private const INTERVAL_MAP = [
        '1m' => '1m',
        '3m' => '3m',
        '5m' => '5m',
        '15m' => '15m',
        '30m' => '30m',
        '1h' => '1H',
        '2h' => '2H',
        '4h' => '4H',
        '6h' => '6H',
        '12h' => '12H',
        '1d' => '1D',
    ];

    public function __construct(
        protected string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('crypto.okx_api_base'), '/'));
    }

    /**
     * @return list<array<int, float|int>>
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array
    {
        $intervalKey = strtolower($interval);
        if (! isset(self::INTERVAL_MAP[$intervalKey])) {
            throw new InvalidArgumentException('Interval tidak didukung untuk OKX: '.$interval);
        }

        $limit = min(max($limit, 5), 300);

        $response = Http::withOptions(HttpClientOptions::forOutbound())
            ->acceptJson()
            ->get($this->baseUrl.'/api/v5/market/candles', [
                'instId' => self::symbolToInstId($symbol),
                'bar' => self::INTERVAL_MAP[$intervalKey],
                'limit' => (string) $limit,
            ]);

        $response->throw();

        $json = $response->json();
        if (! is_array($json) || (string) ($json['code'] ?? '') !== '0') {
            $msg = is_array($json) ? (string) ($json['msg'] ?? 'error') : 'response tidak valid';

            throw new \RuntimeException('OKX API: '.$msg);
        }

        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            return [];
        }

        $data = array_reverse($data);

        $out = [];
        foreach ($data as $row) {
            if (! is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = [
                (int) $row[0],
                (float) $row[1],
                (float) $row[2],
                (float) $row[3],
                (float) $row[4],
                (float) $row[5],
            ];
        }

        return $out;
    }

    private static function symbolToInstId(string $symbol): string
    {
        $s = strtoupper(trim($symbol));
        if (! str_ends_with($s, 'USDT')) {
            throw new InvalidArgumentException('Symbol harus berakhiran USDT untuk OKX swap: '.$symbol);
        }

        $base = substr($s, 0, -4);

        return $base.'-USDT-SWAP';
    }
}

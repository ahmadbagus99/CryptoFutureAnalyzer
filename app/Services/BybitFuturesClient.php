<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class BybitFuturesClient implements FuturesKlinesClient
{
    /** Binance-style interval → Bybit v5 interval */
    private const INTERVAL_MAP = [
        '1m' => '1',
        '3m' => '3',
        '5m' => '5',
        '15m' => '15',
        '30m' => '30',
        '1h' => '60',
        '2h' => '120',
        '4h' => '240',
        '6h' => '360',
        '12h' => '720',
        '1d' => 'D',
    ];

    public function __construct(
        protected string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('crypto.bybit_api_base'), '/'));
    }

    /**
     * @return list<array<int, float|int>>
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array
    {
        $intervalKey = strtolower($interval);
        if (! isset(self::INTERVAL_MAP[$intervalKey])) {
            throw new InvalidArgumentException('Interval tidak didukung untuk Bybit: '.$interval);
        }

        $limit = min(max($limit, 10), 1000);

        $response = Http::withOptions(HttpClientOptions::forOutbound())
            ->acceptJson()
            ->get($this->baseUrl.'/v5/market/kline', [
                'category' => 'linear',
                'symbol' => strtoupper($symbol),
                'interval' => self::INTERVAL_MAP[$intervalKey],
                'limit' => $limit,
            ]);

        $response->throw();

        $json = $response->json();
        if (! is_array($json) || (int) ($json['retCode'] ?? -1) !== 0) {
            $msg = is_array($json) ? (string) ($json['retMsg'] ?? 'error') : 'response tidak valid';

            throw new \RuntimeException('Bybit API: '.$msg);
        }

        $result = $json['result'] ?? null;
        $list = is_array($result) && isset($result['list']) && is_array($result['list'])
            ? $result['list']
            : [];

        $list = array_reverse($list);

        $out = [];
        foreach ($list as $row) {
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
}

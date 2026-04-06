<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BinanceFuturesClient implements FuturesKlinesClient
{
    public function __construct(
        protected string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(rtrim(config('crypto.binance_futures_base'), '/'));
    }

    /**
     * @return list<array<int, float|int>>
     *
     * @throws RequestException
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array
    {
        $response = Http::withOptions(HttpClientOptions::forOutbound())
            ->acceptJson()
            ->get($this->baseUrl.'/fapi/v1/klines', [
                'symbol' => strtoupper($symbol),
                'interval' => $interval,
                'limit' => min(max($limit, 10), 1500),
            ]);

        $response->throw();

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}

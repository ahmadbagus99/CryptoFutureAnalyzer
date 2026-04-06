<?php

namespace App\Contracts;

interface FuturesKlinesClient
{
    /**
     * Kline rows in Binance-compatible shape: [openTime, open, high, low, close, volume].
     *
     * @return list<array<int, float|int|string>>
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array;
}

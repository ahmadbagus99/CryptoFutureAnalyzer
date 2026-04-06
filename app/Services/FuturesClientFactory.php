<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;
use InvalidArgumentException;

final class FuturesClientFactory
{
    public static function make(string $name): FuturesKlinesClient
    {
        return match (trim($name)) {
            'binance' => BinanceFuturesClient::fromConfig(),
            'bybit' => BybitFuturesClient::fromConfig(),
            'okx' => OkxFuturesClient::fromConfig(),
            'cryptocompare' => CryptocompareClient::fromConfig(),
            default => throw new InvalidArgumentException('Penyedia data tidak dikenal: '.$name),
        };
    }

    /**
     * @param  list<string>  $names
     * @return list<FuturesKlinesClient>
     */
    public static function makeChain(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $out[] = self::make(trim($name));
        }

        return $out;
    }
}

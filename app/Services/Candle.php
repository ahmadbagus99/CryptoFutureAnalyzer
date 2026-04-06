<?php

namespace App\Services;

/**
 * Normalized OHLCV dari baris kline (format array kompatibel Binance).
 */
final class Candle
{
    /**
     * @param  array<int, float|int|string>  $row
     */
    public static function fromKlineRow(array $row): array
    {
        return [
            'open_time' => (int) $row[0],
            'open' => (float) $row[1],
            'high' => (float) $row[2],
            'low' => (float) $row[3],
            'close' => (float) $row[4],
            'volume' => (float) $row[5],
        ];
    }

    /**
     * @param  list<array<int, float|int|string>>  $raw
     * @return list<array<string, float|int>>
     */
    public static function collectionFromRaw(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            $out[] = self::fromKlineRow($row);
        }

        return $out;
    }

    public static function isBullish(array $c): bool
    {
        return $c['close'] >= $c['open'];
    }

    public static function isBearish(array $c): bool
    {
        return $c['close'] < $c['open'];
    }
}

<?php

namespace App\Services;

final class HttpClientOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function forOutbound(): array
    {
        $options = [
            'timeout' => (int) config('crypto.http_timeout', 12),
            'connect_timeout' => (int) config('crypto.http_connect_timeout', 6),
        ];
        $proxy = config('crypto.http_proxy');
        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return $options;
    }
}

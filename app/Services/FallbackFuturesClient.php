<?php

namespace App\Services;

use App\Contracts\FuturesKlinesClient;

/**
 * Mencoba beberapa penyedia secara berurutan sampai salah satu berhasil (berguna jika satu domain diblokir).
 */
class FallbackFuturesClient implements FuturesKlinesClient
{
    /**
     * @param  list<FuturesKlinesClient>  $clients
     * @param  list<string>  $labels  nama penyedia (sama urutan dengan $clients), untuk pesan error
     */
    public function __construct(
        private array $clients,
        private array $labels = [],
    ) {
        if ($clients === []) {
            throw new \InvalidArgumentException('Rantai penyedia data kosong.');
        }
    }

    /**
     * @return list<array<int, float|int|string>>
     */
    public function klines(string $symbol, string $interval, int $limit = 500): array
    {
        $last = null;
        $details = [];
        foreach ($this->clients as $i => $client) {
            $label = $this->labels[$i] ?? ('#'.($i + 1));
            try {
                return $client->klines($symbol, $interval, $limit);
            } catch (\Throwable $e) {
                $last = $e;
                $details[] = $label.': '.$e->getMessage();
            }
        }

        $msg = 'Semua penyedia data gagal. Coba VPN/proxy (CRYPTO_HTTP_PROXY), atau ubah FUTURES_AUTO_ORDER / FUTURES_DATA_PROVIDER=cryptocompare.';
        if ($details !== []) {
            $msg .= ' Detail: '.implode(' | ', $details);
        }

        $wrap = new \RuntimeException($msg, 0, $last);
        throw $wrap;
    }
}

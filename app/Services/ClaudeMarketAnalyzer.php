<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class ClaudeMarketAnalyzer
{
    /**
     * @param  array<string, mixed>  $analysis
     */
    public function summarize(array $analysis, string $interval): string
    {
        $apiKey = (string) config('crypto.claude_api_key', '');
        if ($apiKey === '') {
            return '';
        }

        $model = (string) config('crypto.claude_model', 'claude-3-5-sonnet-latest');
        $maxTokens = (int) config('crypto.claude_max_tokens', 350);
        $timeout = (int) config('crypto.claude_timeout', 20);

        $symbol = (string) ($analysis['symbol'] ?? 'UNKNOWN');
        $tradeSetup = $analysis['trade_setup'] ?? [];
        $marketContext = $analysis['market_context'] ?? [];
        $structure = $analysis['structure'] ?? [];
        $ote = $analysis['ote_fibonacci'] ?? [];
        $liquidity = $analysis['liquidity_sweeps'] ?? [];

        $payload = [
            'symbol' => $symbol,
            'interval' => $interval,
            'has_setup' => (bool) ($tradeSetup['has_setup'] ?? false),
            'side' => $tradeSetup['side'] ?? null,
            'basis' => $tradeSetup['basis'] ?? null,
            'entry_zone_low' => $tradeSetup['entry_zone_low'] ?? null,
            'entry_zone_high' => $tradeSetup['entry_zone_high'] ?? null,
            'stop_loss' => $tradeSetup['stop_loss'] ?? null,
            'take_profit_1' => $tradeSetup['take_profit_1'] ?? null,
            'take_profit_2' => $tradeSetup['take_profit_2'] ?? null,
            'take_profit_3' => $tradeSetup['take_profit_3'] ?? null,
            'rr_tp1' => $tradeSetup['rr_tp1'] ?? null,
            'bos' => $structure['bos'] ?? null,
            'choch_mss' => $structure['choch_mss'] ?? null,
            'premium_discount' => $marketContext['premium_discount'] ?? null,
            'atr' => $marketContext['atr'] ?? null,
            'volume' => $marketContext['volume'] ?? null,
            'atr_trade_levels' => $marketContext['atr_trade_levels'] ?? null,
            'ote_confluence_count' => is_array($ote['fvg_ote_confluence'] ?? null) ? count($ote['fvg_ote_confluence']) : 0,
            'liquidity_sweep_count' => is_array($liquidity) ? count($liquidity) : 0,
        ];

        $systemPrompt = <<<'PROMPT'
Anda adalah analis crypto-futures yang konservatif.
Tulis analisis tambahan dalam Bahasa Indonesia berdasarkan JSON input.
Fokus pada:
1) Kualitas setup (struktur + konfluensi),
2) Risiko utama yang bisa invalidasi setup,
3) Rencana eksekusi singkat.
Aturan output:
- Maksimal 6 bullet points.
- Gunakan gaya ringkas, praktis.
- Hindari klaim kepastian.
- Tambahkan 1 baris penutup: "Bukan saran finansial."
PROMPT;

        $userPrompt = "Data setup:\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\nBuat analisis tambahan singkat sesuai aturan.";

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            return '';
        }

        $body = $response->json();
        $parts = $body['content'] ?? [];
        if (! is_array($parts)) {
            return '';
        }

        $texts = [];
        foreach ($parts as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text'])) {
                $texts[] = trim((string) $part['text']);
            }
        }

        return trim(implode("\n\n", array_filter($texts)));
    }
}

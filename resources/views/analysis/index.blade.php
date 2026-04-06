<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analisis Futures — SMC (FVG, OB, MSS, likuiditas, Fib OTE)</title>
    <style>
        :root {
            --bg: #0d1117;
            --panel: #161b22;
            --border: #30363d;
            --text: #e6edf3;
            --muted: #8b949e;
            --green: #3fb950;
            --red: #f85149;
            --accent: #58a6ff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        h1 { font-size: 1.35rem; font-weight: 600; margin: 0 0 0.25rem; }
        .sub { color: var(--muted); font-size: 0.875rem; margin-bottom: 1.25rem; }
        form.row {
            display: flex; flex-wrap: wrap; gap: 0.75rem;
            align-items: flex-end; margin-bottom: 1.25rem;
            padding: 1rem; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;
        }
        label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem; color: var(--muted); }
        input, select {
            background: var(--bg); border: 1px solid var(--border); color: var(--text);
            padding: 0.45rem 0.6rem; border-radius: 6px; min-width: 120px;
        }
        button {
            background: var(--accent); color: #0d1117; border: none; padding: 0.5rem 1rem;
            border-radius: 6px; font-weight: 600; cursor: pointer;
        }
        button:hover { filter: brightness(1.08); }
        button.secondary {
            background: #21262d; color: var(--text); border: 1px solid var(--border);
            font-size: 0.8rem;
        }
        button.secondary:hover { filter: brightness(1.1); }
        button.secondary:disabled { opacity: 0.6; cursor: not-allowed; }
        .err {
            background: #3d1416; border: 1px solid var(--red); color: #ffb1ad;
            padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 900px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
        .card {
            background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 1rem;
        }
        .card h2 { font-size: 0.95rem; margin: 0 0 0.75rem; color: var(--accent); }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th, td { text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 500; }
        .bull { color: var(--green); }
        .bear { color: var(--red); }
        .tag { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; background: #21262d; }
        ul.notes { margin: 0; padding-left: 1.1rem; color: var(--muted); font-size: 0.85rem; }
        ul.notes li { margin-bottom: 0.35rem; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.78rem; }
        .last-price { font-size: 1.1rem; font-weight: 600; }
        #analysis-chart-mount { width: 100%; min-height: 380px; position: relative; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Analisis futures ({{ $providerLabel }})</h1>
        <p class="sub">FVG, Order Block, BOS, MSS/ChoCh (swing), liquidity sweep vs swing, serta konfluensi FVG dengan zona Fibonacci OTE (62–79%). Data publik; bukan saran investasi.</p>

        <form class="row" method="get" action="{{ url('/') }}">
            <label>Symbol
                <input name="symbol" value="{{ $symbol }}" placeholder="BTCUSDT">
            </label>
            <label>Interval
                <select name="interval">
                    @foreach ($intervals as $iv)
                        <option value="{{ $iv }}" @selected($interval === $iv)>{{ $iv }}</option>
                    @endforeach
                </select>
            </label>
            <label>Limit kline
                <input type="number" name="limit" min="50" max="1500" value="{{ $limit }}">
            </label>
            <button type="submit">Analisis</button>
        </form>

        <p style="font-size:0.8rem;margin:0 0 1rem;">
            <a href="{{ route('scan.index', ['interval' => $interval, 'limit' => $limit, 'run' => 1]) }}" style="color:var(--accent);font-weight:600;">Scan semua koin (quick list) →</a>
            <span style="color:var(--muted);"> · cari pair yang punya zona entry + format teks sinyal</span>
        </p>

        <p style="font-size:0.8rem;color:var(--muted);margin:0 0 1rem;">Quick: 
            @foreach ($quickSymbols as $qs)
                <a href="{{ url('/?symbol='.$qs.'&interval='.$interval.'&limit='.$limit) }}" style="color:var(--accent);margin-right:0.5rem;">{{ $qs }}</a>
            @endforeach
        </p>

        @if ($error)
            <div class="err">{{ $error }}</div>
        @endif

        @if ($result)
            <div id="analysis-page-meta" data-symbol="{{ $result['symbol'] }}" hidden></div>
            <script type="application/json" id="analysis-trade-setup">@json($result['trade_setup'] ?? null)</script>

            @php
                $lc = $result['last_candle'];
            @endphp
            <div class="card" style="margin-bottom:1rem;">
                <div class="last-price mono">{{ $result['symbol'] }} — close: {{ $lc ? number_format($lc['close'], 8, '.', '') : '—' }}</div>
                <div style="font-size:0.8rem;color:var(--muted);margin-top:0.25rem;">
                    Klines: {{ $result['candles_count'] }} · Interval: {{ $interval }}
                    @if (!empty($result['analyzed_at']))
                        · Analisis dijalankan: <span class="mono">{{ $result['analyzed_at'] }}</span>
                        @if (($result['analyzed_timezone'] ?? '') === 'Asia/Jakarta')
                            WIB
                        @else
                            ({{ $result['analyzed_timezone'] }})
                        @endif
                    @endif
                    @if (!empty($result['last_candle_open_at']))
                        · Open candle terakhir: <span class="mono">{{ $result['last_candle_open_at'] }}</span> (konversi ke zona aplikasi; sumbu chart mengikuti jam perangkat/browser)
                    @endif
                </div>
            </div>

            @php
                $chartJsonUrl = url('/').'?'.http_build_query([
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'limit' => $limit,
                    'format' => 'json',
                    'chart_only' => '1',
                ]);
                $chartPollMs = max(1000, (int) config('crypto.chart_poll_seconds', 15) * 1000);
            @endphp
            <div class="card" style="margin-bottom:1rem;">
                <h2 style="margin-top:0;">Chart candlestick</h2>
                <p style="font-size:0.78rem;color:var(--muted);margin:0 0 0.5rem;">
                    Pembaruan data chart ± setiap {{ (int) config('crypto.chart_poll_seconds', 15) }} dtk (bisa 1 dtk via <span class="mono">CHART_POLL_SECONDS</span>; ini <strong>bukan</strong> timeframe candle 1 dtk — candle tetap mengikuti interval di form). Polling REST + endpoint ringan <span class="mono">chart_only</span>.
                </p>
                <div
                    id="analysis-chart-mount"
                    data-json-url="{{ $chartJsonUrl }}"
                    data-poll-ms="{{ $chartPollMs }}"
                ></div>
                <p id="analysis-chart-status" style="font-size:0.72rem;color:var(--muted);margin:0.5rem 0 0;"></p>
                <script type="application/json" id="analysis-chart-candles">@json($result['chart_candles'] ?? [])</script>
            </div>

            @php $ts = $result['trade_setup'] ?? []; @endphp
            <div class="card" style="margin-bottom:1rem;">
                <h2 style="margin-top:0;">Pengingat otomatis</h2>
                <p style="font-size:0.78rem;color:var(--muted);margin:0 0 0.75rem;">
                    <strong>Tidak ada prediksi</strong> bahwa entry akan “pas” tepat 5 menit lagi. Yang didukung: <strong>pengingat waktu</strong> untuk cek ulang, dan opsi <strong>pantau zona</strong> saat harga (close candle terakhir) memasuki zona entry dari Analisis terakhir — tetap butuh konfirmasi manual.
                </p>
                <p style="margin:0 0 0.5rem;">
                    <button type="button" class="secondary" id="btn-entry-remind-5m">Izinkan notifikasi &amp; ingatkan ±5 menit lagi</button>
                </p>
                <label style="font-size:0.82rem;display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer;max-width:42rem;">
                    <input type="checkbox" id="chk-entry-zone-watch" style="margin-top:0.2rem;" @if(empty($ts['has_setup'])) disabled @endif />
                    <span>Beritahu saat <strong>close</strong> memasuki <strong>zona entry</strong> (butuh izin notifikasi + chart yang terus refresh)</span>
                </label>
                @if(empty($ts['has_setup']))
                    <p style="font-size:0.75rem;color:var(--muted);margin:0.5rem 0 0;">Jalankan Analisis sampai ada setup entry untuk mengaktifkan pantau zona.</p>
                @endif
            </div>
            <div class="card" style="margin-bottom:1rem;border-color:var(--accent);">
                <h2 style="margin-top:0;">Setup futures (entry / SL / TP)</h2>
                <p style="font-size:0.78rem;color:var(--muted);margin:0 0 0.75rem;">Dihitung dari bias BOS+MSS/ChoCh + zona FVG/OB/OTE. <strong>Bukan saran investasi</strong> — verifikasi manual & risiko likuidasi futures.</p>
                @if (!empty($ts['has_setup']))
                    <p style="margin:0 0 0.5rem;">
                        <span class="tag" style="background:{{ ($ts['side'] ?? '') === 'LONG' ? '#1f3d2a' : '#3d1f1f' }};">{{ $ts['side'] ?? '—' }}</span>
                        <span style="font-size:0.8rem;">{{ $ts['basis'] ?? '' }}</span>
                    </p>
                    <table>
                        <tbody>
                            <tr><th>Zona entry</th><td class="mono">{{ number_format($ts['entry_zone_low'], 8, '.', '') }} – {{ number_format($ts['entry_zone_high'], 8, '.', '') }}</td></tr>
                            <tr><th>Referensi mid</th><td class="mono">{{ number_format($ts['entry_reference'], 8, '.', '') }}</td></tr>
                            <tr><th>Stop loss</th><td class="mono bear">{{ number_format($ts['stop_loss'], 8, '.', '') }}</td></tr>
                            <tr><th>{{ $ts['take_profit_1_label'] ?? 'TP1' }}</th><td class="mono bull">{{ number_format($ts['take_profit_1'], 8, '.', '') }} <span class="tag">RR ≈ {{ $ts['rr_tp1'] ?? '—' }}</span></td></tr>
                            <tr><th>{{ $ts['take_profit_2_label'] ?? 'TP2' }}</th><td class="mono bull">{{ number_format($ts['take_profit_2'], 8, '.', '') }}</td></tr>
                            @if (isset($ts['take_profit_3']))
                                <tr><th>{{ $ts['take_profit_3_label'] ?? 'TP3' }}</th><td class="mono bull">{{ number_format($ts['take_profit_3'], 8, '.', '') }}</td></tr>
                            @endif
                            <tr><th>Risiko (1R)</th><td class="mono">{{ number_format($ts['risk_amount'], 8, '.', '') }}</td></tr>
                        </tbody>
                    </table>
                    @if (!empty($ts['notes']))
                        <ul class="notes" style="margin-top:0.75rem;">
                            @foreach ($ts['notes'] as $n)
                                <li>{{ $n }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (!empty($result['signal_card']))
                        <p style="font-size:0.8rem;margin:0.75rem 0 0.35rem;"><strong>Format teks sinyal</strong> (salin ke Telegram/dll.)</p>
                        <button type="button" class="secondary" id="btn-copy-signal-card" style="margin-bottom:0.35rem;">Salin format</button>
                        <pre id="signal-card-pre" style="background:#0d1117;border:1px solid var(--border);padding:0.6rem;border-radius:6px;font-size:0.72rem;white-space:pre-wrap;word-break:break-all;margin:0;">{{ $result['signal_card'] }}</pre>
                        <script>
                            document.getElementById('btn-copy-signal-card')?.addEventListener('click', function () {
                                var p = document.getElementById('signal-card-pre');
                                if (p && navigator.clipboard?.writeText) navigator.clipboard.writeText(p.textContent);
                            });
                        </script>
                    @endif
                @else
                    <p style="color:var(--muted);font-size:0.9rem;">{{ $ts['reason'] ?? 'Tidak ada setup terbentuk.' }}</p>
                @endif
                <p style="font-size:0.72rem;color:var(--muted);margin:0.75rem 0 0;">{{ $ts['disclaimer'] ?? '' }}</p>
            </div>

            @php $mc = $result['market_context'] ?? []; @endphp
            <div class="card" style="margin-bottom:1rem;">
                <h2 style="margin-top:0;">Konteks pasar lanjutan (efisiensi entry / SL / TP)</h2>
                <p style="font-size:0.75rem;color:var(--muted);margin:0 0 0.75rem;">Gabungan volatilitas (ATR), posisi harga vs range swing (premium/discount), level support/resistance dari swing, volume vs rata-rata, dan <strong>alternatif SL/TP dalam kelipatan ATR</strong> untuk dibandingkan dengan level struktural di atas.</p>

                @if (!empty($mc['atr']['period_14']))
                    <p class="mono" style="font-size:0.82rem;margin:0 0 0.35rem;">ATR(14): {{ number_format($mc['atr']['period_14'], 8, '.', '') }}
                        @if (!empty($mc['atr']['pct_of_price_14']))
                            · {{ $mc['atr']['pct_of_price_14'] }}% dari harga
                        @endif
                        @if (!empty($mc['atr']['period_7']))
                            · ATR(7): {{ number_format($mc['atr']['period_7'], 8, '.', '') }}
                        @endif
                    </p>
                    <p style="font-size:0.72rem;color:var(--muted);margin:0 0 0.75rem;">{{ $mc['atr']['note'] ?? '' }}</p>
                @else
                    <p style="color:var(--muted);font-size:0.85rem;">ATR: data candle tidak cukup panjang.</p>
                @endif

                @if (!empty($mc['premium_discount']['available']))
                    <p style="font-size:0.85rem;margin:0 0 0.25rem;"><span class="tag">{{ strtoupper($mc['premium_discount']['zone'] ?? '') }}</span> · posisi close dalam range swing: <strong>{{ $mc['premium_discount']['close_position_pct'] ?? '—' }}%</strong></p>
                    <p style="font-size:0.75rem;color:var(--muted);margin:0 0 0.75rem;">{{ $mc['premium_discount']['note'] ?? '' }}</p>
                @endif

                @if (!empty($mc['volume']['available']))
                    <p style="font-size:0.8rem;margin:0 0 0.75rem;">Volume: ratio vs SMA20 ≈ <strong>{{ $mc['volume']['ratio_vs_sma'] ?? '—' }}</strong> — {{ $mc['volume']['note'] ?? '' }}</p>
                @endif

                @if (!empty($mc['key_levels']['resistances_nearest']) || !empty($mc['key_levels']['supports_nearest']))
                    <p style="font-size:0.8rem;margin:0 0 0.25rem;"><strong>Level swing terdekat</strong> (vs close)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;font-size:0.78rem;" class="mono">
                        <div>
                            <div style="color:var(--muted);">Resistance di atas</div>
                            @forelse ($mc['key_levels']['resistances_nearest'] ?? [] as $lv)
                                <div>{{ number_format($lv['price'], 8, '.', '') }} (+{{ $lv['distance_pct'] }}%)</div>
                            @empty
                                <div style="color:var(--muted);">—</div>
                            @endforelse
                        </div>
                        <div>
                            <div style="color:var(--muted);">Support di bawah</div>
                            @forelse ($mc['key_levels']['supports_nearest'] ?? [] as $lv)
                                <div>{{ number_format($lv['price'], 8, '.', '') }} (−{{ $lv['distance_pct'] }}%)</div>
                            @empty
                                <div style="color:var(--muted);">—</div>
                            @endforelse
                        </div>
                    </div>
                    <p style="font-size:0.72rem;color:var(--muted);margin:0.5rem 0 0;">{{ $mc['key_levels']['note'] ?? '' }}</p>
                @endif

                @if (!empty($mc['atr_trade_levels']['available']))
                    <p style="font-size:0.85rem;margin:0.75rem 0 0.35rem;"><strong>Alternatif berbasis ATR(14)</strong> dari entry reference (bandingkan dengan SL/TP struktural; pilih satu logika konsisten)</p>
                    <table style="margin-top:0.25rem;">
                        <tbody>
                            <tr><th>SL 1.0 ATR</th><td class="mono">{{ number_format($mc['atr_trade_levels']['stop_loss_1_0_atr'], 8, '.', '') }}</td></tr>
                            <tr><th>SL 1.5 ATR</th><td class="mono">{{ number_format($mc['atr_trade_levels']['stop_loss_1_5_atr'], 8, '.', '') }}</td></tr>
                            <tr><th>TP 2.0 ATR</th><td class="mono bull">{{ number_format($mc['atr_trade_levels']['take_profit_2_0_atr'], 8, '.', '') }}</td></tr>
                            <tr><th>TP 3.0 ATR</th><td class="mono bull">{{ number_format($mc['atr_trade_levels']['take_profit_3_0_atr'], 8, '.', '') }}</td></tr>
                            <tr><th>TP 4.5 ATR</th><td class="mono bull">{{ number_format($mc['atr_trade_levels']['take_profit_4_5_atr'], 8, '.', '') }}</td></tr>
                        </tbody>
                    </table>
                    <p style="font-size:0.72rem;color:var(--muted);margin:0.5rem 0 0;">{{ $mc['atr_trade_levels']['note'] ?? '' }}</p>
                @elseif (!empty($ts['has_setup']))
                    <p style="font-size:0.75rem;color:var(--muted);margin-top:0.75rem;">Alternatif ATR: tidak tersedia (ATR belum terhitung).</p>
                @endif
            </div>

            <div class="grid grid-2">
                <div class="card">
                    <h2>Struktur: BOS & MSS/ChoCh</h2>
                    @php $s = $result['structure']; @endphp
                    @if (!empty($s['bos']))
                        <p><span class="tag">{{ $s['bos']['direction'] === 'bullish' ? 'BOS ↑' : 'BOS ↓' }}</span>
                        level: <span class="mono">{{ number_format($s['bos']['broken_level'], 8, '.', '') }}</span></p>
                    @else
                        <p style="color:var(--muted);">Tidak ada BOS sederhana vs swing terakhir.</p>
                    @endif
                    @if (!empty($s['choch_mss']))
                        <p style="margin-top:0.5rem;"><span class="tag">{{ $s['choch_mss']['direction'] === 'bullish' ? 'MSS/ChoCh ↑' : 'MSS/ChoCh ↓' }}</span>
                        <span style="font-size:0.8rem;">{{ $s['choch_mss']['note'] ?? '' }}</span></p>
                        <p class="mono" style="font-size:0.78rem;">Level: {{ number_format($s['choch_mss']['broken_level'], 8, '.', '') }}</p>
                    @else
                        <p style="color:var(--muted);font-size:0.8rem;margin-top:0.5rem;">MSS/ChoCh: tidak terpenuhi (butuh urutan HH/HL atau LH/LL pada swing).</p>
                    @endif
                    @if (!empty($s['last_swing_high']))
                        <p class="mono" style="font-size:0.8rem;">Swing high terakhir: {{ number_format($s['last_swing_high']['price'], 8, '.', '') }}</p>
                    @endif
                    @if (!empty($s['last_swing_low']))
                        <p class="mono" style="font-size:0.8rem;">Swing low terakhir: {{ number_format($s['last_swing_low']['price'], 8, '.', '') }}</p>
                    @endif
                </div>
                <div class="card">
                    <h2>Catatan otomatis</h2>
                    <ul class="notes">
                        @foreach ($result['narrative'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h2>FVG (20 terakhir)</h2>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Arah</th>
                                <th>Zona low</th>
                                <th>Zona high</th>
                                <th>Status isi</th>
                                <th>Index</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result['fvgs'] as $f)
                                <tr>
                                    <td class="{{ $f['direction'] === 'bullish' ? 'bull' : 'bear' }}">{{ $f['direction'] }}</td>
                                    <td class="mono">{{ number_format($f['zone_low'], 8, '.', '') }}</td>
                                    <td class="mono">{{ number_format($f['zone_high'], 8, '.', '') }}</td>
                                    <td>{{ $f['fill_status'] ?? '—' }}</td>
                                    <td class="mono">{{ $f['formed_index'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="color:var(--muted);">Tidak ada FVG pada rentang ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-2" style="margin-top:1rem;">
                <div class="card">
                    <h2>Liquidity sweep</h2>
                    <p style="font-size:0.8rem;color:var(--muted);margin:0 0 0.5rem;">Wick menembus swing high/low lalu close kembali (pengambilan stop di atas/bawah).</p>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tipe</th>
                                    <th>Level</th>
                                    <th>Candle #</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($result['liquidity_sweeps'] ?? [] as $sw)
                                    <tr>
                                        <td>{{ $sw['type'] === 'sweep_high' ? 'Sweep ↑' : 'Sweep ↓' }}</td>
                                        <td class="mono">{{ number_format($sw['swept_level'], 8, '.', '') }}</td>
                                        <td class="mono">{{ $sw['candle_index'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="color:var(--muted);">Tidak ada sweep terdeteksi pada rentang candle ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <h2>Fibonacci & OTE vs FVG</h2>
                    @php $ote = $result['ote_fibonacci'] ?? []; @endphp
                    @if (!empty($ote['impulse']))
                        <p style="font-size:0.8rem;">Impuls terakhir (swing): <strong>{{ $ote['impulse'] }}</strong> · leg {{ number_format($ote['leg_low'], 8, '.', '') }} – {{ number_format($ote['leg_high'], 8, '.', '') }}</p>
                        @if (!empty($ote['levels']))
                            <p class="mono" style="font-size:0.78rem;">0.618: {{ number_format($ote['levels']['0.618'], 8, '.', '') }} · 0.705: {{ number_format($ote['levels']['0.705'], 8, '.', '') }} · 0.786: {{ number_format($ote['levels']['0.786'], 8, '.', '') }}</p>
                        @endif
                        @if (!empty($ote['golden_pocket']))
                            <p style="font-size:0.78rem;color:var(--muted);">Golden pocket (0.618–0.705): <span class="mono">{{ number_format($ote['golden_pocket']['low'], 8, '.', '') }} – {{ number_format($ote['golden_pocket']['high'], 8, '.', '') }}</span></p>
                        @endif
                        @if (!empty($ote['fvg_ote_confluence']))
                            <p style="font-size:0.8rem;margin-top:0.5rem;">Konfluensi FVG × OTE:</p>
                            <ul class="notes">
                                @foreach ($ote['fvg_ote_confluence'] as $cf)
                                    <li class="mono">{{ $cf['fvg_direction'] }} · {{ number_format($cf['zone_low'], 8, '.', '') }}–{{ number_format($cf['zone_high'], 8, '.', '') }}
                                        @if (!empty($cf['matches_golden_pocket_618_705'])) <span class="tag">618–705</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p style="color:var(--muted);font-size:0.8rem;">Belum ada FVG aktif yang berhimpit dengan zona OTE pada leg ini.</p>
                        @endif
                    @else
                        <p style="color:var(--muted);font-size:0.8rem;">Tidak cukup swing untuk menghitung Fib (butuh swing high & low terakhir).</p>
                    @endif
                </div>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h2>Order blocks (sederhana)</h2>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Tipe</th>
                                <th>Zona low</th>
                                <th>Zona high</th>
                                <th>Index</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result['order_blocks'] as $ob)
                                <tr>
                                    <td>{{ str_replace('_', ' ', $ob['type']) }}</td>
                                    <td class="mono">{{ number_format($ob['zone_low'], 8, '.', '') }}</td>
                                    <td class="mono">{{ number_format($ob['zone_high'], 8, '.', '') }}</td>
                                    <td class="mono">{{ $ob['formed_index'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="color:var(--muted);">Belum terdeteksi OB dengan pola displacement sederhana.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @vite(['resources/js/entry-watch.js', 'resources/js/analysis-chart.js'])
        @endif
    </div>
</body>
</html>

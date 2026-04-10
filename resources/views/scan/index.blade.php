<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan setup — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #0d1117; --panel: #161b22; --border: #30363d; --text: #e6edf3;
            --muted: #8b949e; --accent: #58a6ff; --green: #3fb950; --red: #f85149;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        .wrap { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        .sub { color: var(--muted); font-size: 0.875rem; margin-bottom: 1rem; }
        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        form.row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 1rem; }
        label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.75rem; color: var(--muted); }
        input, select, button {
            background: var(--bg); border: 1px solid var(--border); color: var(--text);
            padding: 0.45rem 0.6rem; border-radius: 6px;
        }
        button.primary { background: var(--accent); color: #0d1117; font-weight: 600; border: none; cursor: pointer; }
        button.secondary { background: #21262d; cursor: pointer; font-size: 0.8rem; }
        pre.signal {
            background: #0d1117; border: 1px solid var(--border); padding: 0.75rem; border-radius: 6px;
            font-size: 0.78rem; white-space: pre-wrap; word-break: break-all;
        }
        .err { color: var(--red); font-size: 0.85rem; }
        .tag { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; background: #21262d; margin-right: 0.35rem; }
        .claude { margin-top: 0.6rem; padding: 0.65rem; border: 1px solid var(--border); border-radius: 6px; background: #10151d; font-size: 0.8rem; white-space: pre-wrap; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Scan koin — zona entry siap (sesuai analisis)</h1>
        <p class="sub">Memeriksa daftar simbol di <span class="tag">SCAN_SYMBOLS</span> / quick list. Hanya menampilkan pair yang <strong>trade_setup.has_setup</strong> terpenuhi. Bukan sinyal investasi.</p>

        <form class="row" method="get" action="{{ route('scan.index') }}">
            <input type="hidden" name="run" value="1">
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
            <label style="font-size:0.8rem;display:flex;flex-direction:row;gap:0.5rem;align-items:center;color:var(--text);">
                <input type="checkbox" name="claude" value="1" @checked($withClaude || $claudeRequested) @disabled(!$claudeAvailable)>
                Tambah analisis Claude
            </label>
            <button type="submit" class="primary">Jalankan scan</button>
        </form>
        @if (!$claudeAvailable)
            <p style="font-size:0.78rem;color:var(--muted);margin-top:-0.5rem;margin-bottom:1rem;">Claude nonaktif: isi <code>ANTHROPIC_API_KEY</code> di .env untuk mengaktifkan analisis tambahan.</p>
        @endif

        <p style="font-size:0.8rem;color:var(--muted);">Simbol di-scan: {{ implode(', ', $scanSymbols) }} · Leverage teks: {{ $leverage }}x</p>
        <p style="font-size:0.8rem;"><a href="{{ route('analysis.index') }}">← Kembali ke analisis</a></p>

        @if ($ran && $errors !== [])
            <div class="card">
                <h2 style="margin-top:0;font-size:1rem;">Error / lewati</h2>
                @foreach ($errors as $e)
                    <p class="err">{{ $e['symbol'] }}: {{ $e['message'] }}</p>
                @endforeach
            </div>
        @endif

        @if ($ran && $signals === [])
            <div class="card">
                <p style="color:var(--muted);margin:0;">Tidak ada simbol yang memenuhi setup entry pada pengaturan ini. Coba interval lain atau perluas <code>SCAN_SYMBOLS</code> di .env.</p>
            </div>
        @endif

        @foreach ($signals as $sig)
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
                    <span>
                        <span class="tag">{{ $sig['symbol'] }}</span>
                        <span class="tag">{{ $sig['side'] }}</span>
                        <span class="tag">Accuracy: {{ $sig['accuracy'] }}</span>
                    </span>
                    <button type="button" class="secondary copy-one">Salin teks</button>
                </div>
                <pre class="signal">{{ $sig['card'] }}</pre>
                @if (!empty($sig['claude']))
                    <div class="claude"><strong>Analisis Claude</strong>
{{ $sig['claude'] }}</div>
                @endif
            </div>
        @endforeach

        @if ($ran && $signals !== [])
            <button type="button" class="primary" id="copy-all">Salin semua sinyal</button>
        @endif
    </div>

    @if ($ran && $signals !== [])
        <script id="all-signals-json" type="application/json">@json(collect($signals)->pluck('card')->implode("\n\n---\n\n"))</script>
        <script>
            document.querySelectorAll('.copy-one').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var pre = this.closest('.card')?.querySelector('pre.signal');
                    if (pre && navigator.clipboard?.writeText) {
                        navigator.clipboard.writeText(pre.textContent);
                    }
                });
            });
            var allEl = document.getElementById('all-signals-json');
            var copyAll = document.getElementById('copy-all');
            if (copyAll && allEl) {
                copyAll.addEventListener('click', function () {
                    try {
                        var txt = JSON.parse(allEl.textContent);
                        navigator.clipboard.writeText(txt);
                    } catch (e) {}
                });
            }
        </script>
    @endif
</body>
</html>

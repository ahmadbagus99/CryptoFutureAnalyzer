import { ColorType, createChart, CandlestickSeries } from 'lightweight-charts';

function parseBootstrap() {
    const el = document.getElementById('analysis-chart-candles');
    if (!el?.textContent) {
        return [];
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return [];
    }
}

function normalizeBars(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }
    const byTime = new Map();
    for (const r of rows) {
        if (!r || typeof r.time !== 'number') {
            continue;
        }
        byTime.set(r.time, {
            time: r.time,
            open: Number(r.open),
            high: Number(r.high),
            low: Number(r.low),
            close: Number(r.close),
        });
    }
    return Array.from(byTime.values()).sort((a, b) => a.time - b.time);
}

function initChart() {
    const root = document.getElementById('analysis-chart-mount');
    if (!root) {
        return;
    }

    const pollMs = Math.max(1000, parseInt(root.dataset.pollMs || '15000', 10) || 15000);
    const jsonUrl = root.dataset.jsonUrl || '';
    let bars = normalizeBars(parseBootstrap());

    const chart = createChart(root, {
        autoSize: true,
        layout: {
            background: { type: ColorType.Solid, color: '#161b22' },
            textColor: '#8b949e',
        },
        grid: {
            vertLines: { color: '#30363d' },
            horzLines: { color: '#30363d' },
        },
        rightPriceScale: {
            borderColor: '#30363d',
            scaleMargins: { top: 0.08, bottom: 0.12 },
        },
        timeScale: {
            borderColor: '#30363d',
            timeVisible: true,
            secondsVisible: false,
        },
        crosshair: {
            vertLine: { color: '#58a6ff', labelBackgroundColor: '#21262d' },
            horzLine: { color: '#58a6ff', labelBackgroundColor: '#21262d' },
        },
    });

    const series = chart.addSeries(CandlestickSeries, {
        upColor: '#3fb950',
        downColor: '#f85149',
        borderVisible: false,
        wickUpColor: '#3fb950',
        wickDownColor: '#f85149',
    });

    function emitChartUpdated() {
        if (bars.length === 0) {
            return;
        }
        const last = bars[bars.length - 1];
        window.dispatchEvent(
            new CustomEvent('analysis:chart-updated', {
                detail: {
                    lastClose: last.close,
                    lastBar: last,
                    bars,
                },
            })
        );
    }

    if (bars.length > 0) {
        series.setData(bars);
        chart.timeScale().fitContent();
        emitChartUpdated();
    }

    const statusEl = document.getElementById('analysis-chart-status');

    async function refresh() {
        if (!jsonUrl) {
            return;
        }
        try {
            const res = await fetch(jsonUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                throw new Error(String(res.status));
            }
            const payload = await res.json();
            const next = normalizeBars(payload?.data?.chart_candles);
            if (next.length === 0) {
                return;
            }
            bars = next;
            series.setData(bars);
            chart.timeScale().scrollToRealTime();
            emitChartUpdated();
            if (statusEl) {
                statusEl.textContent = 'Diperbarui: ' + new Date().toLocaleTimeString();
            }
        } catch {
            if (statusEl) {
                statusEl.textContent = 'Gagal refresh — coba lagi';
            }
        }
    }

    if (jsonUrl && pollMs > 0) {
        setInterval(refresh, pollMs);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChart);
} else {
    initChart();
}

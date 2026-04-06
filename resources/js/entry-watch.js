/**
 * Pengingat 5 menit + pantau zona entry (notifikasi browser).
 * Bukan prediksi — hanya alarm & kondisi harga vs zona analisis terakhir.
 */

const STORAGE_PREFIX = 'entryWatch_notified_';

function parseSetup() {
    const el = document.getElementById('analysis-trade-setup');
    if (!el?.textContent) {
        return null;
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

function parseSymbol() {
    const el = document.getElementById('analysis-page-meta');
    if (!el?.dataset?.symbol) {
        return '';
    }
    return el.dataset.symbol || '';
}

async function ensureNotificationPermission() {
    if (!('Notification' in window)) {
        return false;
    }
    if (Notification.permission === 'granted') {
        return true;
    }
    if (Notification.permission !== 'denied') {
        const p = await Notification.requestPermission();

        return p === 'granted';
    }

    return false;
}

function notify(title, body) {
    if (Notification.permission !== 'granted') {
        return;
    }
    try {
        new Notification(title, { body, tag: 'crypto-futures-analyzer', requireInteraction: false });
    } catch {
        // ignore
    }
}

function zoneStorageKey(setup) {
    const sym = parseSymbol();
    const z = `${setup.entry_zone_low}_${setup.entry_zone_high}_${setup.side}`;

    return STORAGE_PREFIX + sym + '_' + z.slice(0, 80);
}

function priceInEntryZone(close, setup) {
    if (!setup?.has_setup) {
        return false;
    }
    const lo = Number(setup.entry_zone_low);
    const hi = Number(setup.entry_zone_high);
    if (Number.isNaN(lo) || Number.isNaN(hi)) {
        return false;
    }
    const c = Number(close);

    return c >= lo && c <= hi;
}

function initRemind5m() {
    const btn = document.getElementById('btn-entry-remind-5m');
    if (!btn) {
        return;
    }

    const FIVE_MS = 5 * 60 * 1000;

    btn.addEventListener('click', async () => {
        const ok = await ensureNotificationPermission();
        if (!ok) {
            alert('Izinkan notifikasi di browser untuk pengingat 5 menit.');

            return;
        }
        btn.disabled = true;
        btn.textContent = 'Pengingat aktif…';
        setTimeout(() => {
            notify(
                'Review entry',
                'Sudah ±5 menit — buka halaman ini dan klik Analisis lagi untuk data terbaru (bukan sinyal otomatis).'
            );
            btn.disabled = false;
            btn.textContent = 'Ingatkan lagi 5 menit';
        }, FIVE_MS);
    });
}

const zoneNotified = new Map();

function handleChartUpdated(ev) {
    const chk = document.getElementById('chk-entry-zone-watch');
    if (!chk?.checked) {
        return;
    }
    const setup = parseSetup();
    if (!setup?.has_setup) {
        return;
    }
    const close = ev.detail?.lastClose;
    if (close === undefined || close === null) {
        return;
    }
    if (!priceInEntryZone(close, setup)) {
        return;
    }
    const storageKey = zoneStorageKey(setup);
    if (zoneNotified.get(storageKey) || sessionStorage.getItem(storageKey) === '1') {
        return;
    }
    zoneNotified.set(storageKey, true);
    sessionStorage.setItem(storageKey, '1');
    if (Notification.permission !== 'granted') {
        return;
    }
    notify(
        'Harga di zona entry',
        `${parseSymbol()}: close memasuki zona entry (${setup.side}). Cek chart & konfirmasi manual sebelum buka posisi.`
    );
}

window.addEventListener('analysis:chart-updated', handleChartUpdated);

function initZoneCheckbox() {
    const chk = document.getElementById('chk-entry-zone-watch');
    if (!chk) {
        return;
    }
    const setup = parseSetup();
    if (!setup?.has_setup) {
        chk.disabled = true;

        return;
    }
    chk.addEventListener('change', async () => {
        if (chk.checked) {
            const ok = await ensureNotificationPermission();
            if (!ok) {
                chk.checked = false;
            }
        }
    });
}

function init() {
    initRemind5m();
    initZoneCheckbox();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

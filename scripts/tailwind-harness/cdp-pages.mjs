/**
 * Runner CDP untuk HALAMAN NYATA aplikasi (pilot Feedback, F3): baseline "sebelum"
 * dan, dengan --label=sesudah, pengukuran ulang yang sama setelah migrasi.
 *
 * Node 22 (WebSocket + fetch global), TANPA npm. Meluncurkan SATU browser headless
 * milik runner (port debug 9333 Chrome / 9334 Edge, --user-data-dir sementara baru),
 * login lewat FORMULIR nyata (klik + ketik + Enter), mengunjungi halaman, menyimpan
 * screenshot (hanya browser --foto), ringkasan gaya terkomputasi, daftar CSS/JS yang
 * dimuat, galat konsol/JS, overflow horizontal, dan pemeriksaan fokus/ARIA dasar.
 * Terakhir mematikan HANYA proses miliknya (Browser.close; taskkill /PID hanya bila
 * browser tidak keluar sendiri) dan memverifikasi sisa proses = 0.
 *
 * Prasyarat: server pilot sudah berjalan (php -S) ke DB UJI, mis. http://127.0.0.1:8099,
 * akun uji dari seeder (DatabaseSeeder -> superadmin; DummyUserPerRoleSeeder -> dummy.staff).
 * Kata sandi dibaca dari opsi/variabel lingkungan, TIDAK ditulis di berkas ini:
 *   --pw-superadmin=... --pw-staff=...  atau  TESYS_PW_SUPERADMIN / TESYS_PW_STAFF
 *   (akun hak akses penuh bawaan 'superadmin'; ganti dengan --user-superadmin=dummy.superadmin bila kata sandinya berbeda)
 *
 * Pakai (jalur node CONTOH; cari lewat `Get-Command node`):
 *   node scripts/tailwind-harness/cdp-pages.mjs --browser=chrome,edge --label=sebelum
 *     --base=http://127.0.0.1:8099   --out=docs/ui/tailwind-evidence/f3
 *     --foto=chrome  --chrome=<path> --edge=<path>
 *   Hasil: <out>/<label>/*.png (screenshot) dan <out>/hasil-<label>-<browser>.json
 *
 * PERINGATAN: skenario MENULIS data uji (1 submission valid + 1 tanggapan + 1 close,
 * serta 2 kiriman invalid yang ditolak server) ke DB yang dipakai server pilot.
 *
 * Keselamatan proses: jangan menjalankan chrome.exe/msedge.exe langsung (termasuk
 * --version) dan jangan python/python3. Versi berkas dibaca lewat PowerShell VersionInfo.
 */

import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath, pathToFileURL } from 'node:url';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const arg = (nama, bawaan) => {
    const a = process.argv.find((x) => x.startsWith('--' + nama + '='));
    return a ? a.slice(nama.length + 3) : bawaan;
};
const BASE = arg('base', 'http://127.0.0.1:8099').replace(/\/$/, '');
const LABEL = arg('label', 'sebelum');
const OUT_ROOT = path.resolve(AKAR, arg('out', 'docs/ui/tailwind-evidence/f3'));
const OUT_FOTO = path.join(OUT_ROOT, LABEL);
const DAFTAR_BROWSER = arg('browser', 'chrome,edge').split(',').filter(Boolean);
const FOTO = arg('foto', 'chrome').split(',');
const EXE = {
    chrome: arg('chrome', 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'),
    edge: arg('edge', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe'),
};
const PORT = { chrome: 9333, edge: 9334 };
const AKUN = {
    superadmin: { username: arg('user-superadmin', 'superadmin'), pw: arg('pw-superadmin', process.env.TESYS_PW_SUPERADMIN || '') },
    staff: { username: 'dummy.staff', pw: arg('pw-staff', process.env.TESYS_PW_STAFF || '') },
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sha = (x) => crypto.createHash('sha256').update(typeof x === 'string' ? x : JSON.stringify(x)).digest('hex');

// Viewport bawaan 390x844 / 1366x768 (F3). Aditif (F4): --viewport=1440x900 mengganti desktop,
// --viewport-mobile=390x844 mengganti mobile. Tanpa opsi, perilaku F3 tidak berubah.
const vpDariArg = (nama, bawaan, mobile) => {
    const m = /^(\d{3,4})x(\d{3,4})$/.exec(arg(nama, ''));
    return m ? { id: m[0], w: Number(m[1]), h: Number(m[2]), mobile } : bawaan;
};
const VP = {
    mobile: vpDariArg('viewport-mobile', { id: '390x844', w: 390, h: 844, mobile: true }, true),
    desktop: vpDariArg('viewport', { id: '1366x768', w: 1366, h: 768, mobile: false }, false),
};
const TEMA = ['terang', 'gelap'];

// ---------------------------------------------------------------------------
// Klien CDP (sama dengan cdp-run.mjs, ditambah pendengar peristiwa)
// ---------------------------------------------------------------------------
class Cdp {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.id = 0;
        this.pending = new Map();
        this.listeners = [];
        this.ws.addEventListener('message', (m) => {
            const d = JSON.parse(m.data);
            if (d.id) {
                const p = this.pending.get(d.id);
                if (!p) return;
                this.pending.delete(d.id);
                if (d.error) p.rej(new Error(`${p.method}: ${d.error.message}`));
                else p.res(d.result);
            } else {
                for (const l of this.listeners) l(d);
            }
        });
    }
    open() {
        return new Promise((res, rej) => {
            this.ws.addEventListener('open', () => res());
            this.ws.addEventListener('error', () => rej(new Error('WebSocket CDP gagal')));
        });
    }
    send(method, params = {}, sessionId) {
        const id = ++this.id;
        const msg = { id, method, params };
        if (sessionId) msg.sessionId = sessionId;
        this.ws.send(JSON.stringify(msg));
        return new Promise((res, rej) => this.pending.set(id, { res, rej, method }));
    }
    close() { try { this.ws.close(); } catch { /* abaikan */ } }
}

// ---------------------------------------------------------------------------
// Peluncuran / penghentian browser (hanya milik sendiri)
// ---------------------------------------------------------------------------
function versiBerkas(exe) {
    if (exe.includes("'")) return 'ditolak: jalur memuat tanda kutip tunggal';
    try {
        return execFileSync('powershell', ['-NoProfile', '-Command', `(Get-Item -LiteralPath '${exe}').VersionInfo.ProductVersion`], { encoding: 'utf8' }).trim();
    } catch { return 'tidak diketahui'; }
}
function prosesDenganPenanda(penanda) {
    try {
        const cmd = `@(Get-CimInstance Win32_Process | Where-Object { $_.ProcessId -ne $PID -and $_.CommandLine -like '*${penanda}*' }).Count`;
        return Number(execFileSync('powershell', ['-NoProfile', '-Command', cmd], { encoding: 'utf8' }).trim());
    } catch { return -1; }
}
async function portBebas(port) {
    try { await fetch(`http://127.0.0.1:${port}/json/version`, { signal: AbortSignal.timeout(1500) }); return false; } catch { return true; }
}
async function mulaiBrowser(nama) {
    const exe = EXE[nama];
    if (!fs.existsSync(exe)) throw new Error(`Browser ${nama} tidak ditemukan: ${exe}`);
    const port = PORT[nama];
    if (!(await portBebas(port))) throw new Error(`Port ${port} sudah dipakai proses lain; runner berhenti (tidak menyentuh proses itu).`);
    const userDir = fs.mkdtempSync(path.join(os.tmpdir(), `tesys-pages-${nama}-`));
    const args = [
        '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${userDir}`,
        `--remote-allow-origins=http://127.0.0.1:${port}`,
        '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-sync',
        '--disable-background-networking', '--disable-component-update', '--mute-audio',
        'about:blank',
    ];
    const child = spawn(exe, args, { stdio: 'ignore', windowsHide: true });
    const B = { nama, exe, port, userDir, child, pid: child.pid, keluar: false };
    child.on('exit', () => { B.keluar = true; });
    let ver = null;
    for (let i = 0; i < 100 && !ver; i++) {
        try { ver = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); } catch { await sleep(200); }
    }
    if (!ver) { await hentikanBrowser(B); throw new Error('Endpoint debug tidak muncul'); }
    B.versi = ver.Browser;
    B.cdp = new Cdp(ver.webSocketDebuggerUrl);
    await B.cdp.open();
    return B;
}
async function hentikanBrowser(B) {
    const log = { pid: B.pid, penanda: path.basename(B.userDir) };
    try { if (B.cdp) { await Promise.race([B.cdp.send('Browser.close'), sleep(3000)]); B.cdp.close(); } } catch { /* abaikan */ }
    for (let i = 0; i < 40 && !B.keluar; i++) await sleep(150);
    log.keluarSendiri = B.keluar;
    if (!B.keluar) {
        try { execFileSync('taskkill', ['/PID', String(B.pid), '/T', '/F'], { stdio: 'ignore' }); log.taskkill = true; } catch { log.taskkill = 'gagal'; }
        for (let i = 0; i < 20 && !B.keluar; i++) await sleep(150);
    }
    // Tunggu proses milik runner (penanda = nama user-data-dir unik) benar-benar hilang: induk Edge bisa
    // masih terdaftar beberapa detik setelah Browser.close/kill (pemeriksaan 0,6 dtk menghasilkan positif palsu).
    // Polling minimal 3 dtk, maksimal 20 dtk; hanya proses ber-penanda runner yang dihitung.
    const t0 = Date.now();
    let sisa = prosesDenganPenanda(log.penanda);
    while ((sisa !== 0 || Date.now() - t0 < 3000) && Date.now() - t0 < 20000) {
        await sleep(500);
        sisa = prosesDenganPenanda(log.penanda);
    }
    log.menungguSisaMs = Date.now() - t0;
    log.sisaProsesPenanda = sisa;
    for (let i = 0; i < 10; i++) {
        try { fs.rmSync(B.userDir, { recursive: true, force: true }); break; } catch { await sleep(300); }
    }
    log.userDirDihapus = !fs.existsSync(B.userDir);
    return log;
}

// ---------------------------------------------------------------------------
// Skrip di dalam halaman
// ---------------------------------------------------------------------------
const KUNCI = [
    'h1', '.breadcrumb', '.content .card', '.card-header', '.card-title', '.card-body', 'table.table', 'thead th', 'tbody td',
    '.badge', '.btn', '.btn-primary', '.btn-success', '.btn-secondary', '.btn-info', '.form-control', 'label', '.form-group',
    '.alert', '.alert-success', '.alert-danger', '.alert-warning', '.ts-action-primary', '.custom-control-label', '.custom-control-input',
    'small.text-muted', '.dataTables_wrapper', '.dataTables_paginate .paginate_button.current', '.dataTables_length select', '.sidebar, .side, aside', 'main, .main',
];
const PROPS = ['color', 'backgroundColor', 'borderTopColor', 'borderTopWidth', 'borderTopLeftRadius', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight',
    'paddingTop', 'paddingRight', 'paddingLeft', 'marginBottom', 'boxShadow', 'display', 'textTransform', 'letterSpacing', 'opacity', 'outlineStyle', 'zoom'];

function ukurDiHalaman(KUNCI, PROPS, rinci) {
    const vis = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const ringkas = (el) => {
        const s = getComputedStyle(el); const r = el.getBoundingClientRect(); const o = {};
        for (const p of PROPS) o[p] = p === 'fontFamily' ? String(s[p]).split(',')[0].trim() : s[p];
        o.w = Math.round(r.width); o.h = Math.round(r.height);
        return o;
    };
    const kunci = {};
    for (const sel of KUNCI) {
        const el = [...document.querySelectorAll(sel)].find(vis);
        kunci[sel] = el ? ringkas(el) : null;
    }
    // digest seluruh elemen tampak di area konten (atau body) pada sejumlah properti inti
    const akar = document.querySelector('.main .content, .content, main') || document.body;
    const inti = ['color', 'backgroundColor', 'fontSize', 'fontWeight', 'borderTopWidth', 'borderTopColor', 'borderTopLeftRadius', 'paddingTop', 'paddingLeft', 'display', 'boxShadow'];
    const baris = [];
    let n = 0;
    for (const el of akar.querySelectorAll('*')) {
        if (n >= 1500) break;
        if (!vis(el)) continue;
        n++;
        const s = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        baris.push(el.tagName + '.' + String(el.className && el.className.baseVal !== undefined ? el.className.baseVal : el.className).trim().replace(/\s+/g, '.') + '|' + inti.map((p) => s[p]).join('|') + '|' + Math.round(r.width) + 'x' + Math.round(r.height));
    }
    // overflow horizontal
    const de = document.documentElement;
    const lebar = window.innerWidth;
    const pelanggar = [];
    for (const el of document.body.querySelectorAll('*')) {
        const r = el.getBoundingClientRect();
        if (r.width > 0 && r.right > lebar + 1 && vis(el) && getComputedStyle(el).position !== 'fixed') {
            // abaikan yang berada di dalam wadah yang menggulir sendiri
            let p = el.parentElement, terpotong = false;
            while (p && p !== document.body) { const ox = getComputedStyle(p).overflowX; if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') { terpotong = true; break; } p = p.parentElement; }
            if (!terpotong) pelanggar.push(el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + '.' + String(el.className).trim().split(/\s+/).slice(0, 3).join('.') + ' kanan=' + Math.round(r.right));
            if (pelanggar.length >= 6) break;
        }
    }
    // aksesibilitas dasar
    const namaAksesibel = (el) => {
        if (el.getAttribute('aria-label')) return true;
        const lb = el.getAttribute('aria-labelledby');
        if (lb && lb.split(/\s+/).some((i) => document.getElementById(i))) return true;
        if (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) return true;
        if (el.closest('label')) return true;
        if (el.getAttribute('title')) return true;
        if (el.matches('button, a, [role=button]') && (el.textContent || '').trim()) return true;
        if (el.matches('input[type=submit],input[type=button]') && el.value) return true;
        return false;
    };
    const kontrol = [...document.querySelectorAll('.content input:not([type=hidden]), .content select, .content textarea')].filter(vis);
    const tanpaNama = kontrol.filter((e) => !namaAksesibel(e)).map((e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.name ? '[name=' + e.name + ']' : ''));
    const tombol = [...document.querySelectorAll('.content button, .content a.btn, .content [role=button]')].filter(vis);
    const tombolTanpaNama = tombol.filter((e) => !namaAksesibel(e)).length;
    const sentuh = [...document.querySelectorAll('.content a, .content button, .content input:not([type=hidden]), .content select, .content textarea, .content .custom-control-label')].filter(vis)
        .map((e) => { const r = e.getBoundingClientRect(); return { el: e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className ? '.' + String(e.className).trim().split(/\s+/)[0] : ''), w: Math.round(r.width), h: Math.round(r.height) }; });
    const kecil = sentuh.filter((s) => s.w < 44 || s.h < 44);
    const hasil = {
        title: document.title,
        url: location.pathname + location.search,
        temaAttr: de.getAttribute('data-theme'),
        lang: de.lang,
        zoomHtml: getComputedStyle(de).zoom,
        scrollWidth: de.scrollWidth, clientWidth: de.clientWidth, bodyScrollWidth: document.body.scrollWidth, innerWidth: lebar,
        overflowHorizontal: de.scrollWidth > de.clientWidth + 1 || document.body.scrollWidth * (parseFloat(getComputedStyle(de).zoom) || 1) > lebar + 2,
        pelanggarOverflow: pelanggar,
        h1: [...document.querySelectorAll('h1')].filter(vis).map((h) => h.textContent.trim().replace(/\s+/g, ' ').slice(0, 80)),
        landmark: { main: document.querySelectorAll('main, [role=main]').length, nav: document.querySelectorAll('nav, [role=navigation]').length, banner: document.querySelectorAll('header, [role=banner]').length },
        a11y: { kontrolTanpaNama: tanpaNama, tombolTanpaNama, imgTanpaAlt: [...document.querySelectorAll('.content img:not([alt])')].length, ariaInvalid: document.querySelectorAll('.content [aria-invalid=true]').length, roleAlert: document.querySelectorAll('.content [role=alert]').length },
        sentuh: { total: sentuh.length, di44: sentuh.length - kecil.length, kurangDari44: kecil.length, contoh: kecil.slice(0, 8) },
        badge: [...document.querySelectorAll('.content .badge')].filter(vis).map((b) => ({ t: b.textContent.trim(), c: [...b.classList].filter((k) => k.startsWith('badge-')).join(' '), bg: getComputedStyle(b).backgroundColor, fg: getComputedStyle(b).color })).slice(0, 8),
        dataTables: !!document.querySelector('.dataTables_wrapper'),
        teksAlert: [...document.querySelectorAll('.content .alert')].filter(vis).map((a) => a.className.replace(/\s+/g, ' ') + ' :: ' + a.textContent.trim().replace(/\s+/g, ' ').slice(0, 160)),
        stylesheetAktif: [...document.styleSheets].map((s) => s.href ? s.href.replace(location.origin, '').replace(/\?.*$/, '') : '(inline)').filter((h) => h !== '(inline)'),
        digestGaya: null,
        jumlahElemenDigest: n,
    };
    return { hasil, kunci, baris };
}

// ---------------------------------------------------------------------------
// Halaman (satu target di satu browser context)
// ---------------------------------------------------------------------------
class Halaman {
    constructor(cdp, sessionId) {
        this.cdp = cdp; this.sid = sessionId;
        this.reset();
        cdp.listeners.push((d) => { if (d.sessionId === sessionId) this.onEvent(d.method, d.params); });
    }
    reset() { this.konsol = []; this.jaringan = new Map(); this.gagal = []; this.dokumen = null; this.dialog = []; this.loaded = false; }
    send(m, p = {}) { return this.cdp.send(m, p, this.sid); }
    onEvent(m, p) {
        if (m === 'Runtime.exceptionThrown') this.konsol.push({ jenis: 'exception', teks: (p.exceptionDetails.exception?.description || p.exceptionDetails.text || '').split('\n')[0].slice(0, 220) });
        else if (m === 'Runtime.consoleAPICalled' && (p.type === 'error' || p.type === 'warning')) this.konsol.push({ jenis: 'console.' + p.type, teks: p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 220) });
        else if (m === 'Log.entryAdded' && (p.entry.level === 'error' || p.entry.level === 'warning')) this.konsol.push({ jenis: 'log.' + p.entry.level, teks: (p.entry.text + ' ' + (p.entry.url || '')).slice(0, 220) });
        else if (m === 'Network.requestWillBeSent') this.jaringan.set(p.requestId, { url: p.request.url, tipe: p.type, status: null });
        else if (m === 'Network.responseReceived') {
            const r = this.jaringan.get(p.requestId) || { url: p.response.url, tipe: p.type };
            r.status = p.response.status; r.tipe = p.type; this.jaringan.set(p.requestId, r);
            if (p.type === 'Document') this.dokumen = { url: p.response.url, status: p.response.status };
        } else if (m === 'Network.loadingFailed') {
            const r = this.jaringan.get(p.requestId);
            this.gagal.push({ url: r?.url || '?', tipe: r?.tipe, galat: p.errorText });
        } else if (m === 'Page.javascriptDialogOpening') {
            this.dialog.push({ tipe: p.type, pesan: p.message });
            this.send('Page.handleJavaScriptDialog', { accept: true }).catch(() => {});
        } else if (m === 'Page.loadEventFired') this.loaded = true;
    }
    async ev(ekspr, opsi = {}) {
        const r = await this.send('Runtime.evaluate', { expression: ekspr, returnByValue: true, awaitPromise: true, ...opsi });
        if (r.exceptionDetails) throw new Error('evaluate: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text));
        return r.result.value;
    }
    async viewport(v) {
        await this.send('Emulation.setDeviceMetricsOverride', { width: v.w, height: v.h, deviceScaleFactor: 1, mobile: !!v.mobile, screenWidth: v.w, screenHeight: v.h });
        await this.send('Emulation.setTouchEmulationEnabled', { enabled: !!v.mobile, maxTouchPoints: 5 });
    }
    async tungguLoad(timeout = 30000) {
        const t0 = Date.now();
        while (!this.loaded && Date.now() - t0 < timeout) await sleep(100);
        const dipenuhi = this.loaded;
        await this.ev('document.fonts && document.fonts.ready.then(()=>true)').catch(() => {});
        await sleep(900);
        return dipenuhi;
    }
    async goto(path_) {
        this.reset();
        await this.send('Page.navigate', { url: BASE + path_ });
        return this.tungguLoad();
    }
    async ada(sel) { return this.ev(`!!document.querySelector(${JSON.stringify(sel)})`); }
    async tunggu(sel, ms = 8000) {
        const t0 = Date.now();
        while (Date.now() - t0 < ms) { if (await this.ev(`(()=>{const e=document.querySelector(${JSON.stringify(sel)});if(!e)return false;const r=e.getBoundingClientRect();return r.width>0&&r.height>0})()`)) return true; await sleep(150); }
        return false;
    }
    async klik(sel) {
        const pos = await this.ev(`(()=>{const e=document.querySelector(${JSON.stringify(sel)});if(!e)return null;e.scrollIntoView({block:'center',inline:'center'});const r=e.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2}})()`);
        if (!pos) throw new Error('klik: selektor tidak ditemukan ' + sel);
        await sleep(120);
        await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: pos.x, y: pos.y });
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: pos.x, y: pos.y, button: 'left', clickCount: 1 });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: pos.x, y: pos.y, button: 'left', clickCount: 1 });
    }
    async ketik(sel, teks) {
        await this.klik(sel);
        await this.ev(`(()=>{const e=document.querySelector(${JSON.stringify(sel)});if(e&&e.select)e.select()})()`);
        if (teks === '') await this.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Delete', code: 'Delete', windowsVirtualKeyCode: 46 });
        else await this.send('Input.insertText', { text: teks });
    }
    async enter() {
        await this.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r' });
        await this.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    }
    async tab(shift = false) {
        const mod = shift ? 8 : 0;
        await this.send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: mod });
        await this.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: mod });
    }
    /** Pilih nilai <select> lewat JS + peristiwa change (headless tak punya popup native). */
    async pilih(sel, nilai) {
        await this.ev(`(()=>{const e=document.querySelector(${JSON.stringify(sel)});e.value=${JSON.stringify(nilai)};e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}))})()`);
    }
    /** Jalankan aksi yang memicu navigasi; tunggu load berikutnya (atau tidak ada navigasi). */
    async aksiNavigasi(fn, timeout = 20000) {
        this.reset();
        await fn();
        const t0 = Date.now();
        while (!this.loaded && Date.now() - t0 < timeout) await sleep(100);
        const bernavigasi = this.loaded;
        if (bernavigasi) { await this.ev('document.fonts && document.fonts.ready.then(()=>true)').catch(() => {}); await sleep(900); }
        return bernavigasi;
    }
    async foto(nama, aktif) {
        if (!aktif) return null;
        fs.mkdirSync(OUT_FOTO, { recursive: true });
        const { data } = await this.send('Page.captureScreenshot', { format: 'png' });
        const f = path.join(OUT_FOTO, nama + '.png');
        fs.writeFileSync(f, Buffer.from(data, 'base64'));
        return path.relative(AKAR, f).replace(/\\/g, '/');
    }
    async setTema(tema) {
        await this.ev(`localStorage.setItem('tesys-theme', ${JSON.stringify(tema === 'gelap' ? 'dark' : 'light')})`);
    }
    /** Fokus papan ketik dasar: Tab pertama dari awal dokumen. */
    async fokusAwal() {
        await this.ev('document.activeElement && document.activeElement.blur(); window.scrollTo(0,0)');
        await this.tab(); await sleep(150);
        return this.ev(`(()=>{const e=document.activeElement;if(!e||e===document.body)return {el:'body'};const s=getComputedStyle(e);return {el:e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.className?'.'+String(e.className).trim().split(/\\s+/)[0]:''),teks:(e.getAttribute('aria-label')||e.textContent||'').trim().replace(/\\s+/g,' ').slice(0,40),outline:s.outlineStyle+' '+s.outlineWidth+' '+s.outlineColor,boxShadow:s.boxShadow.slice(0,60)}})()`);
    }
    /** Ukur halaman terbuka: ringkasan + CSS/JS dimuat + galat. */
    async ukur(opsi = {}) {
        const { hasil, kunci, baris } = await this.ev(`(${ukurDiHalaman.toString()})(${JSON.stringify(KUNCI)}, ${JSON.stringify(PROPS)})`);
        hasil.digestGaya = sha(baris);
        hasil.digestKunci = sha(kunci);
        const css = [], js = [], lain = [];
        for (const r of this.jaringan.values()) {
            const u = r.url.replace(/\?.*$/, '');
            const rel = u.startsWith(BASE) ? u.slice(BASE.length) : u;
            if (r.tipe === 'Stylesheet') css.push(rel + (r.status && r.status >= 400 ? ' [HTTP ' + r.status + ']' : ''));
            else if (r.tipe === 'Script') js.push(rel + (r.status && r.status >= 400 ? ' [HTTP ' + r.status + ']' : ''));
            else if (r.status && r.status >= 400) lain.push(rel + ' [HTTP ' + r.status + ']');
        }
        const gagal = this.gagal.map((g) => g.tipe + ' ' + g.url.replace(BASE, '').replace(/\?.*$/, '') + ' ' + g.galat);
        const fokus = opsi.fokus === false ? undefined : await this.fokusAwal();
        return {
            httpDokumen: this.dokumen?.status ?? null,
            ...hasil,
            fokusTabPertama: fokus,
            cssDimuat: css, jsDimuat: js,
            adaTesysTwCss: css.some((c) => c.includes('tesys-tw.css')),
            permintaanGalat: lain, permintaanGagal: gagal,
            konsol: this.konsol, dialogNative: this.dialog,
            kunci: opsi.rinci ? kunci : undefined,
        };
    }
}

// ---------------------------------------------------------------------------
// Skenario
// ---------------------------------------------------------------------------
async function bukaKonteks(B, viewport) {
    const { browserContextId } = await B.cdp.send('Target.createBrowserContext');
    const { targetId } = await B.cdp.send('Target.createTarget', { url: 'about:blank', browserContextId });
    const { sessionId } = await B.cdp.send('Target.attachToTarget', { targetId, flatten: true });
    const P = new Halaman(B.cdp, sessionId);
    for (const d of ['Page', 'Runtime', 'Network', 'Log']) await P.send(d + '.enable');
    await P.viewport(viewport);
    return { P, browserContextId, targetId };
}

async function login(P, akun) {
    if (!akun.pw) throw new Error('Kata sandi untuk ' + akun.username + ' tidak diberikan (--pw-... atau TESYS_PW_...).');
    await P.goto('/login');
    await P.tunggu('#username');
    await P.ketik('#username', akun.username);
    await P.klik('#password');
    await P.send('Input.insertText', { text: akun.pw });
    const nav = await P.aksiNavigasi(() => P.enter());
    const url = await P.ev('location.pathname');
    const galat = await P.ev(`(()=>{const a=document.querySelector('.auth-alert-danger');return a?a.textContent.trim().replace(/\\s+/g,' ').slice(0,120):null})()`);
    return { akun: akun.username, navigasi: nav, urlSetelahLogin: url, berhasil: !url.startsWith('/login'), galatLogin: galat };
}

async function jalankan(nama) {
    const B = await mulaiBrowser(nama);
    const fotoAktif = FOTO.includes(nama);
    const R = {
        browser: nama, versiBrowser: B.versi, versiBerkasExe: versiBerkas(EXE[nama]), label: LABEL, base: BASE,
        waktu: new Date().toISOString(), foto: fotoAktif, akunUji: Object.fromEntries(Object.entries(AKUN).map(([k, v]) => [k, v.username])),
        halaman: [], skenario: [], berkasFoto: [], galatRunner: [],
    };
    const catat = (grup, id, data) => R[grup].push({ id, ...data });
    const gabung = (...p) => p.join('-');
    const konteks = [];
    try {
        // Ukur sebuah URL pada seluruh kombinasi tema x viewport (screenshot hanya untuk kombinasi yang diminta).
        async function sapuHalaman(P, id, urlPath, { foto = [], prasyarat = null, rinci = [] } = {}) {
            for (const tema of TEMA) {
                for (const vpNama of Object.keys(VP)) {
                    const v = VP[vpNama];
                    const kombinasi = `${tema}-${v.id}`;
                    try {
                        await P.viewport(v);
                        await P.setTema(tema);
                        const dimuat = await P.goto(urlPath);
                        if (prasyarat) await prasyarat(P);
                        const m = await P.ukur({ rinci: rinci.includes(kombinasi) });
                        m.loadEventTercapai = dimuat;
                        m.foto = await P.foto(gabung(id, tema, v.id), fotoAktif && foto.includes(kombinasi));
                        if (m.foto) R.berkasFoto.push(m.foto);
                        catat('halaman', id, { tema, viewport: v.id, ...m });
                    } catch (e) {
                        R.galatRunner.push(`${id} ${kombinasi}: ${e.message}`);
                        catat('halaman', id, { tema, viewport: v.id, galat: e.message });
                    }
                }
            }
        }
        const D = `terang-${VP.desktop.id}`, M = `terang-${VP.mobile.id}`, DG = `gelap-${VP.desktop.id}`, MG = `gelap-${VP.mobile.id}`;

        // ============ STAFF ============
        const st = await bukaKonteks(B, VP.desktop); konteks.push(st);
        const P = st.P;
        catat('skenario', 'login-staff', await login(P, AKUN.staff));

        await sapuHalaman(P, 'feedback-index-kosong', '/feedback', { foto: [D, M], rinci: [D, MG] });
        await sapuHalaman(P, 'feedback-create-awal', '/feedback/create', { foto: [], rinci: [D] });

        // create: keadaan CRITICISM + bersedia diskusi (dua textarea + WhatsApp)
        await sapuHalaman(P, 'feedback-create-criticism', '/feedback/create', {
            foto: [D, M, DG], rinci: [D],
            prasyarat: async (p) => {
                await p.pilih('#selCategory', 'CRITICISM');
                await p.klik('label[for=chkWilling]');
                await sleep(300);
            },
        });

        // create: kontrak JS (toggle) dan keadaan elemen
        await P.viewport(VP.desktop); await P.setTema('terang');
        await P.goto('/feedback/create');
        const kontrak = {};
        kontrak.awal = await P.ev(`({msgTampak:getComputedStyle(document.querySelector('#wrapMessage')).display!=='none',critTampak:getComputedStyle(document.querySelector('#wrapCriticism')).display!=='none',waTampak:getComputedStyle(document.querySelector('#wrapWhatsapp')).display!=='none',msgReq:document.querySelector('#inpMessage').required,selectTomSelect:!!document.querySelector('#selCategory + .ts-wrapper, #selCategory ~ .ts-wrapper, #selCategory[tabindex="-1"]'),opsi:[...document.querySelectorAll('#selCategory option')].map(o=>o.value)})`);
        await P.pilih('#selCategory', 'CRITICISM'); await P.klik('label[for=chkWilling]'); await sleep(250);
        kontrak.criticism = await P.ev(`({msgTampak:getComputedStyle(document.querySelector('#wrapMessage')).display!=='none',critTampak:getComputedStyle(document.querySelector('#wrapCriticism')).display!=='none',waTampak:getComputedStyle(document.querySelector('#wrapWhatsapp')).display!=='none',critReq:document.querySelector('#inpCriticism').required,sugReq:document.querySelector('#inpSuggestion').required,waReq:document.querySelector('#inpWhatsapp').required,msgReq:document.querySelector('#inpMessage').required,chk:document.querySelector('#chkWilling').checked})`);
        await P.pilih('#selCategory', 'SUGGESTION'); await P.klik('label[for=chkWilling]'); await sleep(250);
        kontrak.suggestionTanpaWilling = await P.ev(`({msgTampak:getComputedStyle(document.querySelector('#wrapMessage')).display!=='none',critTampak:getComputedStyle(document.querySelector('#wrapCriticism')).display!=='none',waTampak:getComputedStyle(document.querySelector('#wrapWhatsapp')).display!=='none',msgReq:document.querySelector('#inpMessage').required,waReq:document.querySelector('#inpWhatsapp').required})`);
        catat('skenario', 'create-kontrak-toggle', kontrak);

        // INVALID A: validasi native peramban memblokir (judul kosong), lalu server (noValidate)
        for (const [vpNama, tema] of [['desktop', 'terang'], ['mobile', 'terang']]) {
            const v = VP[vpNama];
            await P.viewport(v); await P.setTema(tema);
            await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'SUGGESTION');
            await P.ketik('textarea#inpMessage', 'Isi masukan uji invalid');
            const nativeBlok = !(await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'), 2500));
            const nativePesan = await P.ev(`(()=>{const e=document.querySelector('#frmFeedback input[name=title]');return {valid:document.querySelector('#frmFeedback').checkValidity(),pesan:e.validationMessage,fokus:document.activeElement===e}})()`);
            await P.ev(`document.querySelector('#frmFeedback').noValidate=true`);
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            await P.setTema(tema);
            const m = await P.ukur({ fokus: false });
            m.foto = await P.foto(gabung('feedback-create-invalid-judul', tema, v.id), fotoAktif && vpNama === 'desktop');
            if (m.foto) R.berkasFoto.push(m.foto);
            const errorList = await P.ev(`[...document.querySelectorAll('.content .alert-danger li, .content div[role=alert] > div:last-child > div > span:last-child')].map(l=>l.textContent.trim())`);
            const isi = await P.ev(`({judul:document.querySelector('input[name=title]').value,message:document.querySelector('#inpMessage').value,kategori:document.querySelector('#selCategory').value})`);
            catat('skenario', `create-invalid-judul-kosong-${v.id}`, { nativeMemblokirSubmit: nativeBlok, nativeStatus: nativePesan, serverNavigasi: nav, daftarGalat: errorList, nilaiTerjagaSetelahGalat: isi, ...m });
        }

        // INVALID B: bersedia diskusi tanpa WhatsApp (desktop terang)
        {
            await P.viewport(VP.desktop); await P.setTema('terang');
            await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'SUGGESTION');
            await P.ketik('input[name=title]', 'Uji WA kosong');
            await P.ketik('textarea#inpMessage', 'Isi masukan uji WA kosong');
            await P.klik('label[for=chkWilling]');
            const nativeBlok = !(await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'), 2500));
            const nativeWa = await P.ev(`({pesan:document.querySelector('#inpWhatsapp').validationMessage,fokus:document.activeElement===document.querySelector('#inpWhatsapp')})`);
            await P.ev(`document.querySelector('#frmFeedback').noValidate=true`);
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            const m = await P.ukur({ fokus: false });
            m.foto = await P.foto('feedback-create-invalid-wa-terang-1366x768', fotoAktif);
            if (m.foto) R.berkasFoto.push(m.foto);
            const errorList = await P.ev(`[...document.querySelectorAll('.content .alert-danger li, .content div[role=alert] > div:last-child > div > span:last-child')].map(l=>l.textContent.trim())`);
            const waKembali = await P.ev(`({chk:document.querySelector('#chkWilling').checked,waTampak:getComputedStyle(document.querySelector('#wrapWhatsapp')).display!=='none',kategoriTerjaga:document.querySelector('#selCategory').value})`);
            catat('skenario', 'create-invalid-wa-kosong', { nativeMemblokirSubmit: nativeBlok, nativeWa, serverNavigasi: nav, daftarGalat: errorList, keadaanFormSetelahGalat: waKembali, ...m });
        }

        // VALID: CRITICISM + bersedia diskusi + WhatsApp -> redirect /feedback + flash sukses
        {
            await P.viewport(VP.desktop); await P.setTema('terang');
            await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'CRITICISM');
            await P.ketik('input[name=title]', 'Uji baseline F3 sebelum Tailwind');
            await P.ketik('#inpCriticism', 'Kritik uji baseline: tampilan form perlu diseragamkan.');
            await P.ketik('#inpSuggestion', 'Saran uji baseline: gunakan komponen yang konsisten.');
            await P.klik('label[for=chkWilling]');
            await P.ketik('#inpWhatsapp', '081234567890');
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            const m = await P.ukur({ rinci: true });
            m.foto = await P.foto('feedback-index-flash-sukses-terang-1366x768', fotoAktif);
            if (m.foto) R.berkasFoto.push(m.foto);
            catat('skenario', 'create-valid-criticism', { navigasi: nav, urlAkhir: m.url, flashSukses: m.teksAlert, ...m });
        }
        const idBaru = await P.ev(`(()=>{const a=document.querySelector('.content tbody a[href*="/feedback/"]');if(!a)return null;const m=a.getAttribute('href').match(/feedback\\/(\\d+)/);return m?Number(m[1]):null})()`);
        R.idSubmissionBaru = idBaru;
        if (!idBaru) throw new Error('Id submission baru tidak ditemukan di /feedback (tautan Detail).');

        await sapuHalaman(P, 'feedback-index-berisi', '/feedback', { foto: [], rinci: [D] });
        await sapuHalaman(P, 'feedback-show-staff', `/feedback/${idBaru}`, { foto: [D, MG], rinci: [D] });

        // staff mencoba inbox (harus ditolak)
        {
            await P.viewport(VP.desktop); await P.setTema('terang');
            await P.goto('/feedback-inbox');
            const m = await P.ukur({ fokus: false });
            catat('skenario', 'staff-akses-inbox-ditolak', { httpDokumen: m.httpDokumen, title: m.title, url: m.url, teks: await P.ev(`document.body.innerText.trim().replace(/\\s+/g,' ').slice(0,160)`) });
        }

        // ============ SUPERADMIN ============
        const sa = await bukaKonteks(B, VP.desktop); konteks.push(sa);
        const S = sa.P;
        catat('skenario', 'login-superadmin', await login(S, AKUN.superadmin));

        await sapuHalaman(S, 'feedback-inbox', '/feedback-inbox', { foto: [D, M], rinci: [D, MG] });
        {
            await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto('/feedback-inbox');
            catat('skenario', 'inbox-datatables', await S.ev(`({wrapper:!!document.querySelector('.dataTables_wrapper'),baris:document.querySelectorAll('#tblFeedbackInbox tbody tr').length,pencarian:!!document.querySelector('.dataTables_filter'),panjang:!!document.querySelector('.dataTables_length select'),paginasi:!!document.querySelector('.dataTables_paginate'),responsif:document.querySelector('#tblFeedbackInbox').className,statCards:document.querySelectorAll('[class*=stat]').length})`));
        }

        // show superadmin: pembukaan pertama menandai REVIEWED; kartu respond tampak
        await sapuHalaman(S, 'feedback-show-superadmin-respond', `/feedback/${idBaru}`, { foto: [D, M], rinci: [D] });
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        const statusSetelahBuka = await S.ev(`(()=>{const h=[...document.querySelectorAll('[role=heading]')].find(x=>/Informasi/.test(x.textContent));const t=document.querySelector('.card-body table, table.table-sm')||(h&&h.closest('.tw\\\\:border'));return t?t.innerText.replace(/\\s+/g,' ').slice(0,300):null})()`);
        catat('skenario', 'show-superadmin-status-reviewed', { infoTabel: statusSetelahBuka, kartuRespond: await S.ada('form[action$="/respond"]'), kartuClose: await S.ada('.frmCloseFeedback') });

        // respond kosong (noValidate) -> galat server tidak ditampilkan?
        {
            await S.ev(`document.querySelector('form[action$="/respond"]').noValidate=true`);
            await S.ketik('textarea[name=response_text]', '');
            const nav = await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
            const m = await S.ukur({ fokus: false });
            catat('skenario', 'respond-kosong-server', { navigasi: nav, urlAkhir: m.url, alertTampak: m.teksAlert, textareaInvalid: await S.ev(`document.querySelector('textarea[name=response_text]').classList.contains('is-invalid')||document.querySelector('textarea[name=response_text]').getAttribute('aria-invalid')==='true'`), catatan: 'show.blade tidak merender $errors; periksa apakah ada umpan balik sama sekali' });
        }

        // respond valid lewat UI
        {
            await S.goto(`/feedback/${idBaru}`);
            await S.ketik('textarea[name=response_text]', 'Terima kasih atas masukannya. Tanggapan uji baseline F3.');
            const nav = await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
            const m = await S.ukur({ rinci: true });
            m.foto = await S.foto('feedback-show-superadmin-setelah-respond-terang-1366x768', fotoAktif);
            if (m.foto) R.berkasFoto.push(m.foto);
            catat('skenario', 'respond-valid-ui', { navigasi: nav, urlAkhir: m.url, flash: m.teksAlert, kartuCloseTampak: await S.ada('.frmCloseFeedback'), kartuRespondMasihAda: await S.ada('form[action$="/respond"]'), ...m });
        }
        await sapuHalaman(S, 'feedback-show-superadmin-responded', `/feedback/${idBaru}`, { foto: [], rinci: [] });

        // close lewat UI dengan SweetAlert konfirmasi
        {
            await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
            await S.klik('.frmCloseFeedback button[type=submit]');
            const swal = await S.tunggu('.swal2-popup');
            await sleep(700);
            const swalInfo = swal ? await S.ev(`({judul:(document.querySelector('.swal2-title')||{}).textContent,teks:(document.querySelector('.swal2-html-container')||{}).textContent,konfirm:(document.querySelector('.swal2-confirm')||{}).textContent,batal:(document.querySelector('.swal2-cancel')||{}).textContent,bgKonfirm:getComputedStyle(document.querySelector('.swal2-confirm')).backgroundColor,fokus:(document.activeElement||{}).className})`) : null;
            const fSw = await S.foto('feedback-swal-konfirmasi-close-terang-1366x768', fotoAktif);
            if (fSw) R.berkasFoto.push(fSw);
            // Batal dulu: tetap RESPONDED, tidak ada navigasi
            await S.klik('.swal2-cancel'); await sleep(500);
            const setelahBatal = await S.ev(`({swalMasihAda:!!document.querySelector('.swal2-popup'),url:location.pathname})`);
            await S.klik('.frmCloseFeedback button[type=submit]'); await S.tunggu('.swal2-confirm'); await sleep(700);
            const swalKedua = await S.ada('.swal2-popup');
            const nav = await S.aksiNavigasi(() => S.klik('.swal2-confirm'));
            const m = await S.ukur({ rinci: true });
            m.foto = await S.foto('feedback-show-superadmin-closed-terang-1366x768', fotoAktif);
            if (m.foto) R.berkasFoto.push(m.foto);
            catat('skenario', 'close-ui-swal', { swalTerbuka: swal, swalKeduaTerbuka: swalKedua, swalInfo, setelahBatal, navigasiSetelahYa: nav, urlAkhir: m.url, flash: m.teksAlert, kartuRespondMasihAda: await S.ada('form[action$="/respond"]'), kartuCloseMasihAda: await S.ada('.frmCloseFeedback'), ...m });
        }
        await sapuHalaman(S, 'feedback-show-superadmin-closed', `/feedback/${idBaru}`, { foto: [], rinci: [] });
        await sapuHalaman(S, 'feedback-inbox-akhir', '/feedback-inbox', { foto: [], rinci: [D] });

        // ============ REGRESI NON-PILOT (superadmin) ============
        await sapuHalaman(S, 'reg-beranda', '/', { rinci: [] });
        await sapuHalaman(S, 'reg-department', '/department', { rinci: [] });
        await sapuHalaman(S, 'reg-bank', '/bank', { rinci: [] });
        await sapuHalaman(S, 'reg-budget-request', '/budget-request', { rinci: [] });
        await sapuHalaman(S, 'reg-profile', '/profile', { rinci: [] });
    } catch (e) {
        R.galatRunner.push('FATAL: ' + e.message);
    }
    // tutup konteks lalu browser
    for (const k of konteks) { try { await B.cdp.send('Target.disposeBrowserContext', { browserContextId: k.browserContextId }); } catch { /* abaikan */ } }
    R.penghentian = await hentikanBrowser(B);
    return R;
}

// ---------------------------------------------------------------------------
async function main() {
    fs.mkdirSync(OUT_ROOT, { recursive: true });
    let kode = 0;
    for (const nama of DAFTAR_BROWSER) {
        console.log(`[${nama}] mulai (${LABEL}) ...`);
        let R;
        try { R = await jalankan(nama); } catch (e) { console.error(`[${nama}] gagal: ${e.message}`); kode = 1; continue; }
        const f = path.join(OUT_ROOT, `hasil-${LABEL}-${nama}.json`);
        fs.writeFileSync(f, JSON.stringify(R, null, 1));
        const sisa = R.penghentian?.sisaProsesPenanda;
        console.log(`[${nama}] selesai: ${R.halaman.length} pengukuran, ${R.skenario.length} skenario, ${R.berkasFoto.length} foto, galatRunner=${R.galatRunner.length}, sisaProses=${sisa}`);
        console.log('  ->', path.relative(AKAR, f));
        if (sisa !== 0) kode = 1;
        if (R.galatRunner.length) console.log('  galatRunner:', R.galatRunner.join(' | '));
    }
    process.exit(kode);
}
// Dipakai juga sebagai modul oleh cdp-qa-f3.mjs (tanpa menjalankan main()).
export { Cdp, Halaman, mulaiBrowser, hentikanBrowser, bukaKonteks, login, VP, AKUN, BASE, sleep, ukurDiHalaman, KUNCI, PROPS };
if (import.meta.url === pathToFileURL(process.argv[1]).href) main();

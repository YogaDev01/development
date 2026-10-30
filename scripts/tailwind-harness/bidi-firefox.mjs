/**
 * Runner Firefox (WebDriver BiDi mentah) untuk HALAMAN NYATA pilot Feedback (F3).
 * Padanan cdp-pages.mjs + cdp-qa-f3.mjs untuk Firefox. Node 22 (WebSocket + fetch global), TANPA npm,
 * TANPA geckodriver: memakai Remote Agent bawaan Firefox (ws://127.0.0.1:<port>/session).
 *
 * Pakai (jalur node CONTOH; cari lewat `Get-Command node`):
 *   $env:TESYS_PW_STAFF='...'; $env:TESYS_PW_SUPERADMIN='...'
 *   node scripts/tailwind-harness/bidi-firefox.mjs [--bagian=halaman,qa] [--base=http://127.0.0.1:8099]
 *        [--port=9444] [--firefox=<alias/exe>] [--user-superadmin=dummy.superadmin] [--out=docs/ui/tailwind-evidence/f3]
 *   Hasil: <out>/hasil-firefox.json (halaman), <out>/hasil-qa-firefox.json (qa), <out>/firefox/*.png
 *   --bagian=smoke : uji ringan baca-saja (login staff + 1 halaman), tanpa menulis ke DB uji.
 *
 * Keselamatan proses: profil sementara baru (`-no-remote -profile`), headless, port khusus. Proses milik runner
 * dikenali dari command line yang memuat nama folder profil unik (+ turunannya). Penutupan: BiDi browser.close;
 * taskkill /PID hanya untuk PID milik runner yang masih hidup. TIDAK PERNAH taskkill /IM, TIDAK menyentuh sesi
 * Firefox pengguna; daftar PID non-runner dibandingkan sebelum/sesudah. Jangan python/python3, jangan firefox --version.
 * PERINGATAN: skenario MENULIS data uji ke DB server pilot (submission, tanggapan, close).
 */
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { ukurDiHalaman, KUNCI, PROPS } from './cdp-pages.mjs';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const arg = (n, b) => { const a = process.argv.find((x) => x.startsWith('--' + n + '=')); return a ? a.slice(n.length + 3) : b; };
const BASE = arg('base', 'http://127.0.0.1:8099').replace(/\/$/, '');
const PORT = Number(arg('port', '9444'));
const OUT_ROOT = path.resolve(AKAR, arg('out', 'docs/ui/tailwind-evidence/f3'));
const OUT_FOTO = path.join(OUT_ROOT, 'firefox');
const BAGIAN = arg('bagian', 'halaman,qa').split(',');
const EXE = arg('firefox', path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WindowsApps', 'firefox.exe'));
const AKUN = {
    superadmin: { username: arg('user-superadmin', 'dummy.superadmin'), pw: arg('pw-superadmin', process.env.TESYS_PW_SUPERADMIN || '') },
    staff: { username: 'dummy.staff', pw: arg('pw-staff', process.env.TESYS_PW_STAFF || '') },
};
const FOTO_BOLEH = new Set([
    'feedback-index-kosong-terang-1366x768', 'feedback-index-kosong-terang-390x844',
    'feedback-create-criticism-terang-1366x768', 'feedback-create-criticism-gelap-1366x768', 'feedback-create-criticism-terang-390x844',
    'feedback-create-invalid-judul-terang-1366x768', 'feedback-index-flash-sukses-terang-1366x768',
    'feedback-show-staff-gelap-390x844', 'feedback-show-staff-terang-1366x768',
    'feedback-inbox-terang-1366x768', 'feedback-inbox-terang-390x844',
    'feedback-show-superadmin-respond-terang-1366x768', 'feedback-swal-konfirmasi-close-terang-1366x768',
    'feedback-show-superadmin-closed-terang-1366x768',
]);
const VP = { mobile: { id: '390x844', w: 390, h: 844 }, desktop: { id: '1366x768', w: 1366, h: 768 } };
const TEMA = ['terang', 'gelap'];
const PRASET = ['#92232b', '#1f3a5f', '#0f6e66', '#2f6d43', '#3d3a8c', '#3f4a59', '#9a4a23', '#6a2a63'];
const NAV = '.content a.tw\\:inline-flex[href]';
const J = JSON.stringify;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sha = (x) => crypto.createHash('sha256').update(typeof x === 'string' ? x : J(x)).digest('hex');
const K = { TAB: '', ENTER: '', SHIFT: '', CTRL: '', DEL: '' };

// ---------------------------------------------------------------------------
// Proses & klien BiDi
// ---------------------------------------------------------------------------
function ps(script) {
    return execFileSync('powershell', ['-NoProfile', '-NonInteractive', '-EncodedCommand', Buffer.from("$ProgressPreference='SilentlyContinue';" + script, 'utf16le').toString('base64')], { encoding: 'utf8', timeout: 40000, stdio: ['ignore', 'pipe', 'ignore'] });
}
/** Daftar proses firefox.exe (hanya nama itu). milik = command line memuat penanda profil runner. */
function snapshotFirefox(penanda) {
    const out = ps(`$p = @(Get-CimInstance Win32_Process -Filter "Name='firefox.exe'" | ForEach-Object { [pscustomobject]@{ pid=[int]$_.ProcessId; ppid=[int]$_.ParentProcessId; cr=[string]$_.CreationDate; cmd=[string]$_.CommandLine } }); ConvertTo-Json -InputObject $p -Compress`).trim();
    const lst = JSON.parse(out || '[]');
    const kunci = (p) => p.pid + ':' + p.cr;
    const milik = new Set(lst.filter((p) => penanda && (p.cmd || '').includes(penanda)).map(kunci));
    for (let ubah = true; ubah;) { ubah = false; for (const p of lst) if (!milik.has(kunci(p)) && lst.some((q) => milik.has(kunci(q)) && q.pid === p.ppid)) { milik.add(kunci(p)); ubah = true; } }
    return lst.map((p) => ({ pid: p.pid, ppid: p.ppid, key: kunci(p), milik: milik.has(kunci(p)) }));
}
async function portBebas(port) { try { await fetch(`http://127.0.0.1:${port}/status`, { signal: AbortSignal.timeout(1500) }); return false; } catch { return true; } }

class Bidi {
    constructor(url) {
        this.ws = new WebSocket(url); this.id = 0; this.pending = new Map(); this.listeners = [];
        this.ws.addEventListener('message', (m) => {
            const d = JSON.parse(m.data);
            if (d.type === 'event') { for (const l of this.listeners) l(d.method, d.params); return; }
            const p = this.pending.get(d.id); if (!p) return; this.pending.delete(d.id);
            if (d.type === 'error') p.rej(new Error(`${p.method}: ${d.error} ${d.message || ''}`.slice(0, 300))); else p.res(d.result);
        });
    }
    open() { return new Promise((res, rej) => { this.ws.addEventListener('open', () => res()); this.ws.addEventListener('error', () => rej(new Error('WebSocket BiDi gagal'))); }); }
    send(method, params = {}) { const id = ++this.id; this.ws.send(J({ id, method, params })); return new Promise((res, rej) => this.pending.set(id, { res, rej, method })); }
    close() { try { this.ws.close(); } catch { /* abaikan */ } }
}

async function mulaiFirefox() {
    // Alias MSIX = reparse point: existsSync menolak, lstatSync berhasil.
    let adaExe = false; try { fs.lstatSync(EXE); adaExe = true; } catch { /* tidak ada */ }
    if (!adaExe) throw new Error('Peluncur Firefox tidak ditemukan: ' + EXE);
    if (!(await portBebas(PORT))) throw new Error(`Port ${PORT} sudah dipakai proses lain; runner berhenti (tidak menyentuh proses itu).`);
    const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'tesys-ff-'));
    const penanda = path.basename(profil);
    fs.writeFileSync(path.join(profil, 'user.js'), [
        'browser.shell.checkDefaultBrowser|false', 'datareporting.policy.dataSubmissionEnabled|false', 'datareporting.healthreport.uploadEnabled|false',
        'toolkit.telemetry.reportingpolicy.firstRun|false', 'toolkit.telemetry.enabled|false', 'toolkit.telemetry.unified|false',
        'app.update.auto|false', 'app.update.enabled|false', 'app.update.service.enabled|false', 'extensions.update.enabled|false',
        'browser.aboutwelcome.enabled|false', 'browser.startup.homepage_override.mstone|"ignore"', 'startup.homepage_welcome_url|"about:blank"',
        'browser.startup.page|0', 'browser.rights.3.shown|true', 'network.captive-portal-service.enabled|false',
        'browser.safebrowsing.malware.enabled|false', 'browser.safebrowsing.phishing.enabled|false', 'app.normandy.enabled|false',
        'browser.tabs.warnOnClose|false', 'browser.sessionstore.resume_from_crash|false',
    ].map((l) => { const [k, v] = l.split('|'); return `user_pref("${k}", ${v});`; }).join('\n') + '\n');
    const B = { profil, penanda, port: PORT, milik: new Set(), exe: EXE };
    const snap0 = snapshotFirefox(penanda).filter((p) => !p.milik); const ada0 = new Set(snap0.map((p) => p.pid));
    B.userSebelum = snap0.map((p) => p.pid).sort((a, b) => a - b);
    B.userPuncakSebelum = snap0.filter((p) => !ada0.has(p.ppid)).map((p) => p.pid).sort((a, b) => a - b);
    const args = ['-no-remote', '-profile', profil, '-headless', `-remote-debugging-port=${PORT}`, 'about:blank'];
    B.perintah = `${path.basename(EXE)} ${args.map((a) => (a === profil ? '<profil-sementara>' : a)).join(' ')}`;
    const child = spawn(EXE, args, { stdio: 'ignore', windowsHide: true });
    B.pidAlias = child.pid; B.aliasKeluar = null;
    child.on('exit', (k) => { B.aliasKeluar = k; });
    let siap = false;
    for (let i = 0; i < 120 && !siap; i++) { try { await fetch(`http://127.0.0.1:${PORT}/status`, { signal: AbortSignal.timeout(1000) }); siap = true; } catch { await sleep(500); } }
    if (!siap) { await hentikanFirefox(B); throw new Error('Remote Agent BiDi tidak muncul'); }
    B.bidi = new Bidi(`ws://127.0.0.1:${PORT}/session`); await B.bidi.open();
    const s = await B.bidi.send('session.new', { capabilities: { alwaysMatch: {} } });
    B.caps = s.capabilities; B.versi = s.capabilities.browserVersion;
    B.profilCocok = path.resolve(String(s.capabilities['moz:profile'])).toLowerCase() === path.resolve(profil).toLowerCase();
    B.mozPid = s.capabilities['moz:processID']; B.headless = s.capabilities['moz:headless'];
    if (!B.profilCocok) { await hentikanFirefox(B); throw new Error('Profil sesi BUKAN profil sementara runner; dihentikan.'); }
    await B.bidi.send('session.subscribe', { events: ['log.entryAdded', 'network.beforeRequestSent', 'network.responseCompleted', 'network.fetchError', 'browsingContext.load', 'browsingContext.navigationStarted', 'browsingContext.contextCreated', 'browsingContext.userPromptOpened'] });
    perbaruiMilik(B);
    return B;
}
function perbaruiMilik(B) { for (const p of snapshotFirefox(B.penanda)) if (p.milik) B.milik.add(p.key); B.pidMilik = [...B.milik].map((k) => Number(k.split(':')[0])); }

async function hentikanFirefox(B) {
    const log = { penanda: B.penanda };
    try { perbaruiMilik(B); } catch { /* abaikan */ }
    log.pidMilikRunner = [...(B.pidMilik || [])];
    try { if (B.bidi) await Promise.race([B.bidi.send('browser.close', {}), sleep(5000)]); } catch { /* abaikan */ }
    const sisa = () => snapshotFirefox(B.penanda).filter((p) => p.milik || B.milik.has(p.key));
    const t0 = Date.now(); let s = sisa();
    while (s.length && Date.now() - t0 < 20000) { await sleep(500); s = sisa(); }
    log.penutupanSopanBiDi = s.length === 0; log.menungguMs = Date.now() - t0;
    if (s.length) {
        log.taskkillPid = [];
        for (const p of s) { try { execFileSync('taskkill', ['/PID', String(p.pid), '/T', '/F'], { stdio: 'ignore' }); log.taskkillPid.push(p.pid); } catch { log.taskkillPid.push(p.pid + ':gagal'); } }
        const t1 = Date.now(); s = sisa(); while (s.length && Date.now() - t1 < 15000) { await sleep(500); s = sisa(); }
    }
    log.sisaProsesPenanda = s.length;
    try { B.bidi?.close(); } catch { /* abaikan */ }
    for (let i = 0; i < 15; i++) { try { fs.rmSync(B.profil, { recursive: true, force: true }); break; } catch { await sleep(400); } }
    log.profilDihapus = !fs.existsSync(B.profil);
    const snap = snapshotFirefox(B.penanda).filter((p) => !p.milik && !B.milik.has(p.key));
    const lain = snap.map((p) => p.pid).sort((a, b) => a - b);
    const adaPid = new Set(snap.map((p) => p.pid));
    const puncak = snap.filter((p) => !adaPid.has(p.ppid)).map((p) => p.pid).sort((a, b) => a - b); // proses induk Firefox pengguna (anak/content process boleh berganti)
    log.pidBukanRunnerSebelum = B.userSebelum; log.pidBukanRunnerSesudah = lain;
    log.pidPuncakPenggunaSebelum = B.userPuncakSebelum; log.pidPuncakPenggunaSesudah = puncak;
    log.pidPenggunaTakBerubah = lain.length === 0 && B.userSebelum.length === 0 ? true : J(B.userPuncakSebelum) === J(puncak);
    return log;
}

// ---------------------------------------------------------------------------
// Halaman (satu tab di satu user context)
// ---------------------------------------------------------------------------
class Hal {
    constructor(B, ctx, userContext) { this.B = B; this.b = B.bidi; this.ctx = ctx; this.userContext = userContext; this.mulai = new Map(); this.reset(); B.bidi.listeners.push((m, p) => this.onEvent(m, p)); }
    // urlAktif/navMulaiT/dokumenSelesaiT dipakai menyaring abort jaringan: abort permintaan DOKUMEN LAMA terjadi saat dokumen baru commit (navigasi), bukan galat halaman baru.
    reset() { this.konsol = []; this.jaringan = new Map(); this.gagal = []; this.dokumen = null; this.dialog = []; this.loaded = false; this.navMulaiT = Date.now(); this.dokumenSelesaiT = null; this.urlDokumen = null; }
    onEvent(m, p) {
        if (m === 'log.entryAdded') {
            if (p.source?.context !== this.ctx) return;
            if (p.level === 'error' || p.level === 'warn') this.konsol.push({ jenis: `${p.type}.${p.level}`, teks: String(p.text).slice(0, 220) });
            return;
        }
        if (p.context !== this.ctx) return;
        if (m === 'network.beforeRequestSent') {
            this.jaringan.set(p.request.request, { url: p.request.url, dest: p.request.destination, init: p.request.initiatorType, status: null, t: p.timestamp });
            // Catatan waktu MULAI permintaan yang TIDAK ikut dihapus reset(): dipakai menentukan apakah permintaan yang kemudian di-abort dimulai sebelum navigasi baru (dokumen lama).
            this.mulai.set(p.request.request, { t: p.timestamp, dest: p.request.destination, init: p.request.initiatorType, nav: p.navigation ?? null });
        }
        else if (m === 'network.responseCompleted') {
            const r = this.jaringan.get(p.request.request) || { url: p.request.url, dest: p.request.destination };
            r.status = p.response.status; this.jaringan.set(p.request.request, r);
            if (r.dest === 'document') { this.dokumen = { url: p.response.url, status: p.response.status }; this.dokumenSelesaiT = p.timestamp; this.urlDokumen = p.response.url; }
        } else if (m === 'network.fetchError') {
            const r = this.jaringan.get(p.request.request);
            this.gagal.push({ id: p.request.request, url: p.request.url, dest: r?.dest ?? p.request.destination, init: r?.init ?? p.request.initiatorType, galat: p.errorText, t: p.timestamp });
        } else if (m === 'browsingContext.navigationStarted') { this.navMulaiT = p.timestamp ?? Date.now(); }
        else if (m === 'browsingContext.load') this.loaded = true;
        else if (m === 'browsingContext.userPromptOpened') {
            this.dialog.push({ tipe: p.type, pesan: p.message });
            this.b.send('browsingContext.handleUserPrompt', { context: this.ctx, accept: true }).catch(() => {});
        }
    }
    async callFn(body) {
        const fn = `async () => { const __v = await (async () => { ${body} })(); return __v === undefined ? 'null' : JSON.stringify(__v); }`;
        const r = await this.b.send('script.callFunction', { functionDeclaration: fn, target: { context: this.ctx }, awaitPromise: true, resultOwnership: 'none' });
        if (r.type === 'exception') throw new Error('evaluate: ' + (r.exceptionDetails?.text || '') + ' ' + (r.exceptionDetails?.exception?.value || ''));
        return JSON.parse(r.result.value);
    }
    ev(ekspr) { return this.callFn(`return (${ekspr});`); }
    evS(badan) { return this.callFn(badan); }
    async viewport(v) { this.vp = v; await this.b.send('browsingContext.setViewport', { context: this.ctx, viewport: { width: v.w, height: v.h }, devicePixelRatio: 1 }); }
    async tungguLoad(timeout = 30000) {
        const t0 = Date.now();
        while (!this.loaded && Date.now() - t0 < timeout) await sleep(100);
        const ok = this.loaded;
        await this.ev('document.fonts && document.fonts.ready.then(()=>true)').catch(() => {});
        await sleep(900);
        return ok;
    }
    async goto(p) {
        this.reset();
        // Navigasi baru dapat menabrak navigasi sebelumnya yang belum tuntas (mis. akibat Enter pada tautan tepat sebelumnya): ulang maks 2x setelah jeda dan CATAT (B.navDiulang).
        for (let i = 0; ; i++) {
            try { await this.b.send('browsingContext.navigate', { context: this.ctx, url: BASE + p, wait: 'complete' }); break; }
            catch (e) { if (!/interrupted an unfinished navigation/.test(e.message) || i >= 2) throw e; (this.B.navDiulang ||= []).push(`${p} (percobaan ${i + 1})`); await sleep(1500); this.reset(); }
        }
        this.loaded = true;
        await this.ev('document.fonts && document.fonts.ready.then(()=>true)').catch(() => {});
        await sleep(900);
        return true;
    }
    async ada(sel) { return this.ev(`!!document.querySelector(${J(sel)})`); }
    async tunggu(sel, ms = 8000) {
        const t0 = Date.now();
        while (Date.now() - t0 < ms) { if (await this.ev(`(()=>{const e=document.querySelector(${J(sel)});if(!e)return false;const r=e.getBoundingClientRect();return r.width>0&&r.height>0})()`)) return true; await sleep(150); }
        return false;
    }
    async posisi(sel) {
        const q = `(()=>{const e=document.querySelector(${J(sel)});if(!e)return null;const r=e.getBoundingClientRect();const x=r.left+r.width/2,y=r.top+r.height/2;const h=document.elementFromPoint(x,y);return {x:Math.round(x),y:Math.round(y),kena:!!h&&(e.contains(h)||h.contains(e))}})()`;
        await this.ev(`(()=>{const e=document.querySelector(${J(sel)});if(e)e.scrollIntoView({block:'center',inline:'center',behavior:'instant'})})()`);
        await sleep(250);
        const pos = await this.ev(q);
        if (!pos) throw new Error('klik: selektor tidak ditemukan ' + sel);
        if (!pos.kena) throw new Error('klik: titik tengah tidak mengenai elemen ' + sel + ' (zoom/koordinat?)');
        return pos;
    }
    ptr(acts, ctrl = false) {
        const aksi = [{ type: 'pointer', id: 'mouse', parameters: { pointerType: 'mouse' }, actions: acts }];
        if (ctrl) { const n = acts.length; aksi.unshift({ type: 'key', id: 'kbd', actions: [{ type: 'keyDown', value: K.CTRL }, ...Array(n - 1).fill({ type: 'pause', duration: 0 }), { type: 'keyUp', value: K.CTRL }] }); }
        return this.b.send('input.performActions', { context: this.ctx, actions: aksi });
    }
    async klik(sel, { ctrl = false } = {}) {
        const pos = await this.posisi(sel);
        const acts = [{ type: 'pointerMove', x: pos.x, y: pos.y, origin: 'viewport' }, { type: 'pointerDown', button: 0 }, { type: 'pointerUp', button: 0 }];
        if (ctrl) { // key: down Ctrl pada tick pertama, up setelah klik (tick tambahan)
            await this.b.send('input.performActions', { context: this.ctx, actions: [{ type: 'key', id: 'kbd', actions: [{ type: 'keyDown', value: K.CTRL }] }] });
            await this.ptr(acts); await this.b.send('input.releaseActions', { context: this.ctx });
        } else await this.ptr(acts);
    }
    keys(actions) { return this.b.send('input.performActions', { context: this.ctx, actions: [{ type: 'key', id: 'kbd', actions }] }); }
    async teks(t) { const a = []; for (const ch of [...t]) a.push({ type: 'keyDown', value: ch }, { type: 'keyUp', value: ch }); await this.keys(a); }
    async ketik(sel, t) {
        await this.klik(sel);
        await this.ev(`(()=>{const e=document.querySelector(${J(sel)});if(e&&e.select)e.select()})()`);
        if (t === '') await this.keys([{ type: 'keyDown', value: K.DEL }, { type: 'keyUp', value: K.DEL }]); else await this.teks(t);
    }
    enter() { return this.keys([{ type: 'keyDown', value: K.ENTER }, { type: 'keyUp', value: K.ENTER }]); }
    tab(shift = false) {
        const a = [{ type: 'keyDown', value: K.TAB }, { type: 'keyUp', value: K.TAB }];
        return this.keys(shift ? [{ type: 'keyDown', value: K.SHIFT }, ...a, { type: 'keyUp', value: K.SHIFT }] : a);
    }
    async pilih(sel, nilai) {
        await this.ev(`(()=>{const e=document.querySelector(${J(sel)});e.value=${J(nilai)};e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}))})()`);
    }
    async aksiNavigasi(fn, timeout = 20000) {
        this.reset(); await fn();
        const t0 = Date.now();
        while (!this.loaded && Date.now() - t0 < timeout) await sleep(100);
        const nav = this.loaded;
        if (nav) { await this.ev('document.fonts && document.fonts.ready.then(()=>true)').catch(() => {}); await sleep(900); }
        return nav;
    }
    async foto(nama, aktif, R) {
        if (!aktif || !FOTO_BOLEH.has(nama)) return null;
        fs.mkdirSync(OUT_FOTO, { recursive: true });
        const { data } = await this.b.send('browsingContext.captureScreenshot', { context: this.ctx, origin: 'viewport' });
        const f = path.join(OUT_FOTO, nama + '.png');
        fs.writeFileSync(f, Buffer.from(data, 'base64'));
        const rel = path.relative(AKAR, f).replace(/\\/g, '/');
        R.berkasFoto.push(rel);
        return rel;
    }
    setTema(tema) { return this.ev(`localStorage.setItem('tesys-theme', ${J(tema === 'gelap' ? 'dark' : 'light')})`); }
    async fokusAwal() {
        await this.evS(`document.activeElement && document.activeElement.blur(); window.scrollTo({top:0,left:0,behavior:'instant'});`);
        await this.tab(); await sleep(200);
        return this.ev(`(()=>{const e=document.activeElement;if(!e||e===document.body)return {el:'body'};const s=getComputedStyle(e);return {el:e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.className?'.'+String(e.className).trim().split(/\\s+/)[0]:''),teks:(e.getAttribute('aria-label')||e.textContent||'').trim().replace(/\\s+/g,' ').slice(0,40),outline:s.outlineStyle+' '+s.outlineWidth+' '+s.outlineColor,focusVisible:e.matches(':focus-visible')}})()`);
    }
    async sharedId(sel) {
        const r = await this.b.send('script.callFunction', { functionDeclaration: `() => document.querySelector(${J(sel)})`, target: { context: this.ctx }, awaitPromise: false, resultOwnership: 'none' });
        return r.result?.sharedId ?? null;
    }
    /**
     * Aturan penyaringan abort jaringan (hanya NS_BINDING_ABORTED yang boleh disaring; galat lain selalu FAIL).
     * Sebuah abort disaring ("milik dokumen LAMA") HANYA bila SEMUA syarat terpenuhi:
     *  1. permintaan itu tercatat dimulai (beforeRequestSent) SEBELUM navigasi baru dimulai (mulaiT < navMulaiT) -> tidak mungkin milik dokumen baru;
     *  2. abort terjadi setelah navigasi baru dimulai (t >= navMulaiT) dan <= 400 ms setelah dokumen baru selesai (dokumen lama diputus saat commit);
     *  3. GUARD: URL (tanpa kueri) yang sama TIDAK diminta oleh dokumen baru (permintaan dengan mulaiT >= navMulaiT); abort font/stylesheet/skrip/gambar/dokumen
     *     yang dimulai pada/setelah navigasi baru, atau yang waktu mulainya tak diketahui, TIDAK PERNAH disaring (tetap FAIL).
     * Setiap entri membawa kategori (destination || initiatorType), mulaiT relatif navigasi, dan alasan agar dapat diaudit di JSON.
     */
    gagalDetail() {
        const potong = (u) => String(u).replace(BASE, '').replace(/\?.*$/, '');
        const urlBaru = new Set([...this.jaringan.values()].filter((r) => r.t != null && r.t >= this.navMulaiT).map((r) => potong(r.url)));
        return this.gagal.map((g) => {
            const info = g.id ? this.mulai.get(g.id) : null; const url = potong(g.url);
            const kategori = g.dest || g.init || 'tak-diketahui';
            const msMulaiSebelumNav = info && info.t != null ? Math.round(this.navMulaiT - info.t) : null;
            let alasan = 'bukan NS_BINDING_ABORTED'; let tersaring = false;
            if (g.galat === 'NS_BINDING_ABORTED') {
                if (g.t == null || this.dokumenSelesaiT == null) alasan = 'stempel waktu abort/dokumen tak ada';
                else if (msMulaiSebelumNav == null) alasan = 'waktu mulai permintaan tak diketahui';
                else if (msMulaiSebelumNav <= 0) alasan = 'dimulai pada/setelah navigasi baru (milik dokumen baru)';
                else if (urlBaru.has(url)) alasan = 'URL yang sama diminta dokumen baru';
                else if (g.t < this.navMulaiT) alasan = 'abort sebelum navigasi baru dimulai';
                else if (g.t > this.dokumenSelesaiT + 400) alasan = 'abort > 400 ms setelah dokumen baru selesai';
                else { tersaring = true; alasan = 'dimulai sebelum navigasi baru, dibatalkan saat navigasi (dokumen lama)'; }
            }
            return { dest: g.dest, kategori, url, galat: g.galat, msSejakNavMulai: g.t != null ? Math.round(g.t - this.navMulaiT) : null, msSetelahDokumenSelesai: g.t != null && this.dokumenSelesaiT != null ? Math.round(g.t - this.dokumenSelesaiT) : null, msMulaiSebelumNav, dokumenAktif: this.urlDokumen ? potong(this.urlDokumen) : null, dalamJendelaNavigasi: tersaring, alasan };
        });
    }
    async ukur(opsi = {}) {
        const { hasil, kunci, baris } = await this.ev(`(${ukurDiHalaman.toString()})(${J(KUNCI)}, ${J(PROPS)})`);
        hasil.digestGaya = sha(baris); hasil.digestKunci = sha(kunci);
        const lnk = await this.ev(`[...document.querySelectorAll('link[rel~=stylesheet]')].map(l=>{try{return new URL(l.href).pathname}catch(e){return l.href}})`);
        const iTw = lnk.map((x, i) => (x.endsWith('/css/tesys-tw.css') ? i : -1)).filter((i) => i >= 0);
        const iBase = lnk.findIndex((x) => x.endsWith('/css/tesys-ui-base.css'));
        const iLain = lnk.map((x, i) => (/\/css\/tesys-ui[^/]*\.css$/.test(x) && !x.endsWith('/css/tesys-ui-base.css') ? i : -1)).filter((i) => i >= 0);
        const css = [], js = [], lain = []; let favicon = null;
        for (const r of this.jaringan.values()) {
            const u = r.url.replace(/\?.*$/, ''); const rel = u.startsWith(BASE) ? u.slice(BASE.length) : u;
            const gal = r.status && r.status >= 400 ? ` [HTTP ${r.status}]` : '';
            if (/favicon/.test(rel)) { favicon = { rel, status: r.status }; continue; }
            if (r.dest === 'style') css.push(rel + gal); else if (r.dest === 'script') js.push(rel + gal); else if (gal) lain.push(rel + gal);
        }
        const latar = await this.ev(`({bg:getComputedStyle(document.body).backgroundColor,fg:getComputedStyle(document.body).color})`);
        const fokus = opsi.fokus === false ? undefined : await this.fokusAwal();
        return {
            httpDokumen: this.dokumen?.status ?? null, ...hasil, fokusTabPertama: fokus, latar,
            linkCssDom: lnk, twLinkCount: iTw.length, urutanTwOk: iTw.length === 1 && iBase >= 0 && iTw[0] > iBase && iLain.every((i) => i > iTw[0]),
            cssDimuat: css, jsDimuat: js, adaTesysTwCss: css.some((c) => c.includes('tesys-tw.css')),
            favicon, permintaanGalat: lain, permintaanGagal: this.gagal.map((g) => `${g.dest} ${g.url.replace(BASE, '').replace(/\?.*$/, '')} ${g.galat}`),
            // Rinci: aturan penyaringan abort dijelaskan di komentar gagalDetail() (dimulai sebelum navigasi baru + guard URL dokumen baru). Selain itu = galat nyata.
            permintaanGagalDetail: this.gagalDetail(),
            konsol: this.konsol, dialogNative: this.dialog,
        };
    }
}

async function bukaKonteks(B, viewport) {
    const { userContext } = await B.bidi.send('browser.createUserContext', {});
    const { context } = await B.bidi.send('browsingContext.create', { type: 'tab', userContext });
    const P = new Hal(B, context, userContext);
    await P.viewport(viewport);
    return P;
}
async function tutupKonteks(B, P) {
    try { await B.bidi.send('browsingContext.close', { context: P.ctx }); } catch { /* abaikan */ }
    try { await B.bidi.send('browser.removeUserContext', { userContext: P.userContext }); } catch { /* abaikan */ }
}
async function login(P, akun) {
    if (!akun.pw) throw new Error('Kata sandi untuk ' + akun.username + ' tidak diberikan (--pw-... atau TESYS_PW_...).');
    await P.goto('/login'); await P.tunggu('#username');
    await P.ketik('#username', akun.username);
    await P.klik('#password'); await P.teks(akun.pw); if (process.env.BIDI_DEBUG) console.log('dbg', J(await P.ev(`({u:document.querySelector('#username').value,pwLen:document.querySelector('#password').value.length,foc:document.activeElement.id})`)));
    let nav = await P.aksiNavigasi(() => P.enter());
    for (let i = 0; i < 40 && (await P.ev('location.pathname')).startsWith('/login'); i++) await sleep(500); // jaga-jaga event load terlewat / server lambat
    if (!nav) { await sleep(900); nav = !(await P.ev('location.pathname')).startsWith('/login'); }
    const url = await P.ev('location.pathname');
    const galat = await P.ev(`(()=>{const a=document.querySelector('.auth-alert-danger');return a?a.textContent.trim().replace(/\\s+/g,' ').slice(0,120):null})()`);
    return { akun: akun.username, navigasi: nav, urlSetelahLogin: url, berhasil: !url.startsWith('/login'), galatLogin: galat };
}

function versiAppx() {
    try { return ps(`(Get-AppxPackage Mozilla.Firefox | Select-Object -First 1 | ForEach-Object { $_.Name + ' ' + $_.Version })`).trim(); } catch { return 'tidak diketahui'; }
}
const lumRgb = (s) => { const m = String(s).match(/[\d.]+/g); if (!m) return null; const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(+m[0]) + 0.7152 * f(+m[1]) + 0.0722 * f(+m[2]); };
const kanon = (o) => J(o, (k, v) => (v && typeof v === 'object' && !Array.isArray(v) ? Object.fromEntries(Object.entries(v).sort()) : v));
const sama = (a, b) => kanon(a) === kanon(b);

// ---------------------------------------------------------------------------
// BAGIAN HALAMAN (padanan cdp-pages.mjs)
// ---------------------------------------------------------------------------
async function bagianHalaman(B) {
    const R = { halaman: [], skenario: [], berkasFoto: [], galatRunner: [], ulangOffline: [] };
    const catat = (g, id, d) => R[g].push({ id, ...d });
    const kon = [];
    const sapu = async (P, id, urlPath, { foto = [], prasyarat = null } = {}) => {
        for (const tema of TEMA) for (const vpN of Object.keys(VP)) {
            const v = VP[vpN]; const komb = `${tema}-${v.id}`;
            try {
                await P.viewport(v); await P.setTema(tema);
                let dimuat = await P.goto(urlPath);
                // Halaman 'Tidak ada koneksi' (fallback offline service worker) = navigasi gagal sesaat di server uji php -S (1 utas), BUKAN isi halaman: ulang maks 2x dan CATAT.
                for (let u = 0; u < 2 && /Tidak ada koneksi/.test(await P.ev('document.title')); u++) { R.ulangOffline.push(`${id} ${komb}`); await sleep(1500); dimuat = await P.goto(urlPath); }
                if (prasyarat) await prasyarat(P);
                const m = await P.ukur(); m.loadEventTercapai = dimuat;
                m.foto = await P.foto(`${id}-${tema}-${v.id}`, foto.includes(komb), R);
                catat('halaman', id, { tema, viewport: v.id, ...m });
            } catch (e) { R.galatRunner.push(`${id} ${komb}: ${e.message}`); catat('halaman', id, { tema, viewport: v.id, galat: e.message }); }
        }
    };
    const D = 'terang-1366x768', M = 'terang-390x844', DG = 'gelap-1366x768', MG = 'gelap-390x844';
    try {
        const P = await bukaKonteks(B, VP.desktop); kon.push(P);
        catat('skenario', 'login-staff', await login(P, AKUN.staff));
        await sapu(P, 'feedback-index-kosong', '/feedback', { foto: [D, M] });
        await sapu(P, 'feedback-create-awal', '/feedback/create');
        await sapu(P, 'feedback-create-criticism', '/feedback/create', { foto: [D, M, DG], prasyarat: async (p) => { await p.pilih('#selCategory', 'CRITICISM'); await p.klik('label[for=chkWilling]'); await sleep(300); } });

        await P.viewport(VP.desktop); await P.setTema('terang'); await P.goto('/feedback/create');
        const vis = (w) => `getComputedStyle(document.querySelector('${w}')).display!=='none'`;
        const kontrak = {};
        kontrak.awal = await P.ev(`({msgTampak:${vis('#wrapMessage')},critTampak:${vis('#wrapCriticism')},waTampak:${vis('#wrapWhatsapp')},msgReq:document.querySelector('#inpMessage').required,selectTomSelect:!!document.querySelector('#selCategory + .ts-wrapper, #selCategory ~ .ts-wrapper, #selCategory[tabindex="-1"]'),opsi:[...document.querySelectorAll('#selCategory option')].map(o=>o.value)})`);
        await P.pilih('#selCategory', 'CRITICISM'); await P.klik('label[for=chkWilling]'); await sleep(250);
        kontrak.criticism = await P.ev(`({msgTampak:${vis('#wrapMessage')},critTampak:${vis('#wrapCriticism')},waTampak:${vis('#wrapWhatsapp')},critReq:document.querySelector('#inpCriticism').required,sugReq:document.querySelector('#inpSuggestion').required,waReq:document.querySelector('#inpWhatsapp').required,msgReq:document.querySelector('#inpMessage').required,chk:document.querySelector('#chkWilling').checked})`);
        await P.pilih('#selCategory', 'SUGGESTION'); await P.klik('label[for=chkWilling]'); await sleep(250);
        kontrak.suggestionTanpaWilling = await P.ev(`({msgTampak:${vis('#wrapMessage')},critTampak:${vis('#wrapCriticism')},waTampak:${vis('#wrapWhatsapp')},msgReq:document.querySelector('#inpMessage').required,waReq:document.querySelector('#inpWhatsapp').required})`);
        catat('skenario', 'create-kontrak-toggle', kontrak);

        for (const [vpN, tema] of [['desktop', 'terang'], ['mobile', 'terang']]) {
            const v = VP[vpN];
            await P.viewport(v); await P.setTema(tema); await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'SUGGESTION'); await P.ketik('textarea#inpMessage', 'Isi masukan uji invalid');
            const nativeBlok = !(await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'), 2500));
            const nativePesan = await P.ev(`(()=>{const e=document.querySelector('#frmFeedback input[name=title]');return {valid:document.querySelector('#frmFeedback').checkValidity(),pesan:e.validationMessage,fokus:document.activeElement===e}})()`);
            await P.ev(`(document.querySelector('#frmFeedback').noValidate=true)`);
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            await P.setTema(tema);
            const m = await P.ukur({ fokus: false });
            m.foto = await P.foto(`feedback-create-invalid-judul-${tema}-${v.id}`, vpN === 'desktop', R);
            const daftarGalat = await P.ev(`[...document.querySelectorAll('.content .alert-danger li')].map(l=>l.textContent.trim())`);
            const isi = await P.ev(`({judul:document.querySelector('input[name=title]').value,message:document.querySelector('#inpMessage').value,kategori:document.querySelector('#selCategory').value})`);
            catat('skenario', `create-invalid-judul-kosong-${v.id}`, { nativeMemblokirSubmit: nativeBlok, nativeStatus: nativePesan, serverNavigasi: nav, daftarGalat, nilaiTerjagaSetelahGalat: isi, ...m });
        }
        {
            await P.viewport(VP.desktop); await P.setTema('terang'); await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'SUGGESTION');
            await P.ketik('input[name=title]', 'Uji WA kosong'); await P.ketik('textarea#inpMessage', 'Isi masukan uji WA kosong');
            await P.klik('label[for=chkWilling]');
            const nativeBlok = !(await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'), 2500));
            const nativeWa = await P.ev(`({pesan:document.querySelector('#inpWhatsapp').validationMessage,fokus:document.activeElement===document.querySelector('#inpWhatsapp')})`);
            await P.ev(`(document.querySelector('#frmFeedback').noValidate=true)`);
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            const m = await P.ukur({ fokus: false });
            const daftarGalat = await P.ev(`[...document.querySelectorAll('.content .alert-danger li')].map(l=>l.textContent.trim())`);
            const kf = await P.ev(`({chk:document.querySelector('#chkWilling').checked,waTampak:${vis('#wrapWhatsapp')},kategoriTerjaga:document.querySelector('#selCategory').value})`);
            catat('skenario', 'create-invalid-wa-kosong', { nativeMemblokirSubmit: nativeBlok, nativeWa, serverNavigasi: nav, daftarGalat, keadaanFormSetelahGalat: kf, ...m });
        }
        {
            await P.viewport(VP.desktop); await P.setTema('terang'); await P.goto('/feedback/create');
            await P.pilih('#selCategory', 'CRITICISM');
            await P.ketik('input[name=title]', 'Uji Firefox F3 sesudah Tailwind');
            await P.ketik('#inpCriticism', 'Kritik uji Firefox: tampilan form perlu diseragamkan.');
            await P.ketik('#inpSuggestion', 'Saran uji Firefox: gunakan komponen yang konsisten.');
            await P.klik('label[for=chkWilling]'); await P.ketik('#inpWhatsapp', '081234567890');
            const nav = await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
            const m = await P.ukur();
            m.foto = await P.foto('feedback-index-flash-sukses-terang-1366x768', true, R);
            catat('skenario', 'create-valid-criticism', { navigasi: nav, urlAkhir: m.url, flashSukses: m.teksAlert, ...m });
        }
        const idBaru = await P.ev(`(()=>{const a=document.querySelector('.ts-action-primary');if(!a)return null;const m=a.getAttribute('href').match(/feedback\\/(\\d+)/);return m?Number(m[1]):null})()`);
        R.idSubmissionBaru = idBaru;
        if (!idBaru) throw new Error('Id submission baru tidak ditemukan di /feedback (tautan Detail).');
        await sapu(P, 'feedback-index-berisi', '/feedback');
        await sapu(P, 'feedback-show-staff', `/feedback/${idBaru}`, { foto: [D, MG] });
        {
            await P.viewport(VP.desktop); await P.setTema('terang'); await P.goto('/feedback-inbox');
            const m = await P.ukur({ fokus: false });
            catat('skenario', 'staff-akses-inbox-ditolak', { httpDokumen: m.httpDokumen, title: m.title, url: m.url, teks: await P.ev(`document.body.innerText.trim().replace(/\\s+/g,' ').slice(0,160)`) });
        }

        const S = await bukaKonteks(B, VP.desktop); kon.push(S);
        catat('skenario', 'login-superadmin', await login(S, AKUN.superadmin));
        await sapu(S, 'feedback-inbox', '/feedback-inbox', { foto: [D, M] });
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto('/feedback-inbox');
        catat('skenario', 'inbox-datatables', await S.ev(`({wrapper:!!document.querySelector('.dataTables_wrapper'),baris:document.querySelectorAll('#tblFeedbackInbox tbody tr').length,pencarian:!!document.querySelector('.dataTables_filter'),panjang:!!document.querySelector('.dataTables_length select'),paginasi:!!document.querySelector('.dataTables_paginate'),responsif:document.querySelector('#tblFeedbackInbox').className,statCards:document.querySelectorAll('[class*=stat]').length})`));
        await sapu(S, 'feedback-show-superadmin-respond', `/feedback/${idBaru}`, { foto: [D, M] });
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        catat('skenario', 'show-superadmin-status-reviewed', { infoTabel: await S.ev(`(()=>{const t=document.querySelector('table.table-sm');return t?t.innerText.replace(/\\s+/g,' ').slice(0,300):null})()`), kartuRespond: await S.ada('form[action$="/respond"]'), kartuClose: await S.ada('.frmCloseFeedback') });
        {
            await S.ev(`(document.querySelector('form[action$="/respond"]').noValidate=true)`);
            await S.ketik('textarea[name=response_text]', '');
            const nav = await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
            const m = await S.ukur({ fokus: false });
            catat('skenario', 'respond-kosong-server', { navigasi: nav, urlAkhir: m.url, alertTampak: m.teksAlert, textareaInvalid: await S.ev(`document.querySelector('textarea[name=response_text]').classList.contains('is-invalid')`) });
        }
        {
            await S.goto(`/feedback/${idBaru}`);
            await S.ketik('textarea[name=response_text]', 'Terima kasih atas masukannya. Tanggapan uji Firefox F3.');
            const nav = await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
            const m = await S.ukur();
            catat('skenario', 'respond-valid-ui', { navigasi: nav, urlAkhir: m.url, flash: m.teksAlert, kartuCloseTampak: await S.ada('.frmCloseFeedback'), kartuRespondMasihAda: await S.ada('form[action$="/respond"]'), ...m });
        }
        await sapu(S, 'feedback-show-superadmin-responded', `/feedback/${idBaru}`);
        {
            await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
            await S.klik('.frmCloseFeedback button[type=submit]');
            const swal = await S.tunggu('.swal2-popup'); await sleep(700);
            const swalInfo = swal ? await S.ev(`({judul:(document.querySelector('.swal2-title')||{}).textContent,teks:(document.querySelector('.swal2-html-container')||{}).textContent,konfirm:(document.querySelector('.swal2-confirm')||{}).textContent,batal:(document.querySelector('.swal2-cancel')||{}).textContent,bgKonfirm:getComputedStyle(document.querySelector('.swal2-confirm')).backgroundColor})`) : null;
            await S.foto('feedback-swal-konfirmasi-close-terang-1366x768', true, R);
            await S.klik('.swal2-cancel'); await sleep(500);
            const setelahBatal = await S.ev(`({swalMasihAda:!!document.querySelector('.swal2-popup'),url:location.pathname})`);
            await S.klik('.frmCloseFeedback button[type=submit]'); await S.tunggu('.swal2-confirm'); await sleep(700);
            const swalKedua = await S.ada('.swal2-popup');
            const nav = await S.aksiNavigasi(() => S.klik('.swal2-confirm'));
            const m = await S.ukur();
            m.foto = await S.foto('feedback-show-superadmin-closed-terang-1366x768', true, R);
            catat('skenario', 'close-ui-swal', { swalTerbuka: swal, swalKeduaTerbuka: swalKedua, swalInfo, setelahBatal, navigasiSetelahYa: nav, urlAkhir: m.url, flash: m.teksAlert, kartuRespondMasihAda: await S.ada('form[action$="/respond"]'), kartuCloseMasihAda: await S.ada('.frmCloseFeedback'), ...m });
        }
        await sapu(S, 'feedback-show-superadmin-closed', `/feedback/${idBaru}`);
        await sapu(S, 'feedback-inbox-akhir', '/feedback-inbox');
        for (const [id, u] of [['reg-beranda', '/'], ['reg-department', '/department'], ['reg-bank', '/bank'], ['reg-budget-request', '/budget-request'], ['reg-profile', '/profile']]) await sapu(S, id, u);

        // Fitur CSS efektif (sekali, desktop terang, halaman pilot)
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto('/feedback');
        R.fiturCss = await S.ev(`(()=>{
            const sup=(p,v)=>(v===undefined?CSS.supports(p):CSS.supports(p,v));
            const sheet=[...document.styleSheets].find(s=>s.href&&s.href.includes('tesys-tw.css'));
            let aturan=0,layer=0,galat=null; try{for(const r of sheet.cssRules){aturan++;const n=r.constructor.name;if(n==='CSSLayerBlockRule'||n==='CSSLayerStatementRule')layer++;}}catch(e){galat=String(e)}
            const pr=document.createElement('div');pr.style.cssText='position:absolute;background-color:var(--accent-dark)';document.body.appendChild(pr);const accDark=getComputedStyle(pr).backgroundColor;pr.remove();
            const a=document.querySelector('.content a.tw\\\\:inline-flex[href]');const cs=a?getComputedStyle(a):null;
            return {colorMix:sup('color','color-mix(in srgb, red 50%, blue)'),layerSelector:sup('selector(:where(a))'),focusVisible:sup('selector(:focus-visible)'),has:sup('selector(:has(a))'),zoom:sup('zoom','.8'),
              atProperty:typeof CSSPropertyRule!=='undefined',cssLayerBlockRule:typeof CSSLayerBlockRule!=='undefined',tw:{aturan,aturanLayer:layer,galat},
              accentDarkTerurai:accDark,tautan:cs?{display:cs.display,parentDisplay:getComputedStyle(a.parentElement).display,cursor:cs.cursor,minHeight:cs.minHeight,bg:cs.backgroundColor,color:cs.color,radius:cs.borderTopLeftRadius}:null}})()`);
    } catch (e) { R.galatRunner.push('FATAL: ' + e.message); }
    for (const p of kon) await tutupKonteks(B, p);
    return R;
}

// ---------------------------------------------------------------------------
// Butir dari bagian halaman (dibanding Chrome bila acuan ada)
// ---------------------------------------------------------------------------
function butirHalaman(R, ref) {
    const out = []; const b = (id, status, detail) => out.push({ id, status, detail });
    const fb = R.halaman.filter((h) => h.id.startsWith('feedback-') && !h.galat);
    const nonp = R.halaman.filter((h) => h.id.startsWith('reg-') && !h.galat);
    const sk = (id) => R.skenario.find((s) => s.id === id);
    const rsk = (id) => ref?.skenario?.find((s) => s.id === id);
    b('F-login', R.skenario.filter((s) => s.id.startsWith('login-')).every((s) => s.berhasil) ? 'PASS' : 'FAIL', R.skenario.filter((s) => s.id.startsWith('login-')).map((s) => ({ akun: s.akun, berhasil: s.berhasil, galat: s.galatLogin })));
    b('F-pengukuran-tanpa-galat-runner', R.galatRunner.length === 0 && R.halaman.every((h) => !h.galat) ? 'PASS' : 'FAIL', { n: R.halaman.length, galat: R.galatRunner });
    b('F-tw-dimuat-1x-urutan', fb.length && fb.every((h) => h.twLinkCount === 1 && h.urutanTwOk) ? 'PASS' : 'FAIL', { n: fb.length, salah: fb.filter((h) => !(h.twLinkCount === 1 && h.urutanTwOk)).map((h) => `${h.id} ${h.tema} ${h.viewport}`), aturan: 'tw tepat 1x di DOM, setelah tesys-ui-base.css, sebelum tesys-ui*.css lain' });
    b('F-tw-jaringan-diminta', fb.every((h) => h.adaTesysTwCss) ? 'PASS' : 'FAIL', { tanpaPermintaan: fb.filter((h) => !h.adaTesysTwCss).map((h) => `${h.id} ${h.tema} ${h.viewport}`) });
    b('F-isolasi-nonpilot-tanpa-tw', nonp.length && nonp.every((h) => h.twLinkCount === 0 && !h.adaTesysTwCss) ? 'PASS' : 'FAIL', { n: nonp.length, halaman: [...new Set(nonp.map((h) => h.url))] });
    if (ref) {
        const norm = (l) => [...new Set(l.map((x) => x.replace(/\?.*$/, '')))].sort();
        const beda = [];
        for (const h of nonp) { const c = ref.halaman.find((x) => x.id === h.id && x.tema === h.tema && x.viewport === h.viewport); if (c && !sama(norm(h.stylesheetAktif), norm(c.stylesheetAktif))) beda.push(`${h.id} ${h.tema} ${h.viewport}`); }
        b('F-isolasi-nonpilot-css-sama-chrome', beda.length === 0 ? 'PASS' : 'FAIL', { bedaDariChrome: beda, catatan: 'himpunan document.styleSheets (href) Firefox vs Chrome href' });
    } else b('F-isolasi-nonpilot-css-sama-chrome', 'NOT RUN', 'hasil-href-chrome.json tidak ada');
    const tk = sk('create-kontrak-toggle'), rtk = rsk('create-kontrak-toggle');
    b('F-toggle-kategori-whatsapp', tk && tk.awal.msgTampak && !tk.awal.critTampak && !tk.awal.waTampak && tk.criticism.critTampak && tk.criticism.waTampak && !tk.criticism.msgTampak && tk.criticism.critReq && tk.criticism.waReq && tk.criticism.chk && tk.suggestionTanpaWilling.msgTampak && !tk.suggestionTanpaWilling.waTampak && !tk.suggestionTanpaWilling.waReq ? 'PASS' : 'FAIL', { firefox: tk, samaDenganChrome: rtk ? sama(tk, rtk) : null });
    for (const [id, n] of [['create-invalid-judul-kosong-1366x768', 'F-validasi-native-server-judul-desktop'], ['create-invalid-judul-kosong-390x844', 'F-validasi-native-server-judul-mobile']]) {
        const s = sk(id), r = rsk(id);
        b(n, s && s.nativeMemblokirSubmit && s.nativeStatus.valid === false && s.nativeStatus.pesan && s.nativeStatus.fokus && s.serverNavigasi && s.daftarGalat.length > 0 && (!r || (sama(s.daftarGalat, r.daftarGalat) && sama(s.nilaiTerjagaSetelahGalat, r.nilaiTerjagaSetelahGalat))) ? 'PASS' : 'FAIL', { nativeStatus: s?.nativeStatus, daftarGalat: s?.daftarGalat, chromeDaftarGalat: r?.daftarGalat, nilai: s?.nilaiTerjagaSetelahGalat, catatan: 'teks validationMessage native bergantung peramban/bahasa (tidak dibandingkan)' });
    }
    { const s = sk('create-invalid-wa-kosong'), r = rsk('create-invalid-wa-kosong'); b('F-validasi-native-server-wa', s && s.nativeMemblokirSubmit && s.nativeWa.pesan && s.nativeWa.fokus && s.serverNavigasi && s.daftarGalat.length > 0 && (!r || (sama(s.daftarGalat, r.daftarGalat) && sama(s.keadaanFormSetelahGalat, r.keadaanFormSetelahGalat))) ? 'PASS' : 'FAIL', { nativeWa: s?.nativeWa, daftarGalat: s?.daftarGalat, keadaan: s?.keadaanFormSetelahGalat, chrome: r && { daftarGalat: r.daftarGalat, keadaan: r.keadaanFormSetelahGalat }, catatan: 'perilaku aplikasi (old() tidak memulihkan kategori/centang/isi) sama di Chrome; asersi = sama dengan Chrome' }); }
    { const s = sk('create-valid-criticism'); b('F-submit-valid-flash', s && s.navigasi && s.urlAkhir === '/feedback' && s.flashSukses.some((t) => /berhasil/i.test(t)) ? 'PASS' : 'FAIL', { url: s?.urlAkhir, flash: s?.flashSukses }); }
    { const s = sk('staff-akses-inbox-ditolak'); b('F-staff-inbox-403', s && s.httpDokumen === 403 ? 'PASS' : 'FAIL', s); }
    { const s = sk('inbox-datatables'); const r = rsk('inbox-datatables'); b('F-inbox-datatables', s && s.wrapper && s.panjang && s.paginasi && s.baris >= 1 && (!r || s.pencarian === r.pencarian) ? 'PASS' : 'FAIL', { firefox: s, chromePencarian: r?.pencarian, catatan: 'inbox memang tanpa kotak cari (sama di Chrome)' }); }
    { const s = sk('show-superadmin-status-reviewed'); const r = rsk('show-superadmin-status-reviewed'); b('F-show-kartu-respond-close-berwenang', s && s.kartuRespond && (!r || s.kartuClose === r.kartuClose) ? 'PASS' : 'FAIL', { firefox: s && { kartuRespond: s.kartuRespond, kartuClose: s.kartuClose }, chrome: r && { kartuRespond: r.kartuRespond, kartuClose: r.kartuClose }, catatan: 'kartu Close baru muncul setelah ada tanggapan (status Reviewed -> tanpa Close); sama di Chrome' }); }
    { const s = sk('respond-kosong-server'), r = rsk('respond-kosong-server'); b('F-respond-kosong-server', s && (!r || (s.navigasi === r.navigasi && s.urlAkhir.replace(/[0-9]+/, 'N') === r.urlAkhir.replace(/[0-9]+/, 'N') && sama(s.alertTampak, r.alertTampak) && s.textareaInvalid === r.textareaInvalid)) ? 'PASS' : 'FAIL', { firefox: s && { navigasi: s.navigasi, url: s.urlAkhir, alert: s.alertTampak, invalid: s.textareaInvalid }, chrome: r && { navigasi: r.navigasi, url: r.urlAkhir, alert: r.alertTampak, invalid: r.textareaInvalid } }); }
    { const s = sk('respond-valid-ui'); const rv = rsk('respond-valid-ui'); b('F-respond-valid', s && s.navigasi && s.flash.some((t) => /berhasil/i.test(t)) && s.kartuCloseTampak && (!rv || s.kartuRespondMasihAda === rv.kartuRespondMasihAda) ? 'PASS' : 'FAIL', { flash: s?.flash, close: s?.kartuCloseTampak, respond: s?.kartuRespondMasihAda }); }
    { const s = sk('close-ui-swal'), r = rsk('close-ui-swal'); b('F-close-swal', s && s.swalTerbuka && s.swalKeduaTerbuka && s.setelahBatal.swalMasihAda === false && s.navigasiSetelahYa && !s.kartuRespondMasihAda && !s.kartuCloseMasihAda && (!r || (s.swalInfo?.judul === r.swalInfo?.judul && s.swalInfo?.teks === r.swalInfo?.teks && s.swalInfo?.konfirm === r.swalInfo?.konfirm && s.swalInfo?.batal === r.swalInfo?.batal)) ? 'PASS' : 'FAIL', { swalInfo: s?.swalInfo, setelahBatal: s?.setelahBatal, flash: s?.flash }); }
    const jsErr = fb.concat(R.skenario.filter((s) => s.konsol)).flatMap((h) => (h.konsol || []).filter((k) => /error/.test(k.jenis)).map((k) => `${h.id}: ${k.teks}`));
    b('F-konsol-tanpa-error-halaman-feedback', jsErr.length === 0 ? 'PASS' : 'FAIL', { error: jsErr.slice(0, 10), peringatan: [...new Set(fb.concat(R.skenario.filter((s) => s.konsol)).flatMap((h) => (h.konsol || []).filter((k) => /warn/.test(k.jenis)).map((k) => k.teks)))].slice(0, 10) });
    b('F-konsol-nonpilot-informasi', 'INFO', Object.fromEntries([...new Set(nonp.map((h) => h.id))].map((id) => [id, [...new Set(nonp.filter((h) => h.id === id).flatMap((h) => h.konsol.map((k) => `${k.jenis}: ${k.teks.slice(0, 100)}`)))]])));
    let gal, abortNav, caraSaring, rincianSaring = null;
    if (fb.every((h) => Array.isArray(h.permintaanGagalDetail))) {
        // Data baru: saring HANYA abort dalam jendela navigasi (stempel waktu); abort di luar jendela (mis. font) tetap galat.
        const nyata = fb.flatMap((h) => h.permintaanGalat.map((x) => `${h.id} ${h.tema} ${h.viewport}: ${x}`));
        const gagalDet = fb.flatMap((h) => h.permintaanGagalDetail.map((d) => ({ ...d, halaman: `${h.id} ${h.tema} ${h.viewport}` })));
        abortNav = gagalDet.filter((d) => d.dalamJendelaNavigasi);
        gal = [...nyata, ...gagalDet.filter((d) => !d.dalamJendelaNavigasi).map((d) => `${d.halaman}: ${d.dest} ${d.url} ${d.galat} (+${d.msSejakNavMulai}ms sejak navigasi, ${d.msSetelahDokumenSelesai}ms setelah dokumen selesai, dokumen ${d.dokumenAktif})`)];
        caraSaring = 'NS_BINDING_ABORTED disaring hanya bila permintaan dimulai SEBELUM navigasi baru, abort terjadi antara mulai navigasi dan 400 ms setelah dokumen baru selesai, dan URL itu tidak diminta dokumen baru; font/stylesheet/skrip/gambar/dokumen dari dokumen baru tidak pernah disaring';
        const per = (l) => l.reduce((a, d) => { a[d.kategori] = (a[d.kategori] || 0) + 1; return a; }, {});
        rincianSaring = { tersaringPerKategori: per(abortNav), gagalPerKategori: per(gagalDet.filter((d) => !d.dalamJendelaNavigasi)), totalFetchError: gagalDet.length, urlTersaring: [...new Set(abortNav.map((d) => `${d.kategori} ${d.url}`))].sort() };
    } else {
        const semuaGal = fb.flatMap((h) => [...h.permintaanGalat, ...h.permintaanGagal].map((x) => `${h.id}: ${x}`));
        abortNav = semuaGal.filter((x) => /: +\/(notifications|todo-list\/has-pending) NS_BINDING_ABORTED$/.test(x)); // data lama tanpa stempel waktu: polling AJAX terputus navigasi
        gal = semuaGal.filter((x) => !abortNav.includes(x));
        caraSaring = 'data lama tanpa stempel waktu: penyaringan berdasarkan URL polling';
    }
    b('F-jaringan-tanpa-gagal-halaman-feedback', gal.length === 0 ? 'PASS' : 'FAIL', { gagal: gal.slice(0, 10), favicon: [...new Set(fb.map((h) => h.favicon && h.favicon.status))], abortNavigasiTersaring: abortNav.length, rincianSaring, caraSaring, catatanAbort: 'Firefox melaporkan fetchError NS_BINDING_ABORTED untuk permintaan dokumen lama yang terputus navigasi; Chrome tidak mencatatnya (0). Galat lain tetap FAIL.' });
    b('F-overflow-horizontal-feedback', fb.every((h) => !h.overflowHorizontal) ? 'PASS' : 'FAIL', { melanggar: fb.filter((h) => h.overflowHorizontal).map((h) => `${h.id} ${h.tema} ${h.viewport} ${h.pelanggarOverflow.join(',')}`) });
    b('F-overflow-horizontal-nonpilot-informasi', 'INFO', { melanggar: nonp.filter((h) => h.overflowHorizontal).map((h) => `${h.id} ${h.tema} ${h.viewport}`) });
    b('F-viewport-efektif', R.halaman.filter((h) => !h.galat).every((h) => h.innerWidth === Number(h.viewport.split('x')[0])) ? 'PASS' : 'FAIL', { salah: R.halaman.filter((h) => !h.galat && h.innerWidth !== Number(h.viewport.split('x')[0])).slice(0, 5).map((h) => `${h.id} ${h.viewport} innerWidth=${h.innerWidth}`) });
    b('F-zoom-computed', R.halaman.filter((h) => !h.galat).every((h) => (h.viewport === '1366x768' ? parseFloat(h.zoomHtml) === 0.8 : parseFloat(h.zoomHtml) === 1)) ? 'PASS' : 'FAIL', { contoh: [...new Set(R.halaman.filter((h) => !h.galat).map((h) => `${h.viewport}=${h.zoomHtml}`))] });
    b('F-tema-gelap-terang', R.halaman.filter((h) => !h.galat).every((h) => (h.tema === 'gelap' ? h.temaAttr === 'dark' && lumRgb(h.latar.bg) < lumRgb(h.latar.fg) : h.temaAttr !== 'dark' && lumRgb(h.latar.bg) > lumRgb(h.latar.fg))) ? 'PASS' : 'FAIL', { contoh: [...new Set(R.halaman.filter((h) => !h.galat).map((h) => `${h.tema}:${h.temaAttr}:${h.latar.bg}`))] });
    const f = R.fiturCss;
    b('F-fitur-css-efektif', f && f.colorMix && f.focusVisible && f.has && f.zoom && f.cssLayerBlockRule && f.tw.aturanLayer > 0 && f.tautan && (f.tautan.display === 'inline-flex' || (f.tautan.display === 'flex' && /flex|grid/.test(f.tautan.parentDisplay))) && f.accentDarkTerurai !== 'rgba(0, 0, 0, 0)' ? 'PASS' : 'FAIL', f);
    b('F-aksesibilitas-dasar-dibanding-chrome', ref ? (fb.every((h) => { const c = ref.halaman.find((x) => x.id === h.id && x.tema === h.tema && x.viewport === h.viewport); return !c || (h.a11y.tombolTanpaNama === c.a11y.tombolTanpaNama && sama(h.a11y.kontrolTanpaNama, c.a11y.kontrolTanpaNama)); }) ? 'PASS' : 'FAIL') : 'NOT RUN', { catatan: 'tombolTanpaNama/kontrolTanpaNama sama dengan Chrome href' });
    b('F-sentuh-390-informasi', 'INFO', { contoh: fb.filter((h) => h.viewport === '390x844').slice(0, 4).map((h) => ({ id: h.id, kurangDari44: h.sentuh.kurangDari44, contoh: h.sentuh.contoh.slice(0, 3) })) });
    b('F-screenshot', R.berkasFoto.length > 0 && R.berkasFoto.length <= 14 ? 'PASS' : 'FAIL', { jumlah: R.berkasFoto.length });
    return out;
}

// ---------------------------------------------------------------------------
// BAGIAN QA (padanan cdp-qa-f3.mjs)
// ---------------------------------------------------------------------------
function dalamHalaman() {
    const kanvas = document.createElement('canvas'); kanvas.width = kanvas.height = 1;
    const cx = kanvas.getContext('2d', { willReadFrequently: true });
    const rgba = (c) => { cx.clearRect(0, 0, 1, 1); cx.fillStyle = '#010203'; cx.fillStyle = c; if (cx.fillStyle === '#010203' && !/^#?010203$/i.test(String(c))) throw new Error('warna tak terurai: ' + c); cx.fillRect(0, 0, 1, 1); const d = cx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
    const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const rasio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
    const campur = (atas, bawah) => { const a = atas[3]; return [atas[0] * a + bawah[0] * (1 - a), atas[1] * a + bawah[1] * (1 - a), atas[2] * a + bawah[2] * (1 - a), 1]; };
    const bgEfektif = (el) => {
        const rantai = [];
        for (let e = el; e; e = e.parentElement) rantai.push(rgba(getComputedStyle(e).backgroundColor));
        let hasil = rgba(getComputedStyle(document.documentElement).backgroundColor);
        if (hasil[3] < 1) hasil = campur(hasil, [255, 255, 255, 1]);
        for (let i = rantai.length - 1; i >= 0; i--) hasil = campur(rantai[i], hasil);
        return hasil;
    };
    const vis = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const hex = (c) => '#' + c.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
    return { rgba, rasio, bgEfektif, vis, hex, campur };
}
const BANTU = `(${dalamHalaman.toString()})`;

const kontras = (P, daftar) => P.ev(`(()=>{const H=${BANTU}();const hasil=[];for(const d of ${J(daftar)}){const els=[...document.querySelectorAll(d.sel)].filter(H.vis);if(!els.length){hasil.push({nama:d.nama,sel:d.sel,ada:false});continue;}for(const el of els.slice(0,d.maks||6)){const s=d.jenis==='placeholder'?getComputedStyle(el,'::placeholder'):getComputedStyle(el);const fg=H.rgba(s.color);const bg=H.bgEfektif(el);const fgc=H.campur(fg,bg);hasil.push({nama:d.nama,sel:d.sel,ada:true,teks:(el.textContent||'').trim().slice(0,24),fg:H.hex(fgc),bg:H.hex(bg),rasio:Math.round(H.rasio(fgc,bg)*100)/100,batas:d.batas||4.5});}}return hasil;})()`);
const ukurKontrol = (P, sels) => P.ev(`(()=>{const z=parseFloat(getComputedStyle(document.documentElement).zoom)||1;return ${J(sels)}.map((sel)=>{const e=[...document.querySelectorAll(sel)].find((x)=>{const r=x.getBoundingClientRect();return r.width>0&&r.height>0});if(!e)return{sel,ada:false};const r=e.getBoundingClientRect();return{sel,ada:true,zoom:z,w:Math.round(r.width/z*10)/10,h:Math.round(r.height/z*10)/10,wLayar:Math.round(r.width),hLayar:Math.round(r.height)}})})()`);
const kontrakDom = (P, sels) => P.ev(`${J(sels)}.map((s)=>({sel:s,n:document.querySelectorAll(s).length}))`);

/** Nama & peran aksesibel: dihitung dari DOM, lalu DIVERIFIKASI ke pohon aksesibilitas Firefox lewat browsingContext.locateNodes (locator accessibility). */
async function namaAksesibel(P, sel) {
    const dom = await P.ev(`(()=>{const e=document.querySelector(${J(sel)});if(!e)return null;const t=e.tagName.toLowerCase();const role=e.getAttribute('role')||(t==='a'&&e.hasAttribute('href')?'link':t==='button'?'button':t);let nama=e.getAttribute('aria-label');if(!nama){const lb=e.getAttribute('aria-labelledby');if(lb)nama=lb.split(/\\s+/).map(i=>(document.getElementById(i)||{}).textContent||'').join(' ');}if(!nama)nama=(e.textContent||'').trim().replace(/\\s+/g,' ');return {role,nama}})()`);
    if (!dom) return { sel, ada: false };
    const r = { sel, ada: true, roleDom: dom.role, namaDom: dom.nama, role: null, nama: null, axTerverifikasi: false };
    try {
        const id = await P.sharedId(sel);
        const l = await P.b.send('browsingContext.locateNodes', { context: P.ctx, locator: { type: 'accessibility', value: { role: dom.role, name: dom.nama } } });
        r.axTerverifikasi = !!id && l.nodes.some((n) => n.sharedId === id);
        if (r.axTerverifikasi) { r.role = dom.role; r.nama = dom.nama; }
    } catch (e) { r.axGalat = String(e.message).slice(0, 160); }
    return r;
}
async function urutanTab(P, awalSel, maks) {
    await P.evS(`document.activeElement&&document.activeElement.blur();window.scrollTo({top:0,left:0,behavior:'instant'});`);
    await P.tab(); await sleep(200);
    await P.ev(`document.querySelector(${J(awalSel)}).focus()`); await sleep(550);
    const urut = [];
    for (let i = 0; i < maks; i++) {
        const info = await P.ev(`(()=>{const H=${BANTU}();const e=document.activeElement;if(!e||e===document.body)return null;const s=getComputedStyle(e);const par=H.bgEfektif(e.parentElement||e);const oc=H.campur(H.rgba(s.outlineColor),par);return{el:e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.name?'[name='+e.name+']':''),dalamContent:!!e.closest('.content'),fokusVisible:e.matches(':focus-visible'),outline:s.outlineStyle+' '+s.outlineWidth,outlineOffset:s.outlineOffset,warna:H.hex(oc),rasioOutline:Math.round(H.rasio(oc,par)*100)/100,boxShadow:s.boxShadow==='none'?'none':'ada'}})()`);
        if (!info) break;
        urut.push(info);
        if (!info.dalamContent && urut.length > 1) break;
        await P.tab(); await sleep(550);
    }
    return urut;
}
async function periksaTautan(P, jalur, tujuan) {
    await P.setTema('terang'); await P.goto(jalur);
    const info = await P.ev(`(()=>{const a=document.querySelector(${J(NAV)});if(!a)return null;return{tag:a.tagName.toLowerCase(),href:a.getAttribute('href'),pathname:new URL(a.href).pathname,search:new URL(a.href).search,dalamForm:!!a.closest('form'),teks:a.textContent.trim().replace(/\\s+/g,' '),busyAttr:a.getAttribute('aria-busy'),disabled:a.hasAttribute('aria-disabled')||a.hasAttribute('disabled'),jumlahFormGet:document.querySelectorAll('.content form[method=GET i]').length}})()`);
    if (!info) return { ada: false, jalur, tujuan };
    const ax = await namaAksesibel(P, NAV);
    const roots = async () => (await P.b.send('browsingContext.getTree', {})).contexts.map((c) => ({ id: c.context, url: c.url }));
    const sebelum = await roots();
    await P.klik(NAV, { ctrl: true });
    await sleep(1200);
    const sesudah = await roots();
    const baru = sesudah.filter((c) => !sebelum.some((s) => s.id === c.id));
    const urlBaru = baru.map((c) => String(c.url).replace(/^https?:\/\/[^/]+/, ''));
    for (const c of baru) { try { await P.b.send('browsingContext.close', { context: c.id }); } catch { /* abaikan */ } }
    const jalurAsalTetap = (await P.ev('location.pathname')) === jalur;
    const nav = await P.aksiNavigasi(() => P.klik(NAV));
    const setelah = await P.ev('({p:location.pathname,s:location.search})');
    await P.goto(jalur);
    await P.evS(`document.activeElement&&document.activeElement.blur();`); await P.tab(); await sleep(80);
    await P.ev(`document.querySelector(${J(NAV)}).focus()`);
    const navEnter = await P.aksiNavigasi(() => P.enter());
    const setelahEnter = await P.ev('({p:location.pathname,s:location.search})');
    const ok = info.tag === 'a' && info.pathname === tujuan && info.search === '' && !/\?/.test(info.href) && !info.dalamForm && info.jumlahFormGet === 0 && !info.disabled && info.busyAttr === null
        && !/Memproses/.test(info.teks) && ax.role === 'link' && !!ax.nama && jalurAsalTetap && baru.length === 1 && urlBaru.some((u) => u.startsWith(tujuan))
        && nav && setelah.p === tujuan && setelah.s === '' && navEnter && setelahEnter.p === tujuan && setelahEnter.s === '';
    return { ok, jalur, tujuan, info, ax, ctrlKlik: { tabBaru: baru.length, urlBaru, halamanAsalTetap: jalurAsalTetap }, klikBiasa: { navigasi: nav, ...setelah }, enter: { navigasi: navEnter, ...setelahEnter } };
}

/** Tab MURNI dari awal dokumen sampai tombol kirim form (tanpa .focus()/klik skrip); kembalikan info fokus tombol itu. */
async function tabKeKirim(T, maks = 40) {
    for (let i = 0; i < maks; i++) {
        await T.tab(); await sleep(100);
        if (await T.ev(`(()=>{const e=document.activeElement;return !!e&&e.matches('#frmFeedback button[type=submit]')})()`)) { await sleep(450); return { tabDitekan: i + 1, info: await T.ev(INFO_FOKUS(true)) }; }
    }
    return { tabDitekan: null, info: null };
}
/** Muat /feedback/create di TAB BARU (konteks browsing baru, cookie sesi sama) dengan tema/aksen, lalu jalankan fn(T). */
async function dalamTabBaru(B, userContext, { tema, aksen = null }, fn) {
    const T = await bukaTab(B, userContext, VP.desktop);
    try {
        await T.goto('/feedback/create');
        await T.ev(`(()=>{localStorage.setItem('tesys-theme',${J(tema === 'gelap' ? 'dark' : 'light')});${aksen ? `localStorage.setItem('tesys-accent',${J(aksen)})` : `localStorage.removeItem('tesys-accent')`}})()`);
        await muatDenganUlang(T, '/feedback/create', []);
        return await fn(T);
    } finally {
        await T.ev(`(()=>{localStorage.setItem('tesys-theme','light');localStorage.removeItem('tesys-accent')})()`).catch(() => {}); // localStorage dibagi antar tab satu user context: pulihkan
        await tutupTab(B, T);
    }
}

const XSS_JUDUL ='<i id="xss-uji">F3 QA</i> & <script>window.__x=1</script>';
const XSS_ISI = 'Isi uji QA F3 Firefox <b id="xss-isi">tebal</b>';
/**
 * Escape XSS dipecah: butir `escape-xss-index` (judul di tabel riwayat) dan `escape-xss-show` (judul di h1 + isi di kartu).
 * Id submission baru diambil dari BARIS yang memuat judul payload (bukan baris pertama). Show wajib memuat halaman yang benar
 * (bukan fallback "Tidak ada koneksi", path = /feedback/<id>, title "Detail Submission"); jika tidak, butir FAIL dengan alasan.
 * Bila elemen/skrip hasil injeksi ditemukan => butir FAIL berlabel KRITIS dan pengujian dihentikan (throw).
 * foto = { foto: [], fotoMaks } bila screenshot show diminta.
 */
async function qaXss(P, butir, { foto = null } = {}) {
    const ulang = [];
    await P.viewport(VP.desktop); await P.setTema('terang'); await muatDenganUlang(P, '/feedback/create', ulang);
    await P.pilih('#selCategory', 'SUGGESTION');
    await P.ketik('input[name=title]', XSS_JUDUL);
    await P.ketik('textarea#inpMessage', XSS_ISI);
    await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
    for (let u = 0; u < 2 && /Tidak ada koneksi/.test(await P.ev('document.title')); u++) { ulang.push('setelah submit'); await sleep(1500); await P.goto('/feedback'); }
    const flash = await P.ev(`(document.querySelector('.alert-success')||{}).textContent`);
    const idx = await P.ev(`(()=>{const idDari=(tr)=>{const a=tr&&tr.querySelector('.ts-action-primary');const m=a&&a.getAttribute('href').match(/feedback\\/(\\d+)/);return m?Number(m[1]):null};const baris=[...document.querySelectorAll('tbody tr')];const trJudul=baris.find((tr)=>tr.textContent.includes('<i id="xss-uji">'));return {path:location.pathname,title:document.title,jumlahBaris:baris.length,idBarisPertama:idDari(baris[0]),idBarisJudul:idDari(trJudul),elInjeksi:!!document.querySelector('#xss-uji, #xss-isi'),skripInjeksi:window.__x===1,teksJudul:!!trJudul}})()`);
    const idBaru = idx.idBarisJudul;
    const injIdx = idx.elInjeksi || idx.skripInjeksi;
    butir('escape-xss-index', !injIdx && flash && /berhasil/.test(flash) && idx.path === '/feedback' && idx.teksJudul ? 'PASS' : 'FAIL', { flash: (flash || '').trim(), ...idx, ulangOffline: ulang, kritis: injIdx ? 'INJEKSI TERDETEKSI di index' : undefined });
    if (injIdx) throw new Error('KRITIS: elemen/skrip injeksi terdeteksi di index');
    if (!idBaru) { butir('escape-xss-show', 'FAIL', { alasan: 'id submission baru tidak ditemukan pada baris yang memuat judul payload', idx }); return { idBaru: null }; }
    const ulangShow = [];
    await muatDenganUlang(P, `/feedback/${idBaru}`, ulangShow);
    const xs = await P.ev(`(()=>{const t=document.body.innerText;const h1=document.querySelector('.content h1')||document.querySelector('h1');return {path:location.pathname,title:document.title,h1:h1?h1.textContent.trim().replace(/\\s+/g,' ').slice(0,140):null,halamanFallback:/Tidak ada koneksi/.test(document.title),elInjeksi:!!document.querySelector('#xss-uji, #xss-isi, #xss-t'),skripInjeksi:window.__x===1,teksJudul:t.includes('<i id="xss-uji">'),teksPesan:t.includes('<b id="xss-isi">'),judulLiteralDiH1:!!h1&&h1.textContent.includes('<i id="xss-uji">'),wadahJudul:[...document.querySelectorAll('body *')].filter((e)=>e.textContent.includes('<i id="xss-uji">')&&![...e.children].some((c)=>c.textContent.includes('<i id="xss-uji">'))).slice(0,3).map((e)=>e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.className?'.'+String(e.className).trim().split(/\\s+/)[0]:'')+(e.closest('.content')?' [dalam .content]':' [di luar .content]')),catatanH1:'public/js/tesys-shell.js memindahkan teks h1 pertama di .content ke judul topbar lalu menghapus h1 (baris 24-29): h1 null di DOM akhir itu NORMAL; judul diperiksa lewat wadahJudul'}})()`);
    if (foto) xs.foto = await fotoDiag(P, 'diag-xss-show-terang-1366x768', foto);
    const halamanBenar = xs.path === `/feedback/${idBaru}` && !xs.halamanFallback && /Detail Submission/.test(xs.title);
    const injShow = xs.elInjeksi || xs.skripInjeksi;
    butir('escape-xss-show', !injShow && halamanBenar && xs.teksJudul && xs.teksPesan ? 'PASS' : 'FAIL', { idDimuat: idBaru, halamanBenar, ...xs, ulangOffline: ulangShow, kritis: injShow ? 'INJEKSI TERDETEKSI di show' : undefined });
    if (injShow) throw new Error('KRITIS: elemen/skrip injeksi terdeteksi di show');
    return { idBaru };
}

async function bagianQa(B) {
    const R = { butir: [], galat: [] };
    const butir = (id, status, detail) => { R.butir.push({ id, status, detail }); console.log(`[firefox] ${status.padEnd(7)} ${id}`); };
    const kon = [];
    try {
        const P = await bukaKonteks(B, VP.mobile); kon.push(P);
        await login(P, AKUN.staff);
        // viewport & fitur
        await P.viewport(VP.mobile); await P.setTema('terang'); await P.goto('/feedback/create');
        butir('viewport-390-efektif', (await P.ev('window.innerWidth')) === 390 ? 'PASS' : 'FAIL', { innerWidth: await P.ev('window.innerWidth'), catatan: 'browsingContext.setViewport; tanpa emulasi sentuh/DPR/meta viewport seluler' });
        await P.ev(`document.querySelector('#chkWilling').click()`); await sleep(200);
        const sentuhCreate = await ukurKontrol(P, [NAV, '#selCategory', 'input#title', 'label[for=chkWilling]', 'input#inpWhatsapp', '#frmFeedback button[type=submit]']);
        await P.goto('/feedback'); const sentuhIdx = await ukurKontrol(P, [NAV]);
        butir('sentuh-44-create-390', sentuhCreate.filter((x) => x.ada && x.sel !== 'label[for=chkWilling]').every((x) => x.w >= 44 && x.h >= 44) ? 'PASS' : 'FAIL', { create: sentuhCreate, index: sentuhIdx, catatan: 'label[for=chkWilling] (custom-control legacy) tidak dimigrasi: dilaporkan, tidak masuk kriteria' });

        await P.viewport(VP.desktop); await P.goto('/feedback/create');
        const kc = await kontrakDom(P, ['form#frmFeedback[action$="/feedback"][method=POST]', 'input[name=_token]', 'select#selCategory[name=category][required]', 'input#title[name=title][maxlength="150"][required]', 'textarea#inpMessage[name=message]', 'textarea#inpCriticism[name=criticism_text]', 'textarea#inpSuggestion[name=suggestion_text]', 'input#chkWilling[name=willing_to_discuss][value="1"][type=checkbox]', 'input#inpWhatsapp[name=whatsapp_number]', '#wrapMessage', '#wrapCriticism', '#wrapWhatsapp', '#wrapWhatsapp .form-group', 'label[for=chkWilling]', '.feedback-disclaimer', 'label[for=selCategory]', 'label[for=title]', 'label[for=inpWhatsapp]', '#inpWhatsapp[aria-describedby="inpWhatsapp-hint"]', '#inpWhatsapp-hint']);
        butir('kontrak-dom-create', kc.every((k) => k.n >= 1) ? 'PASS' : 'FAIL', kc.filter((k) => k.n < 1));

        const axBack = await namaAksesibel(P, NAV);
        butir('nama-aksesibel-tombol-kembali-create', axBack.ada && axBack.axTerverifikasi && axBack.role === 'link' && /Kembali/.test(axBack.nama || '') ? 'PASS' : 'FAIL', axBack);
        await P.goto('/feedback');
        const axBuat = await namaAksesibel(P, NAV);
        butir('nama-aksesibel-tombol-buat-index', axBuat.ada && axBuat.axTerverifikasi && /Buat Submission/.test(axBuat.nama || '') ? 'PASS' : 'FAIL', axBuat);

        for (const tema of ['terang', 'gelap']) {
            // Tab MURNI (input.performActions) dari awal dokumen di TAB BARU; Space pada checkbox lewat papan ketik. Tanpa .focus()/klik skrip.
            const h = await dalamTabBaru(B, P.userContext, { tema }, (T) => tabMurni(T, { R: { foto: [], fotoMaks: 0 } }));
            const ids = h.urutanContent;
            const urutanBenar = h.mencapaiTombolKirim && ids[0] === 'a' && ids[1] === 'select#selCategory[name=category]' && ids[2] === 'input#title[name=title]' && ids[3] === 'textarea#inpMessage[name=message]' && ids[4] === 'input#chkWilling[name=willing_to_discuss]' && ids[5] === 'input#inpWhatsapp[name=whatsapp_number]' && ids[6] === 'button';
            const tw = h.maju.filter((x) => x.dalamContent && x.tw);
            const wajib = ['a', 'select#selCategory[name=category]', 'input#title[name=title]', 'input#inpWhatsapp[name=whatsapp_number]', 'button'];
            const fokusOk = wajib.every((w) => tw.some((x) => x.el === w)) && h.ringkas.tw.gagal.length === 0 && tw.every((x) => x.fokusVisible && /solid|auto/.test(x.outlineStyle) && parseFloat(x.outlineWidth) > 0 && x.rasio >= 3);
            const metode = 'Tab murni dari awal dokumen di tab baru; tanpa .focus()/klik skrip (Space pada checkbox lewat papan ketik)';
            butir(`urutan-tab-create-${tema}`, urutanBenar ? 'PASS' : 'FAIL', { urut: ids, urutBalikShiftTab: h.urutanBalik, tabDitekan: h.tabDitekan, metode });
            butir(`indikator-fokus-komponen-${tema}`, fokusOk ? 'PASS' : 'FAIL', { metode, tw: tw.map((x) => ({ el: x.el, fokusVisible: x.fokusVisible, outline: `${x.outlineStyle} ${x.outlineWidth}`, offset: x.outlineOffset, radius: x.radius, warna: x.warnaIndikator, latar: x.latarInduk, rasio: x.rasio })), ringkasTw: h.ringkas.tw });
            butir(`fokus-komponen-legacy-informasi-${tema}`, 'INFO', { ...h.ringkas.legacy, catatan: 'komponen legacy (textarea .form-control = ring box-shadow, checkbox custom-control = outline accent) TIDAK dimigrasi ke Tailwind: di luar cakupan butir fokus komponen; belum dibandingkan dengan Chrome' });
            R[`tab_${tema}`] = { urutanContent: h.urutanContent, ringkas: h.ringkas };
        }
        await P.setTema('terang'); await P.goto('/feedback/create');
        await P.evS(`document.activeElement&&document.activeElement.blur();`); await P.tab(); await sleep(80);
        await P.ev(`document.querySelector(${J(NAV)}).focus()`);
        const navEnter = await P.aksiNavigasi(() => P.enter());
        butir('keyboard-enter-tombol-kembali', navEnter && (await P.ev('location.pathname')) === '/feedback' ? 'PASS' : 'FAIL', { path: await P.ev('location.pathname') });

        await P.viewport(VP.desktop);
        const tc = await periksaTautan(P, '/feedback/create', '/feedback'); butir('tautan-href-kembali-create', tc.ok ? 'PASS' : 'FAIL', tc);
        const ti = await periksaTautan(P, '/feedback', '/feedback/create'); butir('tautan-href-buat-index', ti.ok ? 'PASS' : 'FAIL', ti);

        const DAFTAR = [
            { nama: 'label', sel: '.content label[for=selCategory], .content label[for=title]' }, { nama: 'select-teks', sel: '#selCategory' }, { nama: 'input-teks', sel: 'input#title' },
            { nama: 'placeholder', sel: 'input#title', jenis: 'placeholder' }, { nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' }, { nama: 'tombol-kembali', sel: NAV },
            { nama: 'kartu-teks', sel: '.content label[for=selCategory]' },
        ];
        const kh = {};
        for (const tema of ['terang', 'gelap']) {
            await P.viewport(VP.desktop); await P.setTema(tema); await P.goto('/feedback/create');
            await P.ev(`document.querySelector('#chkWilling').click()`); await sleep(150);
            const k1 = await kontras(P, [...DAFTAR, { nama: 'hint', sel: '#inpWhatsapp-hint' }, { nama: 'placeholder-wa', sel: 'input#inpWhatsapp', jenis: 'placeholder' }]);
            await P.goto('/feedback');
            const k2 = await kontras(P, [{ nama: 'badge', sel: '.content .tw\\:rounded-pill', maks: 12 }, { nama: 'th', sel: '.content thead th', maks: 6 }, { nama: 'td', sel: '.content tbody td', maks: 6 }, { nama: 'tombol-buat', sel: NAV }]);
            kh[tema] = [...k1, ...k2];
            const gagal = kh[tema].filter((x) => x.ada && x.rasio < x.batas);
            butir(`kontras-teks-${tema}-aksen-bawaan`, gagal.length === 0 && kh[tema].some((x) => x.ada) ? 'PASS' : 'FAIL', { jumlah: kh[tema].filter((x) => x.ada).length, gagal, minimum: Math.min(...kh[tema].filter((x) => x.ada).map((x) => x.rasio)) });
        }
        R.kontrasDetail = kh;

        await P.setTema('terang'); await P.goto('/feedback');
        const badges = await P.ev(`[...document.querySelectorAll('.content .tw\\\\:rounded-pill')].map((b)=>({t:b.textContent.trim(),bg:getComputedStyle(b).backgroundColor,fg:getComputedStyle(b).color}))`);
        butir('badge-teks-bermakna', badges.length > 0 && badges.every((b) => b.t.length > 0) && new Set(badges.map((b) => b.t)).size >= 3 ? 'PASS' : 'FAIL', { jumlah: badges.length, teksUnik: [...new Set(badges.map((b) => b.t))] });
        const ki = await kontrakDom(P, ['.table-responsive table', '.ts-actions', 'a.ts-action-primary.is-neutral[aria-label="Detail masukan"]', 'td.action-column', 'thead tr']);
        butir('kontrak-dom-index', ki.every((k) => k.n >= 1) && (await P.ev(`document.querySelectorAll('thead tr').length`)) === 1 ? 'PASS' : 'FAIL', ki);

        await P.viewport(VP.desktop); await P.goto('/feedback/create');
        const zD = await P.ev(`getComputedStyle(document.documentElement).zoom`);
        const tD = await ukurKontrol(P, ['#selCategory', 'input#title', '#frmFeedback button[type=submit]', NAV]);
        await P.viewport(VP.mobile); await P.goto('/feedback/create');
        const zM = await P.ev(`getComputedStyle(document.documentElement).zoom`);
        butir('zoom-desktop-0.8', parseFloat(zD) === 0.8 && parseFloat(zM) === 1 ? 'PASS' : 'FAIL', { desktop: zD, mobile: zM, tinggiCssPxDesktop: tD.map((x) => ({ sel: x.sel, h: x.h, hLayar: x.hLayar })) });

        const { idBaru } = await qaXss(P, butir, { foto: null });

        await P.viewport(VP.mobile); await P.goto(`/feedback/${idBaru}`);
        const axBs = await namaAksesibel(P, NAV); const sBs = await ukurKontrol(P, [NAV]);
        await P.viewport(VP.desktop);
        const ts = await periksaTautan(P, `/feedback/${idBaru}`, '/feedback'); butir('tautan-href-kembali-show-staff', ts.ok ? 'PASS' : 'FAIL', ts);
        butir('show-staff-tombol-kembali', axBs.axTerverifikasi && /Kembali ke daftar/.test(axBs.nama || '') && sBs[0].w >= 44 && sBs[0].h >= 44 ? 'PASS' : 'FAIL', { ax: axBs, sentuh: sBs });

        const aksen = [];
        for (const hex of PRASET) for (const tema of ['terang', 'gelap']) {
            await P.viewport(VP.desktop); await P.ev(`localStorage.setItem('tesys-accent', ${J(hex)})`); await P.setTema(tema); await P.goto('/feedback/create');
            const k = await kontras(P, [{ nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' }, { nama: 'label', sel: '.content label[for=title]' }]);
            // INFORMASI saja (metode LAMA runner, tidak diasersikan): blur, Tab sekali, tunggu 60 ms, lalu .focus() skrip pada tombol kirim.
            await P.evS(`document.activeElement&&document.activeElement.blur();`); await P.tab(); await sleep(60);
            await P.ev(`document.querySelector('#frmFeedback button[type=submit]').focus()`); await sleep(550);
            const fLama = await P.ev(`(()=>{const H=${BANTU}();const e=document.activeElement;const s=getComputedStyle(e);const par=H.bgEfektif(e.parentElement);const oc=H.campur(H.rgba(s.outlineColor),par);return{fokusVisible:e.matches(':focus-visible'),outline:s.outlineStyle+' '+s.outlineWidth,rasioOutline:Math.round(H.rasio(oc,par)*100)/100,accentDiMuat:getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()}})()`);
            await P.goto('/feedback'); const kT = await kontras(P, [{ nama: 'tautan-buat', sel: NAV }]);
            // Fokus: Tab MURNI dari awal dokumen di tab baru (tema+aksen yang sama) sampai tombol kirim.
            const fk = await dalamTabBaru(B, P.userContext, { tema, aksen: hex }, (T) => tabKeKirim(T));
            const fi = fk.info;
            const fokus = fi && { tabDitekan: fk.tabDitekan, fokusVisible: fi.fokusVisible, outline: `${fi.outlineStyle} ${fi.outlineWidth}`, outlineOffset: fi.outlineOffset, radius: fi.radius, warna: fi.warnaIndikator, latar: fi.latarInduk, rasioOutline: fi.rasio, terlihat: fi.terlihat, hasFocus: fi.hasFocus };
            aksen.push({ hex, tema, tautan: kT[0], tombol: k.find((x) => x.nama === 'tombol-primer'), label: k.find((x) => x.nama === 'label'), fokus, fokusMetodeLamaInformasi: fLama });
        }
        await P.ev(`localStorage.removeItem('tesys-accent')`);
        const gk = aksen.filter((a) => !a.tombol?.ada || a.tombol.rasio < 4.5 || !a.tautan.ada || a.tautan.rasio < 4.5);
        butir('aksen-kontras-8x2', aksen.length === 16 && gk.length === 0 ? 'PASS' : 'FAIL', { total: aksen.length, gagal: gk, minTautan: Math.min(...aksen.map((a) => a.tautan.rasio)), minTombol: Math.min(...aksen.map((a) => a.tombol.rasio)), catatan: 'teks tombol primer dan tautan primer >= 4,5:1 pada 8 praset x 2 tema (dipisah dari fokus)' });
        const gf = aksen.filter((a) => !a.fokus || !a.fokus.fokusVisible || !a.fokus.terlihat || a.fokus.rasioOutline < 3);
        butir('aksen-fokus-8x2', aksen.length === 16 && gf.length === 0 ? 'PASS' : 'FAIL', { total: aksen.length, gagal: gf, minRasioFokus: Math.min(...aksen.map((a) => a.fokus?.rasioOutline ?? 0)), metode: 'Tab murni dari awal dokumen di tab baru sampai tombol kirim; :focus-visible cocok + outline solid terlihat + rasio >= 3:1 terhadap latar induk' });
        const lama = aksen.filter((a) => a.fokusMetodeLamaInformasi && !a.fokusMetodeLamaInformasi.fokusVisible).length;
        butir('aksen-fokus-metode-lama-informasi', 'INFO', { fokusVisibleFalsePadaMetodeLama: lama, total: aksen.length, catatan: 'pengukuran lama (Tab sekali + .focus() skrip setelah interaksi mouse di tab yang sama) disimpan hanya sebagai pembanding; tidak menentukan PASS/FAIL' });
        R.aksenDetail = aksen;
        await P.ev(`localStorage.setItem('tesys-accent','#ffd400')`); await P.setTema('terang'); await P.goto('/feedback/create');
        const kuning = await kontras(P, [{ nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' }]);
        await P.ev(`localStorage.removeItem('tesys-accent')`);
        butir('aksen-bebas-terang-#ffd400-informasi', 'INFO', { tombol: kuning[0], catatan: 'sama dengan tombol legacy; deviasi F2 #4 (pemilih warna bebas)' });

        const S = await bukaKonteks(B, VP.mobile); kon.push(S);
        await login(S, AKUN.superadmin);
        await S.viewport(VP.mobile); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        const sSa = await ukurKontrol(S, [NAV, 'form[action$="/respond"] button[type=submit]']); const axSa = await namaAksesibel(S, NAV);
        butir('show-superadmin-sentuh-390', sSa.every((x) => x.ada && x.w >= 44 && x.h >= 44) && axSa.axTerverifikasi && /Kembali ke Feedback Inbox/.test(axSa.nama || '') ? 'PASS' : 'FAIL', { sentuh: sSa, ax: axSa });
        await S.viewport(VP.desktop);
        const tSa = await periksaTautan(S, `/feedback/${idBaru}`, '/feedback-inbox'); butir('tautan-href-kembali-show-superadmin', tSa.ok ? 'PASS' : 'FAIL', tSa);
        await S.goto(`/feedback/${idBaru}`);
        await S.ketik('textarea[name=response_text]', 'Tanggapan QA F3 <u id="xss-t">uji</u>');
        await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
        const sr = await S.ev(`({flash:(document.querySelector('.alert-success')||{}).textContent,kartuClose:!!document.querySelector('.frmCloseFeedback'),xss:!!document.querySelector('#xss-t'),teks:document.body.innerText.includes('<u id="xss-t">')})`);
        butir('respond-ui-dan-escape', sr.flash && !sr.xss && sr.teks && sr.kartuClose ? 'PASS' : 'FAIL', sr);
        await S.viewport(VP.mobile); await S.goto(`/feedback/${idBaru}`);
        const sCl = await ukurKontrol(S, ['.frmCloseFeedback button[type=submit]']);
        butir('show-superadmin-tombol-selesai-sentuh-390', sCl[0].ada && sCl[0].w >= 44 && sCl[0].h >= 44 ? 'PASS' : 'FAIL', sCl);
        const dk = [{ nama: 'badge', sel: '.content .tw\\:rounded-pill', maks: 8 }, { nama: 'tombol-selesai', sel: '.frmCloseFeedback button' }, { nama: 'header-kartu', sel: '.content [role=heading]', maks: 6 }];
        await S.viewport(VP.desktop); await S.goto(`/feedback/${idBaru}`);
        const ks = await kontras(S, dk); R.kontrasShow = ks;
        butir('kontras-show-terang', ks.filter((x) => x.ada).length > 0 && ks.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...ks.filter((x) => x.ada).map((x) => x.rasio)), n: ks.filter((x) => x.ada).length });
        await S.setTema('gelap'); await S.goto(`/feedback/${idBaru}`);
        const kg = await kontras(S, dk);
        butir('kontras-show-gelap', kg.filter((x) => x.ada).length > 0 && kg.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...kg.filter((x) => x.ada).map((x) => x.rasio)), n: kg.filter((x) => x.ada).length });
        const ks2 = await kontrakDom(S, ['.col-md-7', '.col-md-5', 'form.frmCloseFeedback input[name=_token]', 'form[action$="/respond"] textarea[name=response_text][required]', 'table.table-sm', '[role=heading][aria-level="2"]']);
        butir('kontrak-dom-show', ks2.every((k) => k.n >= 1) ? 'PASS' : 'FAIL', ks2);
        await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        await S.klik('.frmCloseFeedback button[type=submit]'); await S.tunggu('.swal2-confirm'); await sleep(500);
        const nc = await S.aksiNavigasi(() => S.klik('.swal2-confirm'));
        butir('close-swal-ui', nc && (await S.ev(`!document.querySelector('.frmCloseFeedback')`)) ? 'PASS' : 'FAIL', { navigasi: nc });
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto('/feedback-inbox');
        const ib = await S.ev(`({wrapper:!!document.querySelector('.dataTables_wrapper'),paginasi:!!document.querySelector('.dataTables_paginate'),panjang:!!document.querySelector('.dataTables_length select'),tabel:document.querySelector('#tblFeedbackInbox').className,badgeTw:document.querySelectorAll('#tblFeedbackInbox .tw\\\\:rounded-pill').length,badgeLegacy:document.querySelectorAll('#tblFeedbackInbox .badge').length,twCss:[...document.styleSheets].filter((s)=>s.href&&s.href.includes('tesys-tw.css')).length})`);
        butir('inbox-datatables-badge-tw', ib.wrapper && ib.paginasi && ib.panjang && ib.badgeTw > 0 && ib.badgeLegacy === 0 && ib.twCss === 1 && /dataTable/.test(ib.tabel) ? 'PASS' : 'FAIL', ib);
        const ki2 = await kontras(S, [{ nama: 'badge', sel: '#tblFeedbackInbox .tw\\:rounded-pill', maks: 12 }]);
        butir('kontras-inbox-terang', ki2.filter((x) => x.ada).length > 0 && ki2.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...ki2.filter((x) => x.ada).map((x) => x.rasio)), n: ki2.filter((x) => x.ada).length });
    } catch (e) { R.galat.push('FATAL: ' + e.message); console.error(e); }
    for (const p of kon) await tutupKonteks(B, p);
    return R;
}

// ---------------------------------------------------------------------------
// DIAGNOSTIK ULANG (hasil-diag-firefox.json): fokus papan ketik dengan Tab MURNI, XSS index/show, jaringan
// ---------------------------------------------------------------------------
/** Dijalankan DI HALAMAN. buatH = dalamHalaman. rinci=false => hanya identitas + status fokus (elemen di luar .content). */
function infoFokusDiHalaman(buatH, rinci) {
    const e = document.activeElement;
    if (!e || e === document.body) return { el: 'body', hasFocus: document.hasFocus() };
    const H = buatH();
    const s = getComputedStyle(e);
    const dalamContent = !!e.closest('.content');
    const id = e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.name ? '[name=' + e.name + ']' : '');
    if (!rinci) return { el: id, dalamContent, hasFocus: document.hasFocus(), fokus: e.matches(':focus'), fokusVisible: e.matches(':focus-visible'), outlineStyle: s.outlineStyle, outlineWidth: s.outlineWidth };
    const par = H.bgEfektif(e.parentElement || e);
    const oc = H.campur(H.rgba(s.outlineColor), par);
    const lebarPx = parseFloat(s.outlineWidth) || 0;
    const rasio = Math.round(H.rasio(oc, par) * 100) / 100;
    const info = {
        el: id, tw: /(^|\s)tw:/.test(String(e.className)), teks: (e.getAttribute('aria-label') || e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30), dalamContent,
        hasFocus: document.hasFocus(), visibilitas: document.visibilityState,
        fokus: e.matches(':focus'), fokusVisible: e.matches(':focus-visible'),
        outlineStyle: s.outlineStyle, outlineWidth: s.outlineWidth, outlineColor: s.outlineColor, outlineOffset: s.outlineOffset,
        boxShadow: s.boxShadow === 'none' ? 'none' : s.boxShadow.slice(0, 80), radius: s.borderTopLeftRadius,
        warnaIndikator: H.hex(oc), latarInduk: H.hex(par), rasio, terlihat: s.outlineStyle !== 'none' && lebarPx > 0 && rasio >= 3,
    };
    // Aturan CSS yang memuat :focus/:focus-visible dan menyentuh outline/radius pada elemen ini. cocokAsli = selektor apa adanya
    // cocok SAAT INI; cocokTanpaPseudo = selektor cocok bila :focus(-visible) diabaikan (aturan yang akan berlaku bila pseudo cocok).
    const aturan = [];
    const jalan = (rules, lapis) => {
        for (const r of rules) {
            if (!r.selectorText) { if (r.cssRules) jalan(r.cssRules, r.name || r.conditionText || lapis); continue; }
            if (!/:focus/.test(r.selectorText)) continue;
            const st = r.style;
            const outline = ['outline', 'outline-style', 'outline-width', 'outline-color', 'outline-offset'].map((p) => (st.getPropertyValue(p) ? p + ':' + st.getPropertyValue(p) + st.getPropertyPriority(p) : '')).filter(Boolean).join(' ');
            const radius = st.getPropertyValue('border-radius');
            if (!outline && !radius) continue;
            let asli = false, tanpa = false;
            try { asli = e.matches(r.selectorText); } catch (x) { /* abaikan */ }
            try { tanpa = e.matches(r.selectorText.replace(/:focus-visible/g, '').replace(/:focus(?![-\w])/g, '')); } catch (x) { /* abaikan */ }
            if (asli || tanpa) aturan.push({ sel: r.selectorText.slice(0, 100), lapis: lapis || '-', outline: outline.slice(0, 120), radius, cocokAsli: asli, cocokTanpaPseudo: tanpa });
        }
    };
    for (const sh of document.styleSheets) { let rs; try { rs = sh.cssRules; } catch (x) { continue; } jalan(rs, ''); }
    info.aturanFokus = aturan.slice(0, 16); info.aturanFokusTotal = aturan.length;
    return info;
}
const INFO_FOKUS = (rinci) => `(${infoFokusDiHalaman.toString()})(${BANTU}, ${rinci})`;

async function bukaTab(B, userContext, viewport) {
    const { context } = await B.bidi.send('browsingContext.create', { type: 'tab', userContext });
    const T = new Hal(B, context, userContext);
    await T.viewport(viewport);
    return T;
}
async function tutupTab(B, T) { try { await B.bidi.send('browsingContext.close', { context: T.ctx }); } catch { /* abaikan */ } }
async function muatDenganUlang(T, jalur, log) {
    await T.goto(jalur);
    for (let u = 0; u < 2 && /Tidak ada koneksi/.test(await T.ev('document.title')); u++) { log.push(`ulang offline ${jalur}`); await sleep(1500); await T.goto(jalur); }
}
async function fotoDiag(T, nama, R) {
    if (R.foto.length >= (R.fotoMaks ?? 4)) return null;
    fs.mkdirSync(OUT_FOTO, { recursive: true });
    const { data } = await T.b.send('browsingContext.captureScreenshot', { context: T.ctx, origin: 'viewport' });
    const f = path.join(OUT_FOTO, nama + '.png'); fs.writeFileSync(f, Buffer.from(data, 'base64'));
    const rel = path.relative(AKAR, f).replace(/\\/g, '/'); R.foto.push(rel); return rel;
}

/** Tab MURNI (input.performActions Tab/Shift+Tab; tanpa .focus() skrip, tanpa klik) dari awal dokumen sampai tombol kirim, lalu Shift+Tab 4x. */
async function tabMurni(T, { fotoPrefix = null, R }) {
    const luar = { jumlah: 0, fokusVisibleTrue: 0, fokusVisibleFalse: 0, contohFalse: [] };
    const maju = [], balik = [];
    const awal = await T.ev(`({aktif:document.activeElement===document.body?'body':document.activeElement.tagName,hasFocus:document.hasFocus(),visibilitas:document.visibilityState})`);
    let bodyBeruntun = 0, selesai = false, langkah = 0;
    const ambil = async (arah, daftar) => {
        langkah++;
        await T.tab(arah === 'balik'); await sleep(130);
        const ringan = await T.ev(INFO_FOKUS(false));
        if (ringan.el === 'body') { daftar.push({ langkah, ...ringan }); bodyBeruntun++; return ringan; }
        bodyBeruntun = 0;
        if (!ringan.dalamContent) {
            luar.jumlah++; if (ringan.fokusVisible) luar.fokusVisibleTrue++; else { luar.fokusVisibleFalse++; if (luar.contohFalse.length < 5) luar.contohFalse.push({ langkah, ...ringan }); }
            return ringan;
        }
        await sleep(450); // transisi warna outline (--dur 0,22 s) selesai
        const info = { langkah, ...(await T.ev(INFO_FOKUS(true))) };
        daftar.push(info);
        return info;
    };
    for (let i = 0; i < 150 && !selesai && bodyBeruntun < 2; i++) {
        const info = await ambil('maju', maju);
        if (!info.dalamContent) continue;
        const adaSel = await T.ev(`(()=>{const e=document.activeElement;return {nav:e.matches(${J(NAV)}),kirim:e.matches('#frmFeedback button[type=submit]'),chk:e.id==='chkWilling'}})()`);
        if (fotoPrefix && adaSel.nav) info.foto = await fotoDiag(T, `diag-fokus-${fotoPrefix}-kembali`, R);
        if (adaSel.chk) { await T.keys([{ type: 'keyDown', value: ' ' }, { type: 'keyUp', value: ' ' }]); await sleep(300); info.spasiTombolKeyboard = true; }
        if (adaSel.kirim) { if (fotoPrefix) info.foto = await fotoDiag(T, `diag-fokus-${fotoPrefix}-kirim`, R); selesai = true; }
    }
    for (let i = 0; i < 4 && selesai; i++) await ambil('balik', balik);
    const dlm = [...maju, ...balik].filter((x) => x.dalamContent);
    const gagal = dlm.filter((x) => !x.fokusVisible || !x.terlihat);
    const twEl = dlm.filter((x) => x.tw), legacy = dlm.filter((x) => !x.tw);
    return {
        awal, tabDitekan: langkah, mencapaiTombolKirim: selesai, urutanContent: maju.filter((x) => x.dalamContent).map((x) => x.el), urutanBalik: balik.map((x) => x.el),
        luarContent: luar, maju, balik,
        ringkas: {
            dalamContent: dlm.length, fokusTrue: dlm.filter((x) => x.fokus).length, fokusVisibleTrue: dlm.filter((x) => x.fokusVisible).length, outlineTerlihat: dlm.filter((x) => x.terlihat).length, minRasio: dlm.length ? Math.min(...dlm.map((x) => x.rasio)) : null,
            // Komponen Tailwind (kelas tw:) = cakupan F3; legacy (textarea .form-control, checkbox custom-control) tidak dimigrasi = temuan sisi.
            tw: { jumlah: twEl.length, elemen: [...new Set(twEl.map((x) => x.el))], fokusVisibleTrue: twEl.filter((x) => x.fokusVisible).length, outlineTerlihat: twEl.filter((x) => x.terlihat).length, minRasio: twEl.length ? Math.min(...twEl.map((x) => x.rasio)) : null, gagal: twEl.filter((x) => !x.fokusVisible || !x.terlihat).map((x) => `${x.el} fv=${x.fokusVisible} ${x.outlineStyle} ${x.outlineWidth} rasio=${x.rasio}`) },
            legacy: { jumlah: legacy.length, elemen: [...new Set(legacy.map((x) => x.el))], fokusVisibleTrue: legacy.filter((x) => x.fokusVisible).length, tanpaOutlineTerlihat: [...new Set(legacy.filter((x) => !x.terlihat).map((x) => `${x.el} fv=${x.fokusVisible} outline=${x.outlineStyle} ${x.outlineWidth} rasioOutline=${x.rasio} boxShadow=${x.boxShadow.slice(0, 60)}`))] },
            gagal: gagal.map((x) => `${x.el} fv=${x.fokusVisible} ${x.outlineStyle} ${x.outlineWidth} rasio=${x.rasio}`),
        },
    };
}

async function bagianDiagFokus(B) {
    const R = { urutanEksekusi: [], jalankan: [], foto: [], galat: [], ulangOffline: [], login: null };
    const L = await bukaKonteks(B, VP.desktop);
    try {
        R.login = await login(L, AKUN.staff);
        if (!R.login.berhasil) throw new Error('LOGIN GAGAL (' + J(R.login) + '); berhenti, tidak diulang.');
        // [tema, aksen (null = bawaan), mode]
        const RUNS = [['gelap', null, 'tab-murni', 'gelap-bawaan'], ['terang', null, 'tab-murni', 'terang-bawaan'], ['gelap', null, 'lama-focus-skrip', null], ['terang', null, 'lama-focus-skrip', null],
            ['gelap', '#92232b', 'tab-murni', null], ['terang', '#92232b', 'tab-murni', null], ['gelap', '#0f6e66', 'tab-murni', null], ['terang', '#0f6e66', 'tab-murni', null], ['gelap', null, 'tab-murni', null]];
        for (const [no, [tema, aksen, mode, fotoPrefix]] of RUNS.entries()) {
            const T = await bukaTab(B, L.userContext, VP.desktop); // konteks browsing BARU (tab baru) per pengukuran
            const ket = `${no + 1}. ${tema} aksen=${aksen || 'bawaan'} mode=${mode}${no === RUNS.length - 1 ? ' (ULANG gelap-bawaan di akhir: cek efek urutan)' : ''}`;
            R.urutanEksekusi.push(ket); console.log('[diag-fokus] ' + ket);
            try {
                await T.goto('/feedback/create');
                await T.ev(`(()=>{localStorage.setItem('tesys-theme',${J(tema === 'gelap' ? 'dark' : 'light')});${aksen ? `localStorage.setItem('tesys-accent',${J(aksen)})` : `localStorage.removeItem('tesys-accent')`}})()`);
                await muatDenganUlang(T, '/feedback/create', R.ulangOffline);
                const lingk = await T.ev(`({tema:document.documentElement.getAttribute('data-theme'),accent:getComputedStyle(document.documentElement).getPropertyValue('--accent').trim(),hasFocus:document.hasFocus(),visibilitas:document.visibilityState,zoom:getComputedStyle(document.documentElement).zoom,innerWidth:window.innerWidth,title:document.title})`);
                let hasil;
                if (mode === 'tab-murni') hasil = await tabMurni(T, { fotoPrefix, R });
                else { // metode LAMA runner: klik skrip chkWilling, Tab sekali, lalu .focus() skrip pada tautan, lalu Tab
                    await T.ev(`document.querySelector('#chkWilling').click()`); await sleep(200);
                    const urut = await urutanTab(T, NAV, 12);
                    const tw = urut.filter((u) => ['a', 'select#selCategory[name=category]', 'input#title[name=title]', 'input#inpWhatsapp[name=whatsapp_number]'].includes(u.el));
                    hasil = { urut, ringkas: { elemen: urut.map((u) => `${u.el} fv=${u.fokusVisible} ${u.outline}`), twFokusVisibleTrue: tw.filter((u) => u.fokusVisible).length, twTotal: tw.length } };
                }
                R.jalankan.push({ no: no + 1, tema, aksenDiminta: aksen || 'bawaan', mode, lingkungan: lingk, ...hasil });
            } catch (e) { R.galat.push(`${ket}: ${e.message}`); }
            await tutupTab(B, T);
        }
        await L.ev(`(()=>{localStorage.removeItem('tesys-accent');localStorage.setItem('tesys-theme','light')})()`).catch(() => {});
    } catch (e) { R.galat.push('FATAL: ' + e.message); console.error(e.message); }
    await tutupKonteks(B, L);
    return R;
}

/**
 * Jaringan: navigasi baca-saja (tanpa menulis ke DB uji) meniru urutan runner halaman (tiap URL dimuat 4x: tema x viewport) untuk
 * staff dan superadmin, lalu menyajikan SEMUA fetchError dengan stempel waktu + URL dokumen aktif dan klasifikasi jendela navigasi.
 */
async function bagianDiagJaringan(B) {
    const R = { login: {}, idDipakai: null, sapuan: [], galat: [], ringkas: null };
    const K1 = await bukaKonteks(B, VP.desktop); const K2 = await bukaKonteks(B, VP.desktop);
    try {
        R.login.staff = await login(K1, AKUN.staff);
        if (!R.login.staff.berhasil) throw new Error('LOGIN staff GAGAL (' + J(R.login.staff) + '); berhenti, tidak diulang.');
        R.login.superadmin = await login(K2, AKUN.superadmin);
        if (!R.login.superadmin.berhasil) throw new Error('LOGIN superadmin GAGAL (' + J(R.login.superadmin) + '); berhenti, tidak diulang.');
        await K1.goto('/feedback');
        R.idDipakai = await K1.ev(`(()=>{const a=document.querySelector('.ts-action-primary');const m=a&&a.getAttribute('href').match(/feedback\\/(\\d+)/);return m?Number(m[1]):null})()`);
        if (!R.idDipakai) throw new Error('Id submission tidak ditemukan di /feedback.');
        const id = R.idDipakai;
        const langkah = [[K1, 'staff', '/feedback'], [K1, 'staff', '/feedback/create'], [K1, 'staff', `/feedback/${id}`], [K2, 'superadmin', '/feedback-inbox'], [K2, 'superadmin', `/feedback/${id}`], [K2, 'superadmin', `/feedback/${id}`]];
        for (const [K, akun, jalur] of langkah) for (const tema of TEMA) for (const vpN of Object.keys(VP)) {
            const v = VP[vpN];
            try {
                await K.viewport(v); await K.setTema(tema);
                await K.goto(jalur);
                const ulang = []; for (let u = 0; u < 2 && /Tidak ada koneksi/.test(await K.ev('document.title')); u++) { ulang.push(u + 1); await sleep(1500); await K.goto(jalur); }
                const status = jalur === `/feedback/${id}` && akun === 'superadmin' ? await K.ev(`({kartuRespond:!!document.querySelector('form[action$="/respond"]'),kartuClose:!!document.querySelector('.frmCloseFeedback')})`) : null;
                R.sapuan.push({ akun, jalur, tema, viewport: v.id, title: await K.ev('document.title'), ulangOffline: ulang, status, gagal: K.gagalDetail() });
            } catch (e) { R.galat.push(`${akun} ${jalur} ${tema} ${v.id}: ${e.message}`); }
        }
        const semua = R.sapuan.flatMap((s) => s.gagal.map((g) => ({ halaman: `${s.akun} ${s.jalur} ${s.tema} ${s.viewport}`, ...g })));
        const luar = semua.filter((g) => !g.dalamJendelaNavigasi);
        R.ringkas = { muatan: R.sapuan.length, totalFetchError: semua.length, tersaringDalamJendelaNavigasi: semua.length - luar.length, diLuarJendela: luar, perDest: semua.reduce((a, g) => { a[g.dest] = (a[g.dest] || 0) + 1; return a; }, {}) };
    } catch (e) { R.galat.push(e.message); console.error(e.message); }
    await tutupKonteks(B, K1); await tutupKonteks(B, K2);
    R.butir = [{ id: 'F-jaringan-abort-dalam-jendela-navigasi', status: R.galat.length === 0 && R.ringkas && R.ringkas.diLuarJendela.length === 0 ? 'PASS' : 'FAIL', detail: R.ringkas }];
    return R;
}

/** Escape XSS saja (index dan show terpisah) + 1 screenshot show. Menulis 1 submission uji ke DB uji. */
async function bagianQaXss(B) {
    const R = { butir: [], galat: [], foto: [], fotoMaks: 1, login: null };
    const butir = (id, status, detail) => { R.butir.push({ id, status, detail }); console.log(`[firefox] ${status.padEnd(7)} ${id}`); };
    const P = await bukaKonteks(B, VP.desktop);
    try {
        R.login = await login(P, AKUN.staff);
        if (!R.login.berhasil) throw new Error('LOGIN GAGAL (' + J(R.login) + '); berhenti, tidak diulang.');
        R.idBaru = (await qaXss(P, butir, { foto: R })).idBaru;
    } catch (e) { R.galat.push(e.message); console.error(e.message); }
    await tutupKonteks(B, P);
    return R;
}

/** Diagnosis login TANPA mengirim form (tidak menambah hitungan throttle): ketik lalu laporkan nilai field. */
async function diagLogin(B) {
    const P = await bukaKonteks(B, VP.desktop);
    try {
        await P.goto('/login'); console.log('tunggu', await P.tunggu('#username'));
        await P.ketik('#username', 'dummy.staff');
        await P.klik('#password'); await P.teks('abcdef');
        console.log('diag', J(await P.ev(`({u:document.querySelector('#username').value,pwLen:document.querySelector('#password').value.length,foc:document.activeElement.id,form:document.querySelector('form')&&document.querySelector('form').outerHTML.slice(0,300)})`)));
        await P.ev(`document.querySelector('#username').focus()`); await P.teks('x');
        console.log('diag2', J(await P.ev(`document.querySelector('#username').value`)));
        // Enter pada form KOSONG (ditolak validasi server sebelum autentikasi): apakah Enter mengirim form?
        await P.goto('/login'); await P.tunggu('#username'); await P.klik('#password');
        const nav = await P.aksiNavigasi(() => P.enter(), 8000);
        console.log('diagEnter', J({ nav, url: await P.ev('location.pathname'), galat: await P.ev(`(document.querySelector('.auth-alert-danger')||{}).textContent`) }));
    } catch (e) { console.error('diag gagal:', e.message); }
    await tutupKonteks(B, P);
}

async function smoke(B) {
    const R = { butir: [], galat: [] };
    const P = await bukaKonteks(B, VP.desktop);
    try {
        const l = await login(P, AKUN.staff); console.log('login', J(l));
        await P.setTema('terang'); await P.goto('/feedback');
        const m = await P.ukur();
        console.log(J({ http: m.httpDokumen, title: m.title, zoom: m.zoomHtml, inner: m.innerWidth, tw: m.twLinkCount, urut: m.urutanTwOk, fokus: m.fokusTabPertama, konsol: m.konsol.length, favicon: m.favicon }));
        const ax = await namaAksesibel(P, NAV); console.log(J(ax));
        console.log(J(await P.ev(`({cm:CSS.supports('color','color-mix(in srgb, red, blue)'),fv:CSS.supports('selector(:focus-visible)'),z:getComputedStyle(document.documentElement).zoom})`)));
    } catch (e) { console.error('smoke gagal:', e.message); R.galat.push(e.message); }
    await tutupKonteks(B, P);
    return R;
}

// ---------------------------------------------------------------------------
async function main() {
    const ref = (() => { try { return JSON.parse(fs.readFileSync(path.join(OUT_ROOT, 'hasil-href-chrome.json'), 'utf8')); } catch { return null; } })();
    const ringkasPerintah = `node scripts/tailwind-harness/bidi-firefox.mjs --bagian=${BAGIAN.join(',')} --base=${BASE} --port=${PORT} --user-superadmin=${AKUN.superadmin.username} (kata sandi via env TESYS_PW_STAFF / TESYS_PW_SUPERADMIN)`;
    let kode = 0;
    for (const bagian of BAGIAN) {
        console.log(`[firefox] mulai bagian=${bagian}`);
        let B;
        try { B = await mulaiFirefox(); } catch (e) { console.error('start gagal:', e.message); kode = 1; continue; }
        const meta = { browser: 'firefox', versiBrowser: B.versi, userAgent: B.caps.userAgent, buildID: B.caps['moz:buildID'], paketAppx: versiAppx(), driver: 'WebDriver BiDi bawaan Firefox (Remote Agent, ws://127.0.0.1:' + PORT + '/session); tanpa geckodriver', headless: B.headless, perintahStart: B.perintah, perintahUji: ringkasPerintah, base: BASE, waktu: new Date().toISOString(), akunUji: { superadmin: AKUN.superadmin.username, staff: AKUN.staff.username }, setup: { pidAlias: B.pidAlias, pidProsesUtama: B.mozPid, profilSesiCocokProfilSementara: B.profilCocok, pidBukanRunnerSebelum: B.userSebelum }, emulasi: 'browsingContext.setViewport (lebar x tinggi, DPR 1); TANPA emulasi sentuh/seluler; input = input.performActions (mouse/keyboard sintetis)', acuanChrome: ref ? 'hasil-href-chrome.json' : null };
        let hasil, berkas;
        const DIAG = { 'diag-fokus': bagianDiagFokus, 'qa-xss': bagianQaXss, 'diag-jaringan': bagianDiagJaringan };
        if (DIAG[bagian]) {
            const Rd = await DIAG[bagian](B); Rd.navigasiDiulang = B.navDiulang || [];
            const pen = await hentikanFirefox(B);
            const berkasDiag = path.join(OUT_ROOT, 'hasil-diag-firefox.json');
            let semua = {}; try { semua = JSON.parse(fs.readFileSync(berkasDiag, 'utf8')); } catch { /* baru */ }
            semua.bagian = { ...(semua.bagian || {}), [bagian]: { ...meta, hasil: Rd, penghentian: pen } };
            fs.mkdirSync(OUT_ROOT, { recursive: true });
            fs.writeFileSync(berkasDiag, J(semua, null, 1));
            console.log(`[firefox] ${bagian}: galat=${J(Rd.galat)} butir=${(Rd.butir || []).map((x) => x.id + '=' + x.status).join(',')} -> ${path.relative(AKAR, berkasDiag)}`);
            console.log(`[firefox] penghentian: ${J(pen)}`);
            if (Rd.galat.length || pen.sisaProsesPenanda !== 0 || !pen.profilDihapus || !pen.pidPenggunaTakBerubah) kode = 1;
            continue;
        }
        if (bagian === 'diag') { await diagLogin(B); hasil = null; }
        else if (bagian === 'smoke') { await smoke(B); hasil = null; }
        else if (bagian === 'halaman') {
            const R = await bagianHalaman(B);
            hasil = { ...meta, label: 'firefox', ...R, butir: butirHalaman(R, ref) };
            berkas = path.join(OUT_ROOT, 'hasil-firefox.json');
        } else if (bagian === 'qa') {
            const R = await bagianQa(B);
            hasil = { ...meta, label: 'qa-firefox', ...R };
            berkas = path.join(OUT_ROOT, 'hasil-qa-firefox.json');
        }
        const pen = await hentikanFirefox(B);
        if (hasil) {
            hasil.penghentian = pen; hasil.navigasiDiulang = B.navDiulang || [];
            const gagal = hasil.butir.filter((x) => x.status === 'FAIL').length;
            hasil.ringkas = { PASS: hasil.butir.filter((x) => x.status === 'PASS').length, FAIL: gagal, INFO: hasil.butir.filter((x) => x.status === 'INFO').length, NOT_RUN: hasil.butir.filter((x) => x.status === 'NOT RUN').length, galatRunner: (hasil.galatRunner || hasil.galat || []).length };
            fs.mkdirSync(OUT_ROOT, { recursive: true });
            fs.writeFileSync(berkas, J(hasil, null, 1));
            console.log(`[firefox] ${bagian}: ${J(hasil.ringkas)} -> ${path.relative(AKAR, berkas)}`);
            if (gagal || hasil.ringkas.galatRunner) kode = 1;
        }
        console.log(`[firefox] penghentian: ${J(pen)}`);
        if (pen.sisaProsesPenanda !== 0 || !pen.profilDihapus || !pen.pidPenggunaTakBerubah) kode = 1;
    }
    process.exit(kode);
}
main();

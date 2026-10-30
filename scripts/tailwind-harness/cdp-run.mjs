/**
 * Runner CDP (Chrome DevTools Protocol) untuk galeri komponen Tailwind (x-tw.*).
 *
 * Node 22 (WebSocket + fetch global), TANPA npm/instal. Meluncurkan SATU browser
 * headless milik runner ini sendiri (port debug khusus, --user-data-dir sementara
 * baru), menjalankan matriks pengukuran pada galeri statis hasil render.php, lalu
 * mematikan HANYA proses yang diluncurkan sendiri (PID dicatat; tidak menyentuh
 * proses browser/PHP/MySQL lain).
 *
 * Prasyarat: php scripts/tailwind-harness/render.php (galeri-*.html ada di
 * tools/tailwind/uji/) dan public/css/tesys-tw.css sudah dibangun.
 *
 * Pakai (dari akar proyek; jalur node di bawah hanya CONTOH — cari lewat `Get-Command node`):
 *   C:\laragon\bin\nodejs\node-v22\node.exe scripts/tailwind-harness/cdp-run.mjs [opsi]
 *     --browser=chrome|edge|chrome,edge   (bawaan: chrome,edge — berurutan)
 *     --out=docs/ui/tailwind-evidence      (folder hasil-*.json + png)
 *     --bagian=iso,token,...               (bawaan: semua; lihat BAGIAN di bawah)
 *     --foto=chrome                        (browser yang mengambil screenshot; bawaan chrome)
 *     --chrome=<path> --edge=<path>        (menimpa lokasi bawaan)
 *
 * Kode keluar: 0 bila runner selesai (status tiap butir ada di JSON; butir FAIL
 * pengukuran TIDAK membuat kode keluar non-nol), 1 bila runner sendiri gagal ATAU
 * proses browser milik runner masih tersisa setelah penghentian (butir G0-proses-bersih).
 *
 * Keselamatan proses: jangan menjalankan chrome.exe/msedge.exe langsung (termasuk
 * --version; dapat membuka tab di sesi pengguna) dan jangan memakai python/python3
 * (stub Windows Store menggantung). Versi berkas dibaca lewat PowerShell VersionInfo.
 */

import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const AKAR_URL = 'file:///' + AKAR.replace(/\\/g, '/');
const UJI_URL = AKAR_URL + '/tools/tailwind/uji';

const arg = (nama, bawaan) => {
    const a = process.argv.find((x) => x.startsWith('--' + nama + '='));
    return a ? a.slice(nama.length + 3) : bawaan;
};
const OUT = path.resolve(AKAR, arg('out', 'docs/ui/tailwind-evidence'));
const DAFTAR_BROWSER = arg('browser', 'chrome,edge').split(',').filter(Boolean);
const BAGIAN = arg('bagian', 'iso,token,kaskade,fokus,keyboard,aria,modal,sentuh,zoom,responsif,kontras,kartu,lain,landmark,tautan').split(',');
const FOTO = arg('foto', 'chrome').split(',');
const EXE = {
    chrome: arg('chrome', 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'),
    edge: arg('edge', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe'),
};
const PORT = { chrome: 9333, edge: 9334 };

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sha = (x) => crypto.createHash('sha256').update(typeof x === 'string' ? x : JSON.stringify(x)).digest('hex');

// ---------------------------------------------------------------------------
// Matriks
// ---------------------------------------------------------------------------
const AKSEN_KUSTOM = '#0f6e66'; // Teal (praset profil)
const PRASET = ['#92232b', '#1f3a5f', '#0f6e66', '#2f6d43', '#3d3a8c', '#3f4a59', '#9a4a23', '#6a2a63'];
const KEADAAN = [
    { id: 'terang-bawaan', tema: 'terang', aksen: null },
    { id: 'gelap-bawaan', tema: 'gelap', aksen: null },
    { id: 'terang-kustom', tema: 'terang', aksen: AKSEN_KUSTOM },
    { id: 'gelap-kustom', tema: 'gelap', aksen: AKSEN_KUSTOM },
];
const VP = {
    '360x740': { w: 360, h: 740, mobile: true },
    '390x844': { w: 390, h: 844, mobile: true },
    '768x1024': { w: 768, h: 1024, mobile: true },
    '1024x768': { w: 1024, h: 768, mobile: false },
    '1366x768': { w: 1366, h: 768, mobile: false },
    '1920x1080': { w: 1920, h: 1080, mobile: false },
    '1280x600': { w: 1280, h: 600, mobile: false },
    '900x700': { w: 900, h: 700, mobile: false },
    '901x700': { w: 901, h: 700, mobile: false },
};

// ---------------------------------------------------------------------------
// Klien CDP
// ---------------------------------------------------------------------------
class Cdp {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.id = 0;
        this.pending = new Map();
        this.waiters = [];
        this.ws.addEventListener('message', (m) => {
            const d = JSON.parse(m.data);
            if (d.id) {
                const p = this.pending.get(d.id);
                if (!p) return;
                this.pending.delete(d.id);
                if (d.error) p.rej(new Error(`${p.method}: ${d.error.message}`));
                else p.res(d.result);
            } else {
                this.waiters = this.waiters.filter((w) => {
                    if (w.method === d.method && (!w.sessionId || w.sessionId === d.sessionId) && (!w.pred || w.pred(d.params))) {
                        w.res(d.params);
                        return false;
                    }
                    return true;
                });
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
    waitEvent(method, sessionId, timeout = 15000, pred) {
        return new Promise((res, rej) => {
            const w = { method, sessionId, pred, res };
            this.waiters.push(w);
            setTimeout(() => {
                if (this.waiters.includes(w)) {
                    this.waiters = this.waiters.filter((x) => x !== w);
                    rej(new Error('timeout menunggu ' + method));
                }
            }, timeout);
        });
    }
    close() { try { this.ws.close(); } catch { /* abaikan */ } }
}

// ---------------------------------------------------------------------------
// Peluncuran / penghentian browser (hanya milik sendiri)
// ---------------------------------------------------------------------------
function versiBerkas(exe) {
    // jalur disisipkan ke string PowerShell berkutip tunggal: kutip tunggal akan memutus kutipan
    if (exe.includes("'")) return 'ditolak: jalur memuat tanda kutip tunggal';
    try {
        return execFileSync('powershell', ['-NoProfile', '-Command', `(Get-Item -LiteralPath '${exe}').VersionInfo.ProductVersion`], { encoding: 'utf8' }).trim();
    } catch { return 'tidak diketahui'; }
}

function prosesDenganPenanda(penanda) {
    try {
        // $PID dikecualikan: proses powershell pemeriksa ini sendiri memuat penanda di command line-nya
        const cmd = `@(Get-CimInstance Win32_Process | Where-Object { $_.ProcessId -ne $PID -and $_.CommandLine -like '*${penanda}*' }).Count`;
        return Number(execFileSync('powershell', ['-NoProfile', '-Command', cmd], { encoding: 'utf8' }).trim());
    } catch { return -1; }
}

async function portBebas(port) {
    try {
        await fetch(`http://127.0.0.1:${port}/json/version`, { signal: AbortSignal.timeout(1500) });
        return false;
    } catch { return true; }
}

async function mulaiBrowser(nama) {
    const exe = EXE[nama];
    if (!fs.existsSync(exe)) throw new Error(`Browser ${nama} tidak ditemukan: ${exe}`);
    const port = PORT[nama];
    if (!(await portBebas(port))) throw new Error(`Port ${port} sudah dipakai proses lain; runner berhenti (tidak menyentuh proses itu).`);
    const userDir = fs.mkdtempSync(path.join(os.tmpdir(), `tesys-cdp-${nama}-`));
    const args = [
        '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${userDir}`,
        // asal WebSocket dibatasi ke loopback port ini saja (bukan "*"); klien Node tidak mengirim Origin lain
        '--allow-file-access-from-files', `--remote-allow-origins=http://127.0.0.1:${port}`,
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
    B.ua = ver['User-Agent'];
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
        // hanya PID anak langsung milik runner ini (beserta anak-anaknya)
        try { execFileSync('taskkill', ['/PID', String(B.pid), '/T', '/F'], { stdio: 'ignore' }); log.taskkill = true; } catch { log.taskkill = 'gagal'; }
        for (let i = 0; i < 20 && !B.keluar; i++) await sleep(150);
    }
    await sleep(600);
    log.sisaProsesPenanda = prosesDenganPenanda(log.penanda);
    for (let i = 0; i < 10; i++) {
        try { fs.rmSync(B.userDir, { recursive: true, force: true }); break; } catch { await sleep(300); }
    }
    log.userDirDihapus = !fs.existsSync(B.userDir);
    return log;
}

// ---------------------------------------------------------------------------
// Pustaka di dalam halaman (dipasang lewat addScriptToEvaluateOnNewDocument)
// ---------------------------------------------------------------------------
function pustaka() {
    const u = (window.__u = {});
    const cvs = document.createElement('canvas');
    cvs.width = cvs.height = 1;
    const ctx = cvs.getContext('2d', { willReadFrequently: true });

    u.rgba = (c) => {
        c = String(c).trim();
        let m = c.match(/^rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/);
        if (m) {
            let a = m[4] === undefined ? 1 : (m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
            return [+m[1], +m[2], +m[3], a];
        }
        m = c.match(/^color\(srgb\s+([\d.e-]+)\s+([\d.e-]+)\s+([\d.e-]+)(?:\s*\/\s*([\d.]+%?))?\s*\)$/);
        if (m) {
            let a = m[4] === undefined ? 1 : (m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
            return [+m[1] * 255, +m[2] * 255, +m[3] * 255, a];
        }
        ctx.clearRect(0, 0, 1, 1);
        ctx.fillStyle = '#000';
        ctx.fillStyle = c;
        ctx.fillRect(0, 0, 1, 1);
        const d = ctx.getImageData(0, 0, 1, 1).data;
        return [d[0], d[1], d[2], d[3] / 255];
    };
    u.over = (fg, bg) => {
        const a = fg[3];
        return [fg[0] * a + bg[0] * (1 - a), fg[1] * a + bg[1] * (1 - a), fg[2] * a + bg[2] * (1 - a), 1];
    };
    const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    u.lum = (c) => 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]);
    u.cr = (a, b) => {
        const x = u.lum(a), y = u.lum(b);
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
    };
    // latar efektif di belakang elemen (komposit ancestor sampai buram)
    u.effBg = (el) => {
        const lapis = [];
        for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
            const c = u.rgba(getComputedStyle(n).backgroundColor);
            if (c[3] > 0) lapis.push(c);
            if (c[3] >= 1) break;
        }
        let acc = [255, 255, 255, 1];
        for (let i = lapis.length - 1; i >= 0; i--) acc = u.over(lapis[i], acc);
        return acc;
    };
    u.hex = (c) => '#' + c.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
    u.shadows = (str) => {
        const out = [];
        let depth = 0, cur = '';
        for (const ch of String(str)) {
            if (ch === '(') depth++;
            if (ch === ')') depth--;
            if (ch === ',' && depth === 0) { out.push(cur.trim()); cur = ''; } else cur += ch;
        }
        if (cur.trim()) out.push(cur.trim());
        return out.filter((s) => s && s !== 'none');
    };
    u.shadowInfo = (s) => {
        const mc = s.match(/(rgba?\([^)]*\)|color\([^)]*\))/);
        const rest = s.replace(mc ? mc[0] : '', '');
        const nums = (rest.match(/-?[\d.]+px/g) || []).map((x) => parseFloat(x));
        return { color: mc ? u.rgba(mc[0]) : null, x: nums[0], y: nums[1], blur: nums[2], spread: nums[3], inset: /inset/.test(s) };
    };
    // buang bayangan transparan (rantai ring Tailwind) lalu rapikan spasi
    u.normShadow = (str) => u.shadows(str).filter((s) => { const i = u.shadowInfo(s); return !(i.color && i.color[3] === 0); }).join(', ').replace(/\s+/g, ' ');

    u.probe = (prop, expr, ref) => {
        const p = document.createElement('div');
        p.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;display:block;border-style:solid;';
        if (ref) { const c = getComputedStyle(ref); p.style.fontSize = c.fontSize; p.style.fontFamily = c.fontFamily; }
        p.style.setProperty(prop, expr);
        document.body.appendChild(p);
        const v = getComputedStyle(p).getPropertyValue(prop);
        p.remove();
        return v;
    };

    u.check = (spec) => {
        const hasil = [];
        spec.forEach((g) => {
            const el = document.querySelector(g.sel);
            if (!el) { hasil.push({ sel: g.sel, galat: 'elemen tidak ditemukan' }); return; }
            g.rows.forEach(([prop, expr, pseudo]) => {
                const cs = getComputedStyle(el, pseudo || null);
                let got = cs.getPropertyValue(prop);
                let want;
                if (expr.startsWith('@body:')) want = getComputedStyle(document.body).getPropertyValue(prop);
                else if (expr.startsWith('@el:')) { const [alamat, p2] = expr.slice(4).split('|'); want = getComputedStyle(document.querySelector(alamat)).getPropertyValue(p2 || prop); }
                else want = u.probe(prop, expr, el);
                if (prop === 'box-shadow') { got = u.normShadow(got); want = u.normShadow(want); }
                hasil.push({ sel: g.sel + (pseudo || ''), prop, got, want, ok: got === want });
            });
        });
        return hasil;
    };

    u.quiet = () => {
        if (document.getElementById('uji-quiet')) return;
        const s = document.createElement('style');
        s.id = 'uji-quiet';
        s.textContent = '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}';
        document.head.appendChild(s);
    };

    u.snap = (daftar) => {
        const out = {};
        daftar.forEach((entri) => {
            const [sel, pseudo] = Array.isArray(entri) ? entri : [entri, null];
            const key = sel + (pseudo || '');
            const el = document.querySelector(sel);
            if (!el) { out[key] = null; return; }
            const cs = getComputedStyle(el, pseudo);
            const props = {};
            let tw = 0;
            for (let i = 0; i < cs.length; i++) {
                const n = cs[i];
                if (n.startsWith('--tw-')) { tw++; continue; }
                props[n] = cs.getPropertyValue(n);
            }
            // posisi absolut bergeser karena isi galeri di atasnya berbeda (komponen tw tak bergaya di baseline);
            // yang dibandingkan: ukuran (lebar, tinggi) dan, untuk elemen di #g-legacy, posisi relatif terhadap #g-legacy.
            const r = el.getBoundingClientRect();
            const g = document.getElementById('g-legacy');
            const gr = g ? g.getBoundingClientRect() : { x: 0, y: 0 };
            const dalam = !!el.closest('#g-legacy');
            const bulat = (x) => (x === null ? null : Math.round(x * 100) / 100);
            out[key] = { props, tw, rect: pseudo ? null : [dalam ? bulat(r.x - gr.x) : null, dalam ? bulat(r.y - gr.y) : null, bulat(r.width), bulat(r.height)] };
        });
        return out;
    };

    u.tabbables = () => {
        const sel = 'a[href],button,input,select,textarea,[tabindex]';
        return [...document.querySelectorAll(sel)].filter((e) => {
            if (e.disabled || e.getAttribute('tabindex') === '-1' || e.type === 'hidden') return false;
            if (e.closest('.modal:not(.show)')) return false;
            const cs = getComputedStyle(e);
            return e.getClientRects().length > 0 && cs.visibility !== 'hidden';
        });
    };
    u.nama = (e) => (!e || e === document.body ? 'body' : e.id || (e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.split(/\s+/)[0] : '')));

    // --- pemindaian "!" (sebelum/sesudah) ---
    // pecah daftar selektor per koma TINGKAT-ATAS (koma di dalam (), [] atau yang di-escape tidak memecah)
    u.pecahSelektor = (s) => {
        const out = [];
        let d = 0, cur = '';
        for (let i = 0; i < s.length; i++) {
            const ch = s[i];
            if (ch === '\\') { cur += ch + (s[i + 1] ?? ''); i++; continue; }
            if (ch === '(' || ch === '[') d++;
            if (ch === ')' || ch === ']') d--;
            if (ch === ',' && d === 0) { out.push(cur.trim()); cur = ''; } else cur += ch;
        }
        if (cur.trim()) out.push(cur.trim());
        return out;
    };
    u.enumerasi = () => {
        const sheet = [...document.styleSheets].find((s) => s.ownerNode && s.ownerNode.id === 'tesys-tw-css');
        if (!sheet) return { galat: 'stylesheet tw tidak ada' };
        const rules = [];
        const jalan = (list) => {
            for (const r of list) {
                if (r.selectorText) rules.push(r);
                else if (r.cssRules) jalan(r.cssRules);
            }
        };
        jalan(sheet.cssRules);
        const imp = rules.filter((r) => /!important/.test(r.cssText));
        const items = [];
        const seen = new Set();
        let n = 0;
        document.querySelectorAll('[class]').forEach((el) => {
            [...el.classList].forEach((cls) => {
                if (!cls.endsWith('!')) return;
                const esc = '.' + CSS.escape(cls);
                // Tailwind menggabungkan selektor berdeklarasi sama dengan koma (mis. _td dan _th dalam satu aturan):
                // pecah per koma tingkat-atas, lalu cocokkan SETIAP bagian (bukan hanya yang pertama).
                // Setelah awalan tidak boleh ada karakter pengenal (mencegah awalan kelas lain).
                const pasangan = [];
                imp.forEach((r) => u.pecahSelektor(r.selectorText).forEach((bagian) => {
                    if (bagian.startsWith(esc) && !/^[A-Za-z0-9_\\-]/.test(bagian.slice(esc.length))) pasangan.push([r, bagian]);
                }));
                pasangan.forEach(([r, bagian]) => {
                    let rest = bagian.slice(esc.length);
                    // buang :not(...) yang menjaga kondisi (bukan state), mis. :not(.dataTable)
                    while (/^:not\([^)]*\)/.test(rest)) rest = rest.replace(/^:not\([^)]*\)/, '');
                    const props = [...r.style].filter((p) => !p.startsWith('--'));
                    let state = null, target = null;
                    if (rest.startsWith(' ')) target = rest.trim();
                    else if (rest) state = rest.replace(/^:/, '');
                    // elemen yang belum disabled dilewati SEBELUM kunci dicatat; bila tidak, tombol aktif pertama
                    // "menghabiskan" kunci dan tombol disabled yang sebenarnya tidak pernah terukur.
                    if (state === 'disabled' && !el.disabled) return;
                    const kunci = cls + '|' + el.tagName + '|' + rest + '|' + bagian + (el.tagName === 'TABLE' ? '|' + el.id : '') + (el.closest('.modal') ? '|modal' : '');
                    if (seen.has(kunci)) return;
                    seen.add(kunci);
                    el.setAttribute('data-uji-idx', ((el.getAttribute('data-uji-idx') || '') + ' ' + n).trim());
                    let media = null;
                    for (let p = r.parentRule; p; p = p.parentRule) { if (p.conditionText) { media = p.conditionText; break; } }
                    items.push({ n, cls, tag: el.tagName.toLowerCase(), id: el.id || null, state, target, props, selBagian: bagian, decl: r.style.cssText, media, fokus: el.matches('button,input,select,textarea,a[href],[tabindex]'), modal: !!el.closest('.modal') });
                    n++;
                });
            });
        });
        return { items };
    };
    u.ukurItem = (item, mode) => {
        const el = document.querySelector(`[data-uji-idx~="${item.n}"]`);
        const tgt = item.target ? el.querySelector(item.target) : el;
        if (!tgt) return { galat: 'target tidak ada' };
        const baca = () => {
            const cs = getComputedStyle(tgt);
            const o = {};
            item.props.forEach((p) => { o[p] = cs.getPropertyValue(p); });
            return o;
        };
        if (mode === 'A') return baca();
        if (mode === 'B') { el.classList.remove(item.cls); const v = baca(); el.classList.add(item.cls); return v; }
        const sh = 'uji-sh-' + item.n;
        const st = document.createElement('style');
        st.id = sh + '-style';
        const esc = '.' + CSS.escape(item.cls);
        // hanya bagian selektor milik kelas ini (aturan gabungan _td/_th tidak boleh diganti di bagian yang salah)
        const aturan = item.selBagian.replace(esc, '.' + sh) + '{' + item.decl.replace(/\s*!important/g, '') + '}';
        st.textContent = '@layer utilities{' + (item.media ? '@media ' + item.media + '{' + aturan + '}' : aturan) + '}';
        document.head.appendChild(st);
        el.classList.remove(item.cls);
        el.classList.add(sh);
        const v = baca();
        el.classList.remove(sh);
        el.classList.add(item.cls);
        st.remove();
        return v;
    };

}

// ---------------------------------------------------------------------------
// Halaman (satu tab per browser)
// ---------------------------------------------------------------------------
class Halaman {
    constructor(cdp, sid) { this.cdp = cdp; this.sid = sid; this.vp = null; }
    send(m, p) { return this.cdp.send(m, p || {}, this.sid); }
    async ev(fn, ...args) {
        const ekspresi = typeof fn === 'function' ? `(${fn.toString()})(...${JSON.stringify(args)})` : fn;
        const r = await this.send('Runtime.evaluate', { expression: ekspresi, awaitPromise: true, returnByValue: true, userGesture: true });
        if (r.exceptionDetails) throw new Error('galat halaman: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text));
        return r.result.value;
    }
    async viewport(nama, touch) {
        const v = VP[nama];
        this.vp = nama;
        await this.send('Emulation.setDeviceMetricsOverride', { width: v.w, height: v.h, deviceScaleFactor: 1, mobile: !!v.mobile, screenWidth: v.w, screenHeight: v.h });
        await this.send('Emulation.setTouchEmulationEnabled', { enabled: touch ?? !!v.mobile, maxTouchPoints: 5 });
    }
    async buka(varian, keadaan, vpNama, opsi = {}) {
        const q = new URLSearchParams();
        if (keadaan.tema === 'gelap') q.set('tema', 'gelap');
        if (keadaan.aksen) q.set('aksen', keadaan.aksen);
        if (opsi.dt) q.set('dt', '1');
        if (opsi.polos) q.set('bungkus', 'polos');
        if (opsi.tw0) q.set('tw', '0');
        await this.viewport(vpNama, opsi.touch);
        const muat = this.cdp.waitEvent('Page.loadEventFired', this.sid, 20000);
        await this.send('Page.navigate', { url: `${UJI_URL}/galeri-${varian === 'awal' ? 'tw-awal' : varian}.html?${q.toString()}` });
        await muat;
        try {
            await this.tunggu(() => document.documentElement.getAttribute('data-galeri-siap') === '1' && !!window.__u, 8000);
        } catch (e) {
            const diag = await this.ev(() => ({ rs: document.readyState, siap: document.documentElement.getAttribute('data-galeri-siap'), u: !!window.__u, url: location.href.slice(-60), jq: typeof window.jQuery })).catch((x) => String(x));
            throw new Error('halaman tidak siap: ' + JSON.stringify(diag));
        }
        await this.ev(async () => { await document.fonts.ready; });
        await this.tunggu(() => document.getAnimations().length === 0, 4000).catch(() => {});
        await this.send('DOM.getDocument', { depth: 0 });
    }
    async tunggu(fn, timeout = 5000, interval = 40) {
        const t0 = Date.now();
        for (;;) {
            let ok = false;
            try { ok = await this.ev(fn); } catch { ok = false; }
            if (ok) return true;
            if (Date.now() - t0 > timeout) throw new Error('timeout tunggu kondisi: ' + String(fn).slice(0, 90));
            await sleep(interval);
        }
    }
    async paksa(sel, states) {
        const { result } = await this.send('Runtime.evaluate', { expression: `document.querySelector(${JSON.stringify(sel)})` });
        if (!result.objectId) throw new Error('paksa: elemen tidak ada ' + sel);
        const { nodeId } = await this.send('DOM.requestNode', { objectId: result.objectId });
        await this.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: states });
        return nodeId;
    }
    async lepas(nodeId) { await this.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] }); }
    async tombol(key, mods = 0) {
        const peta = {
            Tab: { code: 'Tab', vk: 9 },
            Enter: { code: 'Enter', vk: 13, text: '\r' },
            ' ': { code: 'Space', vk: 32, text: ' ' },
            Escape: { code: 'Escape', vk: 27 },
        }[key];
        const dasar = { key, code: peta.code, windowsVirtualKeyCode: peta.vk, nativeVirtualKeyCode: peta.vk, modifiers: mods };
        await this.send('Input.dispatchKeyEvent', { ...dasar, type: peta.text ? 'keyDown' : 'rawKeyDown', ...(peta.text ? { text: peta.text } : {}) });
        await this.send('Input.dispatchKeyEvent', { ...dasar, type: 'keyUp' });
        await sleep(40);
    }
    async klik(sel) {
        const p = await this.ev((s) => {
            const e = document.querySelector(s);
            e.scrollIntoView({ block: 'center' });
            const r = e.getBoundingClientRect();
            // getBoundingClientRect sudah dalam koordinat viewport tersaji (zoom ikut)
            return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
        }, sel);
        await this.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: p.x, y: p.y });
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: p.x, y: p.y, button: 'left', clickCount: 1 });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: p.x, y: p.y, button: 'left', clickCount: 1 });
        await sleep(40);
    }
    async ketuk(sel) {
        const p = await this.ev((s) => {
            const e = document.querySelector(s);
            e.scrollIntoView({ block: 'center' });
            const r = e.getBoundingClientRect();
            return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
        }, sel);
        await this.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: p.x, y: p.y, id: 1 }] });
        await sleep(80);
        await this.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await sleep(150);
    }
    async kotak(sel) {
        const { result } = await this.send('Runtime.evaluate', { expression: `document.querySelector(${JSON.stringify(sel)})` });
        if (!result.objectId) return null;
        const { model } = await this.send('DOM.getBoxModel', { objectId: result.objectId });
        const q = model.border;
        const xs = [q[0], q[2], q[4], q[6]], ys = [q[1], q[3], q[5], q[7]];
        return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(...xs) - Math.min(...xs), h: Math.max(...ys) - Math.min(...ys) };
    }
    async ax(sel) {
        const { result } = await this.send('Runtime.evaluate', { expression: `document.querySelector(${JSON.stringify(sel)})` });
        if (!result.objectId) return null;
        const { nodes } = await this.send('Accessibility.getPartialAXTree', { objectId: result.objectId, fetchRelatives: false });
        const n = nodes[0];
        if (!n) return null;
        const props = {};
        (n.properties || []).forEach((p) => { props[p.name] = p.value?.value; });
        return { role: n.role?.value, nama: n.name?.value ?? '', deskripsi: n.description?.value ?? '', props };
    }
    async foto(nama) {
        const { data } = await this.send('Page.captureScreenshot', { format: 'png' });
        const f = path.join(OUT, nama);
        fs.writeFileSync(f, Buffer.from(data, 'base64'));
        return path.relative(AKAR, f).replace(/\\/g, '/');
    }
}

async function buatHalaman(B) {
    const { targetId } = await B.cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await B.cdp.send('Target.attachToTarget', { targetId, flatten: true });
    const P = new Halaman(B.cdp, sessionId);
    await P.send('Page.enable');
    await P.send('Runtime.enable');
    await P.send('DOM.enable');
    await P.send('CSS.enable');
    await P.send('Accessibility.enable');
    await P.send('Emulation.setFocusEmulationEnabled', { enabled: true });
    await P.send('Page.addScriptToEvaluateOnNewDocument', { source: `(${pustaka.toString()})()` });
    return P;
}

// ---------------------------------------------------------------------------
// Pencatat butir
// ---------------------------------------------------------------------------
class Butir {
    constructor() { this.daftar = []; }
    tambah(id, judul, status, nilai, catatan) {
        this.daftar.push({ id, judul, status, nilai, catatan: catatan || undefined });
        console.log(`  [${status.padEnd(7)}] ${id} — ${judul}`);
    }
}
const st = (ok) => (ok ? 'PASS' : 'FAIL');

// ---------------------------------------------------------------------------
// Bagian 1 — ISOLASI
// ---------------------------------------------------------------------------
const ELEMEN_LEGACY = [
    'html', 'body', '.shell', '.main', '.content', '.galeri-judul',
    '#lg-btn-primary', '#lg-btn-secondary', '#lg-btn-danger', '#lg-btn-disabled', '#lg-badge', '#lg-a', '#lg-h1',
    '#lg-label', '#lg-label-in', '.invalid-feedback', '#lg-in-normal', '#lg-sel-normal',
    '#lg-card', '#lg-card .card-header', '#lg-card .card-body',
    '#lg-tabel', '#lg-tabel th', '#lg-tabel td', '#lg-tabel tbody tr',
    '#lg-tabs', '#lg-tabs .nav-link.active', '.tab-pane.active', '#lg-dropdown', '#lg-dropdown .dropdown-item', '#lg-dropdown .dropdown-item.active',
    '#lg-modal', '#lg-modal .modal-dialog', '#lg-modal .modal-content', '#lg-modal .modal-header', '#lg-modal .modal-title', '#lg-modal .close', '#lg-modal .modal-body', '#lg-modal .modal-footer .btn',
    '#lg-buka-modal-legacy',
    ['#lg-btn-primary', '::before'], ['#lg-btn-primary', '::after'], ['#lg-a', '::before'], ['#lg-card', '::before'], ['#lg-in-normal', '::placeholder'], ['#lg-dropdown .dropdown-item.active', '::before'],
];

function bedaSnap(a, b) {
    const hasil = [];
    for (const k of Object.keys(a)) {
        const x = a[k], y = b[k];
        if (!x && !y) continue;
        if (!x || !y) { hasil.push({ el: k, prop: '(ada/tidak)', a: !!x, b: !!y }); continue; }
        for (const p of Object.keys(x.props)) if (x.props[p] !== y.props[p]) hasil.push({ el: k, prop: p, a: x.props[p], b: y.props[p] });
        for (const p of Object.keys(y.props)) if (!(p in x.props)) hasil.push({ el: k, prop: p, a: undefined, b: y.props[p] });
        // html/body/.shell/.main/.content memuat seluruh galeri: tinggi/lebarnya wajar berbeda karena isi tw di baseline tak bergaya
        if (!['html', 'body', '.shell', '.main', '.content'].includes(k) && JSON.stringify(x.rect) !== JSON.stringify(y.rect)) hasil.push({ el: k, prop: '(rect)', a: x.rect, b: y.rect });
    }
    return hasil;
}

async function bagianIso(P, R, ctx) {
    console.log('> ISOLASI');
    const rincian = [];
    let semuaNol = true;
    let kontrolOk = true;
    let twDimuat = true;
    const digest = {};
    let twPropEl = 0;
    for (const vpNama of ['1366x768', '390x844']) {
        for (const k of KEADAAN) {
            const snaps = {};
            for (const varian of ['baseline', 'tw', 'awal']) {
                if (process.env.CDP_DEBUG) console.log('  iso', vpNama, k.id, varian);
                await P.buka(varian, k, vpNama);
                const meta = await P.ev(() => {
                    const l = document.getElementById('tesys-tw-css');
                    let aturan = 0, prop = 0;
                    if (l && l.sheet) {
                        const jalan = (list) => { for (const r of list) { if (r.selectorText) aturan++; if (r.cssRules) jalan(r.cssRules); if (r.constructor.name === 'CSSPropertyRule') prop++; } };
                        jalan(l.sheet.cssRules);
                    }
                    const probe = document.createElement('div');
                    document.body.appendChild(probe);
                    const twInit = getComputedStyle(probe).getPropertyValue('--tw-ring-offset-color');
                    probe.remove();
                    return { link: !!l, aturan, prop, twInit, interSiap: document.fonts.check('14px Inter') };
                });
                if (varian !== 'baseline' && (!meta.link || meta.aturan < 20)) twDimuat = false;
                snaps[varian] = await P.ev((d) => window.__u.snap(d), ELEMEN_LEGACY);
                digest[`${vpNama}/${k.id}/${varian}`] = sha(snaps[varian]).slice(0, 16);
                if (varian === 'tw') twPropEl = meta.prop;
                if (varian === 'tw' && k.id === 'terang-bawaan' && vpNama === '1366x768') ctx.metaTw = meta;
                if (varian === 'tw') {
                    // kontrol positif: CSS bocor sengaja harus terdeteksi
                    await P.ev(() => {
                        const s = document.createElement('style');
                        s.id = 'uji-bocor';
                        s.textContent = 'button{border-radius:0 !important}h1{margin-top:7px}label{margin-bottom:9px}a{color:inherit}';
                        document.head.appendChild(s);
                    });
                    const bocor = await P.ev((d) => window.__u.snap(d), ELEMEN_LEGACY);
                    const bd = bedaSnap(bocor, snaps.baseline);
                    if (bd.length < 4) kontrolOk = false;
                    rincian.push({ vp: vpNama, keadaan: k.id, kontrolPositifSelisih: bd.length });
                }
            }
            for (const par of ['tw', 'awal']) {
                const d = bedaSnap(snaps[par], snaps.baseline);
                if (d.length) semuaNol = false;
                rincian.push({ vp: vpNama, keadaan: k.id, pasangan: `${par} vs baseline`, selisih: d.length, contoh: d.slice(0, 6) });
            }
        }
    }
    R.tambah('G1-isolasi', 'Elemen legacy tak berubah dengan/ tanpa tesys-tw.css (2 posisi <link>, 4 keadaan, 2 viewport)', st(semuaNol && twDimuat), { elemenDiukur: ELEMEN_LEGACY.length, selisihNol: semuaNol, cssTwDimuat: twDimuat, propertiRegistrasi: twPropEl, digest }, semuaNol ? undefined : 'ada selisih; lihat rincian');
    R.tambah('G1-kontrol-positif', 'CSS bocor sengaja terdeteksi oleh snapshot', st(kontrolOk), { rincian: rincian.filter((x) => x.kontrolPositifSelisih !== undefined) });
    ctx.detail.isolasi = rincian.filter((x) => x.pasangan);
    ctx.detail.lingkungan = ctx.metaTw || null; // link tw dimuat, jumlah aturan/@property, font Inter siap
}

// ---------------------------------------------------------------------------
// Bagian 2 — TOKEN
// ---------------------------------------------------------------------------
// Spesifikasi token tombol; dipakai bersama oleh <button> dan <a> (x-tw.button href).
const TOKEN_UKURAN = (sz) => (sz === 'md'
    ? { fs: 'var(--fs-control)', h: 'var(--ctl-h)', px: 'var(--sp-4)' }
    : { fs: 'var(--fs-data)', h: 'var(--ctl-h-sm)', px: 'var(--sp-3)' });
const TOKEN_VARIAN = {
    primary: [['background-color', 'var(--accent)'], ['border-top-color', 'var(--accent)'], ['color', '#fff'], ['box-shadow', 'var(--shadow-accent)']],
    secondary: [['background-color', 'var(--surface)'], ['border-top-color', 'var(--border)'], ['color', 'var(--text)'], ['box-shadow', 'none']],
    danger: [['background-color', 'var(--danger-bg)'], ['border-top-color', 'var(--danger)'], ['color', 'var(--danger)'], ['box-shadow', 'none']],
    ghost: [['background-color', 'transparent'], ['border-top-color', 'transparent'], ['color', 'var(--text-dim)'], ['box-shadow', 'none']],
};
const TOKEN_HOVER = {
    primary: [['background-color', 'var(--accent-dark)'], ['border-top-color', 'var(--accent-dark)'], ['color', '#fff']],
    secondary: [['background-color', 'var(--surface-2)'], ['border-top-color', 'var(--border-strong)'], ['color', 'var(--text)']],
    danger: [['background-color', 'var(--danger)'], ['border-top-color', 'var(--danger)'], ['color', 'var(--surface)']],
    ghost: [['background-color', 'var(--surface-2)'], ['border-top-color', 'transparent'], ['color', 'var(--text)']],
};

function spesifikasiToken() {
    const s = [];
    const ukuran = TOKEN_UKURAN;
    const varian = TOKEN_VARIAN;
    for (const v of Object.keys(varian)) {
        for (const sz of ['md', 'sm']) {
            const u = ukuran(sz);
            s.push({
                sel: `#tw-btn-${v}-${sz}`,
                rows: [
                    ...varian[v],
                    ['border-top-left-radius', 'var(--radius-sm)'], ['border-top-width', '1px'], ['font-weight', 'var(--fw-label)'],
                    ['line-height', 'var(--lh-tight)'], ['font-size', u.fs], ['min-height', u.h], ['padding-left', u.px],
                    ['font-family', '@body:font-family'], ['display', 'inline-flex'], ['text-transform', 'none'],
                ],
            });
        }
        s.push({ sel: `#tw-btn-${v}-disabled`, rows: [['opacity', '.45'], ['cursor', 'not-allowed']] });
        s.push({ sel: `#tw-btn-${v}-loading`, rows: [['opacity', '.45'], ['cursor', 'progress']] });
    }
    for (const id of ['tw-in-normal', 'tw-in-hint', 'tw-sel-normal', 'tw-sel-hint']) {
        s.push({
            sel: `#${id}`,
            rows: [
                ['background-color', 'var(--surface)'], ['border-top-color', 'var(--border)'], ['color', 'var(--text)'],
                ['border-top-left-radius', 'var(--radius-sm)'], ['border-top-width', '1px'], ['height', 'var(--ctl-h)'],
                ['padding-left', 'var(--sp-3)'], ['font-size', 'var(--fs-control)'], ['font-family', '@body:font-family'],
            ],
        });
    }
    for (const id of ['tw-in-error', 'tw-sel-error']) {
        s.push({ sel: `#${id}`, rows: [['background-color', 'var(--danger-bg)'], ['border-top-color', 'var(--danger)'], ['height', 'var(--ctl-h)']] });
    }
    for (const id of ['tw-in-disabled', 'tw-sel-disabled']) {
        s.push({ sel: `#${id}`, rows: [['background-color', 'var(--surface-2)'], ['color', 'var(--text-faint)'], ['cursor', 'not-allowed']] });
    }
    s.push({ sel: '#tw-in-normal', rows: [['color', 'var(--text-faint)', '::placeholder']] });
    s.push({
        sel: '.form-group:has(> #tw-in-normal) > label',
        rows: [['font-size', '@el:#lg-label'], ['font-weight', '@el:#lg-label'], ['color', '@el:#lg-label'], ['margin-bottom', '@el:#lg-label']],
    });
    s.push({ sel: '.form-group:has(> #tw-in-normal)', rows: [['font-size', 'var(--fs-control)'], ['line-height', 'var(--lh-body)']] });
    s.push({ sel: '#tw-in-hint-hint', rows: [['font-size', 'var(--fs-caption)'], ['color', 'var(--text-dim)']] });
    s.push({ sel: '#tw-in-error-error', rows: [['font-size', 'var(--fs-caption)'], ['color', 'var(--danger)']] });
    const nada = {
        neutral: ['var(--surface-2)', 'var(--text-dim)', 'var(--border)'],
        attention: ['var(--warning-bg)', 'var(--warning)', 'transparent'],
        progress: ['var(--info-bg)', 'var(--info)', 'transparent'],
        positive: ['var(--success-bg)', 'var(--success)', 'transparent'],
        done: ['var(--darkbadge-bg)', 'var(--darkbadge)', 'transparent'],
        danger: ['var(--danger-bg)', 'var(--danger)', 'transparent'],
    };
    for (const t of Object.keys(nada)) {
        for (const sz of ['sm', 'md']) {
            s.push({
                sel: `#tw-badge-${t}-${sz}`,
                rows: [
                    ['background-color', nada[t][0]], ['color', nada[t][1]], ['border-top-color', nada[t][2]],
                    ['border-top-left-radius', 'var(--radius-pill)'], ['font-weight', 'var(--fw-heading)'],
                    ['font-size', sz === 'sm' ? 'var(--fs-caption)' : 'var(--fs-data)'],
                    ['padding-left', sz === 'sm' ? 'var(--sp-2)' : 'var(--sp-3)'], ['padding-top', 'var(--sp-1)'], ['line-height', '1'],
                ],
            });
        }
    }
    const alias = { primary: 'progress', secondary: 'neutral', success: 'positive', warning: 'attention', info: 'progress', dark: 'neutral', light: 'neutral' };
    for (const a of Object.keys(alias)) s.push({ sel: `#tw-badge-alias-${a}`, rows: [['background-color', nada[alias[a]][0]], ['color', nada[alias[a]][1]]] });
    s.push({ sel: '#tw-badge-unknown', rows: [['background-color', nada.neutral[0]], ['color', nada.neutral[1]]] });
    s.push({
        sel: '#tw-card-md',
        rows: [['background-color', 'var(--surface)'], ['border-top-color', 'var(--border)'], ['border-top-width', '1px'], ['border-top-left-radius', 'var(--radius-md)'], ['box-shadow', 'var(--shadow-xs)'], ['color', 'var(--text)']],
    });
    s.push({ sel: '#tw-card-md > div:first-child', rows: [['font-size', 'var(--fs-section)'], ['font-weight', 'var(--fw-section)'], ['padding-left', 'var(--card-pad)'], ['padding-top', 'var(--sp-3)'], ['border-bottom-color', 'var(--border)'], ['border-bottom-width', '1px'], ['color', 'var(--text)']] });
    s.push({ sel: '#tw-card-md > div:nth-child(2)', rows: [['padding-left', 'var(--card-pad)'], ['padding-top', 'var(--card-pad)']] });
    s.push({ sel: '#tw-card-sm > div:nth-child(2)', rows: [['padding-left', 'var(--card-pad-sm)'], ['padding-top', 'var(--card-pad-sm)']] });
    s.push({ sel: '#tw-card-none > div:nth-child(2)', rows: [['padding-left', '0px'], ['padding-top', '0px']] });
    s.push({ sel: '#tw-card-md > div:last-child', rows: [['font-size', 'var(--fs-caption)'], ['color', 'var(--text-dim)'], ['background-color', 'var(--surface-2)'], ['border-top-color', 'var(--border)'], ['padding-left', 'var(--card-pad)'], ['border-bottom-left-radius', 'var(--radius-md)']] });
    s.push({ sel: '#tw-tabel', rows: [['font-size', 'var(--fs-data)'], ['border-collapse', 'collapse'], ['color', 'var(--text)'], ['width', '@dummy']].filter((r) => r[1] !== '@dummy') });
    s.push({
        sel: '#tw-tabel thead th:nth-child(2)',
        rows: [
            ['font-size', 'var(--fs-th)'], ['color', 'var(--text-dim)'], ['background-color', 'var(--surface-2)'], ['padding-left', 'var(--cell-x)'], ['padding-top', 'var(--cell-y)'],
            ['border-bottom-color', 'var(--border-strong)'], ['border-bottom-width', '1px'], ['text-transform', 'none'], ['letter-spacing', 'var(--ls-micro)'], ['font-weight', 'var(--fw-heading)'], ['text-align', 'left'],
        ],
    });
    s.push({ sel: '#tw-tabel tbody tr:first-child td:nth-child(2)', rows: [['padding-left', 'var(--cell-x)'], ['padding-top', 'var(--cell-y)'], ['vertical-align', 'middle'], ['font-size', 'var(--fs-data)'], ['border-bottom-color', 'var(--border)']] });
    s.push({ sel: '#tw-tabel-rapat tbody tr:first-child td:nth-child(2)', rows: [['padding-top', 'var(--sp-1)'], ['padding-left', 'var(--cell-x)']] });
    return s;
}

function spesifikasiModal() {
    return [
        { sel: '#tw-modal-uji .modal-content > section:first-child', rows: [['padding-left', 'var(--card-pad)'], ['padding-top', 'var(--sp-3)'], ['border-bottom-color', 'var(--border)'], ['border-bottom-width', '1px'], ['display', 'flex'], ['justify-content', 'space-between']] },
        { sel: '#tw-modal-uji-title', rows: [['font-size', 'var(--fs-section)'], ['font-weight', 'var(--fw-section)'], ['color', 'var(--text)']] },
        {
            sel: '#tw-modal-uji .modal-content > section:first-child > button',
            rows: [['width', 'var(--sp-10)'], ['height', 'var(--sp-10)'], ['border-top-left-radius', 'var(--radius-sm)'], ['color', 'var(--text-dim)'], ['background-color', 'transparent'], ['font-size', 'var(--fs-sub)'], ['font-family', '@body:font-family']],
        },
        { sel: '#tw-modal-uji .modal-content > section:nth-child(2)', rows: [['padding-left', 'var(--card-pad)'], ['padding-top', 'var(--sp-4)'], ['font-size', 'var(--fs-data)'], ['color', 'var(--text)'], ['overflow-y', 'auto']] },
        { sel: '#tw-modal-uji .modal-content > section:last-child', rows: [['padding-left', 'var(--card-pad)'], ['border-top-color', 'var(--border)'], ['display', 'flex'], ['justify-content', 'flex-end'], ['gap', 'var(--sp-2)']] },
    ];
}

async function bagianToken(P, R, ctx) {
    console.log('> TOKEN');
    const dev = [];
    let total = 0;
    const perKeadaan = {};
    for (const k of KEADAAN) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        const hasil = await P.ev((s) => window.__u.check(s), spesifikasiToken());
        // hover (dipaksa lewat CSS.forcePseudoState): tombol aktif berubah sesuai token; tombol disabled TIDAK berubah
        const hov = TOKEN_HOVER;
        const hasilHover = [];
        for (const [v, rows] of Object.entries(hov)) {
            let nid = await P.paksa(`#tw-btn-${v}-md`, ['hover']);
            hasilHover.push(...(await P.ev((sp) => window.__u.check(sp).map((x) => ({ ...x, sel: x.sel + ':hover' })), [{ sel: `#tw-btn-${v}-md`, rows }])));
            await P.lepas(nid);
            const sebelum = await P.ev((id) => { const cs = getComputedStyle(document.querySelector(id)); return [cs.backgroundColor, cs.color, cs.borderTopColor].join('|'); }, `#tw-btn-${v}-disabled`);
            nid = await P.paksa(`#tw-btn-${v}-disabled`, ['hover']);
            const sesudah = await P.ev((id) => { const cs = getComputedStyle(document.querySelector(id)); return [cs.backgroundColor, cs.color, cs.borderTopColor].join('|'); }, `#tw-btn-${v}-disabled`);
            await P.lepas(nid);
            hasilHover.push({ sel: `#tw-btn-${v}-disabled:hover`, prop: 'tidak berubah saat hover', got: sesudah, want: sebelum, ok: sesudah === sebelum });
        }
        hasil.push(...hasilHover);
        // modal: buka lalu ukur
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
        const hm = await P.ev((s) => window.__u.check(s), spesifikasiModal());
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true)).modal('hide'); }));
        const semua = hasil.concat(hm);
        total += semua.length;
        const salah = semua.filter((x) => x.galat || x.ok === false);
        perKeadaan[k.id] = { cek: semua.length, menyimpang: salah.length };
        salah.forEach((x) => dev.push({ keadaan: k.id, ...x }));
    }
    // deduplikasi deviasi per (sel, prop) lintas keadaan
    const ringkas = {};
    dev.forEach((d) => {
        const key = `${d.sel}|${d.prop}`;
        (ringkas[key] ||= { sel: d.sel, prop: d.prop, got: d.got, want: d.want, galat: d.galat, keadaan: [] }).keadaan.push(d.keadaan);
    });
    const daftar = Object.values(ringkas);
    ctx.detail.tokenDeviasi = daftar;
    R.tambah('G2-token', 'Computed (warna, tepi, radius, font, min-height, padding, bayangan) = nilai token, 4 keadaan', st(daftar.length === 0), { totalCek: total, perKeadaan, jumlahDeviasi: daftar.length, deviasi: daftar.slice(0, 40) });
}

// ---------------------------------------------------------------------------
// Bagian 3 — KASKADE
// ---------------------------------------------------------------------------
async function pindaiBang(P, keadaan, vpNama, opsi, filter) {
    await P.buka('tw', keadaan, vpNama, opsi);
    await P.ev(() => window.__u.quiet());
    if (opsi.bukaModal) {
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
    }
    const { items, galat } = await P.ev(() => window.__u.enumerasi());
    if (galat) throw new Error(galat);
    const hasil = [];
    for (const item of items.filter(filter || (() => true))) {
        const keadaanUji = item.state ? [item.state] : (item.fokus ? [null, 'focus-visible'] : [null]);
        const per = {};
        let perlu = false, legacyMenang = true, contoh = null;
        for (const stt of keadaanUji) {
            let nodeId = null;
            const sel = `[data-uji-idx~="${item.n}"]`;
            const forced = stt || null;
            if (forced) nodeId = await P.paksa(sel, [forced]);
            const A = await P.ev((it) => window.__u.ukurItem(it, 'A'), item);
            const B = await P.ev((it) => window.__u.ukurItem(it, 'B'), item);
            const C = await P.ev((it) => window.__u.ukurItem(it, 'C'), item);
            if (nodeId !== null) await P.lepas(nodeId);
            const perluIni = JSON.stringify(A) !== JSON.stringify(C);
            per[forced || 'rest'] = { perlu: perluIni };
            if (perluIni) { perlu = true; if (JSON.stringify(B) !== JSON.stringify(C)) legacyMenang = false; if (!contoh) contoh = { keadaan: forced || 'rest', dengan: A, tanpaBang: C, tanpaKelas: B }; }
            else if (!contoh) contoh = { keadaan: forced || 'rest', dengan: A, tanpaBang: C, tanpaKelas: B };
        }
        hasil.push({ kelas: item.cls, tag: item.tag, modal: item.modal, id: item.id, state: item.state, target: item.target, props: item.props, per, bangPerlu: perlu, legacyMengalahkanUtilitas: perlu ? legacyMenang : null, contoh });
    }
    return hasil;
}

// SHA-256 artefak yang diuji: CSS produksi, galeri HTML yang dimuat, galeri.blade.php dan tiap komponen Blade
function hashBerkasUji() {
    const h = (f) => crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex');
    const rel = (f) => path.relative(AKAR, f).replace(/\\/g, '/');
    const peta = (dir, cocok) => Object.fromEntries(fs.existsSync(dir)
        ? fs.readdirSync(dir).filter(cocok).sort().map((n) => [rel(path.join(dir, n)), h(path.join(dir, n))]) : []);
    const css = path.join(AKAR, 'public', 'css', 'tesys-tw.css');
    return {
        cssProduksi: { berkas: rel(css), sha256: h(css), bytes: fs.statSync(css).size },
        galeriHtml: peta(path.join(AKAR, 'tools', 'tailwind', 'uji'), (n) => /^galeri-(baseline|tw|tw-awal)\.html$/.test(n)),
        galeriSumber: peta(path.join(AKAR, 'scripts', 'tailwind-harness'), (n) => n === 'galeri.blade.php'),
        komponen: peta(path.join(AKAR, 'resources', 'views', 'components', 'tw'), (n) => n.endsWith('.blade.php')),
    };
}

// Himpunan kelas ber-"!" yang ADA di artefak produksi (public/css/tesys-tw.css), dibaca dari berkasnya
// langsung (bukan dari stylesheet halaman): pembuka blok dipecah per koma tingkat-atas, tiap selektor
// yang diawali kelas .tw\:... dan berakhir "!" (setelah di-unescape) dihitung.
function kelasBangArtefak() {
    const css = fs.readFileSync(path.join(AKAR, 'public', 'css', 'tesys-tw.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
    const hasil = new Set();
    for (const m of css.matchAll(/([^{};]+)\{/g)) {
        const pembuka = m[1].trim();
        if (!pembuka || pembuka[0] === '@') continue;
        let d = 0, cur = '';
        const bagian = [];
        for (let i = 0; i < pembuka.length; i++) {
            const ch = pembuka[i];
            if (ch === '\\') { cur += ch + (pembuka[i + 1] ?? ''); i++; continue; }
            if (ch === '(' || ch === '[') d++;
            if (ch === ')' || ch === ']') d--;
            if (ch === ',' && d === 0) { bagian.push(cur.trim()); cur = ''; } else cur += ch;
        }
        if (cur.trim()) bagian.push(cur.trim());
        for (const b of bagian) {
            const k = b.match(/^\.((?:\\.|[A-Za-z0-9_-])+)/);
            if (!k) continue;
            const nama = k[1].replace(/\\(.)/g, '$1');
            if (nama.startsWith('tw:') && nama.endsWith('!')) hasil.add(nama);
        }
    }
    return hasil;
}

async function bagianKaskade(P, R, ctx) {
    console.log('> KASKADE');
    // Bagian warna bisa identik di terang-bawaan (--accent = --accent-text), jadi galeri biasa diukur
    // di terang-bawaan DAN gelap-kustom; tabel DataTables diukur di 1366 dan 390 (mode kartu).
    const gabung = [];
    for (const k of [KEADAAN[0], KEADAAN[3]]) {
        const sc = await pindaiBang(P, k, '1366x768', { bukaModal: true });
        sc.forEach((x) => gabung.push({ halaman: 'galeri 1366', keadaan: k.id, ...x }));
    }
    const scan2 = await pindaiBang(P, KEADAAN[0], '1366x768', { dt: true }, (it) => it.id === 'tw-tabel-dt');
    const scan3 = await pindaiBang(P, KEADAAN[0], '390x844', { dt: true }, (it) => it.id === 'tw-tabel-dt');
    scan2.forEach((x) => gabung.push({ halaman: 'galeri dt=1 1366', keadaan: 'terang-bawaan', ...x }));
    scan3.forEach((x) => gabung.push({ halaman: 'galeri dt=1 390', keadaan: 'terang-bawaan', ...x }));
    const pada = (x) => `${x.tag}${x.modal ? '(modal)' : ''}${x.id ? '#' + x.id : ''}${x.target ? ' ' + x.target : ''}${x.state ? ':' + x.state : ''}`;
    const ringkasKonteks = gabung.map((x) => ({
        halaman: x.halaman, keadaan: x.keadaan, kelas: x.kelas, pada: pada(x), props: x.props.length, perlu: x.bangPerlu,
        perluDi: Object.entries(x.per).filter(([, v]) => v.perlu).map(([k]) => k),
        contohDengan: Object.fromEntries(Object.entries(x.contoh.dengan).slice(0, 3)),
        contohTanpaBang: Object.fromEntries(Object.entries(x.contoh.tanpaBang).slice(0, 3)),
    }));
    // agregasi: sebuah "!" dianggap perlu bila perlu di SALAH SATU konteks (polos, DataTables, mobile, terang/gelap)
    const agregat = {};
    ringkasKonteks.forEach((x) => {
        const kunci = x.kelas + ' | ' + x.pada.replace(/#[A-Za-z0-9_-]+/, '');
        const g = (agregat[kunci] ||= { kelas: x.kelas, pada: x.pada.replace(/#[A-Za-z0-9_-]+/, ''), perluDi: new Set(), konteks: 0 });
        g.konteks++;
        if (x.perlu) g.perluDi.add(`${x.halaman}/${x.keadaan}:${x.perluDi.join('+')}`);
    });
    const daftar = Object.values(agregat).map((g) => ({ kelas: g.kelas, pada: g.pada, konteksDiukur: g.konteks, perlu: g.perluDi.size > 0, perluDi: [...g.perluDi].slice(0, 4) }));
    ctx.detail.bang = daftar;
    const tidakPerlu = daftar.filter((x) => !x.perlu);
    // kelengkapan: SETIAP kelas ber-"!" di artefak produksi harus terukur di galeri (tidak ada "!" yang lolos tanpa bukti)
    const diArtefak = kelasBangArtefak();
    const terukur = new Set(daftar.map((x) => x.kelas));
    const hilang = [...diArtefak].filter((k) => !terukur.has(k)).sort();
    ctx.detail.bangArtefak = [...diArtefak].sort();
    R.tambah('G3-bang', 'Tiap sufiks "!" diukur: hasil dengan "!" vs bentuk tanpa "!" (utilitas berlayer biasa); perlu bila perlu di salah satu konteks (polos/DataTables/mobile, terang/gelap, rest/fokus); dan semua kelas "!" di artefak produksi terukur', st(tidakPerlu.length === 0 && hilang.length === 0), {
        kelasXPada: daftar.length, perlu: daftar.length - tidakPerlu.length, tidakPerlu: tidakPerlu.map((x) => `${x.kelas} pada ${x.pada}`),
        konteksDiukur: ringkasKonteks.length,
        kelasBangDiArtefak: diArtefak.size, kelasBangTerukur: terukur.size, kelasBangTakTerukur: hilang,
    }, 'FAIL = ada "!" yang tidak diperlukan di konteks mana pun, ATAU ada kelas "!" di artefak produksi yang tidak terukur (daftar di kelasBangTakTerukur)');
}

// ---------------------------------------------------------------------------
// Bagian 4 — FOKUS (cincin tunggal, radius, kontras indikator)
// ---------------------------------------------------------------------------
const FOKUS_ELEMEN = [
    ['btn-primary', '#tw-btn-primary-md'], ['btn-secondary', '#tw-btn-secondary-md'], ['btn-danger', '#tw-btn-danger-md'], ['btn-ghost', '#tw-btn-ghost-md'],
    ['btn-sm', '#tw-btn-secondary-sm'], ['input', '#tw-in-normal'], ['input-error', '#tw-in-error'], ['select', '#tw-sel-normal'], ['select-error', '#tw-sel-error'],
    ['tutup-modal', '#tw-modal-uji .modal-content > section:first-child > button'],
];

async function bagianFokus(P, R, ctx) {
    console.log('> FOKUS');
    // (a) indikator tunggal + radius, fokus papan ketik sungguhan (Tab lalu focus()), 4 keadaan.
    //     Tutup modal diuji saat modal terbuka (jebakan fokus Bootstrap menarik fokus kembali bila modal tertutup/terbuka sebaliknya).
    const tunggal = [];
    let semuaTunggal = true, radiusOk = true;
    const ukurFokus = (s) => {
        const e = document.querySelector(s);
        const radiusRest = getComputedStyle(e).borderTopLeftRadius;
        e.focus();
        const cs = getComputedStyle(e);
        const ring = window.__u.shadows(cs.boxShadow).map(window.__u.shadowInfo).filter((i) => i.color && i.color[3] > 0 && i.spread >= 1 && !i.inset);
        const outlineAda = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
        return {
            fokusVisible: e.matches(':focus-visible'), aktif: document.activeElement === e,
            outline: `${cs.outlineStyle} ${cs.outlineWidth} offset ${cs.outlineOffset} ${window.__u.hex(window.__u.rgba(cs.outlineColor))}`, outlineAda,
            cincin: ring.length, radiusRest, radiusFokus: cs.borderTopLeftRadius, jenis: outlineAda ? 'outline' : (ring.length ? 'ring' : 'tidak ada'),
        };
    };
    for (const k of KEADAAN) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        const daftar = FOKUS_ELEMEN.filter(([n]) => n !== 'tutup-modal').concat([['legacy-btn', '#lg-buka-modal-tw']]);
        const urut = daftar.map(([nama, sel]) => ({ nama, sel, modal: false })).concat([{ nama: 'tutup-modal', sel: FOKUS_ELEMEN.find(([n]) => n === 'tutup-modal')[1], modal: true }]);
        for (const { nama, sel, modal } of urut) {
            if (modal) await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
            await P.tombol('Tab');
            const info = await P.ev(ukurFokus, sel);
            const indikator = (info.outlineAda ? 1 : 0) + (info.cincin > 0 ? 1 : 0);
            const rec = { keadaan: k.id, nama, ...info, indikator };
            tunggal.push(rec);
            if (nama !== 'legacy-btn') {
                if (!info.fokusVisible || !info.aktif || indikator !== 1) semuaTunggal = false;
                if (info.radiusRest !== info.radiusFokus) radiusOk = false;
            }
            await P.ev(() => document.activeElement && document.activeElement.blur());
        }
    }
    ctx.detail.fokusTunggal = tunggal;
    R.tambah('G3-cincin-tunggal', 'Fokus papan ketik: tepat satu indikator (outline ATAU ring, tidak ganda) pada tombol/input/select/tutup modal', st(semuaTunggal), {
        elemenXKeadaan: tunggal.filter((x) => x.nama !== 'legacy-btn').length,
        pelanggaran: tunggal.filter((x) => x.nama !== 'legacy-btn' && (!x.fokusVisible || x.indikator !== 1)).slice(0, 10),
        pembandingLegacy: tunggal.filter((x) => x.nama === 'legacy-btn' && x.keadaan === 'terang-bawaan'),
    });
    R.tambah('G3-radius-fokus', 'Radius saat fokus = radius saat istirahat (tidak berubah ke 4px legacy)', st(radiusOk), {
        contoh: tunggal.filter((x) => x.keadaan === 'terang-bawaan').map((x) => `${x.nama}: ${x.radiusRest} -> ${x.radiusFokus}`),
    });

    // (b) kontras indikator fokus: 9 aksen x 2 tema, latar bg/surface/surface-2
    const kontras = [];
    let minRing = 99, gagal = [];
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
    for (const tema of ['terang', 'gelap']) {
        for (const hex of PRASET) {
            await P.ev((t, h) => {
                document.documentElement.setAttribute('data-theme', t === 'gelap' ? 'dark' : 'light');
                window.TesysAccent.apply(h);
            }, tema, hex);
            for (const [nama, sel] of FOKUS_ELEMEN) {
                const nodeId = await P.paksa(sel, ['focus-visible']);
                const r = await P.ev((s) => {
                    const u = window.__u;
                    const e = document.querySelector(s);
                    const cs = getComputedStyle(e);
                    const ringBs = u.shadows(cs.boxShadow).map(u.shadowInfo).find((i) => i.color && i.color[3] > 0 && i.spread >= 1 && !i.inset);
                    const outlineAda = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
                    const ring = outlineAda ? { color: u.rgba(cs.outlineColor), spread: parseFloat(cs.outlineWidth) } : ringBs;
                    // latar tempat komponen lazim berada; di-resolve lewat probe agar seragam
                    const lat = ['--bg', '--surface', '--surface-2'].map((v) => u.rgba(u.probe('background-color', `var(${v})`, null)));
                    const fill = u.rgba(cs.backgroundColor);
                    const tepi = u.rgba(cs.borderTopColor);
                    const cr = ring ? lat.map((b) => u.cr(ring.color, b)) : null;
                    return {
                        jenis: outlineAda ? 'outline' : (ringBs ? 'ring' : 'tidak ada'),
                        cincin: ring ? u.hex(ring.color) : null, spread: ring ? ring.spread : null, kontrasLatar: cr ? cr.map((x) => +x.toFixed(2)) : null,
                        kontrasIsi: ring ? +u.cr(ring.color, u.over(fill, lat[1])).toFixed(2) : null, tepiFokus: u.hex(tepi),
                        kontrasTepiLatar: +Math.min(...lat.map((b) => u.cr(tepi, b))).toFixed(2),
                    };
                }, sel);
                await P.lepas(nodeId);
                const min = r.kontrasLatar ? Math.min(...r.kontrasLatar) : 0;
                if (min < minRing) minRing = min;
                const rec = { tema, aksen: hex, nama, ...r, min };
                kontras.push(rec);
                if (min < 3) gagal.push(rec);
            }
        }
    }
    // aksen ekstrem (jika pengguna memakai pemilih warna bebas)
    const ekstrem = [];
    for (const tema of ['terang', 'gelap']) {
        for (const hex of ['#ffd400', '#00e5ff', '#ff4d6d', '#111111']) {
            await P.ev((t, h) => { document.documentElement.setAttribute('data-theme', t === 'gelap' ? 'dark' : 'light'); window.TesysAccent.apply(h); }, tema, hex);
            const nodeId = await P.paksa('#tw-btn-secondary-md', ['focus-visible']);
            const r = await P.ev(() => {
                const u = window.__u; const e = document.querySelector('#tw-btn-secondary-md'); const cs = getComputedStyle(e);
                const outlineAda = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
                const ring = outlineAda ? { color: u.rgba(cs.outlineColor) } : u.shadows(cs.boxShadow).map(u.shadowInfo).find((i) => i.color && i.color[3] > 0 && i.spread >= 1);
                const lat = ['--bg', '--surface'].map((v) => u.rgba(u.probe('background-color', `var(${v})`, null)));
                return { cincin: ring ? u.hex(ring.color) : null, kontras: ring ? lat.map((b) => +u.cr(ring.color, b).toFixed(2)) : null };
            });
            await P.lepas(nodeId);
            ekstrem.push({ tema, aksen: hex, ...r });
        }
    }
    ctx.detail.fokusKontras = { ringkasPerAksen: kontras.filter((x) => x.nama === 'btn-secondary').map((x) => ({ tema: x.tema, aksen: x.aksen, cincin: x.cincin, min: +x.min.toFixed(2) })), ekstrem };
    R.tambah('G4-fokus-kontras', 'Kontras warna cincin fokus vs latar (--bg/--surface/--surface-2) >= 3:1; 8 praset aksen x terang/gelap x 10 komponen', st(gagal.length === 0), {
        minTerukur: +minRing.toFixed(2), jumlahCek: kontras.length, gagal: gagal.slice(0, 12).map((g) => `${g.tema} ${g.aksen} ${g.nama}: ${g.cincin} min ${g.min.toFixed(2)}`),
        aksenEkstremInformasi: ekstrem,
    });
    // informasi: warna indikator vs isi komponen (indikator primer terang-bawaan = warna isi; celah offset 2px memisahkannya dari tombol)
    ctx.detail.fokusIndikatorVsIsi = kontras.filter((x) => x.tema === 'terang' && x.aksen === '#92232b' && x.nama.startsWith('btn')).map((x) => `${x.nama}: ${x.jenis} ${x.cincin}, kontras vs isi ${x.kontrasIsi}`);

    // (c) forced-colors (Windows High Contrast): box-shadow tidak dilukis, jadi indikator harus berupa outline
    await P.send('Emulation.setEmulatedMedia', { features: [{ name: 'forced-colors', value: 'active' }] });
    const paksa = [];
    try {
        await P.buka('tw', KEADAAN[0], '1366x768');
        await P.ev(() => window.__u.quiet());
        const aktif = await P.ev(() => matchMedia('(forced-colors: active)').matches);
        for (const [nama, sel] of FOKUS_ELEMEN) {
            const modal = nama === 'tutup-modal';
            if (modal) await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
            await P.tombol('Tab');
            const info = await P.ev(ukurFokus, sel);
            paksa.push({ nama, aktif, ...info });
            await P.ev(() => document.activeElement && document.activeElement.blur());
        }
    } finally {
        await P.send('Emulation.setEmulatedMedia', { features: [] });
    }
    ctx.detail.fokusForcedColors = paksa;
    R.tambah('G4-fokus-forced-colors', 'forced-colors: active -> setiap kontrol tetap punya indikator fokus terlukis (outline; box-shadow tidak dilukis)', st(paksa.length > 0 && paksa.every((x) => x.aktif && x.outlineAda)), {
        diperiksa: paksa.length, tanpaOutline: paksa.filter((x) => !x.outlineAda).map((x) => `${x.nama}: ${x.jenis}`),
    });
}

// ---------------------------------------------------------------------------
// Bagian 5 — KEYBOARD / ARIA / MODAL
// ---------------------------------------------------------------------------
async function bagianKeyboard(P, R, ctx) {
    console.log('> KEYBOARD');
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    const harap = await P.ev(() => window.__u.tabbables().map(window.__u.nama));
    await P.ev(() => { document.body.focus(); window.getSelection().removeAllRanges(); });
    const aktual = [];
    for (let i = 0; i < harap.length; i++) {
        await P.tombol('Tab');
        aktual.push(await P.ev(() => window.__u.nama(document.activeElement)));
    }
    const maju = JSON.stringify(aktual) === JSON.stringify(harap);
    // Shift+Tab dari elemen terakhir mundur
    const mundur = [aktual[aktual.length - 1]];
    for (let i = 0; i < harap.length - 1; i++) {
        await P.tombol('Tab', 8);
        mundur.push(await P.ev(() => window.__u.nama(document.activeElement)));
    }
    const mundurOk = JSON.stringify(mundur) === JSON.stringify([...harap].reverse());
    const terlewat = aktual.filter((x, i) => x !== harap[i]).slice(0, 5);
    const nonaktifTerlewati = !aktual.some((n) => /disabled|loading/.test(n));
    R.tambah('G4-tab-maju', 'Tab: urutan fokus = urutan DOM (elemen disabled/loading dilewati)', st(maju && nonaktifTerlewati), { jumlahTabbable: harap.length, urutan: aktual.slice(0, 25), selisih: terlewat });
    R.tambah('G4-shift-tab', 'Shift+Tab: urutan terbalik', st(mundurOk), { jumlah: mundur.length });

    // Enter/Space pada tombol submit
    const sub = await P.ev(() => {
        window.__submits = 0;
        document.getElementById('tw-form-uji').addEventListener('submit', (e) => { window.__submits++; e.preventDefault(); });
        const b = document.getElementById('tw-btn-submit');
        b.focus();
        return window.__submits;
    });
    await P.tombol('Enter');
    const setelahEnter = await P.ev(() => window.__submits);
    await P.tombol(' ');
    const setelahSpace = await P.ev(() => window.__submits);
    // tombol di dalam form lain: tombol type=button TIDAK submit
    R.tambah('G4-enter-space', 'Enter dan Space mengaktifkan <x-tw.button type=submit>', st(sub === 0 && setelahEnter === 1 && setelahSpace === 2), { awal: sub, setelahEnter, setelahSpace });

    // Enter/Space pada tombol pemicu modal tw (x-tw.button data-toggle) lalu Escape
    await P.ev(() => document.getElementById('tw-buka-modal').focus());
    const buka = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true))));
    await P.tombol('Enter');
    await buka;
    const dalam = await P.ev(() => { const m = document.getElementById('tw-modal-uji'); return { fokusDalam: m.contains(document.activeElement), aktif: window.__u.nama(document.activeElement), tampil: m.classList.contains('show') }; });
    const tutup = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true))));
    await P.tombol('Escape');
    await Promise.race([tutup, sleep(3000)]);
    const sesudah = await P.ev(() => ({ tampil: document.getElementById('tw-modal-uji').classList.contains('show'), aktif: window.__u.nama(document.activeElement), pemicuAktif: document.activeElement === document.getElementById('tw-buka-modal') }));
    ctx.detail.modalTombolTw = { dalam, sesudah };
    R.tambah('G4-modal-papan-ketik', 'Modal dibuka dengan Enter pada x-tw.button: fokus masuk, Escape menutup, fokus kembali ke pemicu', st(dalam.fokusDalam && dalam.tampil && !sesudah.tampil && sesudah.pemicuAktif), { dalam, sesudah });
}

async function bagianAria(P, R, ctx) {
    console.log('> ARIA');
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    const sel = {
        'tombol-md': '#tw-btn-primary-md', 'tombol-loading': '#tw-btn-primary-loading', 'tombol-disabled': '#tw-btn-primary-disabled',
        'input-normal': '#tw-in-normal', 'input-hint-required': '#tw-in-hint', 'input-error': '#tw-in-error', 'input-nolabel': '#tw-in-nolabel',
        'select-normal': '#tw-sel-normal', 'select-error': '#tw-sel-error', 'tabel': '#tw-tabel', 'kolom-kode': '#tw-tabel thead th:nth-child(2)',
        'tombol-di-tabel': '#tw-tabel tbody tr:first-child button',
    };
    const ax = {};
    for (const [k, s] of Object.entries(sel)) ax[k] = await P.ax(s);
    // modal
    await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
    ax['modal'] = await P.ax('#tw-modal-uji');
    ax['modal-tutup'] = await P.ax('#tw-modal-uji .modal-content > section:first-child > button');
    ax['modal-input-label-for'] = await P.ax('#tw-modal-in');
    ax['modal-input-legacy-terhubung'] = await P.ax('#tw-modal-in-legacy');
    const dom = await P.ev(() => {
        const q = (s) => document.querySelector(s);
        const rujuk = (el) => (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).map((i) => !!document.getElementById(i));
        return {
            errInput: { invalid: q('#tw-in-error').getAttribute('aria-invalid'), describedby: q('#tw-in-error').getAttribute('aria-describedby'), rujukanAda: rujuk(q('#tw-in-error')), alert: q('#tw-in-error-error')?.getAttribute('role') },
            hintInput: { describedby: q('#tw-in-hint').getAttribute('aria-describedby'), rujukanAda: rujuk(q('#tw-in-hint')), required: q('#tw-in-hint').required },
            errSelect: { invalid: q('#tw-sel-error').getAttribute('aria-invalid'), describedby: q('#tw-sel-error').getAttribute('aria-describedby'), rujukanAda: rujuk(q('#tw-sel-error')), alert: q('#tw-sel-error-error')?.getAttribute('role') },
            loading: { busy: q('#tw-btn-primary-loading').getAttribute('aria-busy'), disabled: q('#tw-btn-primary-loading').disabled },
            labelLegacyFor: q('#tw-modal-in-legacy').labels && q('#tw-modal-in-legacy').labels.length,
            labelTwFor: q('label[for="tw-in-normal"]') ? true : false,
            modal: { role: q('#tw-modal-uji').getAttribute('role'), labelledby: q('#tw-modal-uji').getAttribute('aria-labelledby'), judulAda: !!document.getElementById(q('#tw-modal-uji').getAttribute('aria-labelledby')), ariaHidden: q('#tw-modal-uji').getAttribute('aria-hidden'), ariaModal: q('#tw-modal-uji').getAttribute('aria-modal') },
        };
    });
    ctx.detail.aria = { ax, dom };
    const cek = [
        ['tombol bernama dari isi', ax['tombol-md']?.role === 'button' && /Primary md/.test(ax['tombol-md']?.nama)],
        ['loading: aria-busy + disabled', dom.loading.busy === 'true' && dom.loading.disabled === true],
        ['input bernama dari label', ax['input-normal']?.nama === 'Nama pemohon'],
        ['input wajib: nama tanpa tanda * (aria-hidden)', ax['input-hint-required']?.nama === 'Jumlah'],
        ['input tanpa label: aria-label', ax['input-nolabel']?.nama === 'Tanpa label'],
        ['input galat: aria-invalid + deskripsi', dom.errInput.invalid === 'true' && dom.errInput.rujukanAda.every(Boolean) && /Format email/.test(ax['input-error']?.deskripsi || '')],
        ['role=alert pada pesan galat', dom.errInput.alert === 'alert' && dom.errSelect.alert === 'alert'],
        ['select galat: aria-invalid', dom.errSelect.invalid === 'true' && dom.errSelect.rujukanAda.every(Boolean)],
        ['hint dihubungkan describedby', /Isi dalam rupiah/.test(ax['input-hint-required']?.deskripsi || '') && dom.hintInput.rujukanAda.every(Boolean)],
        ['select bernama dari label', ax['select-normal']?.nama === 'Jenis pengajuan'],
        ['modal: role dialog + nama dari judul', ax['modal']?.role === 'dialog' && ax['modal']?.nama === 'Judul modal uji'],
        ['tombol tutup modal bernama "Tutup"', ax['modal-tutup']?.nama === 'Tutup'],
        ['label legacy tanpa for dihubungkan tesys-ui.js saat shown.bs.modal', dom.labelLegacyFor === 1],
        ['tabel: role table, th columnheader', ['table', 'grid'].includes(ax['tabel']?.role) && ax['kolom-kode']?.role === 'columnheader'],
    ];
    R.tambah('G4-aria', 'Nama aksesibel, aria-invalid/describedby/role=alert, aria-busy, dialog bernama, tombol tutup', st(cek.every((c) => c[1])), { cek: cek.map((c) => `${c[1] ? 'ok' : 'GAGAL'}: ${c[0]}`) });
}

async function bagianModal(P, R, ctx) {
    console.log('> MODAL');
    const cek = {};
    // A: programatik (tanpa pemicu data-toggle) di 1366x768 (zoom .8)
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    const zoom = await P.ev(() => getComputedStyle(document.documentElement).zoom);
    await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
    const fokusMasuk = await P.ev(() => { const m = document.getElementById('tw-modal-uji'); return { dalam: m.contains(document.activeElement), aktif: window.__u.nama(document.activeElement) }; });
    // trap Tab
    const trap = [];
    for (let i = 0; i < 9; i++) { await P.tombol('Tab'); trap.push(await P.ev(() => ({ n: window.__u.nama(document.activeElement), dalam: document.getElementById('tw-modal-uji').contains(document.activeElement) }))); }
    cek.trapTab = { urutan: trap.map((t) => t.n), semuaDalam: trap.every((t) => t.dalam) };
    // backdrop & ukuran
    const vm = await P.send('Page.getLayoutMetrics');
    const vv = vm.cssVisualViewport;
    const bd = await P.kotak('.modal-backdrop');
    const dlg = await P.kotak('#tw-modal-uji .modal-dialog');
    const hit = await P.ev(() => {
        const m = document.getElementById('tw-modal-uji');
        const old = m.style.pointerEvents; m.style.pointerEvents = 'none';
        const w = innerWidth, h = innerHeight;
        const pts = [[1, 1], [w - 2, 1], [1, h - 2], [w - 2, h - 2], [w / 2, 1], [w / 2, h - 2]];
        const r = pts.map(([x, y]) => { const e = document.elementFromPoint(x, y); return e && e.classList.contains('modal-backdrop'); });
        m.style.pointerEvents = old;
        return r;
    });
    cek.backdrop = { zoom, viewport: [vv.clientWidth, vv.clientHeight], kotak: bd, rasioLebar: +(bd.w / vv.clientWidth).toFixed(3), rasioTinggi: +(bd.h / vv.clientHeight).toFixed(3), sudutTertutup: hit };
    // pembanding legacy: ukuran dialog
    await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true)).modal('hide'); }));
    ctx.detail.modalProgramatik = await P.ev(() => ({ fokusSetelahTutup: window.__u.nama(document.activeElement), dibukaLewat: '.modal(show) tanpa pemicu data-toggle' }));
    await P.ev(() => new Promise((r) => { jQuery('#lg-modal').one('shown.bs.modal', () => r(true)).modal('show'); }));
    const dlgLg = await P.kotak('#lg-modal .modal-dialog');
    const kLg = await P.kotak('#lg-modal .modal-content');
    await P.ev(() => new Promise((r) => { jQuery('#lg-modal').one('hidden.bs.modal', () => r(true)).modal('hide'); }));
    cek.ukuranDialog = { tw: dlg && [Math.round(dlg.w), Math.round(dlg.h)], legacy: [Math.round(dlgLg.w), Math.round(dlgLg.h)] };
    const rasioOk = Math.abs(cek.backdrop.rasioLebar - 1) < 0.01 && Math.abs(cek.backdrop.rasioTinggi - 1) < 0.01 && hit.every(Boolean);
    const lebarSama = Math.abs(dlg.w - dlgLg.w) < 1.5;
    R.tambah('G4-modal-backdrop-zoom', 'Backdrop menutup seluruh viewport di bawah html zoom .8 (rasio 1,0), 6 titik uji tepi/sudut', st(rasioOk && zoom === '0.8'), cek.backdrop);
    R.tambah('G4-modal-ukuran', 'Lebar dialog x-tw.modal = dialog legacy pada viewport yang sama', st(lebarSama), cek.ukuranDialog);
    R.tambah('G4-modal-fokus-masuk', 'Modal terbuka: fokus masuk ke dalam modal; Tab tetap di dalam (jebakan fokus Bootstrap)', st(fokusMasuk.dalam && cek.trapTab.semuaDalam), { fokusMasuk, trap: cek.trapTab });

    // B: dibuka via klik mouse pada pemicu: Escape menutup, fokus kembali
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    const buka = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true))));
    await P.klik('#lg-buka-modal-tw');
    await buka;
    const tutup = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true))));
    await P.tombol('Escape');
    await Promise.race([tutup, sleep(3000)]);
    const kembali = await P.ev(() => ({ tampil: document.getElementById('tw-modal-uji').classList.contains('show'), pemicu: document.activeElement === document.getElementById('lg-buka-modal-tw') }));
    // klik tombol tutup (x) dan klik backdrop
    const buka2 = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true))));
    await P.klik('#lg-buka-modal-tw');
    await buka2;
    const tutup2 = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true))));
    await P.klik('#tw-modal-uji .modal-content > section:first-child > button');
    await Promise.race([tutup2, sleep(3000)]);
    const tutupX = await P.ev(() => !document.getElementById('tw-modal-uji').classList.contains('show'));
    // pemicu x-tw.button (data-toggle) via klik
    const buka3 = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true))));
    await P.klik('#tw-buka-modal');
    await buka3;
    const tutup3 = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true))));
    await P.tombol('Escape');
    await Promise.race([tutup3, sleep(3000)]);
    const kembali3 = await P.ev(() => document.activeElement === document.getElementById('tw-buka-modal'));
    R.tambah('G4-modal-escape-kembali', 'Escape menutup modal; fokus kembali ke pemicu (legacy .btn dan x-tw.button); tombol x menutup', st(!kembali.tampil && kembali.pemicu && tutupX && kembali3), { legacyPemicu: kembali, tombolX: tutupX, twPemicu: kembali3 });

    // C: viewport pendek + gulir internal (zoom aktif) dan mobile
    const pendek = {};
    for (const vpNama of ['1280x600', '1024x768', '390x844', '360x740']) {
        await P.buka('tw', KEADAAN[0], vpNama);
        await P.ev(() => window.__u.quiet());
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-tinggi').one('shown.bs.modal', () => r(true)).modal('show'); }));
        await sleep(100);
        const m = await P.ev(() => {
            const q = (s) => document.querySelector(s);
            const modal = q('#tw-modal-tinggi'), sec = q('#tw-modal-tinggi .modal-content > section:nth-child(2)');
            const awal = sec.scrollTop; sec.scrollTop = 150; const geser = sec.scrollTop; sec.scrollTop = awal;
            const mc = getComputedStyle(q('#tw-modal-tinggi .modal-content'));
            return {
                secScrollH: sec.scrollHeight, secClientH: sec.clientHeight, sectionBisaGulir: sec.scrollHeight > sec.clientHeight + 1 && geser > 0,
                modalScrollH: modal.scrollHeight, modalClientH: modal.clientHeight, modalBisaGulir: modal.scrollHeight > modal.clientHeight + 1,
                contentDisplay: mc.display, contentMaxH: mc.maxHeight, contentOverflow: mc.overflow, innerH: innerHeight, innerW: innerWidth, docScrollW: document.documentElement.scrollWidth, docClientW: document.documentElement.clientWidth,
            };
        });
        const hd = await P.kotak('#tw-modal-tinggi .modal-content > section:first-child');
        const ft = await P.kotak('#tw-modal-tinggi .modal-content > section:last-child');
        const mc = await P.kotak('#tw-modal-tinggi .modal-content');
        const vm2 = await P.send('Page.getLayoutMetrics');
        const vh = vm2.cssVisualViewport.clientHeight, vw = vm2.cssVisualViewport.clientWidth;
        pendek[vpNama] = { ...m, headerTop: Math.round(hd.y), footerBottom: Math.round(ft.y + ft.h), contentBottom: Math.round(mc.y + mc.h), contentW: Math.round(mc.w), vh, vw,
            headerFooterTerlihat: hd.y >= -0.5 && ft.y + ft.h <= vh + 0.5, lebarMuat: mc.x >= -0.5 && mc.x + mc.w <= vw + 0.5 };
        if (vpNama === '1280x600' && FOTO.includes(P.nama)) ctx.fotoModalPendek = await P.foto(`${P.nama}-modal-tinggi-1280x600.png`);
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-tinggi').one('hidden.bs.modal', () => r(true)).modal('hide'); }));
        // pembanding: modal legacy berisi panjang yang sama
        await P.ev(() => { document.querySelector('#lg-modal .modal-body').innerHTML = Array.from({ length: 40 }, (_, i) => '<div>Baris isi nomor ' + (i + 1) + '</div>').join(''); });
        await P.ev(() => new Promise((r) => { jQuery('#lg-modal').one('shown.bs.modal', () => r(true)).modal('show'); }));
        await sleep(100);
        pendek[vpNama].legacy = await P.ev(() => {
            const modal = document.getElementById('lg-modal'), body = modal.querySelector('.modal-body');
            const awal = body.scrollTop; body.scrollTop = 150; const geser = body.scrollTop; body.scrollTop = awal;
            return { badanBisaGulir: body.scrollHeight > body.clientHeight + 1 && geser > 0, modalBisaGulir: modal.scrollHeight > modal.clientHeight + 1 };
        });
        await P.ev(() => new Promise((r) => { jQuery('#lg-modal').one('hidden.bs.modal', () => r(true)).modal('hide'); }));
    }
    ctx.detail.modalPendek = pendek;
    const pendekAtauSempit = Object.entries(pendek).filter(([k]) => VP[k].h <= 700 || VP[k].w <= 575);
    const semuaMuat = pendekAtauSempit.every(([, x]) => x.headerFooterTerlihat && x.lebarMuat && x.docScrollW <= x.docClientW + 1);
    const gulirInternal = pendekAtauSempit.every(([, x]) => x.sectionBisaGulir);
    const paritas = Object.entries(pendek).every(([, x]) => x.sectionBisaGulir === x.legacy.badanBisaGulir);
    R.tambah('G4-modal-viewport-pendek', 'Viewport pendek (tinggi <=700px) & sempit (<=575px): header+footer modal tetap terlihat, lebar muat, tanpa overflow horizontal', st(semuaMuat), pendek);
    R.tambah('G4-modal-gulir-internal', 'Viewport pendek/sempit: isi panjang menggulir DI DALAM <section>; viewport lain: perilaku sama dengan modal legacy (.modal-body)', st(gulirInternal && paritas), Object.fromEntries(Object.entries(pendek).map(([k, v]) => [k, { sectionBisaGulir: v.sectionBisaGulir, modalBisaGulir: v.modalBisaGulir, legacy: v.legacy, pendekAtauSempit: VP[k].h <= 700 || VP[k].w <= 575 }])), 'Di luar viewport pendek/sempit modal legacy tidak membatasi tinggi (compat.css:283-310); yang menggulir adalah overlay .modal (perilaku Bootstrap).');
}

// ---------------------------------------------------------------------------
// Bagian 6 — SENTUH
// ---------------------------------------------------------------------------
async function bagianSentuh(P, R, ctx) {
    console.log('> SENTUH');
    const ukur = {};
    for (const vpNama of ['390x844', '1366x768']) {
        await P.buka('tw', KEADAAN[0], vpNama);
        await P.ev(() => window.__u.quiet());
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
        const daftar = {
            'tombol md': '#tw-btn-primary-md', 'tombol sm': '#tw-btn-primary-sm', 'tombol sm di tabel': '#tw-tabel tbody tr:first-child button', 'input': '#tw-in-normal', 'select': '#tw-sel-normal',
            'tutup modal': '#tw-modal-uji .modal-content > section:first-child > button', 'tombol footer modal': '#tw-modal-simpan', 'pembanding legacy .btn': '#lg-btn-primary', 'pembanding legacy .form-control': '#lg-in-normal',
        };
        ukur[vpNama] = {};
        for (const [n, s] of Object.entries(daftar)) {
            const kot = await P.kotak(s);
            ukur[vpNama][n] = kot ? `${Math.round(kot.w * 10) / 10}x${Math.round(kot.h * 10) / 10}` : null;
        }
    }
    ctx.detail.targetSentuh = ukur;
    const m = ukur['390x844'];
    const kecil = Object.entries(m).filter(([n, v]) => { const [w, h] = v.split('x').map(Number); return !n.startsWith('pembanding') && (w < 44 || h < 44); }).map(([n, v]) => `${n} ${v}`);
    const bawah24 = Object.entries(m).filter(([n, v]) => { const [w, h] = v.split('x').map(Number); return h < 24; }).map(([n, v]) => `${n} ${v}`);
    R.tambah('G5-target-sentuh-44', 'Target sentuh >= 44x44 CSS px (390x844)', kecil.length === 0 ? 'PASS' : 'FAIL', { ukuran390: m, dibawah44: kecil, ukuran1366: ukur['1366x768'] }, 'DEVIASI TERCATAT: tinggi kontrol token (34/30px) & tutup modal 40px; legacy memakai ukuran yang sama (mobile.css form-control 36px). WCAG 2.2 AA (2.5.8) mensyaratkan 24px: ' + (bawah24.length ? 'ADA di bawah 24px' : 'lolos 24px'));

    const semua = ['390x844', '1366x768'].flatMap((v) => Object.entries(ukur[v]).filter(([n]) => !n.startsWith('pembanding')).map(([n, d]) => ({ v, n, d, h: Number(d.split('x')[1]), w: Number(d.split('x')[0]) })));
    const bawah24x = semua.filter((x) => x.h < 24 || x.w < 24);
    R.tambah('G5-target-sentuh-24', 'Target >= 24x24 CSS px (WCAG 2.2 AA 2.5.8) pada 390x844 dan 1366x768 (zoom .8: tinggi tersaji 0,8x)', st(bawah24x.length === 0), { terkecil: semua.reduce((a, x) => (x.h < a.h ? x : a), { h: 999 }), bawah24: bawah24x.map((x) => `${x.v} ${x.n} ${x.d}`) }, 'Pada 1366x768 ukuran tersaji = 0,8x ukuran CSS (zoom .8), tombol sm 24px tersaji; dibaca sebagai informasi.');

    // ketuk (touch) sungguhan
    await P.buka('tw', KEADAAN[0], '390x844', { touch: true });
    await P.ev(() => window.__u.quiet());
    await P.ev(() => {
        window.__tap = { submit: 0, btn: 0, fokusInput: 0, sel: 0 };
        document.getElementById('tw-form-uji').addEventListener('submit', (e) => { window.__tap.submit++; e.preventDefault(); });
        document.getElementById('tw-btn-primary-md').addEventListener('click', () => window.__tap.btn++);
        document.getElementById('tw-in-normal').addEventListener('focus', () => window.__tap.fokusInput++);
        document.getElementById('tw-sel-normal').addEventListener('focus', () => window.__tap.sel++);
    });
    await P.ketuk('#tw-btn-primary-md');
    await P.ketuk('#tw-btn-submit');
    await P.ketuk('#tw-in-normal');
    await P.ketuk('#tw-sel-normal');
    await P.ev(() => document.activeElement && document.activeElement.blur());
    const t1 = await P.ev(() => window.__tap);
    const buka = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true))));
    await P.ketuk('#tw-buka-modal');
    await Promise.race([buka, sleep(3000)]);
    const terbuka = await P.ev(() => document.getElementById('tw-modal-uji').classList.contains('show'));
    const tutup = P.ev(() => new Promise((r) => jQuery('#tw-modal-uji').one('hidden.bs.modal', () => r(true))));
    await P.ketuk('#tw-modal-uji .modal-content > section:first-child > button');
    await Promise.race([tutup, sleep(3000)]);
    const tertutup = await P.ev(() => !document.getElementById('tw-modal-uji').classList.contains('show'));
    // tombol disabled: ketukan tidak boleh menjalankan klik
    await P.ev(() => { window.__tap.dis = 0; document.getElementById('tw-btn-primary-disabled').addEventListener('click', () => window.__tap.dis++); });
    await P.ketuk('#tw-btn-primary-disabled');
    const dis = await P.ev(() => window.__tap.dis);
    ctx.detail.ketuk = { ...t1, modalTerbuka: terbuka, modalTertutup: tertutup, disabledKlik: dis };
    R.tambah('G5-ketuk', 'Input.dispatchTouchEvent: tombol (klik+submit), input (fokus), select, pemicu & tutup modal; disabled tidak merespons', st(t1.btn === 1 && t1.submit === 1 && t1.fokusInput >= 1 && t1.sel >= 1 && terbuka && tertutup && dis === 0), ctx.detail.ketuk);
}

// ---------------------------------------------------------------------------
// Bagian 7 — ZOOM / RESPONSIF
// ---------------------------------------------------------------------------
async function bagianZoom(P, R, ctx) {
    console.log('> ZOOM');
    const z = {};
    for (const v of ['360x740', '390x844', '768x1024', '900x700', '901x700', '1024x768', '1366x768', '1920x1080']) {
        await P.buka('tw', KEADAAN[0], v);
        z[v] = await P.ev(() => ({ zoom: getComputedStyle(document.documentElement).zoom, ts: getComputedStyle(document.documentElement).getPropertyValue('--ts-zoom').trim(), innerW: innerWidth }));
    }
    const ok = Object.entries(z).every(([k, x]) => (VP[k].w >= 901 ? x.zoom === '0.8' : x.zoom === '1'));
    R.tambah('G6-zoom-computed', 'documentElement computed zoom: 0.8 pada >=901px, 1 pada <=900px', st(ok), z);
    // artefak produksi tidak memuat satuan viewport / *-screen / fixed
    const css = fs.readFileSync(path.join(AKAR, 'public/css/tesys-tw.css'), 'utf8');
    const terlarang = [/h-screen|w-screen|min-h-screen|h-dvh|w-dvh/, /\b\d+(?:\.\d+)?(?:vh|vw|dvh|svh|lvh)\b/, /position:\s*fixed|\.tw\\:fixed/];
    const ketemu = terlarang.map((re) => re.test(css));
    R.tambah('G6-artefak-tanpa-vh', 'Artefak produksi tanpa *-screen, satuan vh/vw/dvh, dan fixed (komponen tak memakai)', st(ketemu.every((x) => !x)), { ukuranByte: css.length, ketemu });
    // fixed inset-0 (artefak UJI): menutup viewport di bawah zoom .8
    const fixedHasil = {};
    for (const v of ['1366x768', '1920x1080', '390x844']) {
        await P.buka('tw', KEADAAN[0], v);
        await P.ev(async (href) => {
            await new Promise((r, j) => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; l.id = 'uji-tw-uji'; l.onload = r; l.onerror = () => j(new Error('uji css')); document.head.appendChild(l); });
            const d = document.createElement('div'); d.id = 'uji-fixed'; d.className = 'tw:fixed tw:inset-0'; d.style.background = 'rgba(0,0,0,.2)'; document.body.appendChild(d);
        }, '../../../tools/tailwind/uji/tesys-tw.uji.css');
        const kot = await P.kotak('#uji-fixed');
        const vm = await P.send('Page.getLayoutMetrics');
        fixedHasil[v] = { kotak: kot && { w: Math.round(kot.w), h: Math.round(kot.h) }, viewport: [vm.cssVisualViewport.clientWidth, vm.cssVisualViewport.clientHeight] };
    }
    ctx.detail.fixedInset0 = fixedHasil;
    const fixedOk = Object.values(fixedHasil).every((x) => x.kotak && Math.abs(x.kotak.w - x.viewport[0]) <= 1 && Math.abs(x.kotak.h - x.viewport[1]) <= 1);
    R.tambah('G6-fixed-inset-0', 'tw:fixed tw:inset-0 (artefak uji) menutup viewport penuh di bawah zoom .8 (informasi: tidak dipakai komponen)', st(fixedOk), fixedHasil, 'Tanpa kompensasi, lapisan fixed dalam html{zoom} menyusut; hasil di sini menunjukkan perilaku nyata.');
}

async function bagianResponsif(P, R, ctx) {
    console.log('> RESPONSIF');
    const hasil = {};
    let ok = true;
    for (const v of ['360x740', '390x844', '768x1024', '1024x768', '1366x768', '1920x1080']) {
        for (const k of [KEADAAN[0], KEADAAN[1]]) {
            await P.buka('tw', k, v);
            const m = await P.ev(() => {
                const de = document.documentElement;
                const lebarMax = innerWidth;
                const pelanggar = [];
                document.querySelectorAll('[id^="tw-"],.form-group:has([id^="tw-"])').forEach((e) => {
                    if (e.closest('.modal') || e.closest('.table-responsive')) return;
                    const r = e.getBoundingClientRect();
                    if (r.width > 0 && (r.right > lebarMax + 1 || r.left < -1)) pelanggar.push(e.id || e.className.slice(0, 30));
                });
                const gulirTabel = (() => { const t = document.querySelector('#tw-tabel').closest('.table-responsive'); return { scrollW: t.scrollWidth, clientW: t.clientWidth }; })();
                return { docScrollW: de.scrollWidth, docClientW: de.clientWidth, pelanggar, gulirTabel, innerW: lebarMax };
            });
            const bersih = m.docScrollW <= m.docClientW + 1 && m.pelanggar.length === 0;
            if (!bersih) ok = false;
            hasil[`${v}/${k.id}`] = { bersih, ...m };
        }
    }
    ctx.detail.responsif = hasil;
    R.tambah('RESP-viewport', 'Enam viewport x terang/gelap: tanpa overflow horizontal halaman & komponen tw di dalam viewport (di luar .table-responsive/.modal)', st(ok), Object.fromEntries(Object.entries(hasil).map(([k, x]) => [k, x.bersih ? 'ok' : x])));
}

// ---------------------------------------------------------------------------
// Bagian 8 — mode kartu tabel (<= 640px) & th/td & label & cursor
// ---------------------------------------------------------------------------
async function bagianKartu(P, R, ctx) {
    console.log('> KARTU / TABEL');
    const hasil = {};
    for (const v of ['390x844', '360x740', '768x1024', '1366x768']) {
        await P.buka('tw', KEADAAN[0], v, { dt: true });
        await P.ev(() => window.__u.quiet());
        await sleep(300);
        hasil[v] = await P.ev(() => {
            const info = (tbl) => {
                const t = document.querySelector(tbl);
                if (!t) return null;
                const tr = t.querySelector('tbody tr'), td = tr.querySelectorAll('td')[2], th = t.querySelector('thead th:nth-child(3)');
                const cTr = getComputedStyle(tr), cTd = getComputedStyle(td);
                const before = getComputedStyle(td, '::before');
                const cTh = getComputedStyle(th);
                const cThead = getComputedStyle(t.querySelector('thead'));
                return {
                    kelasKartu: t.classList.contains('ts-card-table'), dataLabel: td.getAttribute('data-label'),
                    trDisplay: cTr.display, tdDisplay: cTd.display, tdPad: `${cTd.paddingTop} ${cTd.paddingRight} ${cTd.paddingBottom} ${cTd.paddingLeft}`,
                    tdBorderBottom: cTd.borderBottomWidth, beforeContent: before.content, beforeDisplay: before.display,
                    theadDisplay: cThead.display, thDisplay: cTh.display, thTinggi: Math.round(th.getBoundingClientRect().height), thPad: `${cTh.paddingTop} ${cTh.paddingLeft}`, thFont: cTh.fontSize, thTransform: cTh.textTransform, trTinggi: Math.round(tr.getBoundingClientRect().height),
                    trBorder: cTr.borderTopWidth, trRadius: cTr.borderTopLeftRadius, trBg: cTr.backgroundColor,
                };
            };
            return { tw: info('#tw-tabel-dt'), legacyDt: info('#lg-tabel'), twTanpaDt: (() => { const t = document.querySelector('#tw-tabel'); return { kelasKartu: t.classList.contains('ts-card-table') }; })(), rapatOff: (() => { const t = document.querySelector('#tw-tabel-rapat'); return { dataTsCard: t.getAttribute('data-ts-card'), kelasKartu: t.classList.contains('ts-card-table') }; })() };
        });
        if (v === '390x844' && FOTO.includes(P.nama)) {
            await P.ev(() => document.getElementById('g-table').scrollIntoView());
            ctx.fotoKartu = await P.foto(`${P.nama}-tabel-kartu-390x844.png`);
        }
    }
    ctx.detail.kartu = hasil;
    const m = hasil['390x844'], n = hasil['360x740'];
    const kartuAktif = m.tw?.kelasKartu && m.tw?.dataLabel === 'Uraian' && n.tw?.kelasKartu;
    // thFont/thTransform dikecualikan: tabel pembanding legacy memakai kelas .table (aturan th-nya sendiri);
    // tinggi baris diberi toleransi 2px karena alasan yang sama (tw tak memakai .table).
    const samaDenganLegacy = ['trDisplay', 'tdDisplay', 'beforeDisplay', 'theadDisplay', 'thDisplay', 'thTinggi', 'tdPad', 'trBorder', 'trRadius'].every((p) => m.tw?.[p] === m.legacyDt?.[p])
        && Math.abs((m.tw?.trTinggi ?? 0) - (m.legacyDt?.trTinggi ?? 999)) <= 2;
    const desktopTabel = hasil['1366x768'].tw?.trDisplay === 'table-row' && hasil['1366x768'].tw?.tdDisplay === 'table-cell';
    R.tambah('G3-kartu-aktif', 'x-tw.table + DataTables <=640px: tesys-table.js memasang mode kartu (ts-card-table, data-label)', st(!!kartuAktif && desktopTabel), { m390: m.tw, desktop1366: hasil['1366x768'].tw, tanpaDt: hasil['390x844'].twTanpaDt });
    R.tambah('G3-kartu-vs-legacy', 'Tampilan kartu x-tw.table = tabel DataTables legacy (display tr/td/before/thead, padding td, tepi tr) pada 390px', st(!!samaDenganLegacy), { tw: m.tw, legacy: m.legacyDt });
    R.tambah('G3-kartu-off', 'card-mode=false -> data-ts-card="off" ada dan tidak jadi kartu', st(m.rapatOff.dataTsCard === 'off' && !m.rapatOff.kelasKartu), m.rapatOff);
}

// ---------------------------------------------------------------------------
// Landmark — header/footer komponen tidak boleh menjadi banner/contentinfo
// ---------------------------------------------------------------------------
async function bagianLandmark(P, R, ctx) {
    console.log('> LANDMARK');
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
    // anak langsung kartu dan modal (kasus terburuk: galeri tidak membungkusnya dengan section/article/main)
    const tanda = await P.ev(() => [...document.querySelectorAll('#g-card [id^="tw-card"] > *, .modal.show .modal-content > *')].map((e, i) => {
        e.setAttribute('data-uji-lm', String(i));
        return { i, tag: e.tagName.toLowerCase(), di: (e.closest('.modal') || e.closest('[id^="tw-card"]') || {}).id || null };
    }));
    const tagTerlarang = await P.ev(() => [...document.querySelectorAll('#g-card [id^="tw-card"] header, #g-card [id^="tw-card"] footer, .modal header, .modal footer')].length);
    const rincian = [];
    for (const t of tanda) {
        const a = await P.ax(`[data-uji-lm="${t.i}"]`);
        rincian.push({ ...t, role: a ? a.role : null });
    }
    const penuh = await P.send('Accessibility.getFullAXTree');
    const jumlahLandmark = penuh.nodes.filter((n) => !n.ignored && n.role && (n.role.value === 'banner' || n.role.value === 'contentinfo')).length;
    const landmark = rincian.filter((x) => x.role === 'banner' || x.role === 'contentinfo');
    ctx.detail.landmark = { diperiksa: rincian.length, tagHeaderFooterDiKomponen: tagTerlarang, rincian, landmarkSeluruhHalaman: jumlahLandmark };
    R.tambah('G4-landmark', 'x-tw.card dan x-tw.modal tidak memuat <header>/<footer> dan tidak ada elemen komponen yang terpetakan sebagai landmark banner/contentinfo (pohon aksesibilitas Chromium, kasus terburuk: bukan turunan section/article/main)', st(rincian.length > 0 && tagTerlarang === 0 && landmark.length === 0 && jumlahLandmark === 0), {
        diperiksa: rincian.length, tagHeaderFooterDiKomponen: tagTerlarang, landmarkDitemukan: landmark.map((x) => `${x.tag} di #${x.di}: ${x.role}`), landmarkSeluruhHalaman: jumlahLandmark, role: [...new Set(rincian.map((x) => x.tag + ':' + x.role))],
    });
}

// ---------------------------------------------------------------------------
// Bagian 9 — KONTRAS TEKS
// ---------------------------------------------------------------------------
async function bagianKontras(P, R, ctx) {
    console.log('> KONTRAS TEKS');
    const pasangan = [];
    const gagal = [];
    const daftar = [
        ['tombol primary', '#tw-btn-primary-md', null, 'teks-normal'], ['tombol secondary', '#tw-btn-secondary-md', null, 'teks-normal'], ['tombol danger', '#tw-btn-danger-md', null, 'teks-normal'], ['tombol ghost', '#tw-btn-ghost-md', null, 'teks-normal'],
        ['tombol primary hover', '#tw-btn-primary-md', 'hover', 'teks-normal'], ['tombol secondary hover', '#tw-btn-secondary-md', 'hover', 'teks-normal'], ['tombol danger hover', '#tw-btn-danger-md', 'hover', 'teks-normal'], ['tombol ghost hover', '#tw-btn-ghost-md', 'hover', 'teks-normal'],
        ['label', '.form-group:has(> #tw-in-normal) > label', null, 'teks-normal'], ['isi input', '#tw-in-hint', null, 'teks-normal'], ['isi select', '#tw-sel-normal', null, 'teks-normal'], ['input galat', '#tw-in-error', null, 'teks-normal'],
        ['hint', '#tw-in-hint-hint', null, 'teks-normal'], ['pesan galat', '#tw-in-error-error', null, 'teks-normal'],
        ['badge neutral', '#tw-badge-neutral-sm', null, 'teks-normal'], ['badge attention', '#tw-badge-attention-sm', null, 'teks-normal'], ['badge progress', '#tw-badge-progress-sm', null, 'teks-normal'], ['badge positive', '#tw-badge-positive-sm', null, 'teks-normal'],
        ['badge done', '#tw-badge-done-sm', null, 'teks-normal'], ['badge danger', '#tw-badge-danger-sm', null, 'teks-normal'],
        ['judul kartu', '#tw-card-md > div:first-child', null, 'teks-normal'], ['isi kartu', '#tw-card-md > div:nth-child(2)', null, 'teks-normal'], ['kaki kartu', '#tw-card-md > div:last-child', null, 'teks-normal'],
        ['th', '#tw-tabel thead th:nth-child(2)', null, 'teks-normal'], ['td', '#tw-tabel tbody tr:first-child td:nth-child(2)', null, 'teks-normal'],
        ['placeholder', '#tw-in-normal', '::placeholder', 'teks-normal'],
        ['input disabled (dikecualikan WCAG 1.4.3, informasi)', '#tw-in-disabled', null, 'info'], ['tombol primary disabled (opacity .45 — informasi)', '#tw-btn-primary-disabled', null, 'info'],
    ];
    const keadaanLebar = [...KEADAAN];
    for (const k of keadaanLebar) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        for (const [nama, sel, mode, jenis] of daftar) {
            let nodeId = null;
            const pseudo = mode && mode.startsWith('::') ? mode : null;
            if (mode === 'hover') nodeId = await P.paksa(sel, ['hover']);
            const r = await P.ev((s, ps) => {
                const u = window.__u;
                const e = document.querySelector(s);
                const cs = getComputedStyle(e, ps);
                const fg = u.rgba(cs.color);
                const bgSendiri = u.rgba(getComputedStyle(e).backgroundColor);
                const latar = u.effBg(e);
                const bg = bgSendiri[3] > 0 ? u.over(bgSendiri, latar) : latar;
                const op = parseFloat(getComputedStyle(e).opacity);
                const fgEf = u.over(fg, bg);
                return { fg: u.hex(fgEf), bg: u.hex(bg), kontras: +u.cr(fgEf, bg).toFixed(2), ukuran: cs.fontSize, bobot: cs.fontWeight, opacity: op };
            }, sel, pseudo);
            if (nodeId !== null) await P.lepas(nodeId);
            const rec = { keadaan: k.id, nama, ...r };
            pasangan.push(rec);
            if (jenis === 'teks-normal' && r.kontras < 4.5) gagal.push(rec);
        }
    }
    // on-accent untuk 8 praset x terang/gelap (tombol primary, normal & hover)
    const onAccent = [];
    for (const tema of ['terang', 'gelap']) {
        for (const hex of PRASET.concat(['#ffd400'])) {
            await P.buka('tw', { tema, aksen: hex === '#92232b' ? null : hex }, '1366x768');
            await P.ev(() => window.__u.quiet());
            const normal = await P.ev(() => { const u = window.__u; const e = document.querySelector('#tw-btn-primary-md'); const cs = getComputedStyle(e); const fg = u.rgba(cs.color), bg = u.rgba(cs.backgroundColor); return { fg: u.hex(fg), bg: u.hex(bg), kontras: +u.cr(fg, bg).toFixed(2) }; });
            const nodeId = await P.paksa('#tw-btn-primary-md', ['hover']);
            const hov = await P.ev(() => { const u = window.__u; const e = document.querySelector('#tw-btn-primary-md'); const cs = getComputedStyle(e); const fg = u.rgba(cs.color), bg = u.rgba(cs.backgroundColor); return { fg: u.hex(fg), bg: u.hex(bg), kontras: +u.cr(fg, bg).toFixed(2) }; });
            await P.lepas(nodeId);
            const rec = { tema, aksen: hex, normal, hover: hov };
            onAccent.push(rec);
            if (hex !== '#ffd400' && (normal.kontras < 4.5 || hov.kontras < 4.5)) gagal.push({ keadaan: `${tema} ${hex}`, nama: 'primary on-accent', normal, hover: hov });
        }
    }
    ctx.detail.kontrasOnAccent = onAccent.map((x) => `${x.tema} ${x.aksen}: normal ${x.normal.kontras} hover ${x.hover.kontras}`);
    const minNormal = Math.min(...pasangan.filter((p) => !/informasi/.test(p.nama)).map((p) => p.kontras));
    ctx.detail.kontrasTeks = pasangan.filter((p) => p.keadaan === 'terang-bawaan' || p.keadaan === 'gelap-bawaan').map((p) => `${p.keadaan} | ${p.nama}: ${p.kontras} (${p.fg} on ${p.bg}) ${p.ukuran}`);
    R.tambah('G7-kontras-teks', 'Kontras teks komponen (warna terhitung) >= 4.5:1 pada 4 keadaan + on-accent 8 praset x 2 tema (normal & hover)', st(gagal.length === 0), {
        terendahTeksNormal: minNormal, jumlahPasangan: pasangan.length + onAccent.length * 2,
        gagal: gagal.slice(0, 25).map((g) => (g.kontras !== undefined ? `${g.keadaan} | ${g.nama}: ${g.kontras} (${g.fg} on ${g.bg})` : `${g.keadaan} | ${g.nama}: normal ${g.normal.kontras} hover ${g.hover.kontras}`)),
        informasiAksenKuning: onAccent.filter((x) => x.aksen === '#ffd400'),
    });
}

// ---------------------------------------------------------------------------
// Bagian 10 — cursor / label / lain-lain kaskade yang butuh ukur khusus
// ---------------------------------------------------------------------------
async function bagianLain(P, R, ctx) {
    console.log('> CURSOR / LABEL');
    await P.buka('tw', KEADAAN[0], '1366x768');
    // transisi dibaca SEBELUM gaya senyap dipasang (gaya itu menimpa transition)
    const tr = await P.ev(() => {
        const e = document.querySelector('#tw-btn-primary-md');
        const cs = getComputedStyle(e);
        const u = window.__u;
        return { dur: cs.transitionDuration, durToken: u.probe('transition-duration', 'var(--dur)', e), fn: cs.transitionTimingFunction, fnToken: u.probe('transition-timing-function', 'var(--ease)', e), memuatBackground: /background-color/.test(cs.transitionProperty) && /box-shadow/.test(cs.transitionProperty), legacyDur: getComputedStyle(document.querySelector('#lg-btn-primary')).transitionDuration };
    });
    ctx.detail.transisi = tr;
    R.tambah('G2-transisi', 'tw:transition memakai durasi/kurva token proyek (--dur, --ease)', st(tr.dur === tr.durToken && tr.fn === tr.fnToken && tr.memuatBackground), tr);
    await P.ev(() => window.__u.quiet());
    const cur = await P.ev(() => {
        const g = (s) => getComputedStyle(document.querySelector(s)).cursor;
        return {
            btnAktif: g('#tw-btn-primary-md'), btnDisabled: g('#tw-btn-primary-disabled'), btnLoading: g('#tw-btn-primary-loading'), ghostDisabled: g('#tw-btn-ghost-disabled'),
            inputDisabled: g('#tw-in-disabled'), selectDisabled: g('#tw-sel-disabled'), legacyDisabled: g('#lg-btn-disabled'), legacyAktif: g('#lg-btn-primary'),
        };
    });
    ctx.detail.cursor = cur;
    R.tambah('G3-cursor-disabled', 'cursor: aktif=pointer, disabled=not-allowed, loading=progress, input/select disabled=not-allowed', st(cur.btnAktif === 'pointer' && cur.btnDisabled === 'not-allowed' && cur.btnLoading === 'progress' && cur.inputDisabled === 'not-allowed' && cur.selectDisabled === 'not-allowed' && cur.ghostDisabled === 'not-allowed'), cur);

    // label & kontrol: nilai legacy vs token (pembanding)
    const lab = await P.ev(() => {
        const l = document.querySelector('.form-group:has(> #tw-in-normal) > label');
        const ll = document.querySelector('#lg-label');
        const c = (e) => { const s = getComputedStyle(e); return { fs: s.fontSize, fw: s.fontWeight, color: s.color, mb: s.marginBottom, display: s.display, ls: s.letterSpacing, tt: s.textTransform }; };
        return { tw: c(l), legacy: c(ll) };
    });
    ctx.detail.label = lab;
    const sama = JSON.stringify(lab.tw) === JSON.stringify(lab.legacy);
    R.tambah('G3-label', 'Label x-tw.input memakai nilai yang sama dengan label legacy (font/bobot/warna/margin) atau token', st(sama), lab, sama ? undefined : 'berbeda dari label legacy; bandingkan dengan G2-token');
}

// ---------------------------------------------------------------------------
// Bagian 11 — TAUTAN (x-tw.button href -> <a>)
// ---------------------------------------------------------------------------
async function bagianTautan(P, R, ctx) {
    console.log('> TAUTAN (x-tw.button href)');
    const VAR = Object.keys(TOKEN_VARIAN);
    const barisDasar = (v, sz) => {
        const u = TOKEN_UKURAN(sz);
        return [
            ...TOKEN_VARIAN[v],
            ['border-top-left-radius', 'var(--radius-sm)'], ['border-top-width', '1px'], ['font-weight', 'var(--fw-label)'],
            ['line-height', 'var(--lh-tight)'], ['font-size', u.fs], ['min-height', u.h], ['padding-left', u.px],
            ['font-family', '@body:font-family'], ['display', 'inline-flex'], ['text-transform', 'none'], ['text-decoration-line', 'none'],
        ];
    };
    const spek = () => {
        const s = [];
        for (const v of VAR) {
            for (const sz of ['md', 'sm']) {
                s.push({ sel: `#tw-lnk-${v}-${sz}`, rows: [...barisDasar(v, sz), ['cursor', 'pointer']] });
                s.push({ sel: `#tw-lnk-${v}-${sz}-ikon`, rows: [...barisDasar(v, sz), ['cursor', 'pointer']] });
                s.push({ sel: `#tw-lnk-${v}-${sz}-blank`, rows: [...barisDasar(v, sz), ['cursor', 'pointer']] });
                s.push({ sel: `#tw-lnk-${v}-${sz}-disabled`, rows: [...barisDasar(v, sz), ['opacity', '.45'], ['cursor', 'not-allowed']] });
                s.push({ sel: `#tw-lnk-${v}-${sz}-loading`, rows: [...barisDasar(v, sz), ['opacity', '.45'], ['cursor', 'progress']] });
            }
        }
        return s;
    };
    const dev = [];
    let total = 0;
    const perKeadaan = {};
    const sama = [];
    for (const k of KEADAAN) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        const hasil = await P.ev((s) => window.__u.check(s), spek());
        // hover: aktif berubah sesuai token, dekorasi tetap none; disabled/loading TIDAK berubah
        for (const v of VAR) {
            let nid = await P.paksa(`#tw-lnk-${v}-md`, ['hover']);
            hasil.push(...(await P.ev((sp) => window.__u.check(sp).map((x) => ({ ...x, sel: x.sel + ':hover' })), [{ sel: `#tw-lnk-${v}-md`, rows: [...TOKEN_HOVER[v], ['text-decoration-line', 'none']] }])));
            await P.lepas(nid);
            for (const stt of ['disabled', 'loading']) {
                const baca = (id) => P.ev((s) => { const cs = getComputedStyle(document.querySelector(s)); return [cs.backgroundColor, cs.color, cs.borderTopColor, cs.textDecorationLine, cs.cursor].join('|'); }, id);
                const sebelum = await baca(`#tw-lnk-${v}-md-${stt}`);
                nid = await P.paksa(`#tw-lnk-${v}-md-${stt}`, ['hover']);
                const sesudah = await baca(`#tw-lnk-${v}-md-${stt}`);
                await P.lepas(nid);
                hasil.push({ sel: `#tw-lnk-${v}-md-${stt}:hover`, prop: 'tidak berubah saat hover', got: sesudah, want: sebelum, ok: sesudah === sebelum });
            }
        }
        // ukuran sama dengan kembaran <button> (bukti display/line-height/padding sama)
        const ukur = await P.ev(() => {
            const o = [];
            ['primary', 'secondary', 'danger', 'ghost'].forEach((v) => ['md', 'sm'].forEach((sz) => {
                const a = document.getElementById(`tw-lnk-${v}-${sz}`).getBoundingClientRect();
                const b = document.getElementById(`tw-btn-${v}-${sz}`).getBoundingClientRect();
                o.push({ id: `${v}-${sz}`, tautan: `${a.width.toFixed(2)}x${a.height.toFixed(2)}`, tombol: `${b.width.toFixed(2)}x${b.height.toFixed(2)}` });
            }));
            return o;
        });
        ukur.forEach((x) => hasil.push({ sel: `#tw-lnk-${x.id}`, prop: 'ukuran (lebar x tinggi) = kembaran <button>', got: x.tautan, want: x.tombol, ok: x.tautan === x.tombol }));
        if (k.id === 'terang-bawaan') sama.push(...ukur);
        total += hasil.length;
        const salah = hasil.filter((x) => x.galat || x.ok === false);
        perKeadaan[k.id] = { cek: hasil.length, menyimpang: salah.length };
        salah.forEach((x) => dev.push({ keadaan: k.id, ...x }));
    }
    const ringkas = {};
    dev.forEach((d) => {
        const key = `${d.sel}|${d.prop}`;
        (ringkas[key] ||= { sel: d.sel, prop: d.prop, got: d.got, want: d.want, galat: d.galat, keadaan: [] }).keadaan.push(d.keadaan);
    });
    const daftarDev = Object.values(ringkas);
    ctx.detail.tautanDeviasi = daftarDev;
    ctx.detail.tautanUkuran = sama;
    R.tambah('G2-token-tautan', '<a> x-tw.button href: computed (warna, tepi, radius, font, min-height, padding, bayangan, display, text-decoration, cursor, opacity) = token; hover; disabled/loading tak berubah saat hover; ukuran = kembaran <button>; 4 keadaan', st(daftarDev.length === 0), { totalCek: total, perKeadaan, jumlahDeviasi: daftarDev.length, deviasi: daftarDev.slice(0, 40) });

    // fokus papan ketik (Tab lalu focus()): satu outline accent-text, radius = istirahat, dekorasi none
    const fokus = [];
    const fokusBaik = (x) => x.fokusVisible && x.aktif && x.indikator === 1 && x.warnaOk && x.radiusSama && x.warnaTeksSama && x.dekorNone;
    for (const k of KEADAAN) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        const sels = [...VAR.map((v) => `#tw-lnk-${v}-md`), '#tw-lnk-primary-sm', '#tw-lnk-ghost-sm-ikon', '#tw-lnk-secondary-md-blank'];
        for (const sel of sels) {
            await P.tombol('Tab');
            const r = await P.ev((s) => {
                const u = window.__u;
                const e = document.querySelector(s);
                const rest = getComputedStyle(e);
                const radiusRest = rest.borderTopLeftRadius, warnaRest = rest.color, dekorRest = rest.textDecorationLine;
                e.focus();
                const cs = getComputedStyle(e);
                const outlineAda = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
                const ring = u.shadows(cs.boxShadow).map(u.shadowInfo).filter((i) => i.color && i.color[3] > 0 && i.spread >= 1 && !i.inset);
                const want = u.probe('outline-color', 'var(--accent-text)', null);
                return {
                    fokusVisible: e.matches(':focus-visible'), aktif: document.activeElement === e, outlineAda, indikator: (outlineAda ? 1 : 0) + (ring.length ? 1 : 0),
                    outline: `${cs.outlineStyle} ${cs.outlineWidth} offset ${cs.outlineOffset} ${u.hex(u.rgba(cs.outlineColor))}`,
                    warnaOk: cs.outlineColor === want, radiusSama: radiusRest === cs.borderTopLeftRadius, warnaTeksSama: warnaRest === cs.color, dekorNone: dekorRest === 'none' && cs.textDecorationLine === 'none',
                };
            }, sel);
            await P.ev(() => document.activeElement && document.activeElement.blur());
            fokus.push({ keadaan: k.id, sel, ...r });
        }
    }
    ctx.detail.tautanFokus = fokus.filter((x) => x.keadaan === 'terang-bawaan');
    R.tambah('G3-fokus-tautan', '<a>: fokus papan ketik = satu outline berwarna accent-text, radius sama dengan istirahat, warna teks & dekorasi tak berubah', st(fokus.every(fokusBaik)), { elemenXKeadaan: fokus.length, pelanggaran: fokus.filter((x) => !fokusBaik(x)).slice(0, 10), contoh: ctx.detail.tautanFokus.slice(0, 3) });

    // keyboard + atribut + ctrl+klik (terang-bawaan)
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    const attr = await P.ev(() => {
        const g = (id) => {
            const e = document.getElementById(id);
            let hrefValid = false;
            try { hrefValid = !!new URL(e.href); } catch { hrefValid = false; }
            return { tag: e.tagName, href: e.getAttribute('href'), role: e.getAttribute('role'), tabindex: e.getAttribute('tabindex'), ariaDisabled: e.getAttribute('aria-disabled'), ariaBusy: e.getAttribute('aria-busy'), rel: e.getAttribute('rel'), target: e.getAttribute('target'), type: e.getAttribute('type'), disabled: e.hasAttribute('disabled'), hrefValid };
        };
        return { normal: g('tw-lnk-primary-md'), disabled: g('tw-lnk-primary-md-disabled'), loading: g('tw-lnk-primary-md-loading'), blank: g('tw-lnk-primary-md-blank'), ikon: g('tw-lnk-primary-md-ikon') };
    });
    ctx.detail.tautanAtribut = attr;
    const tabbable = await P.ev(() => window.__u.tabbables().map(window.__u.nama));
    const nonaktifRe = /^tw-lnk-.*-(disabled|loading)$/;
    const nonaktifTabbable = tabbable.filter((n) => nonaktifRe.test(n));
    // Tab maju melewati seluruh elemen tabbable (urutan DOM) dan tidak mendarat di tautan disabled/loading
    await P.ev(() => { document.body.focus(); window.getSelection().removeAllRanges(); });
    const mendarat = [];
    for (let i = 0; i < tabbable.length; i++) { await P.tombol('Tab'); mendarat.push(await P.ev(() => window.__u.nama(document.activeElement))); }
    const tautanDiharap = tabbable.filter((n) => n.startsWith('tw-lnk-'));
    const tabOk = JSON.stringify(mendarat) === JSON.stringify(tabbable) && !mendarat.some((n) => nonaktifRe.test(n));
    // Enter mengaktifkan; Space TIDAK (perilaku <a> native)
    await P.ev(() => {
        window.__lnk = { klik: 0, defaultPrevented: null };
        const e = document.getElementById('tw-lnk-secondary-md');
        e.addEventListener('click', (ev) => { window.__lnk.klik++; window.__lnk.defaultPrevented = ev.defaultPrevented; });
        history.replaceState(null, '', location.pathname + location.search);
        e.focus();
    });
    await P.tombol(' ');
    const setelahSpace = await P.ev(() => ({ klik: window.__lnk.klik, hash: location.hash }));
    await P.tombol('Enter');
    const setelahEnter = await P.ev(() => ({ klik: window.__lnk.klik, hash: location.hash, dp: window.__lnk.defaultPrevented }));
    // ctrl+klik: peramban membuka tab baru (kemampuan <a>); dihitung dari target milik runner, lalu ditutup
    await P.ev(() => history.replaceState(null, '', location.pathname + location.search));
    const halaman = (info) => info.filter((t) => t.type === 'page').map((t) => t.targetId);
    const sebelumInfo = (await P.cdp.send('Target.getTargets')).targetInfos;
    const sebelumT = halaman(sebelumInfo);
    await P.ev(() => {
        window.__ctrl = null;
        document.getElementById('tw-lnk-primary-md').addEventListener('click', (ev) => { window.__ctrl = { ctrlKey: ev.ctrlKey, defaultPrevented: ev.defaultPrevented }; }, { once: true });
        document.getElementById('tw-lnk-primary-md').scrollIntoView({ block: 'center', behavior: 'instant' });
    });
    await sleep(600); // gulir halus legacy selesai sebelum koordinat dibaca
    const pos = await P.ev(() => { const r = document.getElementById('tw-lnk-primary-md').getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
    await P.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: pos.x, y: pos.y, modifiers: 2 });
    await P.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: pos.x, y: pos.y, button: 'left', buttons: 1, clickCount: 1, modifiers: 2 });
    await P.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: pos.x, y: pos.y, button: 'left', buttons: 0, clickCount: 1, modifiers: 2 });
    await sleep(800);
    const sesudahInfo = (await P.cdp.send('Target.getTargets')).targetInfos;
    const sesudahT = halaman(sesudahInfo);
    const targetBaruInfo = sesudahInfo.filter((t) => !sebelumInfo.some((x) => x.targetId === t.targetId)).map((t) => ({ type: t.type, url: String(t.url).slice(-60) }));
    const eventCtrl = await P.ev(() => window.__ctrl);
    const tabBaru = sesudahT.filter((id) => !sebelumT.includes(id));
    for (const id of tabBaru) { try { await P.cdp.send('Target.closeTarget', { targetId: id }); } catch { /* abaikan */ } }
    const hashSetelahCtrl = await P.ev(() => location.hash);
    ctx.detail.tautanKeyboard = { tabbableTotal: tabbable.length, tautanTabbable: tautanDiharap.length, nonaktifTabbable, tabOk, setelahSpace, setelahEnter, ctrlKlik: { tabBaruTerbuka: tabBaru.length, targetBaru: targetBaruInfo, eventKlik: eventCtrl, hashHalamanAsalSetelah: hashSetelahCtrl } };
    R.tambah('G4-tautan-papan-ketik', '<a href>: Enter mengaktifkan (klik + navigasi), Space TIDAK; Tab melewati tautan aktif dan melewati disabled/loading (tabindex -1, tanpa href); ctrl+klik membuka tab baru tanpa menavigasi halaman asal', st(
        tabOk && nonaktifTabbable.length === 0 && tautanDiharap.length === 24
        && setelahSpace.klik === 0 && setelahEnter.klik === 1 && setelahEnter.hash === '#tw-lnk-tujuan' && setelahEnter.dp === false
        && attr.disabled.href === null && attr.disabled.tabindex === '-1' && attr.disabled.ariaDisabled === 'true' && attr.disabled.role === 'link'
        && attr.loading.href === null && attr.loading.tabindex === '-1' && attr.loading.ariaBusy === 'true' && attr.loading.ariaDisabled === 'true'
        && attr.normal.tag === 'A' && attr.normal.hrefValid && attr.normal.type === null && attr.normal.disabled === false
        && tabBaru.length === 1 && eventCtrl && eventCtrl.ctrlKey === true && eventCtrl.defaultPrevented === false && hashSetelahCtrl === ''), ctx.detail.tautanKeyboard);

    // ARIA / pohon aksesibilitas
    const axSel = { normal: '#tw-lnk-primary-md', ikon: '#tw-lnk-primary-md-ikon', disabled: '#tw-lnk-primary-md-disabled', loading: '#tw-lnk-primary-md-loading', blank: '#tw-lnk-primary-md-blank' };
    const ax = {};
    for (const [n, s] of Object.entries(axSel)) ax[n] = await P.ax(s);
    ctx.detail.tautanAx = ax;
    const cek = [
        ['tautan aktif: role link, nama dari isi', ax.normal?.role === 'link' && /Primary md/.test(ax.normal?.nama)],
        ['tautan ber-ikon: nama dari teks (ikon aria-hidden)', ax.ikon?.role === 'link' && ax.ikon?.nama === 'Unduh'],
        ['disabled: role link + disabled (aria-disabled)', ax.disabled?.role === 'link' && ax.disabled?.props?.disabled === true],
        ['loading: role link + disabled + busy', ax.loading?.role === 'link' && ax.loading?.props?.disabled === true && attr.loading.ariaBusy === 'true'],
        ['target _blank otomatis rel="noopener noreferrer"', attr.blank.target === '_blank' && attr.blank.rel === 'noopener noreferrer'],
        ['tautan tanpa target tidak diberi rel', attr.normal.rel === null],
    ];
    R.tambah('G4-aria-tautan', 'Nama aksesibel, peran, disabled/busy, rel pada target _blank (pohon aksesibilitas Chromium)', st(cek.every((c) => c[1])), { cek: cek.map((c) => `${c[1] ? 'ok' : 'GAGAL'}: ${c[0]}`), disabledProps: ax.disabled?.props, loadingProps: ax.loading?.props });

    // kontras teks (istirahat + hover) 4 keadaan
    const gagal = [];
    let minK = 99;
    for (const k of KEADAAN) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        for (const v of VAR) {
            for (const mode of [null, 'hover']) {
                const nid = mode ? await P.paksa(`#tw-lnk-${v}-md`, ['hover']) : null;
                const r = await P.ev((s) => {
                    const u = window.__u; const e = document.querySelector(s); const cs = getComputedStyle(e);
                    const fg = u.rgba(cs.color); const bgS = u.rgba(cs.backgroundColor); const lat = u.effBg(e); const bg = bgS[3] > 0 ? u.over(bgS, lat) : lat;
                    return { fg: u.hex(fg), bg: u.hex(bg), kontras: +u.cr(u.over(fg, bg), bg).toFixed(2) };
                }, `#tw-lnk-${v}-md`);
                if (nid !== null) await P.lepas(nid);
                if (r.kontras < minK) minK = r.kontras;
                if (r.kontras < 4.5) gagal.push(`${k.id} ${v} ${mode || 'istirahat'}: ${r.kontras} (${r.fg} on ${r.bg})`);
            }
        }
    }
    R.tambah('G7-kontras-tautan', 'Kontras teks <a> varian x (istirahat, hover) x 4 keadaan >= 4,5:1', st(gagal.length === 0), { terendah: minK, gagal });

    // sentuh 390x844: ukuran = kembaran <button>; ketukan mengaktifkan tautan; disabled tidak merespons
    await P.buka('tw', KEADAAN[0], '390x844', { touch: true });
    await P.ev(() => window.__u.quiet());
    const ukuranS = await P.ev(() => {
        const o = {};
        ['md', 'sm'].forEach((sz) => {
            const a = document.getElementById(`tw-lnk-primary-${sz}`).getBoundingClientRect();
            const b = document.getElementById(`tw-btn-primary-${sz}`).getBoundingClientRect();
            o[sz] = { tautan: `${a.width.toFixed(1)}x${a.height.toFixed(1)}`, tombol: `${b.width.toFixed(1)}x${b.height.toFixed(1)}` };
        });
        return o;
    });
    await P.ev(() => {
        window.__tapL = { aktif: 0, klikJsDisabled: 0 };
        history.replaceState(null, '', location.pathname + location.search);
        document.getElementById('tw-lnk-primary-md').addEventListener('click', (e) => { window.__tapL.aktif++; e.preventDefault(); });
        document.getElementById('tw-lnk-primary-md-disabled').addEventListener('click', () => { window.__tapL.klikJsDisabled++; });
    });
    await P.ketuk('#tw-lnk-primary-md');
    await P.ketuk('#tw-lnk-primary-md-disabled');
    const tap = await P.ev(() => ({ ...window.__tapL, hashSetelahKetukDisabled: location.hash }));
    const tinggiMd = Number(ukuranS.md.tautan.split('x')[1]), tinggiSm = Number(ukuranS.sm.tautan.split('x')[1]);
    ctx.detail.tautanSentuh = { ukuran390: ukuranS, ketuk: tap };
    R.tambah('G5-tautan-sentuh', '390x844: ukuran <a> = kembaran <button> (>= 24px, WCAG 2.2 AA 2.5.8); ketukan mengaktifkan tautan; ketukan pada disabled tidak menavigasi', st(ukuranS.md.tautan === ukuranS.md.tombol && ukuranS.sm.tautan === ukuranS.sm.tombol && tinggiMd >= 24 && tinggiSm >= 24 && tap.aktif === 1 && tap.hashSetelahKetukDisabled === ''), ctx.detail.tautanSentuh, `tinggi tautan md ${tinggiMd}px, sm ${tinggiSm}px (< 44px: deviasi desain yang sama dengan G5-target-sentuh-44). Tautan disabled tanpa href tidak menavigasi, tetapi event klik JS tetap terkirim (klikJsDisabled=${tap.klikJsDisabled}); berbeda dari <button disabled>. Pemanggil tidak boleh memasang handler/data-toggle pada tautan disabled.`);
}

// ---------------------------------------------------------------------------
// Foto kunci
// ---------------------------------------------------------------------------
async function fotoKunci(P, ctx) {
    const f = [];
    const set = [
        ['390x844', KEADAAN[0], 'terang'], ['390x844', KEADAAN[1], 'gelap'], ['1366x768', KEADAAN[0], 'terang'], ['1366x768', KEADAAN[1], 'gelap'],
    ];
    for (const [v, k, label] of set) {
        await P.buka('tw', k, v);
        await P.ev(() => window.__u.quiet());
        await P.ev(() => document.getElementById('g-button').scrollIntoView());
        f.push(await P.foto(`${P.nama}-tombol-${label}-${v}.png`));
    }
    for (const [label, k] of [['terang', KEADAAN[0]], ['gelap', KEADAAN[1]]]) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        await P.ev(() => document.getElementById('g-link').scrollIntoView({ behavior: 'instant' }));
        await sleep(300);
        f.push(await P.foto(`${P.nama}-tautan-${label}-1366x768.png`));
    }
    for (const [label, k] of [['terang', KEADAAN[0]], ['gelap', KEADAAN[1]]]) {
        await P.buka('tw', k, '1366x768');
        await P.ev(() => window.__u.quiet());
        await P.ev(() => document.getElementById('g-badge').scrollIntoView());
        f.push(await P.foto(`${P.nama}-badge-kartu-${label}-1366x768.png`));
        if (label === 'terang') {
            await P.ev(() => document.getElementById('g-table').scrollIntoView());
            f.push(await P.foto(`${P.nama}-tabel-${label}-1366x768.png`));
        }
        await P.ev(() => new Promise((r) => { jQuery('#tw-modal-uji').one('shown.bs.modal', () => r(true)).modal('show'); }));
        f.push(await P.foto(`${P.nama}-modal-${label}-1366x768.png`));
    }
    // fokus papan ketik (gambar ke-10; tambah modal-tinggi dan tabel-kartu dari bagian modal/kartu = 12 PNG)
    await P.buka('tw', KEADAAN[0], '1366x768');
    await P.ev(() => window.__u.quiet());
    await P.ev(() => document.getElementById('g-button').scrollIntoView());
    await P.tombol('Tab');
    f.push(await P.foto(`${P.nama}-fokus-tombol-terang-1366x768.png`));
    ctx.foto = f;
}

// ---------------------------------------------------------------------------
// Utama
// ---------------------------------------------------------------------------
const PETA_BAGIAN = {
    iso: bagianIso, token: bagianToken, kaskade: bagianKaskade, fokus: bagianFokus, keyboard: bagianKeyboard, aria: bagianAria,
    modal: bagianModal, sentuh: bagianSentuh, zoom: bagianZoom, responsif: bagianResponsif, kontras: bagianKontras, kartu: bagianKartu, lain: bagianLain, landmark: bagianLandmark, tautan: bagianTautan,
};

async function jalankan(nama) {
    console.log(`\n=== ${nama} ===`);
    const B = await mulaiBrowser(nama);
    const catatPid = { pid: B.pid, port: B.port, userDataDir: path.basename(B.userDir) };
    console.log(`Diluncurkan: ${B.versi} PID ${B.pid} port ${B.port}`);
    const R = new Butir();
    const ctx = { detail: {}, foto: [] };
    let galat = null;
    try {
        const P = await buatHalaman(B);
        P.nama = nama;
        for (const b of BAGIAN) {
            if (!PETA_BAGIAN[b]) continue;
            try { await PETA_BAGIAN[b](P, R, ctx); } catch (e) {
                R.tambah('GALAT-' + b, 'Bagian gagal dijalankan', 'FAIL', { pesan: String(e.message || e) }, 'galat runner; bukan hasil pengukuran');
            }
        }
        if (FOTO.includes(nama)) {
            try { await fotoKunci(P, ctx); } catch (e) { ctx.fotoGalat = String(e.message || e); }
        }
    } catch (e) { galat = String(e.stack || e); }
    const henti = await hentikanBrowser(B);
    // proses browser milik runner yang masih tersisa = FAIL + kode keluar non-nol (-1 = tak terverifikasi, juga gagal)
    const bersih = henti.sisaProsesPenanda === 0;
    R.tambah('G0-proses-bersih', 'Setelah penghentian tidak ada proses bertanda user-data-dir milik runner yang tersisa, dan folder sementara terhapus', st(bersih && henti.userDirDihapus), henti, bersih ? undefined : 'proses tersisa/tak terverifikasi: periksa manual per PID, jangan taskkill /IM');
    if (!bersih) process.exitCode = 1;
    ctx.detail.lingkungan = { ...(ctx.detail.lingkungan || {}), ...hashBerkasUji(), versiBerkasExe: versiBerkas(EXE[nama]) };
    const hitung = R.daftar.reduce((a, x) => { a[x.status] = (a[x.status] || 0) + 1; return a; }, {});
    const hasil = {
        browser: { nama, versi: B.versi, userAgent: B.ua, ...catatPid, penghentian: henti },
        waktu: new Date().toISOString(),
        viewport: Object.keys(VP), keadaan: KEADAAN.map((k) => k.id), aksenKustom: AKSEN_KUSTOM,
        ringkasan: hitung,
        butir: R.daftar,
        detail: ctx.detail,
        foto: ctx.foto,
        fotoLain: [ctx.fotoModalPendek, ctx.fotoKartu].filter(Boolean),
        galatRunner: galat || undefined,
        belumDijalankan: ['Firefox', 'WebKit/Safari', 'perangkat sentuh fisik', 'halaman Blade nyata di aplikasi', 'plugin CSS lain'],
    };
    fs.mkdirSync(OUT, { recursive: true });
    fs.writeFileSync(path.join(OUT, `hasil-${nama}.json`), JSON.stringify(hasil, null, 1));
    console.log(`Ringkasan ${nama}:`, hitung, '| penghentian:', JSON.stringify(henti));
    return hasil;
}

fs.mkdirSync(OUT, { recursive: true });
for (const nama of DAFTAR_BROWSER) {
    try { await jalankan(nama); } catch (e) { console.error(`Browser ${nama} gagal:`, e.message); process.exitCode = 1; }
}

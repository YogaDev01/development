/**
 * Runner CDP F4 (redesain visual Feedback): menangkap screenshot dan metrik halaman nyata
 * pada viewport terdokumentasi (bawaan 1440x900 desktop, 390x844 mobile), terang/gelap,
 * keadaan terisi (dummy.staff) dan kosong (akun tanpa submission, bawaan dummy.maker).
 * HANYA MEMBACA (GET + login); tidak menulis submission/tanggapan. Pembukaan halaman show
 * oleh superadmin dapat mengubah status SUBMITTED -> REVIEWED (perilaku aplikasi); runner
 * membuka sekali tanpa foto (pemanasan) sebelum foto supaya keadaan sebelum/sesudah sama.
 *
 * Memakai ulang Halaman/CDP dari cdp-pages.mjs (opsi --base, --out, --label, --viewport,
 * --viewport-mobile, --chrome, --edge dibaca modul itu). Kata sandi HANYA lewat env var
 * TESYS_PW_STAFF / TESYS_PW_SUPERADMIN (maker: TESYS_PW_MAKER, jatuh ke TESYS_PW_STAFF);
 * akun superadmin: --user-superadmin=dummy.superadmin. Maks 1 login per akun per run.
 *
 * Pakai (jalur node CONTOH):
 *   node scripts/tailwind-harness/cdp-f4.mjs --label=sebelum --viewport=1440x900 \
 *        --user-superadmin=dummy.superadmin --out=docs/ui/tailwind-evidence/f4 [--browser=chrome|edge]
 *   Hasil: <out>/<label>/*.png dan <out>/hasil-f4-<label>-<browser>.json
 *
 * Keselamatan proses: sama dengan cdp-pages.mjs (profil sementara, port 9333/9334, hanya PID sendiri).
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { mulaiBrowser, hentikanBrowser, bukaKonteks, login, VP, AKUN, BASE, sleep } from './cdp-pages.mjs';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const arg = (nama, bawaan) => {
    const a = process.argv.find((x) => x.startsWith('--' + nama + '='));
    return a ? a.slice(nama.length + 3) : bawaan;
};
const LABEL = arg('label', 'sebelum');
const BROWSER = arg('browser', 'chrome');
const OUT_ROOT = path.resolve(AKAR, arg('out', 'docs/ui/tailwind-evidence/f4'));
const OUT_FOTO = path.join(OUT_ROOT, LABEL);
const FOTO_AKTIF = arg('foto', 'chrome').split(',').includes(BROWSER);
const MAKER = { username: arg('user-kosong', 'dummy.maker'), pw: process.env.TESYS_PW_MAKER || process.env.TESYS_PW_STAFF || '' };
// --halaman=index-terisi,index-kosong membatasi tangkapan ke id tertentu (urutan/login tetap sama).
const HALAMAN = arg('halaman', '').split(',').filter(Boolean);
const TINGGI_MAKS = Number(arg('tinggi-maks', '1500'));

const sha = (x) => crypto.createHash('sha256').update(x).digest('hex');
const hashBerkas = (rel) => { try { const b = fs.readFileSync(path.join(AKAR, rel)); return { sha256: sha(b), byte: b.length }; } catch { return null; } };

// Sensus kelas di area konten: berapa kelas legacy vs `tw:`; utilitas tw: aktif?
function sensus() {
    const vis = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const akar = document.querySelector('.main > .content') || document.querySelector('.content') || document.body;
    const legacy = {}; const tw = {};
    let nEl = 0, nTw = 0, nStyleAttr = 0;
    for (const el of akar.querySelectorAll('*')) {
        if (!vis(el)) continue;
        nEl++;
        if (el.getAttribute('style')) nStyleAttr++;
        let ada = false;
        for (const c of (el.getAttribute('class') || '').split(/\s+/).filter(Boolean)) {
            if (c.startsWith('tw:')) { ada = true; tw[c] = (tw[c] || 0) + 1; } else legacy[c] = (legacy[c] || 0) + 1;
        }
        if (ada) nTw++;
    }
    const top = (o, n) => Object.entries(o).sort((a, b) => b[1] - a[1]).slice(0, n);
    const jumlahSel = {};
    for (const s of ['.card', '.card-header', '.table', '.btn', '.badge', '.alert', '.breadcrumb', '.d-flex', '.text-muted', '.ts-empty', '.ts-action-primary', '.ts-actions', '.dataTables_wrapper', '[class*="tw:"]']) {
        jumlahSel[s] = [...akar.querySelectorAll(s)].filter(vis).length;
    }
    const aktif = {};
    const cek = (kelas, fn) => { const el = [...akar.querySelectorAll('*')].find((e) => vis(e) && (e.getAttribute('class') || '').split(/\s+/).includes(kelas)); aktif[kelas] = el ? fn(getComputedStyle(el)) : 'tidak-ada-elemen'; };
    cek('tw:inline-flex', (s) => s.display);
    cek('tw:rounded-md', (s) => s.borderTopLeftRadius);
    cek('tw:bg-surface', (s) => s.backgroundColor);
    cek('tw:border-border', (s) => s.borderTopColor);
    const twLinks = [...document.querySelectorAll('link[rel=stylesheet]')].filter((l) => (l.getAttribute('href') || '').includes('tesys-tw.css'));
    let aturanTw = null;
    try { const ss = [...document.styleSheets].find((x) => (x.href || '').includes('tesys-tw.css')); aturanTw = ss ? ss.cssRules.length : 0; } catch { aturanTw = 'tidak-terbaca'; }
    return {
        elemenTampakDiKonten: nEl, elemenBerkelasTw: nTw, elemenBerStyleAttr: nStyleAttr,
        kelasLegacyUnik: Object.keys(legacy).length, kelasTwUnik: Object.keys(tw).length,
        legacyTeratas: top(legacy, 25), twTeratas: top(tw, 15), jumlahSelektor: jumlahSel, utilitasTwAktif: aktif,
        tagLinkTesysTw: twLinks.length, aturanTesysTw: aturanTw,
    };
}


// Konflik CSS legacy: untuk setiap elemen tampak di konten dan setiap aturan tw (termasuk varian
// media yang aktif) yang cocok dengannya, bandingkan nilai terkomputasi dengan nilai deklarasi aturan
// itu (diukur lewat elemen penyelidik di induk yang sama). Selisih = aturan tw dikalahkan legacy
// (kecuali bila ber-!important dan menang). Properti berukuran piksel/persen bergantung tata letak
// dilaporkan terpisah (perlu dibaca manual).
function konflikTw() {
    const ss = [...document.styleSheets].find((x) => (x.href || '').includes('tesys-tw.css'));
    if (!ss) return { galat: 'stylesheet tesys-tw tidak ada' };
    const aturan = [];
    const jalan = (rules) => {
        for (const r of rules) {
            if (r.constructor.name === 'CSSStyleRule') aturan.push(r);
            else if (r.constructor.name === 'CSSMediaRule') { try { if (matchMedia(r.conditionText || r.media.mediaText).matches) jalan(r.cssRules); } catch { /* abaikan */ } }
            else if (r.cssRules && ['CSSLayerBlockRule', 'CSSSupportsRule'].includes(r.constructor.name)) jalan(r.cssRules);
        }
    };
    jalan(ss.cssRules);
    const vis = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
    const akar = document.querySelector('.main > .content') || document.body;
    const probe = document.createElement('div');
    const tataLetak = /^(width|height|min-width|min-height|max-width|max-height|inline-size|block-size|flex|flex-basis|flex-grow|flex-shrink|grid-template-columns|margin-inline|margin-block|padding-inline|padding-block)/;
    const kelompok = new Map();
    let dicek = 0, cocok = 0;
    for (const el of [akar, ...akar.querySelectorAll('*')]) {
        if (!vis(el)) continue;
        const cs = getComputedStyle(el);
        for (const r of aturan) {
            let ok = false;
            try { ok = el.matches(r.selectorText); } catch { ok = false; }
            if (!ok) continue;
            cocok++;
            // Deklarasi dibaca dari cssText (bentuk singkat berisi var() tak terbaca per-properti lewat style[i]).
            for (const dekl of r.style.cssText.split(';').map((x) => x.trim()).filter(Boolean)) {
                const idx = dekl.indexOf(':');
                const prop = dekl.slice(0, idx).trim();
                if (idx < 0 || prop.startsWith('--')) continue;
                const penting = /!important\s*$/.test(dekl);
                const nilai = dekl.slice(idx + 1).replace(/!important\s*$/, '').trim();
                // Penyelidik meniru konteks tipografi elemen dan memakai border-style solid agar lebar tepi
                // terukur (bila tidak, border-width terkomputasi 0 dan line-height relatif memakai font induk).
                probe.style.cssText = 'display:none;border-style:solid;font-size:' + cs.fontSize + ';';
                probe.style.setProperty(prop, nilai);
                el.insertAdjacentElement('afterend', probe);
                const cp = getComputedStyle(probe);
                const longhand = [];
                for (let i = 0; i < probe.style.length; i++) {
                    const lp = probe.style[i];
                    const sisipan = (lp === 'display' && prop !== 'display') || (lp === 'font-size' && prop !== 'font-size') || (lp.startsWith('border-') && lp.endsWith('-style') && !prop.startsWith('border'));
                    if (!sisipan) longhand.push(lp);
                }
                const harapMap = {};
                for (const lp of longhand) harapMap[lp] = cp.getPropertyValue(lp);
                probe.remove();
                for (const lp of longhand) {
                    dicek++;
                    const nyata = cs.getPropertyValue(lp);
                    if (harapMap[lp] === nyata) continue;
                    const k = r.selectorText.replace(/\\/g, '').slice(0, 90) + ' | ' + lp + (penting ? ' !important' : '');
                    const g = kelompok.get(k) || { aturan: k, harap: harapMap[lp], nyata, jumlah: 0, tataLetak: tataLetak.test(lp), contoh: el.tagName.toLowerCase() };
                    g.jumlah++; kelompok.set(k, g);
                }
            }
        }
    }
    return { aturanTw: aturan.length, pasanganAturanElemen: cocok, propertiDicek: dicek, selisih: [...kelompok.values()].sort((a, b) => b.jumlah - a.jumlah) };
}

async function foto(P, nama, v) {
    if (!FOTO_AKTIF) return null;
    fs.mkdirSync(OUT_FOTO, { recursive: true });
    // Mobile saja: tangkapan diperpanjang (tinggi tangkapan, BUKAN viewport ukur) sampai dasar konten
    // (dibatasi TINGGI_MAKS) supaya beberapa kartu/baris terlihat; layout shell menggulir di dalam .main,
    // jadi tinggi dokumen tidak bisa dipakai. Desktop: tangkapan = viewport apa adanya.
    // Metrik (ukur/sensus) diambil SEBELUM perpanjangan, pada viewport terdokumentasi.
    let perlu = v.h;
    if (v.mobile) {
        const bawah = await P.ev(`(()=>{const c=document.querySelector('.main > .content');if(!c)return 0;const m=c.closest('.main');const t=(e)=>e.scrollHeight+Math.max(0,e.getBoundingClientRect().top);return Math.ceil(Math.max(t(c),m?t(m):0,document.documentElement.scrollHeight))})()`);
        perlu = Math.min(TINGGI_MAKS, Math.max(v.h, bawah + 40));
    }
    if (perlu > v.h) {
        await P.send('Emulation.setDeviceMetricsOverride', { width: v.w, height: perlu, deviceScaleFactor: 1, mobile: !!v.mobile, screenWidth: v.w, screenHeight: perlu });
        await sleep(400);
    }
    const { data } = await P.send('Page.captureScreenshot', { format: 'png' });
    if (perlu > v.h) await P.viewport(v);
    const f = path.join(OUT_FOTO, nama + '.png');
    fs.writeFileSync(f, Buffer.from(data, 'base64'));
    return path.relative(AKAR, f).replace(/\\/g, '/');
}

async function main() {
    fs.mkdirSync(OUT_ROOT, { recursive: true });
    const R = {
        tahap: 'F4', label: LABEL, browser: BROWSER, base: BASE, waktu: new Date().toISOString(),
        viewport: { desktop: VP.desktop.id, mobile: VP.mobile.id }, tinggiMaksFoto: TINGGI_MAKS,
        akun: { staff: AKUN.staff.username, superadmin: AKUN.superadmin.username, kosong: MAKER.username },
        berkas: {
            viewFeedback: Object.fromEntries(['index', 'create', 'show', 'inbox', '_disclaimer'].map((n) => [n, hashBerkas(`resources/views/feedback/${n}.blade.php`)])),
            komponenTw: Object.fromEntries(['badge', 'button', 'card', 'input', 'select', 'modal', 'table', 'page-header', 'stat', 'textarea'].map((n) => [n, hashBerkas(`resources/views/components/tw/${n}.blade.php`)])),
            tesysTwCss: hashBerkas('public/css/tesys-tw.css'),
        },
        login: [], halaman: [], galatRunner: [], berkasFoto: [],
    };
    if (!AKUN.staff.pw || !AKUN.superadmin.pw || !MAKER.pw) { console.error('Kata sandi env var tidak lengkap.'); process.exit(2); }
    const B = await mulaiBrowser(BROWSER);
    R.versiBrowser = B.versi;
    const konteks = [];
    const ambil = async (P, id, url, tema, v, { prasyarat = null, fotoNama = null } = {}) => {
        if (HALAMAN.length && !HALAMAN.includes(id)) return;
        try {
            await P.viewport(v); await P.setTema(tema);
            const dimuat = await P.goto(url);
            if (prasyarat) await prasyarat(P);
            const m = await P.ukur({ fokus: false });
            m.loadEventTercapai = dimuat;
            m.sensus = await P.ev(`(${sensus.toString()})()`);
            m.konflikTw = await P.ev(`(${konflikTw.toString()})()`);
            m.foto = await foto(P, fotoNama || `${id}-${tema}-${v.id}`, v);
            if (m.foto) R.berkasFoto.push(m.foto);
            // Interaksi (index terisi, desktop, terang): hover baris dan fokus papan ketik pada tautan "Lihat".
            if (id === 'index-terisi' && tema === 'terang' && !v.mobile) {
                const pos = await P.ev(`(()=>{const tr=document.querySelectorAll('.content tbody tr')[2];if(!tr)return null;tr.scrollIntoView({block:'center'});const r=tr.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2}})()`);
                if (pos) {
                    await P.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: pos.x, y: pos.y });
                    await sleep(400);
                    m.hoverBaris = await P.ev(`(()=>{const tr=document.querySelectorAll('.content tbody tr')[2];const td=tr.querySelector('td');return {trBg:getComputedStyle(tr).backgroundColor,tdBg:getComputedStyle(td).backgroundColor,trTransition:getComputedStyle(tr).transition.slice(0,80),surface2:(()=>{const e=document.createElement('i');e.style.backgroundColor='var(--surface-2)';document.body.appendChild(e);const c=getComputedStyle(e).backgroundColor;e.remove();return c})()}})()`);
                    await P.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 5, y: 5 });
                }
                await P.goto(url); // Tab murni dari awal dokumen (tanpa titik awal fokus bekas hover)
                const urut = [];
                let ketemu = null;
                for (let i = 0; i < 60 && !ketemu; i++) {
                    await P.tab(); await sleep(120);
                    const a = await P.ev(`(()=>{const e=document.activeElement;if(!e||e===document.body)return null;const s=getComputedStyle(e);const lbl=(e.getAttribute('aria-label')||e.textContent||'').trim().replace(/\\s+/g,' ').slice(0,50);return {tag:e.tagName.toLowerCase(),href:e.getAttribute('href'),lbl,fv:e.matches(':focus-visible'),outline:s.outlineStyle+' '+s.outlineWidth+' '+s.outlineColor,offset:s.outlineOffset}})()`);
                    urut.push(a);
                    if (a && a.tag === 'a' && /Lihat detail submission/.test(a.lbl)) ketemu = a;
                }
                m.fokusTab = { tabKe: urut.length, ketemuLihatPertama: ketemu, urutan: urut };
            }
            R.halaman.push({ id, tema, viewport: v.id, ...m });
        } catch (e) {
            R.galatRunner.push(`${id} ${tema}-${v.id}: ${e.message}`);
            R.halaman.push({ id, tema, viewport: v.id, galat: e.message });
        }
    };
    const keduaVp = [VP.desktop, VP.mobile];
    try {
        // --- staff (terisi): baca id submission pertama
        const st = await bukaKonteks(B, VP.desktop); konteks.push(st);
        R.login.push(await login(st.P, AKUN.staff));
        await st.P.goto('/feedback');
        const info = await st.P.ev(`(()=>{const a=document.querySelector('.content tbody a[href*="/feedback/"]');const m=a?a.getAttribute('href').match(/feedback\\/(\\d+)/):null;return {id:m?Number(m[1]):null,baris:document.querySelectorAll('.content tbody tr').length}})()`);
        R.idShow = info.id; R.barisIndexStaff = info.baris;
        if (!info.id) throw new Error('Submission dummy.staff tidak ditemukan (keadaan terisi tidak tersedia).');

        // --- superadmin: pemanasan show (tanpa foto), lalu foto show + inbox
        const sa = await bukaKonteks(B, VP.desktop); konteks.push(sa);
        R.login.push(await login(sa.P, AKUN.superadmin));
        await sa.P.goto(`/feedback/${info.id}`);
        for (const v of keduaVp) await ambil(sa.P, 'show-superadmin', `/feedback/${info.id}`, 'terang', v);
        for (const v of keduaVp) await ambil(sa.P, 'inbox', '/feedback-inbox', 'terang', v);

        // --- akun kosong: login 1x
        const mk = await bukaKonteks(B, VP.desktop); konteks.push(mk);
        R.login.push(await login(mk.P, MAKER));
        await mk.P.goto('/feedback');
        R.barisIndexKosong = await mk.P.ev(`document.querySelectorAll('.content tbody tr').length`);
        R.sentinelKosong = await mk.P.ev(`({tabelAda: !!document.querySelector('.content table'), teks: (document.querySelector('.content')||document.body).innerText.replace(/\\s+/g,' ').slice(0,200)})`);
        for (const tema of ['terang', 'gelap']) for (const v of keduaVp) await ambil(mk.P, 'index-kosong', '/feedback', tema, v);

        // --- staff: index terisi, create, show
        const P = st.P;
        for (const tema of ['terang', 'gelap']) for (const v of keduaVp) await ambil(P, 'index-terisi', '/feedback', tema, v);
        const criticism = async (p) => { await p.pilih('#selCategory', 'CRITICISM'); await p.klik('label[for=chkWilling]'); await sleep(300); };
        for (const tema of ['terang', 'gelap']) for (const v of keduaVp) await ambil(P, 'create-criticism', '/feedback/create', tema, v, { prasyarat: criticism });
        for (const v of keduaVp) await ambil(P, 'show-staff', `/feedback/${info.id}`, 'terang', v);
    } catch (e) {
        R.galatRunner.push('FATAL: ' + e.message);
    }
    for (const k of konteks) { try { await B.cdp.send('Target.disposeBrowserContext', { browserContextId: k.browserContextId }); } catch { /* abaikan */ } }
    R.penghentian = await hentikanBrowser(B);
    const f = path.join(OUT_ROOT, `hasil-f4-${LABEL}-${BROWSER}.json`);
    fs.writeFileSync(f, JSON.stringify(R, null, 1));
    console.log(`selesai: ${R.halaman.length} pengukuran, ${R.berkasFoto.length} foto, galatRunner=${R.galatRunner.length}, sisaProses=${R.penghentian?.sisaProsesPenanda}`);
    console.log('->', path.relative(AKAR, f));
    if (R.galatRunner.length) console.log('galatRunner:', R.galatRunner.join(' | '));
    process.exit(R.penghentian?.sisaProsesPenanda === 0 && !R.galatRunner.length ? 0 : 1);
}
main();

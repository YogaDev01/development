/**
 * Perbandingan berdampingan (sebelum | sesudah) dari screenshot F4: membuat HTML sementara berisi dua
 * <img>, membukanya di SATU browser headless milik runner (profil sementara, port 9333/9334, hanya PID
 * sendiri; modul cdp-pages.mjs), lalu menyimpan PNG gabungan. Tanpa dependensi, tanpa jaringan, tanpa login.
 *
 * Pakai (jalur node CONTOH):
 *   node scripts/tailwind-harness/cdp-banding.mjs --out=docs/ui/tailwind-evidence/f4 \
 *        --pasang=index-terisi-terang-1440x900,index-terisi-terang-390x844
 *   Masukan : <out>/sebelum/<nama>.png dan <out>/sesudah/<nama>.png
 *   Keluaran: <out>/perbandingan/<nama>.png
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { mulaiBrowser, hentikanBrowser, bukaKonteks, sleep } from './cdp-pages.mjs';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const arg = (nama, bawaan) => {
    const a = process.argv.find((x) => x.startsWith('--' + nama + '='));
    return a ? a.slice(nama.length + 3) : bawaan;
};
const OUT = path.resolve(AKAR, arg('out', 'docs/ui/tailwind-evidence/f4'));
const BROWSER = arg('browser', 'chrome');
const PASANG = arg('pasang', '').split(',').filter(Boolean);
const JARAK = 24;
const TAJUK = 36;

const ukuranPng = (f) => { const b = fs.readFileSync(f); return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) }; };

async function main() {
    if (!PASANG.length) { console.error('--pasang=<nama>,<nama> wajib.'); process.exit(2); }
    fs.mkdirSync(path.join(OUT, 'perbandingan'), { recursive: true });
    const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'tesys-banding-'));
    const B = await mulaiBrowser(BROWSER);
    const k = await bukaKonteks(B, { w: 800, h: 600, mobile: false });
    const hasil = [];
    let kode = 0;
    try {
        for (const nama of PASANG) {
            const a = path.join(OUT, 'sebelum', nama + '.png');
            const b = path.join(OUT, 'sesudah', nama + '.png');
            if (!fs.existsSync(a) || !fs.existsSync(b)) { console.error('berkas tidak lengkap:', nama); kode = 1; continue; }
            const ua = ukuranPng(a), ub = ukuranPng(b);
            const W = ua.w + ub.w + JARAK * 3;
            const H = Math.max(ua.h, ub.h) + TAJUK + JARAK * 2;
            const html = `<!doctype html><meta charset="utf-8"><style>
body{margin:0;background:#cfcfcf;font:600 15px/1 Arial,sans-serif;color:#222}
.k{display:flex;gap:${JARAK}px;padding:${JARAK}px;align-items:flex-start}
.p{display:flex;flex-direction:column;gap:10px}.p img{display:block;box-shadow:0 0 0 1px #888}
</style><div class="k"><div class="p"><div>SEBELUM (${nama})</div><img src="${pathToFileURL(a).href}"></div><div class="p"><div>SESUDAH (${nama})</div><img src="${pathToFileURL(b).href}"></div></div>`;
            const hf = path.join(tmp, nama + '.html');
            fs.writeFileSync(hf, html);
            await k.P.send('Emulation.setDeviceMetricsOverride', { width: W, height: H, deviceScaleFactor: 1, mobile: false, screenWidth: W, screenHeight: H });
            k.P.reset();
            await k.P.send('Page.navigate', { url: pathToFileURL(hf).href });
            await k.P.tungguLoad();
            const ok = await k.P.ev('[...document.images].every((i)=>i.complete&&i.naturalWidth>0)');
            const { data } = await k.P.send('Page.captureScreenshot', { format: 'png' });
            const f = path.join(OUT, 'perbandingan', nama + '.png');
            fs.writeFileSync(f, Buffer.from(data, 'base64'));
            hasil.push({ nama, gambarTermuat: ok, lebar: W, tinggi: H, berkas: path.relative(AKAR, f).replace(/\\/g, '/') });
            if (!ok) kode = 1;
        }
    } catch (e) { console.error('galat:', e.message); kode = 1; }
    try { await B.cdp.send('Target.disposeBrowserContext', { browserContextId: k.browserContextId }); } catch { /* abaikan */ }
    const peng = await hentikanBrowser(B);
    fs.rmSync(tmp, { recursive: true, force: true });
    console.log(JSON.stringify({ hasil, penghentian: peng }, null, 1));
    process.exit(kode || (peng.sisaProsesPenanda === 0 ? 0 : 1));
}
main();

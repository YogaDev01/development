/**
 * QA tambahan F3 (pilot Feedback) pada HALAMAN NYATA: target sentuh 390x844, kontras teks/fokus
 * (terang & gelap, 8 praset aksen), urutan Tab + indikator fokus, nama aksesibel tombol ikon,
 * teks badge, zoom desktop, kontrak id/name/kelas Bootstrap, escape XSS, aktivasi papan ketik.
 *
 * Memakai klien CDP dari cdp-pages.mjs (modul; main() runner tidak dijalankan). Server pilot dan
 * kata sandi uji sama dengan cdp-pages.mjs (TESYS_PW_SUPERADMIN / TESYS_PW_STAFF atau --pw-*).
 * MENULIS 1 submission uji ke DB uji (lalu ditanggapi dan ditutup).
 *
 *   node scripts/tailwind-harness/cdp-qa-f3.mjs --browser=chrome   (atau edge)
 *   Hasil: docs/ui/tailwind-evidence/f3/hasil-qa-sesudah-<browser>.json
 *
 * Aturan keselamatan proses sama dengan cdp-pages.mjs (port 9333/9334, user-data-dir baru,
 * hanya mematikan proses milik sendiri).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { mulaiBrowser, hentikanBrowser, bukaKonteks, login, VP, AKUN, sleep } from './cdp-pages.mjs';

const AKAR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const nama = (process.argv.find((x) => x.startsWith('--browser=')) || '--browser=chrome').split('=')[1];
const LABEL = (process.argv.find((x) => x.startsWith('--label=')) || '--label=sesudah').split('=')[1];
// Tombol navigasi kini <a href> (x-tw.button href), bukan <button> di dalam form GET.
const NAV = '.content a.tw\\:inline-flex[href]';
const OUT = path.join(AKAR, 'docs/ui/tailwind-evidence/f3', `hasil-qa-${LABEL}-${nama}.json`);
const PRASET = ['#92232b', '#1f3a5f', '#0f6e66', '#2f6d43', '#3d3a8c', '#3f4a59', '#9a4a23', '#6a2a63'];

// ---- fungsi di dalam halaman ------------------------------------------------------------------
function dalamHalaman() {
    const kanvas = document.createElement('canvas'); kanvas.width = kanvas.height = 1;
    const cx = kanvas.getContext('2d', { willReadFrequently: true });
    const rgba = (c) => { cx.clearRect(0, 0, 1, 1); cx.fillStyle = '#000'; cx.fillStyle = c; cx.fillRect(0, 0, 1, 1); const d = cx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
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

const SKRIP_BANTU = `(${dalamHalaman.toString()})`;

async function kontras(P, daftar) {
    // daftar: [{sel, jenis:'teks'|'placeholder'|'ikon', nama}] -> rasio fg vs bg efektif
    return P.ev(`(()=>{const H=${SKRIP_BANTU}();const hasil=[];for(const d of ${JSON.stringify(daftar)}){const els=[...document.querySelectorAll(d.sel)].filter(H.vis);if(!els.length){hasil.push({nama:d.nama,sel:d.sel,ada:false});continue;}for(const el of els.slice(0,d.maks||6)){const s=d.jenis==='placeholder'?getComputedStyle(el,'::placeholder'):getComputedStyle(el);const fg=H.rgba(s.color);const bg=H.bgEfektif(el);const fgc=H.campur(fg,bg);hasil.push({nama:d.nama,sel:d.sel,ada:true,teks:(el.textContent||'').trim().slice(0,24),fg:H.hex(fgc),bg:H.hex(bg),rasio:Math.round(H.rasio(fgc,bg)*100)/100,batas:d.batas||4.5});}}return hasil;})()`);
}

async function ukurKontrol(P, sels) {
    return P.ev(`(()=>{const z=parseFloat(getComputedStyle(document.documentElement).zoom)||1;return ${JSON.stringify(sels)}.map((sel)=>{const e=[...document.querySelectorAll(sel)].find((x)=>{const r=x.getBoundingClientRect();return r.width>0&&r.height>0});if(!e)return{sel,ada:false};const r=e.getBoundingClientRect();return{sel,ada:true,zoom:z,w:Math.round(r.width/z*10)/10,h:Math.round(r.height/z*10)/10,wLayar:Math.round(r.width),hLayar:Math.round(r.height)}})})()`);
}

async function namaAksesibel(P, sel) {
    const { root } = await P.send('DOM.getDocument', { depth: 0 });
    const { nodeId } = await P.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel });
    if (!nodeId) return { sel, ada: false };
    const { nodes } = await P.send('Accessibility.getPartialAXTree', { nodeId, fetchRelatives: false });
    const n = nodes[0] || {};
    return { sel, ada: true, role: n.role?.value, nama: n.name?.value ?? null, sumberNama: n.name?.sources?.find((s) => s.value)?.type ?? null };
}

async function urutanTab(P, awalSel, maks) {
    await P.ev(`document.activeElement&&document.activeElement.blur();window.scrollTo(0,0)`);
    await P.tab(); await sleep(150); // menandai modalitas papan ketik (:focus-visible)
    await P.ev(`document.querySelector(${JSON.stringify(awalSel)}).focus()`); await sleep(550);
    const urut = [];
    for (let i = 0; i < maks; i++) {
        const info = await P.ev(`(()=>{const H=${SKRIP_BANTU}();const e=document.activeElement;if(!e||e===document.body)return null;const s=getComputedStyle(e);const par=H.bgEfektif(e.parentElement||e);const oc=H.campur(H.rgba(s.outlineColor),par);return{el:e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.name?'[name='+e.name+']':''),dalamContent:!!e.closest('.content'),fokusVisible:e.matches(':focus-visible'),outline:s.outlineStyle+' '+s.outlineWidth,outlineOffset:s.outlineOffset,warna:H.hex(oc),rasioOutline:Math.round(H.rasio(oc,par)*100)/100,boxShadow:s.boxShadow==='none'?'none':'ada'}})()`);
        if (!info) break;
        urut.push(info);
        if (!info.dalamContent && urut.length > 1) break; // keluar dari area konten
        await P.tab(); await sleep(550); // > durasi transisi outline-color (token --dur)
    }
    return urut;
}

/**
 * Tombol navigasi sebagai tautan (x-tw.button href): elemen <a> dengan href benar, tanpa pembungkus form,
 * tanpa akhiran "?", klik biasa menavigasi, ctrl+klik membuka tab baru (tab ditutup runner), Enter menavigasi,
 * nama aksesibel (peran link), teks tak berubah menjadi "Memproses...".
 */
async function periksaTautan(P, jalur, tujuan) {
    await P.setTema('terang'); await P.goto(jalur);
    const info = await P.ev(`(()=>{const a=document.querySelector(${JSON.stringify(NAV)});if(!a)return null;return{tag:a.tagName.toLowerCase(),href:a.getAttribute('href'),pathname:new URL(a.href).pathname,search:new URL(a.href).search,dalamForm:!!a.closest('form'),teks:a.textContent.trim().replace(/\s+/g,' '),busyAttr:a.getAttribute('aria-busy'),disabled:a.hasAttribute('aria-disabled')||a.hasAttribute('disabled'),jumlahFormGet:document.querySelectorAll('.content form[method=GET i]').length}})()`);
    if (!info) return { ada: false, jalur, tujuan };
    const ax = await namaAksesibel(P, NAV);
    // ctrl+klik -> tab baru
    const halaman = (t) => t.filter((x) => x.type === 'page').map((x) => x.targetId);
    await P.ev(`document.querySelector(${JSON.stringify(NAV)}).scrollIntoView({block:'center',behavior:'instant'})`); await sleep(600);
    const pos = await P.ev(`(()=>{const r=document.querySelector(${JSON.stringify(NAV)}).getBoundingClientRect();return{x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    const sebelum = (await P.cdp.send('Target.getTargets')).targetInfos;
    await P.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: pos.x, y: pos.y, modifiers: 2 });
    await P.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: pos.x, y: pos.y, button: 'left', buttons: 1, clickCount: 1, modifiers: 2 });
    await P.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: pos.x, y: pos.y, button: 'left', buttons: 0, clickCount: 1, modifiers: 2 });
    await sleep(900);
    const sesudah = (await P.cdp.send('Target.getTargets')).targetInfos;
    const baru = halaman(sesudah).filter((id) => !halaman(sebelum).includes(id));
    const urlBaru = sesudah.filter((t) => baru.includes(t.targetId)).map((t) => String(t.url).replace(/^https?:\/\/[^/]+/, ''));
    for (const id of baru) { try { await P.cdp.send('Target.closeTarget', { targetId: id }); } catch { /* abaikan */ } }
    const jalurAsalTetap = (await P.ev('location.pathname')) === jalur;
    // klik biasa
    const nav = await P.aksiNavigasi(() => P.klik(NAV));
    const setelah = await P.ev('({p:location.pathname,s:location.search,u:location.pathname+location.search+location.hash})');
    // Enter
    await P.goto(jalur);
    await P.ev(`document.activeElement&&document.activeElement.blur()`); await P.tab(); await sleep(80);
    await P.ev(`document.querySelector(${JSON.stringify(NAV)}).focus()`);
    const navEnter = await P.aksiNavigasi(() => P.enter());
    const setelahEnter = await P.ev('({p:location.pathname,s:location.search})');
    const ok = info.tag === 'a' && info.pathname === tujuan && info.search === '' && !/\?/.test(info.href) && !info.dalamForm && info.jumlahFormGet === 0 && !info.disabled && info.busyAttr === null
        && !/Memproses/.test(info.teks) && ax.role === 'link' && !!ax.nama && jalurAsalTetap && baru.length === 1 && urlBaru.some((u) => u.startsWith(tujuan))
        && nav && setelah.p === tujuan && setelah.s === '' && navEnter && setelahEnter.p === tujuan && setelahEnter.s === '';
    return { ok, jalur, tujuan, info, ax, ctrlKlik: { tabBaru: baru.length, urlBaru, halamanAsalTetap: jalurAsalTetap }, klikBiasa: { navigasi: nav, ...setelah }, enter: { navigasi: navEnter, ...setelahEnter } };
}

async function kontrakDom(P, sels) {
    return P.ev(`${JSON.stringify(sels)}.map((s)=>({sel:s,n:document.querySelectorAll(s).length}))`);
}

async function main() {
    const B = await mulaiBrowser(nama);
    const R = { browser: nama, versi: B.versi, waktu: new Date().toISOString(), butir: [], galat: [] };
    const butir = (id, status, detail) => { R.butir.push({ id, status, detail }); console.log(`[${nama}] ${status.padEnd(7)} ${id}`); };
    const konteks = [];
    try {
        // ===== STAFF =====
        const st = await bukaKonteks(B, VP.mobile); konteks.push(st); const P = st.P;
        await login(P, AKUN.staff);

        // ---- Target sentuh 390x844 (create)
        await P.viewport(VP.mobile); await P.setTema('terang');
        await P.goto('/feedback/create');
        await P.ev(`document.querySelector('#chkWilling').click()`); await sleep(200);
        const sentuhCreate = await ukurKontrol(P, [NAV, '#selCategory', 'input#title', 'div:has(> #chkWilling)', 'label[for=chkWilling]', 'input#inpWhatsapp', '#frmFeedback button[type=submit]']);
        const sentuhIdx = await (async () => { await P.goto('/feedback'); return ukurKontrol(P, [NAV]); })();
        butir('sentuh-44-create-390', sentuhCreate.filter((x) => x.ada && x.sel !== 'label[for=chkWilling]').every((x) => x.w >= 44 && x.h >= 44) ? 'PASS' : 'FAIL', { create: sentuhCreate, index: sentuhIdx, catatan: 'F4-b: baris centang (div:has(> #chkWilling), seluruhnya klik-able lewat label::after) ikut kriteria; label[for=chkWilling] sendiri hanya dilaporkan (kotak teksnya bukan area klik penuh)' });

        // ---- Kontrak DOM create
        await P.viewport(VP.desktop); await P.goto('/feedback/create');
        const kontrakCreate = await kontrakDom(P, ['form#frmFeedback[action$="/feedback"][method=POST]', 'input[name=_token]', 'select#selCategory[name=category][required]', 'input#title[name=title][maxlength="150"][required]', 'textarea#inpMessage[name=message]', 'textarea#inpCriticism[name=criticism_text]', 'textarea#inpSuggestion[name=suggestion_text]', 'input#chkWilling[name=willing_to_discuss][value="1"][type=checkbox]', 'input#inpWhatsapp[name=whatsapp_number]', '#wrapMessage', '#wrapCriticism', '#wrapWhatsapp', '#wrapWhatsapp .form-group', 'label[for=chkWilling]', '.feedback-disclaimer', '#frmFeedback .tw\\:border-b', 'label[for=selCategory]', 'label[for=title]', 'label[for=inpWhatsapp]', '#inpWhatsapp[aria-describedby="inpWhatsapp-hint"]', '#inpWhatsapp-hint']);
        butir('kontrak-dom-create', kontrakCreate.every((k) => k.n >= 1) ? 'PASS' : 'FAIL', kontrakCreate.filter((k) => k.n < 1));

        // ---- Nama aksesibel tombol ikon (kembali)
        const axBack = await namaAksesibel(P, NAV);
        butir('nama-aksesibel-tombol-kembali-create', axBack.ada && axBack.role === 'link' && /Kembali/.test(axBack.nama || '') ? 'PASS' : 'FAIL', axBack);
        await P.goto('/feedback');
        const axBuat = await namaAksesibel(P, NAV);
        butir('nama-aksesibel-tombol-buat-index', axBuat.ada && /Buat Submission/.test(axBuat.nama || '') ? 'PASS' : 'FAIL', axBuat);

        // ---- Urutan Tab + indikator fokus (create, terang 1366)
        for (const tema of ['terang', 'gelap']) {
            await P.setTema(tema); await P.goto('/feedback/create');
            await P.ev(`document.querySelector('#chkWilling').click()`); await sleep(200);
            const urut = await urutanTab(P, NAV, 12);
            const ids = urut.map((u) => u.el);
            const harapan = ['a', 'select#selCategory[name=category]', 'input[name=title]'];
            const urutanBenar = ids[0] === 'a' && ids[1] === 'select#selCategory[name=category]' && ids[2] === 'input#title[name=title]' && ids.indexOf('textarea#inpMessage[name=message]') === 3 && ids.indexOf('input#chkWilling[name=willing_to_discuss]') === 4 && ids.indexOf('input#inpWhatsapp[name=whatsapp_number]') === 5 && ids[6] === 'a' && ids[7] === 'button';
            const twKontrol = urut.filter((u) => ['a', 'select#selCategory[name=category]', 'input#title[name=title]', 'input#inpWhatsapp[name=whatsapp_number]'].includes(u.el));
            const fokusOk = twKontrol.every((u) => u.fokusVisible && /solid|auto/.test(u.outline) && !/ 0px/.test(u.outline) && u.rasioOutline >= 3);
            butir(`urutan-tab-create-${tema}`, urutanBenar ? 'PASS' : 'FAIL', { urut: ids, harapan });
            butir(`indikator-fokus-komponen-${tema}`, fokusOk ? 'PASS' : 'FAIL', twKontrol);
            R[`tab_${tema}`] = urut;
        }
        // aktivasi papan ketik tombol kembali (Enter) -> /feedback
        await P.setTema('terang'); await P.goto('/feedback/create');
        await P.ev(`document.activeElement&&document.activeElement.blur()`); await P.tab(); await sleep(80);
        await P.ev(`document.querySelector(${JSON.stringify(NAV)}).focus()`);
        const navEnter = await P.aksiNavigasi(() => P.enter());
        butir('keyboard-enter-tombol-kembali', navEnter && (await P.ev('location.pathname')) === '/feedback' ? 'PASS' : 'FAIL', { path: await P.ev('location.pathname') });

        // ---- Tombol navigasi = <a href> (create -> /feedback, index -> /feedback/create)
        await P.viewport(VP.desktop);
        const tautCreate = await periksaTautan(P, '/feedback/create', '/feedback');
        butir('tautan-href-kembali-create', tautCreate.ok ? 'PASS' : 'FAIL', tautCreate);
        const tautIdx = await periksaTautan(P, '/feedback', '/feedback/create');
        butir('tautan-href-buat-index', tautIdx.ok ? 'PASS' : 'FAIL', tautIdx);

        // ---- Kontras: create + index (terang/gelap) default
        const DAFTAR_CREATE = [
            { nama: 'label', sel: '.content label[for=selCategory], .content label[for=title]' },
            { nama: 'select-teks', sel: '#selCategory' }, { nama: 'input-teks', sel: 'input#title' },
            { nama: 'placeholder', sel: 'input#title', jenis: 'placeholder' },
            { nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' },
            { nama: 'tombol-kembali', sel: NAV },
            { nama: 'kartu-teks', sel: '.content label[for=selCategory]' },
        ];
        const kontrasHasil = {};
        for (const tema of ['terang', 'gelap']) {
            await P.viewport(VP.desktop); await P.setTema(tema); await P.goto('/feedback/create');
            await P.ev(`document.querySelector('#chkWilling').click()`); await sleep(150);
            const k1 = await kontras(P, [...DAFTAR_CREATE, { nama: 'hint', sel: '#inpWhatsapp-hint', batas: 4.5 }, { nama: 'placeholder-wa', sel: 'input#inpWhatsapp', jenis: 'placeholder' }]);
            await P.goto('/feedback');
            const k2 = await kontras(P, [{ nama: 'badge', sel: '.content .tw\\:rounded-pill:not([aria-hidden=true])', maks: 12 }, { nama: 'th', sel: '.content thead th', maks: 6 }, { nama: 'td', sel: '.content tbody td', maks: 6 }, { nama: 'tombol-buat', sel: NAV }]);
            kontrasHasil[tema] = [...k1, ...k2];
            const gagal = kontrasHasil[tema].filter((x) => x.ada && x.rasio < x.batas);
            butir(`kontras-teks-${tema}-aksen-bawaan`, gagal.length === 0 ? 'PASS' : 'FAIL', { jumlah: kontrasHasil[tema].filter((x) => x.ada).length, gagal, minimum: Math.min(...kontrasHasil[tema].filter((x) => x.ada).map((x) => x.rasio)) });
        }
        R.kontrasDetail = kontrasHasil;

        // ---- Badge: teks membawa makna + warna
        await P.setTema('terang'); await P.goto('/feedback');
        const badges = await P.ev(`[...document.querySelectorAll('.content .tw\\\\:rounded-pill:not([aria-hidden=true])')].map((b)=>({t:b.textContent.trim(),bg:getComputedStyle(b).backgroundColor,fg:getComputedStyle(b).color}))`);
        butir('badge-teks-bermakna', badges.length > 0 && badges.every((b) => b.t.length > 0) && new Set(badges.map((b) => b.t)).size >= 3 ? 'PASS' : 'FAIL', { jumlah: badges.length, teksUnik: [...new Set(badges.map((b) => b.t))], pasanganWarna: [...new Map(badges.map((b) => [b.t + '|' + b.bg + '|' + b.fg, b])).values()].slice(0, 10) });

        // ---- Kontrak index
        const kontrakIdx = await kontrakDom(P, ['.table-responsive table', 'a[aria-label^="Lihat detail submission"][href*="/feedback/"]', 'thead tr', '.tw\\:grid .tw\\:min-w-0']);
        butir('kontrak-dom-index', kontrakIdx.every((k) => k.n >= 1) && (await P.ev(`document.querySelectorAll('thead tr').length`)) === 1 ? 'PASS' : 'FAIL', kontrakIdx);

        // ---- Zoom desktop + tinggi kontrol
        await P.viewport(VP.desktop); await P.goto('/feedback/create');
        const zoomDesk = await P.ev(`getComputedStyle(document.documentElement).zoom`);
        const tinggiDesk = await ukurKontrol(P, ['#selCategory', 'input#title', '#frmFeedback button[type=submit]', NAV]);
        await P.viewport(VP.mobile); await P.goto('/feedback/create');
        const zoomMob = await P.ev(`getComputedStyle(document.documentElement).zoom`);
        butir('zoom-desktop-0.8', zoomDesk === '0.8' && zoomMob === '1' ? 'PASS' : 'FAIL', { desktop: zoomDesk, mobile: zoomMob, tinggiCssPxDesktop: tinggiDesk.map((x) => ({ sel: x.sel, h: x.h, hLayar: x.hLayar })) });

        // ---- Kiriman valid ber-XSS (escape) lewat UI, kemudian superadmin
        await P.viewport(VP.desktop); await P.setTema('terang'); await P.goto('/feedback/create');
        await P.pilih('#selCategory', 'SUGGESTION');
        const JUDUL = '<i id="xss-uji">F3 QA</i> & <script>window.__x=1</script>';
        await P.ketik('input[name=title]', JUDUL);
        await P.ketik('textarea#inpMessage', 'Isi uji QA F3 sesudah Tailwind <b id="xss-isi">tebal</b>');
        await P.aksiNavigasi(() => P.klik('#frmFeedback button[type=submit]'));
        const flash = await P.ev(`(document.querySelector('.alert-success')||{}).textContent`);
        const idBaru = await P.ev(`(()=>{const a=document.querySelector('.content tbody a[href*="/feedback/"]');const m=a&&a.getAttribute('href').match(/feedback\\/(\\d+)/);return m?Number(m[1]):null})()`);
        const xssIdx = await P.ev(`({el:!!document.querySelector('#xss-uji'),skrip:window.__x===1,teks:[...document.querySelectorAll('tbody td')].some((t)=>t.textContent.includes('<i id="xss-uji">'))})`);
        await P.goto(`/feedback/${idBaru}`);
        const xssShow = await P.ev(`({el:!!document.querySelector('#xss-uji, #xss-isi'),skrip:window.__x===1,teks:document.body.innerText.includes('<i id="xss-uji">')&&document.body.innerText.includes('<b id="xss-isi">')})`);
        butir('escape-xss-index-show', flash && /berhasil/.test(flash) && !xssIdx.el && !xssIdx.skrip && xssIdx.teks && !xssShow.el && !xssShow.skrip && xssShow.teks ? 'PASS' : 'FAIL', { flash: (flash || '').trim(), idBaru, xssIdx, xssShow });

        // ---- Show (staff): badge + a11y + sentuh mobile
        await P.viewport(VP.mobile); await P.goto(`/feedback/${idBaru}`);
        const axBackShow = await namaAksesibel(P, NAV);
        const sentuhShowStaff = await ukurKontrol(P, [NAV]);
        await P.viewport(VP.desktop);
        const tautShow = await periksaTautan(P, `/feedback/${idBaru}`, '/feedback');
        butir('tautan-href-kembali-show-staff', tautShow.ok ? 'PASS' : 'FAIL', tautShow);
        await P.viewport(VP.mobile); await P.goto(`/feedback/${idBaru}`);
        butir('show-staff-tombol-kembali', /Kembali ke daftar/.test(axBackShow.nama || '') && sentuhShowStaff[0].w >= 44 && sentuhShowStaff[0].h >= 44 ? 'PASS' : 'FAIL', { ax: axBackShow, sentuh: sentuhShowStaff });

        // ---- Aksen kustom (8 praset x 2 tema) pada create: tombol primer + fokus
        const aksenHasil = [];
        for (const hex of PRASET) {
            for (const tema of ['terang', 'gelap']) {
                await P.viewport(VP.desktop);
                await P.ev(`localStorage.setItem('tesys-accent', ${JSON.stringify(hex)})`);
                await P.setTema(tema); await P.goto('/feedback/create');
                const k = await kontras(P, [{ nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' }, { nama: 'label', sel: '.content label[for=title]' }]);
                await P.ev(`document.activeElement&&document.activeElement.blur()`); await P.tab(); await sleep(60);
                await P.ev(`document.querySelector('#frmFeedback button[type=submit]').focus()`); await sleep(550);
                const f = await P.ev(`(()=>{const H=${SKRIP_BANTU}();const e=document.activeElement;const s=getComputedStyle(e);const par=H.bgEfektif(e.parentElement);const oc=H.campur(H.rgba(s.outlineColor),par);return{fokusVisible:e.matches(':focus-visible'),outline:s.outlineStyle+' '+s.outlineWidth,rasioOutline:Math.round(H.rasio(oc,par)*100)/100,accentDiMuat:getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()}})()`);
                await P.goto('/feedback');
                const kT = await kontras(P, [{ nama: 'tautan-buat', sel: NAV }]);
                aksenHasil.push({ hex, tema, tautan: kT[0], tombol: k.find((x) => x.nama === 'tombol-primer'), label: k.find((x) => x.nama === 'label'), fokus: f });
            }
        }
        await P.ev(`localStorage.removeItem('tesys-accent')`);
        const gagalAksen = aksenHasil.filter((a) => a.tombol.rasio < 4.5 || !a.tautan.ada || a.tautan.rasio < 4.5 || a.fokus.rasioOutline < 3 || !a.fokus.fokusVisible);
        butir('aksen-praset-8x2-kontras-dan-fokus', gagalAksen.length === 0 ? 'PASS' : 'FAIL', { total: aksenHasil.length, gagal: gagalAksen, minTautan: Math.min(...aksenHasil.map((a) => a.tautan.rasio)), minTombol: Math.min(...aksenHasil.map((a) => a.tombol.rasio)), minFokus: Math.min(...aksenHasil.map((a) => a.fokus.rasioOutline)) });
        R.aksenDetail = aksenHasil;
        // aksen kustom di luar praset (diketahui gagal kontras: deviasi F2 #4), hanya informasi
        await P.ev(`localStorage.setItem('tesys-accent','#ffd400')`); await P.setTema('terang'); await P.goto('/feedback/create');
        const kuning = await kontras(P, [{ nama: 'tombol-primer', sel: '#frmFeedback button[type=submit]' }]);
        await P.ev(`localStorage.removeItem('tesys-accent')`);
        butir('aksen-bebas-terang-#ffd400-informasi', 'INFO', { tombol: kuning[0], catatan: 'sama dengan tombol legacy; deviasi F2 #4 (pemilih warna bebas)' });

        // ===== SUPERADMIN =====
        const sa = await bukaKonteks(B, VP.mobile); konteks.push(sa); const S = sa.P;
        await login(S, AKUN.superadmin);
        await S.viewport(VP.mobile); await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        const sentuhSa = await ukurKontrol(S, [NAV, 'form[action$="/respond"] button[type=submit]']);
        const axBackSa = await namaAksesibel(S, NAV);
        butir('show-superadmin-sentuh-390', sentuhSa.every((x) => x.ada && x.w >= 44 && x.h >= 44) && /Kembali ke Feedback Inbox/.test(axBackSa.nama || '') ? 'PASS' : 'FAIL', { sentuh: sentuhSa, ax: axBackSa });
        await S.viewport(VP.desktop);
        const tautSa = await periksaTautan(S, `/feedback/${idBaru}`, '/feedback-inbox');
        butir('tautan-href-kembali-show-superadmin', tautSa.ok ? 'PASS' : 'FAIL', tautSa);
        // respond lewat UI
        await S.viewport(VP.desktop); await S.goto(`/feedback/${idBaru}`);
        await S.ketik('textarea[name=response_text]', 'Tanggapan QA F3 <u id="xss-t">uji</u>');
        await S.aksiNavigasi(() => S.klik('form[action$="/respond"] button[type=submit]'));
        const setelahRespond = await S.ev(`({flash:(document.querySelector('.alert-success')||{}).textContent,kartuClose:!!document.querySelector('.frmCloseFeedback'),xss:!!document.querySelector('#xss-t'),teks:document.body.innerText.includes('<u id="xss-t">')})`);
        butir('respond-ui-dan-escape', setelahRespond.flash && !setelahRespond.xss && setelahRespond.teks && setelahRespond.kartuClose ? 'PASS' : 'FAIL', setelahRespond);
        // close: sentuh + tombol, lalu SweetAlert
        await S.viewport(VP.mobile); await S.goto(`/feedback/${idBaru}`);
        const sentuhClose = await ukurKontrol(S, ['.frmCloseFeedback button[type=submit]']);
        butir('show-superadmin-tombol-selesai-sentuh-390', sentuhClose[0].ada && sentuhClose[0].w >= 44 && sentuhClose[0].h >= 44 ? 'PASS' : 'FAIL', sentuhClose);
        await S.viewport(VP.desktop); await S.goto(`/feedback/${idBaru}`);
        const kontrasShow = await kontras(S, [{ nama: 'badge', sel: '.content .tw\\:rounded-pill:not([aria-hidden=true])', maks: 8 }, { nama: 'tombol-selesai', sel: '.frmCloseFeedback button' }, { nama: 'header-kartu', sel: '.content [role=heading]', maks: 6 }]);
        R.kontrasShow = kontrasShow;
        butir('kontras-show-terang', kontrasShow.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...kontrasShow.filter((x) => x.ada).map((x) => x.rasio)), n: kontrasShow.filter((x) => x.ada).length });
        await S.setTema('gelap'); await S.goto(`/feedback/${idBaru}`);
        const kontrasShowG = await kontras(S, [{ nama: 'badge', sel: '.content .tw\\:rounded-pill:not([aria-hidden=true])', maks: 8 }, { nama: 'tombol-selesai', sel: '.frmCloseFeedback button' }, { nama: 'header-kartu', sel: '.content [role=heading]', maks: 6 }]);
        butir('kontras-show-gelap', kontrasShowG.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...kontrasShowG.filter((x) => x.ada).map((x) => x.rasio)), n: kontrasShowG.filter((x) => x.ada).length });
        const kontrakShow = await kontrakDom(S, ['form.frmCloseFeedback input[name=_token]', 'form[action$="/respond"] input[name=_token]', 'form[action$="/respond"] textarea[name=response_text][required]', '.feedback-disclaimer', '[role=heading][aria-level="2"]']);
        butir('kontrak-dom-show', kontrakShow.every((k) => k.n >= 1) ? 'PASS' : 'FAIL', kontrakShow);
        // tutup (tanpa SweetAlert tidak mungkin: uji Swal ada di cdp-pages); tutup lewat UI agar baris tidak menggantung
        await S.setTema('terang'); await S.goto(`/feedback/${idBaru}`);
        await S.klik('.frmCloseFeedback button[type=submit]'); await S.tunggu('.swal2-confirm'); await sleep(500);
        const navClose = await S.aksiNavigasi(() => S.klik('.swal2-confirm'));
        butir('close-swal-ui', navClose && (await S.ev(`!document.querySelector('.frmCloseFeedback')`)) ? 'PASS' : 'FAIL', { navigasi: navClose });

        // ---- Inbox: badge tw + DataTables tetap
        await S.viewport(VP.desktop); await S.setTema('terang'); await S.goto('/feedback-inbox');
        const inbox = await S.ev(`({wrapper:!!document.querySelector('.dataTables_wrapper'),paginasi:!!document.querySelector('.dataTables_paginate'),panjang:!!document.querySelector('.dataTables_length select'),tabel:document.querySelector('#tblFeedbackInbox').className,badgeTw:document.querySelectorAll('#tblFeedbackInbox .tw\\\\:rounded-pill:not([aria-hidden=true])').length,badgeLegacy:document.querySelectorAll('#tblFeedbackInbox .badge').length,twCss:[...document.styleSheets].filter((s)=>s.href&&s.href.includes('tesys-tw.css')).length})`);
        butir('inbox-datatables-badge-tw', inbox.wrapper && inbox.paginasi && inbox.panjang && inbox.badgeTw > 0 && inbox.badgeLegacy === 0 && inbox.twCss === 1 && /dataTable/.test(inbox.tabel) ? 'PASS' : 'FAIL', inbox);
        const kontrasInbox = await kontras(S, [{ nama: 'badge', sel: '#tblFeedbackInbox .tw\\:rounded-pill:not([aria-hidden=true])', maks: 12 }]);
        butir('kontras-inbox-terang', kontrasInbox.filter((x) => x.ada && x.rasio < x.batas).length === 0 ? 'PASS' : 'FAIL', { minimum: Math.min(...kontrasInbox.filter((x) => x.ada).map((x) => x.rasio)), n: kontrasInbox.filter((x) => x.ada).length });
    } catch (e) {
        R.galat.push('FATAL: ' + e.message);
        console.error(e);
    }
    for (const k of konteks) { try { await B.cdp.send('Target.disposeBrowserContext', { browserContextId: k.browserContextId }); } catch { /* abaikan */ } }
    R.penghentian = await hentikanBrowser(B);
    fs.writeFileSync(OUT, JSON.stringify(R, null, 1));
    const gagal = R.butir.filter((b) => b.status === 'FAIL').length;
    console.log(`[${nama}] selesai: ${R.butir.length} butir, FAIL=${gagal}, galat=${R.galat.length}, sisaProses=${R.penghentian.sisaProsesPenanda}`);
    console.log('  ->', path.relative(AKAR, OUT));
    process.exit(gagal || R.galat.length || R.penghentian.sisaProsesPenanda ? 1 : 0);
}
main();

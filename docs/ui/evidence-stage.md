# Evidence Stage (carousel lampiran)

Tampilan standar untuk lampiran/evidence yang sudah tersimpan. Satu komponen
bersama menggantikan penampil gambar `#imgViewerOverlay` yang dulu disalin per halaman.

- JS: `public/js/tesys-evidence-stage.js`, CSS: `public/css/tesys-evidence-stage.css`
  (dimuat di `layouts/app.blade.php`).
- Gambar: klik untuk zoom. PDF: iframe. Berkas lain: tautan "Buka berkas".
- Item: `{name, url, is_image, is_pdf, source}`; `url` selalu `/storage/{path}`
  (`SecureFileController` + `FileAccess`). URL selain jalur relatif / http(s) ditolak.

## API

| Panggilan | Fungsi |
|---|---|
| `TesysEvidenceStage.attach('drawerId')` → `{render(items), clear()}` | Panggung di kiri drawer (layar >= 1200px). Carousel hanya tampil bila ada item (`.has-evidence` pada root drawer). Ikut hilang saat drawer ditutup. |
| `TesysEvidenceStage.open(items, startIdx)` | Layar penuh. Bila drawer sedang terbuka (layar > 640px), overlay hanya selebar ruang di kiri panel drawer. |
| `TesysEvidenceStage.close()` / `.isOpen()` | Tutup / cek overlay. |
| `TesysEvidenceStage.fromAttachments(atts, source)` | JSON lampiran drawer (`path`, `original_name`/`title`) → item. |
| `TesysEvidenceStage.fromCostRequest(detailJson)` | Semua lampiran satu Cost Request (Request, item Request, item Settlement, Settlement global) → item. |

Bila drawer terbuka dengan panggung samping yang tampil (>= 1200px) dan memuat URL yang diklik,
`open()` cukup memilih item itu di panggung samping (tanpa overlay kedua).

Pengumpul otomatis dari markup:

```html
<div data-evidence-group>
  <a href="/storage/x.jpg" data-evidence data-evidence-name="x.jpg"
     data-evidence-source="Request" data-evidence-type="image|pdf|file">…</a>
</div>
```

Klik biasa membuka layar penuh berisi semua `a[data-evidence]` dalam grup. Grup = kontainer `[data-evidence-group]`, atau semua tautan di halaman dengan nilai `data-evidence` yang sama (mis. `data-evidence="po"`); nilai kosong = tautan itu sendiri. Klik pada berkas selain gambar/PDF tidak dicegat. Panah keyboard tetap aktif saat overlay dibuka di atas modal.
Sebelumnya: ctrl/cmd/shift-klik
dan klik tengah tetap membuka tab baru.

## Perilaku keyboard

- Escape menutup overlay lebih dulu (fase capture) tanpa ikut menutup drawer.
- Panah kiri/kanan tidak aktif saat `.modal.show` / `.swal2-container` terbuka atau fokus di input.

## Halaman yang sudah memakai

Panggung samping drawer: Treasury index, Cost Request (`budget-request/index`), Todo List, Cost Register
(data diambil dari JSON drawer yang sudah ada; tanpa perubahan controller). Serta mode layar penuh di budget-plan/index, budget-request/index,
project/index (+ `_index-assets`), project-monitoring/show, todo-list/index, daily-transaction/show.
Halaman pemicu hanya memanggil `TesysEvidenceStage.open(...)` dari handler klik yang sudah ada.

## Halaman detail dengan `data-evidence` (Tahap 3)

budget-request/show & `_detail_modal`, budget-plan/show, cost-register & daily-transaction (tautan `.ts-dw-file`
di drawer), daily-transaction/show, invoice-cash-receipt/show & `_delivery_note_modal`, delivery-note/show,
project/submit-bast, project/edit-po-numbers, project/`_index-assets` (lampiran PO/BAST), customer/show,
site/show & `_drawer`, assignment/`_detail`, project-monitoring/`_log_item` (PDF).
Tidak diubah: form upload/edit, trouble-ticket (sudah punya lightbox sendiri dan URL route `trouble-ticket.attachment`).

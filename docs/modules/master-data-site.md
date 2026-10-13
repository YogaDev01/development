# Module — Master Data Site & Material

Master data untuk operasional site: Category Item, Master Material,
Accessories, Sites (+ material & accessories terpasang), Network, Region,
Homebase (+ PIC), dan Wilayah berjenjang.

## Kode & akses

| Menu key (`menu:`) | URL | Controller | Tabel |
|---|---|---|---|
| `category-item`   | `/category-item`   | `CategoryItemController`   | `category_item` |
| `master-material` | `/master-material` | `MasterMaterialController` | `master_material` |
| `accessories`     | `/accessories`     | `AccessoryController`      | `accessories` |
| `site`            | `/site`, `/site/{id}` | `SiteController`        | `sites`, `site_material`, `site_accessories` |
| `network`         | `/network`         | `NetworkController`        | `network` |
| `homebase`        | `/homebase`, `/homebase/{id}` | `HomebaseController` | `homebase`, `homebase_detail` |
| `region`          | `/region`          | `RegionController`         | `region` |
| `wilayah`         | `/wilayah/{pulau\|provinsi\|kota\|kecamatan\|desa}` | `WilayahController` | `pulau`, `provinsi`, `kota`, `kecamatan`, `desa` |

- Otorisasi hanya `auth` + `menu:<key>` (sama seperti Customer/PIC). Menu baru
  didaftarkan migrasi `2026_10_09_100005` **tanpa** diberikan ke role mana pun;
  superadmin lolos otomatis, role lain diberi lewat User & Access.
- Dropdown wilayah berjenjang: `GET /wilayah/{level}/options?parent_id=`
  (gerbang `menu:wilayah,site`).
- CRUD bersama: `Concerns\ManagesMasterRecords` + `resources/views/master-data/index.blade.php`.

## Aturan data

- `type`/`status` disimpan varchar; nilai sah = konstanta model
  (`CategoryItem::TYPES`, `MasterMaterial::STATUSES`, `Site::TYPES`,
  `SOURCE_POWERS`, `PROGRAMS`, `STATUSES`). Menambah nilai = ubah konstanta.
- Kategori Material hanya tipe `MATERIAL`/`TOOLS`; kategori Accessories hanya `ACCESSORIES`.
- Kode otomatis (`App\Support\MasterItemCode`, via `DocumentSequence`):
  `P{inisial customer}-{MMYY}{5 digit}` (material), `A…` (accessories).
  Nomor urut per huruf + customer + bulan. Customer wajib punya inisial dan
  tidak bisa diganti setelah item dibuat. Kode tidak bisa diisi lewat form.
- `serial_number` material wajib, tidak unik. `tid` site wajib dan unik.
- `site_material` unik per (site, material); `site_accessories` unik per
  (site, accessories), `qty` ≥ 1. Item yang dipasang harus milik customer yang sama dengan site.
- `homebase_detail` unik per (homebase, PIC); PIC = `master_pics` berstatus ACTIVE.
- `site_pics` (migrasi `2026_10_09_100007`): PIC yang terhubung ke site, unik
  per (site, PIC), PIC harus ACTIVE; dikelola di halaman Detail Site dan lewat
  field Supervisor / PIC Site di form site.
- Migrasi `2026_10_10_000100`: `sites.hb_id` (Homebase) & `sites.region_id`
  (Cluster) + kolom jaringan per site `gateway`, `outroute_id`, `hub`,
  `spotbeam`, `polarization`. Isian form disamakan dengan form Tambah site
  SLAmeet. `network_id` masih ada di tabel untuk site lama tetapi tidak lagi
  tampil di form.
- Site baru selalu `ACTIVE`; status hanya bisa diubah lewat Edit (field
  `edit_only`). Import Site tidak punya kolom Status. Label UI `tid` = "Site ID".
- Semua FK `RESTRICT`; hapus diblok dengan pesan bila masih dipakai
  (termasuk Customer yang punya Site/Material/Accessories dan PIC yang terdaftar di Homebase).
- Latitude −90..90, longitude −180..180, diisi berpasangan (boleh kosong).
- Homebase & Cluster wajib diisi; jenjang wilayah tetap opsional (berbeda dengan
  SLAmeet yang mewajibkan Island & Desa).
- Template Import Site bertambah kolom `Homebase`, `Cluster`, `Gateway`,
  `Outroute ID`, `Hub`, `Spotbeam`, `Polarisasi`. Homebase dicocokkan dari
  alamatnya, Cluster dari nama cluster di master; Cluster boleh kosong — diambil dari
  Homebase.

## Halaman Site

- Tambah: halaman penuh `GET /site/create`, tiga bagian seperti SLAmeet —
  Detail Site (Site ID, Site name, Tipe, Source Power, Program, Status*,
  Customer, PIC Site), Location (Island,
  Provinsi, Kota/Kabupaten, Kecamatan, Desa, Latitude, Longitude, Address) dan
  Network (Gateway, Outroute ID, Hub, Spotbeam, Polarisasi), ditambah kartu
  Site Keeper berisi baris nama + no HP yang bisa ditambah/dihapus. *Status
  hanya saat Edit. `POST /site` menyimpan site + `site_pics` dalam satu transaksi.
- Supervisor & PIC Site adalah field virtual (`spv_id`, `pic_id`), bukan kolom
  `sites`: dibaca lewat `Site::getSpvIdAttribute/getPicIdAttribute` (PIC internal
  yang jabatannya mengandung "supervisor"/"teknisi") dan disimpan sebagai baris
  `site_pics` oleh `SiteController::syncSitePics()`. Pilihan PIC Site = semua
  teknisi internal, **tidak** disaring Homebase (keterikatan teknisi ke
  supervisornya diatur di master PIC). Field yang tidak dikirim tidak melepas
  PIC yang sudah terpasang.
- Site Keeper (migrasi `2026_10_10_000300`, tabel `site_keepers`, model
  `SiteKeeper`): nama + no HP diisi bebas di form, boleh lebih dari satu baris.
  Baris tanpa nama diabaikan; no HP wajib bila nama diisi. Daftar yang dikirim
  menggantikan seluruh isi lama (`syncSiteKeepers()`); form yang tidak mengirim
  `keepers` tidak menyentuh data lama. FK `site_id` CASCADE — ikut terhapus
  bersama site.
- Detail: drawer (`GET /site/{id}/drawer`, partial `site/_drawer.blade.php`)
  dengan tab Informasi, PIC, TT, Assets, Document. Peta: Google Maps Static API
  bergaya bersih bila `GOOGLE_MAPS_KEY` diisi (kunci wajib dibatasi ke Maps
  Static API + HTTP referrer), selain itu embed OpenStreetMap. Koordinat
  dikirim ke penyedia peta saat drawer dibuka.
  Kelola PIC/material/accessories tetap di `GET /site/{id}`.
- Edit: halaman penuh `GET /site/{id}/edit` (form sama dengan create).
- Daftar: tanpa kolom No; pemilih "Kolom" (preferensi di localStorage, kunci
  `tesys-colvis:site-index`); default Site ID, Nama Site, Customer, Power,
  Cluster, Technician, TT, Action; site INACTIVE disembunyikan kecuali sakelar
  "Tampilkan Inactive"; Nama Site dipotong 60 karakter, penuh saat hover.
- Cluster site = `sites.region_id` bila sudah diisi; bila kosong (site lama)
  jatuh ke turunan: cluster homebase tempat Technician site terdaftar
  (site_pics → homebase_detail → homebase → region). Di layar Site istilahnya "Cluster"
  (master-nya sendiri masih bernama "Region"); kolom tetap `sites.region_id`.
- Kartu: Total TT (placeholder), Total Technician = PIC internal unik di site_pics.
- Kolom PIC Site (`SiteController::technicians()`) = HANYA teknisi site, yaitu
  PIC internal berjabatan "teknisi" (`Site::technicianPic()`, isian field PIC
  Site di form). Supervisor dan PIC internal lain yang terpasang di `site_pics`
  tidak ikut tampil di kolom ini — lihat tab PIC di detail/drawer site. Kolom TT & tab TT/Document
  masih placeholder (tabel trouble ticket & dokumen site belum ada).

## Import Excel (Sites, Master Material)

- `GET {site|master-material}/import-template` → xlsx: sheet **Data** (header +
  contoh) dan **Referensi** (nilai sah). `POST …/import` (xlsx/xls, ≤5 MB, ≤1.000 baris).
- `Concerns\ImportsMasterRecords`: tiap baris divalidasi dengan `rules()` yang
  sama dengan form; **satu baris salah = tidak ada yang disimpan**, daftar
  kesalahan per baris ditampilkan. Kode material tetap dibuat otomatis.
- Material: customer via inisial, kategori via nama + merk (tipe Material/Tools).
- Site: customer via nama atau inisial; wilayah via nama berjenjang
  (Provinsi → Kota → Kecamatan → Desa), Pulau diturunkan dari Provinsi; nama
  wilayah ganda dalam satu induk ditolak (pilih lewat form); TID tidak boleh kembar dalam file.

## Seeder wilayah

- `php artisan db:seed --class=WilayahSeeder` — 38 provinsi, 514 kab/kota,
  7.285 kecamatan, 83.762 desa/kelurahan; sumber github.com/cahyadsn/wilayah
  (MIT, Kepmendagri 300.2.2-2138/2025), CSV di `database/seeders/data/wilayah/`.
- Pulau = 7 kelompok (Sumatera, Jawa, Bali & Nusa Tenggara, Kalimantan,
  Sulawesi, Maluku, Papua) dari 2 digit kode provinsi.
- Upsert berdasarkan kolom `kode` (migrasi `2026_10_09_100006`); aman diulang,
  tidak menghapus data. Desa tidak lagi unik per (kecamatan, nama): 16 pasang
  desa/kelurahan resmi bernama sama.

## Needs confirmation

- Material boleh terpasang di lebih dari satu site sekaligus? (saat ini: boleh, hanya duplikat di site yang sama yang ditolak)
- Status material tidak berubah otomatis saat dipasang/dilepas dari site.
- Daftar tipe bangunan site baru Pemerintah / Pendidikan / Tower.
- Dropdown desa di form Site belum menampilkan kode, jadi 16 nama kembar tampil sama.

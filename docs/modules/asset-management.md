# Module — Asset Management

Modul aset tetap di **Tesys Finance** (sidebar Workflow → dropdown *Asset
Management*), dibangun dari mockup `mockup_aset.html` (2026-11-12). URL dasar
`/asset`.

## Akses

| Lapis | Aturan |
|---|---|
| Route | `menu:asset-management` untuk semua halaman & pengajuan |
| Kelola | `role:superadmin,asset_admin` = `AccessRoles::ASSET_MANAGE` (approve/reject Transfer, Disposal, PR; hapus aset & lampiran; selesaikan opname; ubah kategori/lokasi) — dicek di route, controller (`HandlesAssetRecords::authorizeManage`), dan Blade (`$canManage`) |
| Lampiran | folder `asset_attachments` di disk `secure`; `FileAccess` = pemegang menu `asset-management` |

Migrasi `2026_11_12_000200` membuat menu `asset-management` dan role
`asset_admin` (diberi menu tersebut). Role lain diberi lewat modal Menu Access.

## Tabel (migrasi `2026_11_12_000100`, aditif)

`asset_categories`, `asset_locations`, `assets` (SoftDeletes + `deleted_by`/
`delete_reason`), `asset_attachments`, `asset_logs`, `asset_movements`,
`asset_maintenance_schedules`, `asset_maintenances`, `asset_inspections`,
`asset_opnames`, `asset_opname_items`, `asset_purchase_requests`,
`asset_receivings`. Uang = integer rupiah (`BIGINT UNSIGNED`). FK ke master
`RESTRICT`.

Departemen = `departments` (master Team/Department), karyawan = `employees`.

## Penomoran (`App\Support\AssetNumber` di atas `DocumentSequence`)

- Aset: `AST-{KODE KATEGORI}-{TAHUN PEROLEHAN}-{0001}` (kode kategori tidak bisa diubah).
- Dokumen: `{PREFIX}/{TAHUN}/{0001}` — `BAST-AST`, `RTN-AST`, `TRF-AST`,
  `CO-AST`, `DSP-AST`, `MR-AST`, `INS-AST`, `SO-AST`, `PR-AST`, `GR-AST`.

## Status aset & transisi

Semua transisi di dalam `DB::transaction` dengan row lock aset (`lockAsset`)
dan pemeriksaan ulang status.

| Aksi | Syarat status aset | Hasil |
|---|---|---|
| Registrasi | — | `AVAILABLE` atau `IN_USE` |
| Serah Terima (HANDOVER) | AVAILABLE / IN_USE | `ASSIGNED`, `employee_id` diisi |
| Pengembalian (RETURN) | ASSIGNED | `AVAILABLE`, pemegang kosong, lokasi baru |
| Transfer | bukan DISPOSED/CHECKED_OUT/MAINTENANCE | PENDING → APPROVED memindah lokasi (& pemegang → `ASSIGNED` bila diisi) / REJECTED |
| Check-out | AVAILABLE / IN_USE | `CHECKED_OUT`; check-in → status sebelumnya |
| Maintenance | AVAILABLE / ASSIGNED / IN_USE | `MAINTENANCE`; COMPLETED/CANCELLED → status sebelumnya; dari jadwal → `next_due_date = end_date + interval` |
| Disposal | sama dengan Transfer | PENDING → APPROVED = `DISPOSED` (final, `disposed_at`) / REJECTED |
| Inspeksi | bukan DISPOSED | kondisi aset ikut hasil inspeksi |
| Opname selesai | — | kondisi aset ikut hasil; "Tidak Ditemukan" **tidak** otomatis disposal |
| Penerimaan (GR) | PR harus APPROVED, qty ≤ sisa | tiap unit jadi aset `AVAILABLE`; PR → RECEIVED bila penuh |

Satu aset hanya boleh punya satu pengajuan Transfer/Disposal PENDING.
Hapus aset hanya untuk aset tanpa riwayat transaksi; selebihnya lewat Disposal.

## Penyusutan (`App\Support\AssetDepreciation`)

Informasional — **tidak** diposting ke jurnal, Closing, atau Cash Flow.
Bulanan sejak bulan perolehan; Garis Lurus `(harga − residu)/(umur×12)`;
Saldo Menurun Ganda tarif `2/(umur×12)` per bulan dengan batas residu; aset
DISPOSED bernilai buku 0 sejak tanggal disposal. Default umur/metode/% residu
dari kategori, disimpan per aset saat registrasi.

## Di luar scope (Needs confirmation)

- Biaya maintenance, nilai jual disposal, dan harga penerimaan hanya dicatat;
  tidak membuat Cost Request, Cash Receipt, atau jurnal.
- Settings mockup "General", "Department", "User & Access" memakai master
  TESYS yang ada; tidak dibuat ulang.
- Notifikasi & Todo List untuk persetujuan aset belum dibuat (dashboard aset
  menampilkan jumlah pengajuan PENDING).
- Opname sangat besar (> ~300 aset per lokasi) dapat melampaui `max_input_vars`
  PHP saat menyimpan hasil — cek nilai di hosting.

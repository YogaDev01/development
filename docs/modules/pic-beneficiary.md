# Master PIC & Beneficiary (halaman gabungan)

Sejak 2026-11-09 (update v3.3.4) halaman **Beneficiary** (`/beneficiaries`) dan
**PIC** (`/master-pic`) digabung menjadi satu halaman **PIC & Beneficiary**
(`/pic-beneficiary`, sidebar *Personal Master*).

## Alur & tampilan

- **PIC → Beneficiary.** Satu DataTable (`#pbTable`) berisi seluruh PIC — semua
  tipe (Internal, External, Customer, Site Keeper), tanpa tab. Klik baris PIC
  (atau tombol ›/jumlah beneficiary) untuk membuka beneficiary-nya sebagai
  *child row*.
- **Beneficiary tanpa PIC** tampil sebagai baris sendiri di tabel yang sama,
  berlabel *Tanpa PIC* (kolom Jenis), dengan aksi *Kaitkan ke PIC*.
- **Pencarian** mencakup nama/jabatan/kontak/token/akun PIC **dan**
  nickname/nama lengkap/no. rekening beneficiary-nya. Bila baris PIC lolos
  karena beneficiary-nya, detailnya dibuka otomatis dan baris yang cocok disorot.
- **Saringan:** Jenis (tipe PIC + *Tanpa PIC*), Status, tombol cepat
  *Belum Terhubung* (PIC internal tanpa akun User) dan *Tanpa PIC*.
- Data beneficiary untuk detail dikirim sekali bersama halaman
  (`PicBeneficiaryController::index()`), jadi membuka detail tidak memicu
  permintaan baru. Isinya sama dengan yang dulu terlihat di tabel Beneficiary.

## Hak akses

| Aksi | Gerbang |
|---|---|
| Melihat halaman, menambah PIC, menambah beneficiary | `menu:pic-beneficiary` |
| Ubah/hapus beneficiary, Pindah PIC, Kaitkan, pemilih | `menu:pic-beneficiary` + `role:superadmin` (`AccessRoles::BENEFICIARY_MANAGE`) |
| Ubah/hapus PIC (`master-pic.edit-data/update/destroy`) | `menu:pic-beneficiary` + `role:superadmin` (`AccessRoles::PIC_MANAGE`) |

**Perubahan kewenangan:** dulu siapa pun yang punya menu `master-pic` bisa
mengubah/menghapus PIC. Kini hanya superadmin; role lain tetap bisa melihat dan
menambah PIC. Daftar role yang terdampak dicetak oleh migrasi (lihat di bawah).

Tombol & modal kelola (dan JS-nya) tidak dirender untuk role yang tidak berhak;
penjaga sebenarnya tetap di route + controller (`pastikanBolehMengelola()`).

## Menu & migrasi

- Migrasi `2026_11_09_000400_merge_pic_beneficiary_menu`:
  1. membuat `menu_items` `pic-beneficiary`;
  2. memberikannya ke setiap role yang punya `beneficiaries` **atau** `master-pic`;
  3. menghapus dua baris `menu_items` lama beserta `role_menu_items`-nya
     (dicatat di log & dicetak);
  4. menyinkronkan `role_permissions` (tambah saja) untuk permission baru
     `master-pic.*`.
- `down()` mengembalikan dua menu lama dan memberikan keduanya ke setiap
  pemegang menu baru (pembagian asli tidak bisa direkonstruksi persis — lihat
  log `up()`).
- URL lama `GET /beneficiaries` dan `GET /master-pic` dialihkan ke
  `/pic-beneficiary` (dijaga menu yang sama). Endpoint tulis lama tetap
  (`POST/PUT/DELETE /beneficiaries…`, `/master-pic…`).
- Dihapus: `GET /beneficiaries/rows/{part}`, `GET /beneficiaries/pic-groups`,
  `DELETE /beneficiaries/pic/{picId}` (hapus PIC kini lewat
  `DELETE /master-pic/{id}`, yang juga menolak PIC yang dipakai Homebase/Site —
  penjagaan yang dulu tidak ada di endpoint layar Beneficiary).

## Langkah pasca-deploy

1. `php artisan migrate` (menjalankan migrasi di atas).
2. `php artisan config:clear` (registry `config/permissions.php` berubah).
3. Periksa output migrasi: role yang kehilangan hak ubah/hapus PIC ditandai
   `PERHATIAN`.

## Berkas

- `app/Http/Controllers/PicBeneficiaryController.php` — daftar (baca saja).
- `app/Http/Controllers/BeneficiaryController.php`, `MasterPicController.php` — aksi tulis.
- `resources/views/pic-beneficiary/index.blade.php`, `_pic_fields.blade.php`, `_ben_fields.blade.php`.
- Tes: `tests/Feature/PicBeneficiaryPageTest.php`, `BeneficiaryAccessTest.php`, `BeneficiaryPicViewTest.php`.

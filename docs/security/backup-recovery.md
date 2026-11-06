# Backup & Recovery TESYS — Kondisi Aktual vs Standar

- Dicatat: 2026-11-07, Fase 9 blueprint `docs/plans/2026-11-06-security-access-control.md`.
- Sifat: **dokumen saja**. Tidak ada backup, restore, atau perintah yang dijalankan oleh Claude.
- Sumber kondisi aktual: jawaban pemilik 2026-11-07. Sumber standar: `docs/DEPLOYMENT.md` §4.

## 1. Perbandingan

| Aspek | Standar (`docs/DEPLOYMENT.md` §4) | Kondisi aktual (pemilik) | Gap |
|---|---|---|---|
| Frekuensi | Minimal harian | **Seminggu sekali** | Kehilangan data maksimum hingga 7 hari transaksi keuangan |
| Lokasi | Di luar server aplikasi | **"Local storage"** — arti persisnya: **NEEDS CONFIRMATION** (penyimpanan server Hostinger yang sama, atau komputer lokal di kantor?) | Bila di server yang sama, backup ikut hilang saat server/akun hosting bermasalah |
| Isi | Database (`mysqldump`) **dan** lampiran `storage/app/private` | **NEEDS CONFIRMATION** — apakah lampiran ikut | Lampiran keuangan tidak bisa dibuat ulang |
| Retensi | Minimal 30 hari | **NEEDS CONFIRMATION** | — |
| Uji restore | Minimal sekali per kuartal ke lingkungan terpisah | **Belum pernah tercatat** | Backup yang belum pernah diuji pulih belum terbukti bisa dipakai |
| Akses ke berkas backup | Terbatas | **NEEDS CONFIRMATION** | Dump berisi seluruh data keuangan & hash password |
| Enkripsi | Tidak diatur | **NEEDS CONFIRMATION** | — |

## 2. Rekomendasi (keputusan pemilik — tidak dikerjakan otomatis)

1. Naikkan frekuensi backup database menjadi **harian**, sesuai standar yang sudah ditulis.
2. Pastikan **satu salinan berada di luar server Hostinger**, misalnya komputer kantor atau
   penyimpanan cloud milik perusahaan dengan akses terbatas.
3. Sertakan folder `storage/app/private` (lampiran) dalam backup.
4. Lakukan **uji restore pertama** ke database terpisah (bukan produksi, bukan `tesys_test`),
   lalu catat tanggal dan hasilnya di dokumen ini.
5. Simpan backup terenkripsi bila disimpan di luar kantor.

## 3. Catatan keamanan

- Restore ke produksi adalah operasi CRITICAL (CLAUDE.md §4.2/§20) dan hanya dilakukan manusia.
- Dump database memuat hash password dan data keuangan; perlakukan setara kredensial.

## 4. Riwayat uji restore

| Tanggal | Backup yang diuji | Tujuan restore | Hasil | Oleh |
|---|---|---|---|---|
| — | — | — | belum pernah | — |

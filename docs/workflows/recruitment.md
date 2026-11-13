# Recruitment & Kandidat (HRIS) — tahap 2b

Menu `recruitment` (`/recruitment`) dan `candidate-pool` (`/candidates`). Menyambung
ke [Manpower Request](manpower-request.md) lewat `recruitments.detail_id`.
Akses: menu + `ManpowerRequest::HR_ROLES` (`superadmin`, `hr`). **Needs confirmation.**

## Alur
- Recruitment baru: kandidat TERSEDIA + rincian kebutuhan dari MP berstatus APPROVED
  (rincian OPEN/ON_PROCESS). Mulai di SCREENING; rincian OPEN → ON_PROCESS.
- Tahap: SCREENING → INTERVIEW HR → INTERVIEW USER → TRAINING → KONTRAK → ONBOARD.
  TRAINING hanya untuk posisi bernama "teknisi" (dilewati untuk lainnya).
- Reject / Batal (alasan wajib): proses ditutup, rincian kembali OPEN bila tak ada
  proses aktif lain; kandidat kembali ke talent pool.
- ONBOARD = hire: lihat di bawah. Kandidat lain yang masih ON_PROCESS pada rincian
  yang sama otomatis RETURNED.
- Satu kandidat hanya boleh punya satu proses ON_PROCESS.
- Status MP (PROCESS / PARTIALLY DONE / DONE) otomatis ikut status rincian
  (`ManpowerRequest::displayStatus()`); tiap langkah menulis log ke MP & kandidat.

## Hire membuat karyawan (HIGH — perlu konfirmasi)
`RecruitmentController::hire()` membuat baris `employees` baru dari data kandidat
dengan **status INACTIVE, gaji default 0, tanpa employee_number**, agar tidak
otomatis ikut payroll sebelum HR melengkapi NIK/rekening/gaji dan mengaktifkannya
di Master Employee. ID tampilan `EMP-` + id. Ubah di sini bila aturan bisnisnya lain.

## Kandidat
Kode `CND-0001…` lewat `DocumentSequence`. Dokumen di disk `secure` (pdf/jpg/png/doc/docx,
maks 10 MB). Impor: `.csv/.xls/.xlsx`, maks 500 baris, kolom wajib `name`,
`phone_number`, `date_of_birth`; template CSV diunduh dari halaman Kandidat
(mockup memakai XML Spreadsheet .xls — di sini diganti CSV).

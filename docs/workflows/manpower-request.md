# Manpower Request (HRIS) — tahap 2a

Menu `manpower-request` (app HRIS, `/manpower`). Recruitment & Kandidat = tahap 2b (belum ada).

## Status
Tersimpan: DRAFT, SUBMITTED, REJECTED, APPROVED, CANCEL.
Tampilan setelah APPROVED diturunkan dari status rincian
(`ManpowerRequest::displayStatus()`): PROCESS, PARTIALLY DONE, DONE.
Alur: DRAFT → SUBMITTED → APPROVED; SUBMITTED → REJECTED (kembali ke tahap draft,
bisa diedit lalu diajukan ulang); DRAFT/REJECTED/APPROVED(belum ada yang DONE) → CANCEL.
Aksi sah per status: `ManpowerRequest::allowedActions()`.

## Aturan
- Visibilitas: `superadmin`/`hr` melihat semua; user lain hanya pengajuannya sendiri.
- Approve/reject hanya `superadmin`/`hr`. **Needs confirmation** (mockup tidak
  menyebut role; "HR/recruiter" di mockup dipetakan ke role `hr`).
- Baris `REPLACE` wajib memilih karyawan yang digantikan saat diajukan; satu
  karyawan tidak boleh digantikan oleh dua rincian aktif.
- Rincian hanya diubah saat DRAFT/REJECTED (belum ada rekrutmen berjalan).
- Posisi: saran dari `ManpowerRequest::POSITIONS` tapi teks bebas (tidak ada master
  posisi). **Needs confirmation** apakah perlu master Posisi.
- Penomoran `MP001…` lewat `DocumentSequence` (kunci `manpower-request`).
- Diskusi pemohon ↔ HR + peristiwa alur: `manpower_request_logs`.

## Tahap 2b (belum)
Recruitment (tahap Screening → Onboard per rincian), Kandidat/talent pool, impor
Excel. Rincian sudah punya `status` (OPEN/ON_PROCESS/DONE/CANCELED) dan
`filled_employee_id` untuk disambung ke modul itu.

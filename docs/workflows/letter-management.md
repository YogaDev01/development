# Letter Management (HRIS) — tahap 1

Menu `letter-management` (app HRIS, `/letter`). Invoice **bukan** bagian modul ini.

## Status
- External: Draft → Submitted → Approved → Sent → Complete
- Internal: Draft → Submitted → Approved → Complete
- Cabang: Rejected / Cancelled (dapat dikembalikan ke Draft). Aksi yang sah per
  status ada di `Letter::allowedActions()` (satu sumber untuk tombol & validasi).

## Approval dinamis (struktur Team)
Reviewer = leader tiap team ACTIVE tempat pembuat menjadi anggota ACTIVE +
leader semua team induknya (`LetterController::approverIds()`).
- Pembuat tidak boleh meng-approve suratnya sendiri.
- Superadmin selalu boleh.
- Bila pembuat tidak punya atasan di struktur team: fallback role
  `hr`, `finance_manager`, `approver` (agar surat tidak buntu). **Needs confirmation.**
- Kolom `team_members.level` tidak dipakai. **Needs confirmation** bila hierarki
  anggota (bukan hanya leader) harus ikut menentukan reviewer.

## Data
Tabel: `letter_categories`, `letters`, `letter_details`, `letter_attachments`
(disk `secure`), `letter_signatures` (penandatangan = User), `letter_logs`.
Penomoran: `DocumentSequence` — `NNN/KODE/INT|EXT/ROMAWI/TAHUN`.
Template isian per kode kategori: `LetterController::TEMPLATES`.

## Belum ada (tahap berikutnya)
Spesimen tanda tangan gambar, metode TTD basah, export JSON, Manpower Request.

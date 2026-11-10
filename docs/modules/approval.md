# Module — Approval

There is no generic approval engine. Each module implements its own status
chain in its controller, with history in a per-module log table.

- Chains & owners: `docs/workflows/approval.md`.
- Locked transitions (`Concerns\TransitionsStatus`) are used **only** by Cost
  Request; other modules check-then-update.
- Display: `App\Support\WorkflowStatus` (labels, "waiting for whom", tones,
  stepper flows) + `x-status`, `x-workflow-stepper`, `x-workflow-flow`.
  It is presentation only and must not be used to decide transitions.
- Worklist: Todo List (`TodoListController`, `TodoService`,
  `BuildsCrWorklist`) shows each role's pending items, aging since last status
  change, and bulk approve/reject.
- Notifications: `NotifyService::send()` to recipients from `RoleDirectory`
  (queued; in-app + WhatsApp via Fonnte).

## Bulk Approve/Reject dari index Cost Request (tahap pengajuan)

- Tabel index CR (satu tabel gabungan semua tipe sejak 10 Nov 2026; dulu tab
  Cash Advance dan Cost Payment, bukan Database) punya kolom checkbox untuk
  peran efektif (termasuk View As) yang ada di `BudgetRequest::STAGE_ROLES` atau
  superadmin. Settlement **tidak** ikut bulk.
- Endpoint yang dipakai sama dengan section Review Todo List:
  `budget-request.review-bulk-approve` / `-reject` (tanpa route baru).
- Satu aksi = satu status (SUBMITTED, CHECKED, atau VERIFIED). Baris berstatus
  lain atau di luar wewenang peran dikunci di UI; server tetap memeriksa ulang
  per CR (status + peran + visibilitas) lewat `applyReviewApprove/Reject`
  dan `transitionStatus()` (locking tidak diubah).
- Parameter baru opsional `expected[id] => STATUS`: CR hanya diproses bila status
  saat dimuat sama dengan yang dilihat user, kalau tidak dilewati ("status
  berubah dari X menjadi Y"). Todo List tidak mengirimnya sehingga perilakunya
  tetap. `ids` kini wajib, maksimal 50 per proses (`ids.*` integer).
- Respons: flash `success` "N CR berhasil di-approve/ditolak..." tetap, ditambah
  flash `error` "M CR dilewati: KODE (alasan), ..." (maks 5 lalu "...").
- Konfirmasi UI menampilkan transisi, total rupiah, jumlah CR NOB dan yang punya
  item takeout (hanya informasi, tidak memblokir). Reject wajib alasan.
- Needs confirmation: notifikasi/catatan approve massal belum disamakan dengan
  aksi satuan (`headApprove` dll.) - perilaku `applyReviewApprove` sengaja tidak
  diubah; Todo List dengan lebih dari 50 id yang dicentang sekarang ditolak
  validasi.

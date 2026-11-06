# Workflow — Approval chains (all modules)

| Module | Chain | Step owners |
|--------|-------|-------------|
| Cost Request | DRAFT → SUBMITTED → CHECKED → VERIFIED → APPROVED | manager → head → finance/finance_manager/cost_control → approver |
| CR Settlement | REALIZED → SETTLE_CHECKED → SETTLE_VERIFIED → (CLOSED \| SETTLE_APPROVED → SECOND_APPROVED) | head → finance group → finance_manager → approver |
| Budget Plan | DRAFT → SUBMITTED → CHECKED → VERIFIED → ACTIVE → CLOSING → CLOSED | manager → head → finance/finance_manager → approver; closing: manager `submit-closing` (ACTIVE → CLOSING; also accepts CLOSING not yet submitted — `isClosingSubmitted()`; kini hanya sisa data lama karena auto-closing berbasis Plan Finish sudah dihapus), finance group `verify-closing` (CLOSING → CLOSED) |
| Cost Register (29 Okt 2026) | DRAFT → VERIFIED (label "Submitted", tahap Checked dilewati) → APPROVED → QUEUED ("Process") → CLOSED, + CANCELLED. `submit`/`cancel`: superadmin, finance, finance_manager; `approve`/`revise` (VERIFIED → APPROVED / DRAFT, notes wajib): approver, superadmin; Treasury memilih APPROVED (`CostRegister::TREASURY_READY_STATUS`), takeout/reject batch → APPROVED, mark-paid → CLOSED. Header (`cost_registers`) + rincian per item (`cost_register_details`, nominal header = jumlah total rincian, dihitung di server) + log/catatan (`cost_register_logs`). Migrasi data: SUBMITTED → APPROVED, PAID → CLOSED (`2026_10_29_000600`) | finance group / approver |
| PO bulk (daftar PO, 29 Okt 2026) | `project/bulk-approve` (status SUBMITTED → `verify`; status VERIFIED → `approve` dengan keputusan APPROVE saja, status akhir ditentukan `approvalAutoStatus()`; Early Start tidak lewat bulk) dan `project/bulk-revise` (`financeRevise`, notes wajib): satu status per proses, maks 50 id; method satuan TIDAK mengecek role, jadi `bulkProses()` mengecek per tahap (SUBMITTED: superadmin/finance/finance_manager; VERIFIED: superadmin/approver); propagasi ke Budget Plan bundled, log, dan notifikasi identik aksi satuan; PO yang statusnya sudah berubah dilewati dan dilaporkan | finance group / approver (+ superadmin) |
| Budget Plan bulk (daftar Budget Plan, 29 Okt 2026) | `bulk-approve` / `bulk-reject` (notes wajib): satu status per proses (`status` = SUBMITTED / CHECKED / VERIFIED), maks 50 id; tiap BP diproses method satuan `headApprove`/`financeVerify`/`ceoApprove` (atau pasangan reject-nya), jadi role, "via PO", log, dan notifikasi identik; BP yang statusnya sudah berubah dilewati dan dilaporkan | head / finance, finance_manager / approver (+ superadmin), dicek ulang per BP |
| Budget Plan (other closing paths) | `finance-close`: ACTIVE → CLOSED directly · `reject-closing`: CLOSING → ACTIVE | finance, finance_manager, superadmin (`BudgetPlanController`) |
| Budget Plan — Tambah COA | `add-coa` (status tetap ACTIVE): menambah baris detail `type = additional` dengan harga satuan 0, qty 0, total 0 — nilai budget tidak berubah; pemakaian di baris baru tercatat NOB. Per baris wajib Account (aktif & sesuai kategori sumber dana), Sub COA, dan Unit; tidak ada notes per baris. Account + Sub COA kembar ditolak. Log `coa_added` berisi Notes modal (opsional) + daftar COA. Berlaku juga untuk BP bundled PO | manager, superadmin (`BudgetPlanController::addCoa`) |
| Budget Plan bundled (dari form PO, `via_po_approval = 1`) | tidak punya workflow sendiri; mengikuti PO: `submit` DRAFT → CHECKED (tahap Head-check BP dilewati), `verify` → VERIFIED, `approve` → ACTIVE (semua hasil approve, termasuk Early Start), `finance-revise` CHECKED/VERIFIED → DRAFT (tanpa REJECTED) | pelaku = pelaku langkah PO (head/superadmin → finance → approver), propagasi di `ProjectController` dalam transaksi yang sama + `BudgetPlan::addLog()` per transisi. Closing manual BP tetap boleh; selain itu BP ACTIVE/CLOSING otomatis CLOSED (log `closed_with_po`) saat PO ditutup (`close`/`financeClose`/`verifyClosing` → `closeBudgetPlans()`). PO PAID TIDAK lagi memindahkan BP ke CLOSING |
| Treasury batch | PENDING_APPROVER → DONE → PAID | approver → treasury group |
| Invoice | DRAFT → SUBMITTED → VERIFIED → APPROVED → SENT → RECEIVED → PARTIALLY_PAID → PAID | creator (admin/finance/collection) → finance_manager/accounting → finance_manager → Delivery Note → collection |
| Purchase Order | see `purchase.md` | |
| Payroll Run | DRAFT → SUBMITTED → VERIFIED → APPROVED → PAID → CLOSED | Needs confirmation |
| Forecast | DRAFT → ACTIVE → CLOSED (auto-close daily) | `menu:forecast` |
| Cash Management | DRAFT → POSTED / CANCELLED | treasury group + approver |
| Cash Receipt header | DRAFT → ALLOCATED / CANCELLED | finance/treasury/collection/approver |
| Closing | DRAFT → CLOSED → REOPENED | `menu:closing` |

Budget Plan bundled: `BudgetPlanController` menolak submit / head / finance / ceo
approve-reject / manager-revise / edit / update / destroy untuk BP ini (pesan
"dikelola lewat approval PO"); tombolnya disembunyikan di Budget Plan index.
Asumsi keputusan 22 Okt 2026: approval PO tidak melibatkan Head untuk BP.
PO Early Start yang diedit kembali ke DRAFT: BP bundled yang sudah ACTIVE tetap ACTIVE
dan read-only; submit/verify/approve ulang PO tidak menggerakkannya lagi. PO dengan BP
bundled non-DRAFT juga tidak dapat dihapus (`ProjectController::destroy`).
Hapus Budget bundled: lewat Edit PO (tombol "Hapus Budget dari PO ini", `budget[hapus]=1`),
hanya Head/superadmin, semua BP bundled DRAFT dan PO DRAFT. Hard delete (BP tanpa
SoftDeletes) beserta detail/log/lampiran; dicatat di `project_logs` (`budget_deleted`) dan
UserActivityLog. Ditolak bila BP dirujuk `budget_request` (termasuk soft-deleted),
`trouble_ticket_requests`, `trouble_tickets.budget_plan_id`, `project_monitoring_logs`, atau
`payroll_runs`. Bila PO punya >1 BP bundled, hapus ditolak dengan pesan. Tanpa flag, form budget
kosong tetap error. Budget Plan > destroy untuk BP bundled tetap ditolak.
Kode `BD-` berbasis count, sehingga dapat dipakai ulang setelah BP bundled DRAFT dihapus;
jejak kode lama ada di `project_logs` dan `user_activity_logs`.
PO VERIFIED muncul di Todo List Approver (antrean `poVerified`, "PO — Approval Approver").

Rejections: CR → REJECTED then applicant Revise (→ DRAFT) or Cancel. Budget
Plan → REJECTED then manager revise. Settlement rejects stop at SETTLE_REJECTED.

Todo List (`TodoListController`, `BuildsCrWorklist`) aggregates the pending
items per role and supports bulk actions (`todo-list/bulk/{section}/{mode}`).

Not found: self-approval prevention, approval amount thresholds, delegation.
**Needs confirmation** whether these are required.

# Workflow — Settlement (Cash Advance only)

Source: `HandlesCrSettlement`, `BudgetRequest::getAllowedNextStatuses()`,
`BudgetRequestSecondPayment`, `DailyTransactionController::markPaid()`.

| From → To | Action | Roles |
|-----------|--------|-------|
| PAID / SETTLE_REJECTED → REALIZED | `submit-realization` (realization rows; may be 0 without attachment since v2.4.2) | manager, supervisor, superadmin (+ teknisi tiket untuk CR tiketnya) |
| REALIZED → SETTLE_CHECKED / SETTLE_REJECTED | `head-check-settlement` / `head-reject-settlement` | head, superadmin |
| SETTLE_CHECKED → SETTLE_VERIFIED / SETTLE_REJECTED | `finance-verify-settlement` / `finance-reject-settlement` | finance, finance_manager, cost_control, superadmin |
| SETTLE_VERIFIED → CLOSED (no expense claim) | `ceo-close-settlement` | **finance_manager**, superadmin |
| SETTLE_VERIFIED → SETTLE_APPROVED (expense claim) | `settle-approve` | finance_manager, superadmin |
| SETTLE_APPROVED → SECOND_APPROVED / SETTLE_REJECTED | `second-approve` / `second-reject` | approver, superadmin |
| SECOND_APPROVED → QUEUED | Treasury batches the Second Payment | treasury group |
| QUEUED → SECOND_APPROVED | Batch rejected (`reject-batch`) or SP item taken out (`reject-item`); SP stays PENDING and can be batched again (fixed on `develop` 2026-10-02 — before, the CR stayed QUEUED and the SP could never be re-batched) | approver, superadmin |
| QUEUED → CLOSED | batch `mark-paid` closes CR automatically | treasury group |
| REALIZED / SETTLE_CHECKED / SETTLE_VERIFIED / SETTLE_APPROVED → SETTLE_REJECTED | reject actions (head, finance, `settle-reject`, `manager-reject-settlement`, `second-reject`) | per action, see `docs/security.md` |

Koreksi 2026-10-25: versi dokumen sebelumnya menulis reject kembali ke PAID.
Kode (`HandlesCrSettlement`, `getAllowedNextStatuses()`) berhenti di
**SETTLE_REJECTED**; pengaju merevisi lalu submit ulang (SETTLE_REJECTED →
REALIZED) lewat form yang sama.

Expense claim = realization − request amount > 0. Refund = request −
realization, only counted once settled.

## Second Payment lifecycle (expense claim only)

The Second Payment **record** and its **approval/payment** are separate steps:

1. **Creation — at SETTLE_VERIFIED.** `financeVerifySettlement` sets the CR to
   SETTLE_VERIFIED and, inside the same DB transaction, creates a
   `budget_request_second_payments` row with `status = PENDING` and
   `amount = settlement_overage` (`total_realization − amount`) when that
   value is > 0.
2. **Approval of the claim** — `settle-approve` (finance_manager):
   SETTLE_VERIFIED → SETTLE_APPROVED. Then `second-approve` (approver):
   SETTLE_APPROVED → SECOND_APPROVED. The Second Payment row stays PENDING.
3. **Batching** — Treasury adds it to a batch; the parent CR moves
   SECOND_APPROVED → QUEUED (`DailyTransactionController`, store/add-item).
4. **Payment** — batch `mark-paid`: Second Payment PENDING → PAID (`paid_by`,
   `paid_at`), and the parent CR QUEUED → CLOSED.

Settlement reject (`settle-reject`, `second-reject`, `manager-reject-settlement`)
moves the CR to SETTLE_REJECTED and is refused if a Second Payment is already PAID or is
on an INCLUDED batch item (`applySettlementRejectClose`). What happens to the
PENDING row on reject: see that method (Needs confirmation — not traced in
detail).

Discrepancies: comments in `DailyTransactionController` describe an older
design (parent CR "stays SETTLE_VERIFIED"). Behavior = table above.

Needs confirmation: how refunds (money returned by PIC) are physically recorded
— no refund receipt flow found.

## Settlement Custom COA (2026-10-25)

Sumber: `BudgetRequestController::validateRealizationInput()`,
`syncRealizations()`, `storeSettlementSignatures()`,
`BudgetRequest::costUnits()` / `settlementFormRows()` /
`settlementSubItemOptions()`, `BudgetPlanDetail::usedAmount()` /
`costLines()`, kolom `budget_request_realizations.budget_detail_id`.
Hanya Cash Advance (hanya CA yang punya settlement).

- **Baris fleksibel.** Form Submit Settlement dan modal settlement Trouble
  Ticket diisi 1:1 per item (Sub COA & nilai = item asal). Tiap baris boleh
  memilih **Sub COA** = sub-item Budget Plan CR yang **sama** (termasuk tipe
  additional); item boleh **dipecah** jadi beberapa baris, ada **baris
  tambahan** tanpa item asal (`request_detail_id` NULL), dan item boleh tanpa
  baris sama sekali (= settle 0). `account_id` & `description` baris
  diturunkan dari sub-item ("{Akun} — {Sub COA}").
- **Ditolak (submit & draft, tidak ada yang tersimpan):** Sub COA dari Budget
  Plan lain, item TAKEOUT / milik CR lain, baris tambahan tanpa Sub COA.
  Baris tersimpan yang item asalnya kemudian TAKEOUT ("yatim") tetap tampil
  dengan tautan item asalnya dan ditandai di form; server menolaknya dengan
  pesan nomor baris sampai baris itu dihapus. Baris tambahan bernilai 0/kosong
  dibuang saat disimpan (tidak perlu Sub COA/Note). Nomor baris di pesan =
  urutan baris di form.
- **Baris item terkunci (sejak 4 Nov 2026, menggantikan sebagian butir 29 Okt di
  bawah).** Form Submit Settlement kembali **terisi 1:1** dari item pengajuan
  (bukan TAKEOUT) lewat `settlementFormRows()`; tombol Auto Fill dihapus. Baris
  pertama tiap item **tidak bisa dihapus** dan **Sub COA baris item + pecahannya
  dikunci** ke Sub COA item asal — yang disunting hanya deskripsi, qty, nilai.
  Server menolak (submit & draft) baris item yang Sub COA-nya berbeda dari item
  asal, dan saat Submit menolak item yang tidak punya baris. Hanya **Tambah
  Baris** (baris tambahan) yang memilih Sub COA. **Lampiran Item wajib saat
  Submit untuk baris bernilai Settle > 0** (draft tidak mewajibkan); Note per baris
  opsional. **Keterangan** (label kini "Log History Note") dan **Lampiran
  Settlement (global)** tidak lagi wajib, termasuk saat total Settle > pengajuan.
  Bila total Settle **< nilai pengajuan**, muncul form opsional **Bukti Transfer
  Pengembalian** (drag-drop/paste/pilih; PDF/JPG/PNG ≤ 10 MB, field
  `refund_proofs[]`), disimpan sebagai lampiran global CR berawalan
  `bukti-transfer-` (tanpa tabel/kolom baru); diabaikan server bila total ≥ pengajuan.
  Kartu **Bukti Settlement (Global) dihapus dari form** (4 Nov 2026): evidence
  hanya per baris (Lampiran Item) + bukti transfer pengembalian. Bukti transfer
  tersimpan tampil (dan bisa dihapus) di kartu Bukti Transfer. Lampiran global
  lama lainnya tetap tersimpan & terlihat di detail CR, hanya tidak dikelola di
  form ini; server masih menerima `realization_attachments[]` (tidak dikirim form).
  Baris item yang sedang dipecah diberi badge **Sub Utama** (baris pertama) /
  **Sub Pecahan**; kolom lampiran yang wajib tapi kosong diberi badge **Wajib**.
- **Settlement berdiri sendiri (sejak 29 Okt 2026; sebagian diganti 4 Nov, lihat
  di atas).** Form Submit Settlement awalnya **kosong**; baris dibuat lewat Tambah Baris atau **Auto Fill** (menyalin
  seluruh item pengajuan yang belum punya baris). Kolom Request dihapus. Lampiran
  Item dan Note per baris **opsional**. Bila **total Settle > nilai pengajuan CR**
  (`budget_request.amount`, dasar yang sama dengan `expenseClaimAmount()`), maka
  **Keterangan** dan **Lampiran Settlement (global)** wajib saat Submit; draft tidak
  mewajibkan. Keterangan disimpan di `budget_request.settlement_notes` (migrasi
  `2026_10_29_000100`) dan ikut ke Log. Bukti per baris (`validateSettlementEvidence`)
  kini hanya dicek untuk draft dari modal tiket (`from_ticket`). Kolom form:
  Harga Satuan, Qty, Settle (= Harga x Qty); kolom Variance dihapus. Tersimpan:
  `amount` = harga satuan, `qty`, `total` = amount x qty (total inilah yang dipakai
  semua perhitungan). Form tiket tanpa qty dianggap qty 1 (total = amount).
- **Melebihi sisa budget Sub COA tujuan** → hanya peringatan di form, tidak
  diblokir. Sisa = total sub-item − Used + kontribusi CR ini sendiri.
- **Bukti draft (e-sign/lampiran, `validateSettlementEvidence`)**: variance
  dihitung per item asal (jumlah barisnya vs Request); baris tambahan
  bervariance sebesar nilainya. Item ikut yang barisnya dihapus semua
  dihitung settle 0 (variance = −Request) sehingga simpanan ditolak — aturan
  bukti tidak bisa dilewati dengan menghapus baris.
- **Penyimpanan:** baris di-upsert per `realizations[i][id]` (bukan dibuat
  ulang), jadi lampiran per baris tetap menempel pada barisnya setelah
  settlement ditolak lalu diajukan ulang. Hanya form lama (tidak satu baris
  pun membawa kunci `id`) yang dipasangkan ke baris tersimpan pertama dengan
  `request_detail_id` yang sama; form baru selalu mengirim `id` (kosong untuk
  baris baru), jadi baris Pecah/import tidak mengambil alih baris yang dihapus.
  Baris yang tidak dikirim lagi dihapus beserta berkas lampirannya dan e-sign
  `ttd-r<id>`-nya.
- **E-sign** (modal tiket) disimpan sebagai lampiran global
  `ttd-r<id baris realisasi>-<kode CR>.png` — dikunci ke ID baris, bukan nomor
  urut. Format lama `ttd-<n>-<kode>.png` (n = urutan item non-TAKEOUT) tetap
  diakui hanya untuk baris pertama (urutan form) item itu dan dinamai ulang ke
  baris tersebut saat draft disimpan.
- **Data lama** (baris sebelum rilis, `budget_detail_id` NULL): Sub COA
  efektif = `COALESCE(budget_detail_id baris, budget_detail_id item asal)`;
  tanpa backfill.
- **Uang tidak berubah:** Refund, Expense Claim, Second Payment, status,
  locking, dan role tetap memakai rumus/alur lama terhadap
  `total_realization` (jumlah semua baris).
- **Template/import Excel:** kolom `Request Detail ID` (kosong = baris
  tambahan) + `Budget Detail ID` (Sub COA, daftar di sheet Referensi; ID
  Budget Plan lain ditolak). Template lama (tanpa Budget Detail ID) tetap
  bisa diimpor.
- **Tampilan:** drawer CR & Todo List menampilkan "Perbandingan per COA / Sub
  COA" (`settlement_comparison`, Request = amount CR tanpa hitung ganda) dan
  "Rincian Settlement" per baris dengan badge "Baris tambahan" / "Pindah
  dari: {Sub COA asal}".

## Bulk approve Fase 4 (daftar CR)

Sejak 29 Okt 2026 checkbox bulk di tab Cash Advance juga berlaku untuk tahap settlement
(`BudgetRequest::SETTLE_BULK_ROLES`): `REALIZED` (Head → `headCheckSettlement`),
`SETTLE_CHECKED` (Finance/Finance Manager/Cost Control → `financeVerifySettlement`),
`SETTLE_VERIFIED` (Finance Manager → `settleApprove`, atau Close bila tanpa Expense Claim),
`SETTLE_APPROVED` (Approver → `secondApprove`). Endpoint `budget-request.settlement-bulk-approve`
menjalankan aksi satuan per CR (kunci status, log, notifikasi tetap satu sumber); maks 50 CR,
satu status per proses, `expected[id]` mencegah CR yang statusnya sudah berubah. Tidak ada bulk
Reject untuk tahap settlement.

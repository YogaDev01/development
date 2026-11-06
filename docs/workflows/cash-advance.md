# Workflow — Cash Advance (Cost Request, type "Cash Advance")

Source: `BudgetRequest::getAllowedNextStatuses()`, `BudgetRequestController`,
`Concerns/HandlesCrSettlement`, `DailyTransactionController`, `routes/web.php`.
UI labels: QUEUED = "Process", REALIZED = "Settle".

## CR yang lahir dari Trouble Ticket

Pengajuan biaya Trouble Ticket (tab Pengajuan) menghasilkan CR berstatus **DRAFT** saat supervisor team teknisi
menyetujuinya (`TroubleTicketWorkflow::approveRequest()`): applicant = supervisor itu, deskripsi/notes memuat nomor
tiket, NOB lewat `BudgetPlan::isNob()/nobOverage()` (rumus yang sama dengan form CR). Sesudah itu CR menempuh alur
di bawah seperti CR biasa; kuota CA ditegakkan saat `submit`. Satu tiket bisa punya banyak CR, dan pembayaran serta
settlement dicatat per CR. Detail: `docs/modules/trouble-ticket.md`.

## Request leg

| From → To | Action (route) | Roles |
|-----------|----------------|-------|
| — → DRAFT | `store` | manager, supervisor, superadmin |
| DRAFT → SUBMITTED | `submit` (needs ≥1 global attachment; CA quota check) | manager, superadmin |
| SUBMITTED → CHECKED / REJECTED | `head-approve` / `head-reject` (reason required) | head, superadmin |
| CHECKED → VERIFIED / REJECTED | `finance-verify` / `finance-reject` | finance, finance_manager, cost_control, superadmin |
| VERIFIED → APPROVED / REJECTED | `ceo-approve` / `ceo-reject` | approver, superadmin |
| APPROVED → QUEUED | added to Treasury batch (`daily-transaction` store / add-item) | finance, finance_manager, treasury, superadmin |
| QUEUED (batch PENDING_APPROVER → DONE) | batch `approve` (CR status unchanged, log written) | approver, superadmin |
| QUEUED → PAID | batch `mark-paid` | finance, finance_manager, treasury, superadmin |
| REJECTED → DRAFT / CANCELLED | `manager-revise` / `manager-cancel` | manager(+supervisor for revise), superadmin |

## Settlement leg — see `settlement.md`

```
PAID → REALIZED → SETTLE_CHECKED → SETTLE_VERIFIED ─┬─(no expense claim)→ CLOSED
                                                    └─(expense claim)→ SETTLE_APPROVED → SECOND_APPROVED → QUEUED → CLOSED
```

## Rules
- CA quota per PIC (`master_pics.token`/`nominal`), checked at create, edit and submit
  (`App\Support\CashAdvanceQuota::error()`).
- **Syarat beneficiary CA (sejak 2026-11-07, keputusan pemilik):** beneficiary wajib kategori
  INTERNAL, punya PIC, dan Token PIC berupa angka > 0 (`CashAdvanceQuota::eligibilityError()`).
  Token kosong/0/bukan angka = CA **diblokir** (sebelumnya: tanpa batas). Nominal tetap opsional.
  Berlaku saat simpan/ubah draft dan submit (termasuk Manager direct approval Trouble Ticket);
  CA yang sudah lewat submit tidak terpengaruh. Draft CA lama dengan PIC tanpa Token tidak bisa
  disimpan/disubmit sampai Token diisi di Master PIC.
- Refund only after settlement; see `docs/business-rules.md`.
- Bulk approve/reject from Todo List: `review-bulk-*`, `settlement-bulk-*`.

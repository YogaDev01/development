# Module — Finance

| Sub-module | Controller | Menu key(s) | Notes |
|-----------|------------|-------------|-------|
| Budget Plan | `BudgetPlanController` | `finance-budgeting`, `project-budgeting` | consumption via `BudgetConsumption`; tanpa Plan Finish/auto-closing — BP project ditutup otomatis saat PO ditutup, BP lain ditutup manual |
| Cost Request | `BudgetRequestController` (+ `HandlesCrSettlement`, `BuildsCrWorklist`) | `finance-cost-request`, `project-cost-request` | 3 lazy tabs: Cash Advance / Cost Payment / Database; Excel export + import templates |
| Invoice & Cash Receipt | `InvoiceCashReceiptController` | `invoice-cash-receipt` | calc in `InvoiceCalculationService`; comments; timeline (`InvoiceTimeline`) |
| Delivery Note | `DeliveryNoteController` | `delivery-note` | APPROVED invoice → SENT |
| Finance Summary | `FinanceSummaryController` | `finance-summary` | role-based value hiding (`PO_VALUE_ROLES`, …) |
| Reports | `ReportController` | `reports` | |
| Forecast | `ForecastController` | `forecast` | signed amounts; actual entries; daily auto-close |
| Closing | `ClosingController`, `ClosingService` | `closing` | recap rebuilt from source docs; does not lock other modules |
| Cost Center / Account / Source Fund | master controllers | `cost-center`, `account`, `source-fund` | |

Workflows: `docs/workflows/`. Rules: `docs/business-rules.md`.

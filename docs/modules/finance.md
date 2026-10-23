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

## Cash Receipt — index (`/cash-receipt`)

- Kolom: Kode, Tanggal, Customer, Referensi, **Tagihan** (Σ Invoice+PPN invoice unik per header), **Total PPh**, **Total Diterima** (Σ amount detail aktif, bukan `total_amount`), Invoice, Status, Aksi. Header CANCELLED: kolom uang 0. `total_amount` hanya untuk sisa belum teralokasi.
- Filter tanggal terima server-side (`date_from`, `date_to`, `range`; default bulan berjalan, inklusif, tidak valid → default dan `range` kosong; opsi default dropdown berlabel "Tanggal Terima"; `range` 1d/3d/7d/30d/1y dihitung dengan jam server relatif hari ini). Header CANCELLED: Belum Teralokasi 0. Cari/Status/Customer di browser; kartu dan footer dihitung ulang saat draw.
- `<tfoot>` per kolom (Tagihan/PPh/Diterima dari `data-*` baris terfilter). Tombol **Kolom** (localStorage `crCashColVis_*`): Tanggal, Referensi, Tagihan, Total PPh, Invoice bisa disembunyikan; tanpa ekstensi `responsive`, tabel digulir horizontal.
- Create: sisa = sisa bruto; PPh otomatis = sisa − diterima sampai PPh diketik manual, hanya bila ≤ 15% DPP invoice (di atasnya kosong + peringatan merah di dialog simpan, tidak memblokir); konfirmasi menampilkan ringkasan PPh per invoice. Aturan hitung: `docs/business-rules.md`.

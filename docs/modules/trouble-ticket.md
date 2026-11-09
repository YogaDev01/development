# Module — Trouble Ticket (TESYS Operation)

Trouble Ticket **telekomunikasi**: gangguan layanan di site (link down, perangkat, power, dll.): penugasan teknisi, respons pertama,
pemilihan jalur penanganan (Remote / Plan Visit), perjalanan ke site,
pengerjaan, penutupan + approval supervisor, diskusi + lampiran, dan SLA per
tiket.

Sejak **12 Okt 2026** alur status menjadi **7 tahap** (TT Open → Remote /
Plan → Trip → In Progress → Progress Done → Closed). Approval plan dihapus;
satu-satunya approval ada di penutupan tiket, dan hanya role `supervisor`.

Tiga perubahan alur pada **19 Okt 2026**: tidak ada lagi tombol **Remote**
(tiket `OPEN` yang dibuka teknisinya langsung masuk `REMOTE`), `PLAN → TRIP`
**tidak lagi otomatis** (teknisi menggeser swipe pada tanggal plan — sejak
20 Okt 2026 geseran itu "Swipe for travel", satu aksi dengan keberangkatan), dan
**Cancel Visit selalu mengembalikan tiket ke `PLAN`** dengan SLA Travel direset.
Mengubah tanggal plan yang sudah ada kini **hak supervisor**, dan fitur
**Stop Chat dihapus**.

Menu: **TESYS Operation → Workflow → Trouble Ticket** (`config/navigation.php`,
key `trouble-ticket`). URL `/trouble-ticket`.

## Komponen

| Lapisan | Berkas |
|---|---|
| Controller | `TroubleTicketController` (validasi inline + pilih respons) |
| Workflow | `App\Services\TroubleTicketWorkflow` — **satu-satunya** tempat status/timestamp berubah; juga aturan hak akses |
| Model | `TroubleTicket`, `TroubleTicketMessage`, `TroubleTicketAttachment`, `TroubleTicketVisit`, `TroubleTicketAssignment`, `TroubleTicketClosureReview`, `TroubleTicketProblem`, `TroubleTicketSeverity`, `TroubleTicketSlaPolicy`, `TroubleTicketRca`, `TroubleTicketSubRca`, `TroubleTicketIssue`, `TroubleTicketDevice` |
| Presentasi status | `WorkflowStatus` context `trouble-ticket` (`<x-status>`, `<x-workflow-flow>`) |
| Peran | `AccessRoles::TROUBLE_TICKET_FULL_SCOPE / _SUPERVISE / _SETTINGS` (semua `['superadmin']`) |
| View | `resources/views/trouble-ticket/` (`index`, `create`, `show`, `settings` (SLA saja), `_messages`, `_attachments`, `_sla`, `_assets`) |
| Test | `tests/Feature/TroubleTicketTest.php` |

## Skema (migrasi `2026_10_09_200001..200004`, `2026_10_12_000100..000200`)

```
sites ──< trouble_tickets >── users (created_by, assigned_to, first_responded_by,
 │                                    visit_decided_by, closed_by, cancelled_by)
 │   customer_id disalin dari site saat dibuat (filter/indeks)
trouble_ticket_problems ──< trouble_tickets, trouble_ticket_sla_policies   (tanpa UI)
trouble_ticket_severities ──< trouble_ticket_sla_policies (opsional)   (tanpa UI, LEGACY)
trouble_ticket_issues   ──< trouble_tickets.issue_id      (Initial Problem; seeder)
trouble_ticket_devices  ──< trouble_tickets.device_id     (seeder)
trouble_ticket_rcas     ──< trouble_ticket_sub_rcas ──< trouble_tickets.sub_rca_id   (seeder)
trouble_ticket_sla_policies ──< trouble_tickets (sla_policy_id + snapshot menit)

trouble_tickets ──< trouble_ticket_messages     (MESSAGE | EVENT, immutable, client_token)
                ──< trouble_ticket_attachments  (>── messages; disk secure)
                ──< trouble_ticket_visits       (≤1 aktif: unique active_ticket_id generated)
                ──< trouble_ticket_assignments  (riwayat penugasan + responded_at per penugasan)
                ──< trouble_ticket_closure_reviews (attempt_no; ≤1 PENDING: unique pending_ticket_id)
```

- Semua FK `RESTRICT`; tiket tidak pernah dihapus. Tabel `trouble_ticket_visits`
  dan `trouble_ticket_closure_reviews` tidak ditulis lagi sejak 10 Okt 2026
  (dibiarkan untuk riwayat lama).
  `UserController::destroy` dan Site (dependents) menolak hapus dengan pesan.
- Durasi disimpan detik (`response_seconds`, `travel_seconds`, `resolution_seconds`)
  saat difinalkan. Timestamp selalu dari jam server; zona tampilan `Asia/Jakarta`.
  Tiket jalur **Remote (Non-Visit)** tidak diukur Travel maupun **Resolution**
  (16 Okt 2026): `slaMetric('resolution')` mengembalikan `na`, layar menulis
  "Tidak berlaku (Non-Visit)", dan `scopeOverdue()` tidak lagi menghitungnya
  Lewat SLA. Kolom `resolution_seconds` tetap diisi saat close untuk riwayat.
- Tanpa kolom `deleted_at` (tidak ada fitur hapus pada MVP).
- Menu `trouble-ticket` didaftarkan migrasi `200003` **tanpa** diberikan ke role.

## State machine

```
OPEN ─(teknisi membuka detail tiket)───────────→ REMOTE
REMOTE ─(teknisi buat Plan Visit)──────────────→ PLAN
PLAN ─(geser "Swipe for travel", tanggal tiba)─→ TRIP (perjalanan dimulai)
TRIP ─(mulai perjalanan → On Site → mulai progres)→ IN_PROGRESS
TRIP ─(Cancel Visit / reschedule, sebelum On Site)→ PLAN
REMOTE / PLAN / TRIP / IN_PROGRESS ─(form close dikirim)→ PROGRESS_DONE
PROGRESS_DONE ─(supervisor approve)────────────→ CLOSED
              └─(supervisor tolak)─────────────→ REMOTE / IN_PROGRESS
```

Tidak ada approval plan dan tidak ada pembatalan tiket. Status lama `APPROVED`
dan `ON_PLAN` dipetakan ke `PLAN` oleh migrasi `2026_10_12_000300`.

`PLAN → TRIP` **digeser teknisi** (19 Okt 2026): `promoteDueTrips()` dicabut.
Sejak **20 Okt 2026** tidak ada swipe Trip tersendiri — `startTrip()` dan route
`POST {ticket}/trip` dihapus, dan **`startTravel()` (`POST {ticket}/travel`)
yang menaikkan status ke `TRIP` sekaligus memulai perjalanan**: tiket `PLAN`
yang `visit_site`-nya sudah tiba (`tripDue()`), evidence perjalanan tetap
wajib. Swipe "Swipe for travel" baru muncul pada hari-H; sebelum itu panel
Plan hanya memberi tahu kapan tahap Trip dimulai. `startTravel()` juga masih
menerima tiket yang sudah berada di `TRIP` (riwayat lama). Kejadian yang
tercatat hanya `travel_started`; `trip_due` tinggal label riwayat. Di daftar, tiket
`PLAN`/`TRIP` yang `visit_site`-nya sudah lewat tetap diberi tanda seru merah di
kolom Site dan tanggal Plan ditulis merah tebal.

Setiap transisi: transaksi + `lockForUpdate` baris tiket + cek ulang pelaku &
status asal + kejadian timeline di transaksi yang sama (gagal = batal semua).
Permintaan ulang gagal di cek status, jadi timestamp tidak pernah ditimpa dan
notifikasi tidak terkirim dua kali. Error keadaan = `ValidationException`
(`workflow`), hak akses = 403.

- **Respons pertama + Remote otomatis**: dicatat saat **teknisi yang
  ditugaskan** membuka detail (`recordAccess`). User lain tidak mencatat apa
  pun. `acknowledged_at` sekali seumur tiket;
  `trouble_ticket_assignments.responded_at` sekali per penugasan. Sejak 19 Okt
  2026 **tidak ada tombol Remote** (`POST {ticket}/remote` dan `startRemote()`
  dihapus): pada transaksi yang sama, tiket `OPEN` langsung menjadi `REMOTE`
  dengan `visit_decision = NON_VISIT` dan `progress_started_at` — **SLA Response
  berhenti dan SLA Resolution mulai di titik yang sama**. Keduanya ditulis
  sebagai **satu** kejadian `responded_remote` ("Ticket read — Remote"), bukan
  `responded` + `remote_started` pada detik yang sama; dua kode lama tetap
  dipakai riwayat tiket sebelum tanggal itu. Dari `REMOTE` teknisi mengirim form
  close atau membuat Plan Visit.
- **Create Plan** (`POST {ticket}/plan`): teknisi mengisi tanggal plan visit
  (site & pelaksana mengikuti tiket, tidak bisa diubah di modal) — **jadwal
  saja**; biaya diajukan terpisah lewat tab Pengajuan (lihat "Pengajuan Biaya"
  di bawah). Status
  `PLAN`, tanpa approval. Tanggal hari ini **tidak** langsung naik ke `TRIP`
  (19 Okt 2026) — teknisi menggeser swipe Trip. **Membuat plan pertama**
  (dari `REMOTE`): teknisi tiket ini atau supervisor (`canCreatePlan()`).
  **Batas tanggal** (19 Okt 2026, `TroubleTicket::planMinDate()`): plan pertama
  minimal **hari ini** — tanggal lampau tidak bisa dipilih. Saat tanggalnya
  diubah atau di-reschedule (`PLAN`/`TRIP`), tanggal lampau boleh, tapi tidak
  boleh lebih awal dari **hari plan itu dibuat** (kejadian `plan_created`
  pertama; fallback `created_at` tiket). Batas yang sama dipakai kalender
  (flatpickr `minDate` lewat `data-min-date`), validasi controller
  (`after_or_equal:`), dan guard service `assertVisitDateAllowed()` pada
  `createPlan()`, `cancelTravel()`, dan reschedule di `reassign()`.
  **Mengubah tanggal plan yang sudah ada** (`PLAN`/`TRIP`): hanya supervisor
  (`canReschedulePlan()` = leader team teknisinya, `TROUBLE_TICKET_SUPERVISE`,
  atau `TROUBLE_TICKET_APPROVE`); teknisi tidak lagi boleh menggesernya
  sendiri. Gerbang yang dipakai tombol & server: `canPlan()`.
  Hanya dari `REMOTE` (berbalik jadi kunjungan — SLA Resolution yang sempat
  berjalan dibatalkan), `PLAN` (ubah tanggal; kejadian `plan_updated`), dan
  `TRIP` (reschedule: jejak perjalanan dikosongkan supaya SLA Travel dihitung
  ulang; ditolak bila teknisi sudah On Site). **Dari `OPEN` tidak bisa**
  (15 Okt 2026) — teknisi memilih Remote dulu. Target online tidak lagi diisi
  dari form (12 Okt 2026).
- **Cancel Visit** (`POST {ticket}/travel/cancel`): wajib **alasan** dan
  **tanggal plan visit berikutnya** (15 Okt 2026). SLA Travel kembali ke 0
  (`travel_started_at` & `travel_seconds` dikosongkan) tapi riwayatnya tetap:
  `travel_cancelled_at`, `travel_cancel_reason`, dan kejadian `travel_cancelled`
  yang memuat jam keberangkatan yang dibatalkan + tanggal plan baru. Tiket
  **selalu kembali ke `PLAN`** (19 Okt 2026, termasuk bila tanggal berikutnya
  hari ini juga) — tahap Trip dimulai lagi lewat "Swipe for travel". **Inilah cara menjadwalkan ulang setelah perjalanan dimulai** —
  Change Teknisi sudah tertutup di titik itu. Keterangan panjang yang dulu
  tercetak di bawah form (log + SLA + aturan tanggal) **dihapus dari modal**
  (19 Okt 2026) — aturannya tetap berlaku, hanya tidak ditulis lagi di UI.
- **Trip**: tiga aksi teknisi, semuanya tetap di status `TRIP`:
  `POST {ticket}/travel` (geser "mulai perjalanan" membuka modal evidence;
  evidence perjalanan **wajib** (15 Okt 2026) dan menempel pada kejadian
  `travel_started` → `travel_started_at`, **SLA Travel mulai**), `POST {ticket}/travel/cancel` (wajib alasan →
  `travel_started_at` dikosongkan sehingga SLA Travel tidak dihitung,
  `travel_cancelled_at` + alasan tetap tercatat di log; teknisi boleh
  berangkat lagi), dan `POST {ticket}/arrive` (konfirmasi On Site →
  `travel_arrived_at` + `travel_seconds`, **SLA Travel berhenti**).
- **Start Progress** (`POST {ticket}/start`): hanya dari `TRIP` dan hanya
  setelah On Site. Status `IN_PROGRESS`, `progress_started_at` diisi — **SLA
  Resolution mulai**.
- **Form close** (`POST {ticket}/close`): dari `OPEN` (20 Okt 2026), `REMOTE`,
  `PLAN`, `TRIP`, maupun `IN_PROGRESS` (`TroubleTicket::CLOSABLE`). Sejak 20 Okt
  2026 **semua peran yang boleh melihat tiket** dapat mengirimnya (bukan lagi
  hanya teknisi yang ditugaskan + superadmin) — gerbangnya
  `TroubleTicketWorkflow::canClose()`, dengan satu kekecualian: tahap `TRIP`
  tertutup bagi **teknisi tiket itu** — sebelum maupun sesudah On Site ia
  memulai progres dulu dan menutup dari `IN_PROGRESS`. Peran lain tetap bisa
  menutup dari `TRIP`, termasuk sesudah On Site (20 Okt 2026). Dari `TRIP` sebelum On
  Site, perjalanan yang sedang berjalan dibatalkan otomatis (kejadian
  `travel_cancelled`, SLA Travel tidak dihitung). **Setelah On Site form close ditutup
  bagi teknisi** (19 Okt 2026, dipersempit 20 Okt 2026): `TRIP` +
  `travel_arrived_at` terisi ditolak server untuk teknisi tiket itu dan
  tombolnya tidak dirender — progres dimulai dulu, penutupan dari
  `IN_PROGRESS`. Wajib: RCA (dari master),
  keterangan, dan evidence — kolom Action tidak dipakai lagi. RCA/Sub-RCA
  tiket DIPERBARUI mengikuti isian form ini. Tiket yang ditutup langsung
  dari `PLAN` memakai waktu pengiriman form sebagai awal Resolution. Status `PROGRESS_DONE`, `approval = PENDING`,
  `progress_done_at`/`resolved_at` diisi. **Tiket belum ditutup.**
- **Pengajuan Biaya (pra-Cost Request)** (Okt 2026, tab **Pengajuan** pada halaman
  tiket). Menggantikan isian biaya pada Create Plan: **Plan visit = jadwal saja**
  (`createPlan()` tidak menulis atau menyentuh biaya maupun `plan_budget_*`;
  menjadwalkan ulang tidak membatalkan biaya).
  - **Siapa & kapan:** teknisi tiket (atau superadmin) boleh mengajukan di **semua
    tahap kecuali `CLOSED`**; satu tiket boleh punya **banyak** pengajuan, termasuk
    beberapa yang `SUBMITTED` bersamaan (`TroubleTicketWorkflow::canRequest()`).
  - **Reviewer** (`canReviewRequest()`): user ber-role supervisor
    (`AccessRoles::TROUBLE_TICKET_APPROVE`) dalam **team yang sama** dengan teknisi
    tiket (leader atau anggota ACTIVE team ACTIVE), **bukan pembuat pengajuan**;
    superadmin tetap boleh (juga tidak untuk pengajuan buatannya sendiri).
  - **Tabel** (`2026_10_21_000100`, additive, `down()` menolak bila berisi data):
    `trouble_ticket_requests` (header; `status` DRAFT/SUBMITTED/APPROVED/REJECTED/
    CANCELLED, `revision`, `budget_request_id` **UNIQUE tanpa FK**, soft delete),
    `trouble_ticket_request_items` (baris per revisi), `trouble_ticket_request_attachments`.
    **Revisi = baris item BARU** (`revision + 1`); baris lama tidak diubah atau dihapus,
    lampiran yang dipertahankan disalin sebagai baris baru (path sama), berkas tidak
    pernah dihapus. Uang integer rupiah; `total` = harga × qty dihitung server.
  - **Isian** (mengikuti form CR): Jenis (semua `request_type`) → Budget Plan ACTIVE
    (teknisi disaring ke customer team-nya: `abortIfBudgetPlanLuarCustomer()`) →
    Project (= `cluster_id` di Cost Request; wajib untuk budget proyek, otomatis bila satu; ditampilkan `[kode] nama`; daftar = cluster pada milestone project (`project_details.cluster_id`) milik Budget Plan, `BudgetPlan::projectClusters()`, sama dengan form CR) → Beneficiary (Cash
    Advance: beneficiary INTERNAL ACTIVE milik PIC teknisi, otomatis —
    `TroubleTicketWorkflow::caBeneficiary()`: PIC = `trouble_tickets.pic_id`,
    bila kosong PIC internal ACTIVE yang terhubung ke akun teknisi; teknisi
    tanpa Master PIC atau tanpa Beneficiary Internal = jenis Cash Advance
    dinonaktifkan di form dan ditolak server (9 Nov 2026); jenis lain:
    dropdown beneficiary ACTIVE) → Deskripsi → baris (sub-item budget plan,
    deskripsi, harga, qty bulat) → lampiran. **Lampiran:** Cash Advance tidak wajib;
    jenis lain **wajib per baris**.
  - **Tanpa informasi budget** di TT: tidak ada sisa pagu, peringatan NOB, atau kuota
    Cash Advance. Kuota CA tetap ditegakkan saat CR di-submit di modul Cost Request.
  - **Alur:** `saveRequest` (Draft atau langsung `submit`) → `submitRequest` →
    reviewer `approveRequest` / `rejectRequest` (alasan wajib; teknisi merevisi lalu
    mengajukan lagi) / `cancelRequest` (pembuat atau reviewer, alasan wajib). Semua
    transisi: transaksi + kunci baris tiket lalu baris pengajuan + cek status asal.
  - **Supervisor boleh mengubah isi** sebelum approve (`editRequestByReviewer()`):
    revisi baru (`created_by` = supervisor), status tetap `SUBMITTED`, kejadian
    `request_edited` mencatat baris ditambah/dihapus/diubah dan total lama → baru
    (chat & tab Log), pemohon dinotifikasi.
  - **Approve → Cost Request `DRAFT`** (`buatDraftCostRequest()`): jenis & beneficiary
    sesuai pilihan, `applicant_id` = **supervisor yang approve**, cluster & budget plan
    dari pengajuan, deskripsi/notes memuat **nomor tiket** (tidak ada nomor pengajuan
    sendiri), kode lewat `BudgetRequest::generateCode()`, rincian dari revisi terakhir,
    **NOB lewat `BudgetPlan::isNob()/nobOverage()`** — satu sumber rumus dengan form CR
    (tidak ada salinan di TT), lampiran baris → "Lampiran Item" CR (berkas disalin),
    lampiran umum → lampiran global, ditambah PNG ringkasan tiket
    (`TicketDetailImage`, kegagalan menggambar tidak membatalkan approve). Dua approve
    bersamaan tidak membuat CR ganda (kunci + UNIQUE). Penolakan/pembatalan CR di modul
    Cost Request tidak mengubah pengajuan (status CR ditampilkan live).
  - **Kejadian:** `request_submitted/edited/approved/rejected/cancelled` (chat & Log,
    dengan salinan baris di metadata) dan `cost_request_paid` yang kini **unik per CR**
    (`tulisKejadianDibayar()` mencocokkan kode CR, bukan per tiket).
  - **Pembayaran & settlement per CR:** `recordCostRequestPaid()` mencari tiket lewat
    `TroubleTicket::forCostRequest()` (pengajuan **atau** `trouble_tickets.budget_request_id`
    data lama); `syncCostRequestPaid()` memeriksa semua CR tiket; tombol Settlement satu
    per CR yang berhak (`?settle=<id CR>` memilih CR pada modal). `BudgetRequestController`
    (`isTicketTechnicianOfCr()`, `crBerasalDariTiket()`) memakai scope yang sama.
  - **Todo List:** antrean `ticketRequestReview` ("Trouble Ticket — Review Pengajuan",
    role supervisor/superadmin, scope team teknisi, bukan buatan sendiri) lewat
    `TodoService::QUEUES`; tautan membuka tiket dengan tab Pengajuan (`?tab=pengajuan`).
  - **Notifikasi** lewat `NotifyService` (queued): reviewer saat diajukan; pemohon saat
    diubah/disetujui/ditolak.
  - **Data lama (tanpa backfill):** tiket dengan `trouble_ticket_plan_costs`/`plan_budget_*`/
    `budget_request_id` tetap terbaca read-only pada bagian "Biaya Plan (lama)"; CR lama,
    event dibayar, dan settlement-nya tetap berfungsi. Route `plan/approve` & `plan/reject`
    dipertahankan tetapi hanya mengembalikan pesan "Biaya kini lewat tab Pengajuan".
- **Settlement dari halaman tiket** (20 Okt 2026): begitu CR tiket berstatus
  `PAID`/`SETTLE_REJECTED`, panel tahap memunculkan **Submit Settlement**.
  Modalnya meniru layar Settlement per Item (Description Settle, Qty, Request,
  Settle, Variance) dan **mengirim ke endpoint yang sudah ada** —
  `POST budget-request/{id}/save-realization-draft` — jadi ia menyimpan
  **DRAFT**: status CR tidak berubah dan pengajuan resminya tetap dilakukan
  dari modul Cost Request. Tidak ada perhitungan settlement yang ditulis ulang
  di modul ini.
  **Perubahan otorisasi (jalur uang):** teknisi kini boleh submit settlement,
  tetapi HANYA untuk CR yang lahir dari plan visit tiket yang ditugaskan
  kepadanya. Penyempitan itu ditegakkan di tiga lapis yang harus tetap sejalan
  (CLAUDE.md §8): route `role:superadmin,manager,supervisor,teknisi` untuk
  `submit-realization` & `save-realization-draft`,
  `BudgetRequestController::isTicketTechnicianOfCr()` pada keduanya dan pada
  `canViewCr()`, serta tombol di `_stage.blade.php` (`$canSettle`).
  Form realisasi penuh (`realize-form`) TETAP tanpa teknisi.
  **E-sign per baris**: tiap baris punya dua tombol IKON — lampiran item dan
  e-sign. E-sign membuka pad bersama (Upload / Ulang / Batal); coretannya
  disimpan di input tersembunyi baris itu dan baru ditulis server saat
  settlement disimpan, sebagai **lampiran global CR** bernama
  `ttd-<no baris>-<kode CR>.png` di `settlement_signatures/` pada disk
  `secure` — bukan lampiran item, supaya bukti serah-terima terkumpul
  terpisah dari nota belanja. Mengirim ulang mengganti tanda tangan baris itu,
  jadi menyimpan draft berkali-kali tidak menumpuk berkas.
  Catatan: kolom `settlement_signature_path`/`_signer_name`/`_signed_at` dari
  migrasi `2026_10_20_000500` dibuat untuk rancangan awal (satu tanda tangan
  per settlement) dan **kini tidak dipakai** — aditif & nullable, tidak
  mengganggu alur mana pun.
- **Pencairan CR kembali ke tiket** (20 Okt 2026): saat Treasury menandai batch
  **Paid**, `DailyTransactionController::markPaid()` memanggil
  `TroubleTicketWorkflow::recordCostRequestPaid()` yang menulis kejadian
  **`cost_request_paid`** ("Cost Request paid") di timeline chat & tab **Log**
  tiket beserta nomor CR dan nilainya, lalu memberi tahu teknisinya. Status
  tiket maupun CR **tidak** diubah — murni jejak baca; pemanggilan berulang
  tidak menggandakan baris karena kejadiannya dicek lebih dulu.
  Hook itu hanya menangkap pembayaran yang MELEWATI `markPaid()`. CR yang
  statusnya berubah lewat jalur lain (perbaikan data, impor, alur lama) tidak
  memicunya, jadi `syncCostRequestPaid()` dipanggil sekali setiap halaman
  detail tiket dibuka: bila CR-nya sudah `PAID`/`REALIZED`/`SETTLE_*`/`CLOSED`
  tetapi kejadiannya belum ada, barisnya ditulis dengan pelaku **pemohon CR**
  (fallback penyetuju plan), bukan orang yang kebetulan membuka tiket.
  Migrasi: `2026_10_20_000100_create_trouble_ticket_plan_costs_table`,
  `2026_10_20_000200_add_plan_cost_to_trouble_tickets`,
  `2026_10_20_000300_add_budget_detail_to_trouble_ticket_plan_costs`,
  `2026_10_20_000400_add_plan_budget_approval_to_trouble_tickets`.
- **Approval penutupan**: hanya `AccessRoles::TROUBLE_TICKET_APPROVE`
  (`supervisor`, `superadmin`) dan **bukan** teknisi tiket itu.
  `POST {ticket}/close/approve` → `CLOSED` + `approved_at/by` + `closed_at`
  (`closed_by` tetap teknisi) dan `resolution_seconds` difinalkan.
  `POST {ticket}/close/reject` wajib alasan → kembali ke `IN_PROGRESS`
  (atau `REMOTE` untuk jalur non-visit); SLA Resolution tetap berjalan.
- **Reassign** (tombol **Change Teknisi**, dulu "Swap Teknisi"): pelakunya =
  pengawas tiket (`canSupervise()`) **ditambah peran penyetuju penutupan —
  `supervisor`** (keputusan 20 Okt 2026, `canReassignBy()`). Jendelanya
  **sejak plan visit dibuat sampai tepat sebelum swipe "mulai perjalanan"** —
  `TroubleTicketWorkflow::canReassign()` = status `PLAN`/`TRIP` **dan**
  `travel_started_at`/`travel_arrived_at` masih NULL (15 Okt 2026). Setelah
  travel jalan, On Site, atau di tahap `OPEN`/`REMOTE`/`IN_PROGRESS`/
  `PROGRESS_DONE`, tombolnya tidak ada dan server menolak. Kandidatnya =
  **Teknisi Backup site itu** (16 Okt 2026): baris `site_backup_technicians`
  yang diisi manusia di Master Data › Site, disaring akun ACTIVE, bukan leader
  team, dan bukan teknisi tiket itu sendiri. Pemilihan otomatis lewat
  homebase/cluster terdekat (15 Okt 2026) sudah **tidak dipakai lagi**; bila
  daftar backup kosong, modalnya menampilkan empty state dan tombol Simpan mati.
  `reassign()` memeriksa teknisi baru memang ada di daftar kandidat
  itu; `pic_id` jadi NULL bila teknisi baru bukan PIC site tersebut (tiket tetap
  terlihat olehnya lewat `assigned_to`). Bila belum ada keputusan jalur, status kembali `OPEN`; bila
  sudah, status tetap. Pada `PLAN` dan `TRIP` (sebelum On Site) modalnya juga
  memuat **Plan Visit** (`visit_site`, wajib): tiket kembali ke `PLAN` dengan
  tanggal baru, jejak perjalanan dikosongkan supaya SLA Travel dihitung ulang.
  Sejak 16 Okt 2026 tidak ada kejadian `plan_updated` tersendiri: tanggal baru
  ikut di metadata (`visit_site`, `target_online`) kejadian `reassigned`, jadi
  satu aksi = satu baris log dengan satu keterangan. Riwayat lama yang punya
  dua baris tetap terbaca apa adanya. **Inilah satu-satunya
  jalan reschedule di tahap Trip** (15 Okt 2026) — tombol Reschedule di panel
  Trip dicabut; `POST {ticket}/plan` tetap menerima `TRIP` untuk integrasi.
- **Customer mengikuti team pembuat tiket** (19 Okt 2026): form Buat Trouble
  Ticket tidak lagi menanyakan customer bila pembuatnya anggota ACTIVE (atau
  leader) sebuah team ACTIVE yang customer-nya terisi (diisi di Master Data >
  Team). Sejak 6 Nov 2026 satu team boleh punya beberapa customer: daftarnya di
  tabel `team_customers` (migrasi `2026_11_06_000500`), sedangkan
  `teams.customer_id` hanya menyimpan customer pertama dan dibaca selama migrasi
  itu belum dijalankan. Satu customer → kotaknya baca-saja dan daftar site
  langsung menyempit ke customer itu; lebih dari satu customer (dari satu team
  atau beberapa team) → tetap memilih, hanya daftarnya yang menyempit; tanpa
  team (mis. superadmin) → perilaku lama. Penegakannya ada di `store()`: site di
  luar customer team pembuat ditolak (`site_id`), jadi kiriman browser tidak
  bisa menembusnya. Batas Budget Plan teknisi memakai daftar yang sama, jadi
  teknisi team multi-customer dapat memilih Budget Plan semua customer team-nya.
- **Notifikasi tiket baru**: teknisi yang ditugaskan **dan seluruh PIC internal
  site** (`sitePicUsers()`).

## Integrasi Finance (v3.3.1, 6 Nov 2026)

Blueprint & keputusan: `docs/plans/2026-11-06-trouble-ticket-finance-integration.md`. Bagian ini
mengganti aturan lama di dokumen ini bila bertentangan.

- **Otoritas mengikuti hierarki Team** (`App\Services\OrgHierarchy`), bukan role global: atasan =
  leader team teknisi, atau leader team di atasnya (penyambungan bagan). `canSupervise()` memakai
  ini; approve penutupan, reassign, dan ubah plan ikut terbatas ke bawahan, termasuk lewat URL.
- **Form close:** teknisi + pengawas tiket (`canClose()`). Pembuat tiket dan Finance tidak otomatis
  berhak.
- **Chat** (`canMessage()`, OD-7, 6 Nov 2026): teknisi, pengawas, dan **pembuat tiket** (bukti dari
  NOC masuk lewat chat karena form Create tanpa lampiran; karena itu `scopeVisibleTo()` kembali
  memuat tiket buatan sendiri untuk user biasa). Finance, Treasury, dan lainnya baca-saja;
  `postMessage()` menolak (403) pengirim di luar `canMessage()`, juga lewat URL langsung.
- **Dokumen uang** (`canViewFinance()`: kwitansi, lampiran CR tiket; 6 Nov 2026): pemohon dana,
  atasan peninjau settlement (leader team teknisi yang sedang ditugaskan atau di atasnya), Finance
  read, superadmin. Teknisi yang bukan pemohon dan pembuat tiket tidak berhak. `FileAccess` tetap
  membuka lampiran CR bagi yang lolos `scopeByRole` atau pernah menindak CR.
- **Reassign membatalkan pengajuan yang belum jadi CR** (6 Nov 2026): DRAFT, SUBMITTED, REJECTED
  milik selain teknisi baru menjadi `CANCELLED` dalam transaksi `reassign()` (kejadian
  `request_cancelled`, alasan "Otomatis: tiket dialihkan ke …", pemohon dinotifikasi). Pengajuan yang
  sudah jadi CR tidak disentuh (OD-20).
- **Keterlihatan tambahan:** bawahan menurut hierarki; pemohon dana tetap melihat tiketnya setelah
  dialihkan; `AccessRoles::TROUBLE_TICKET_FINANCE_READ` baca-saja untuk tiket ber-CR.
- **Review pengajuan:** leader team; leader atas boleh mengambil alih kapan saja (metadata `takeover`
  + notifikasi leader team). Sesama anggota tidak lagi bisa me-review.
- **Approve & Submit** (`approveRequest(..., langsung)`): role `manager` yang menjadi atasan. Memakai
  `BudgetRequest::submissionError()` + `markSubmitted()` yang sama dengan tombol Submit; gagal = seluruh
  transaksi batal. Harus punya akses proyek Budget Plan.
- **CR gugur** (`costRequestGone()`): tombol Ajukan Ulang membuat pengajuan baru; yang lama utuh.
- **Nomor pengajuan** `FR-mmyy-0001` lewat `DocumentSequence`; data lama tampil `#id`.
- **Cermin status CR:** `BudgetRequest::addLog()` -> `mirrorCostRequestLog()` sesudah commit, gagal-aman,
  idempoten lewat `client_token`. Kejadian: `cost_request_status`, `settlement_*`, `financial_closed`.
- **Settlement dari tiket** (Cash Advance saja): draft -> Kirim Settlement -> atasan Teruskan ke Finance
  (CR -> `REALIZED`) atau Kembalikan. Terkunci selama review. Pemilik = pemohon dana
  (`isSettlementOwner()`). CR dari pengajuan bernomor tidak bisa disubmit dari modul Cost Request.
  Syarat kirim: `settlementBlockers()` (tiap item punya baris, baris bernilai punya nota/kwitansi,
  kelebihan beralasan).
- **Kwitansi digital** (`SettlementReceiptService`, tabel `settlement_receipts`): penerima uang tanda tangan
  di HP teknisi; PDF dompdf disimpan sebagai arsip dan salinan lampiran baris. Nilai berubah = `SUPERSEDED`.
  Ini kwitansi biasa, bukan tanda tangan elektronik tersertifikasi. Nomor `KW-…` diambil dari
  `DocumentSequence` di dalam transaksi simpan, jadi tetap urut tanpa celah (ikut batal bila gagal);
  konsekuensinya penerbitan kwitansi lain menunggu selama PDF dirender (keputusan 6 Nov 2026,
  diterima karena kwitansi jarang terbit bersamaan).
- **Status keuangan terpisah dari status tiket:** tiket boleh `CLOSED` walau dana belum settle.
  `financialState()` / `financialBlockers()` / `scopeFinanceOpen()` dihitung dari pengajuan & CR, tidak
  disimpan. Pengajuan yang ada tetap bisa diselesaikan setelah `CLOSED`; pengajuan baru tidak.
- **Import Excel** (`Concerns\ImportsTroubleTickets`): template, pratinjau, konfirmasi; maks. 500 baris,
  parsial, token sekali pakai. Duplikat site+RCA+tanggal hanya peringatan. Hanya sheet Data,
  kolom A–H, dan 2.000 baris pertama yang dimuat (data saja); sheet lebih panjang ditolak sebelum
  dimuat supaya berkas besar tidak menghabiskan memori hosting.
- **Cermin log CR ke tiket:** setiap `addLog()` CR mencari tiket asalnya lewat
  `TroubleTicket::idForCostRequest()` (dua pencarian ber-index, di-cache per instance CR).
- **Direct approval** wajib lolos jalur `scopeByRole()` Manager: proyek per Access Group, Budget
  Plan non-proyek hanya bila cost center overhead-like-nya ada di Access Group Manager.
- **Todo List:** `TodoService::TICKET_ACTION_QUEUES` tampil sebagai satu bagian "Trouble Ticket — Tindakan".

## SLA

| Ukuran | Rumus | Catatan |
|---|---|---|
| Response | `acknowledged_at − created_at` | Berhenti saat **teknisi yang ditugaskan membaca detail tiket** (`recordAccess()`). Penugasan wajib saat dibuat, jadi = waktu penugasan awal. Reassign tidak me-restart jam. |
| Travel | `travel_arrived_at − travel_started_at` | Sejak 12 Okt 2026: dari geser "mulai perjalanan" sampai konfirmasi On Site. Visit yang dibatalkan mengosongkan `travel_started_at`, jadi tidak ikut dihitung. Jalur Remote tidak pernah punya Travel. |
| Resolution | `closed_at − progress_started_at` | Mulai saat Remote dipilih atau progres di site dimulai; **berhenti saat supervisor approve**, bukan saat teknisi mengirim form close. |

- Angka SLA hanya ditampilkan di tab **SLA** halaman detail, dan hanya untuk
  teknisi tiket itu (atau superadmin); targetnya tidak ditampilkan di layar
  (keputusan 12 Okt 2026) — datanya tetap disimpan dan tetap dipakai penanda
  "Lewat SLA" di daftar.
- Target di `trouble_ticket_sla_policies`, dikelola superadmin di
  `/trouble-ticket/settings`. **Belum ada nilai** — diisi perusahaan. Target
  disalin ke tiket saat dibuat (snapshot).
- Sejak 12 Okt 2026 kebijakan untuk tiket baru dicari **per Problem saja**
  (`applicableForProblem()`), karena severity tidak lagi jadi masukan form.
  Kolom `severity` pada kebijakan jadi opsional (kosong = semua severity);
  `applicableFor()` yang lama masih ada untuk baris/riwayat lama.
- Level: tanpa target = `none` (tidak pernah "terlambat"); berjalan ≥75% =
  warning; ≥100% = critical. Filter "Hanya lewat SLA" = `TroubleTicket::scopeOverdue()`.
- Timer di layar dihitung dari jam server (offset jam perangkat dikoreksi).

## Tampilan halaman detail (13 Okt 2026)

Tab **Tiket** sengaja ringkas; jejak waktu lengkap dibaca di tab **Log**, angka
SLA di tab **SLA**.

- **Identitas**: No. TT, Date Open, Dibuat oleh (nama saja), dan Plan visit
  (tanggal kunjungan) bila jalurnya Visit. Tanpa jam, tanpa Date Update.
- **Gangguan**: Aging, Severity (tanpa catatan "otomatis dari aging"),
  RCA & Sub-RCA, Device, Keterangan. Problem, Initial Problem dan Flag alarm
  tidak lagi ditampilkan; RCA/Sub-RCA disembunyikan setelah tiket Closed
  supaya tidak dobel dengan Penyelesaian.
- Bagian **Plan** dihapus (13 Okt 2026): jalur Visit/Non-Visit terbaca dari
  status tiket dan tab Log; Plan visit (tanggal) ada di Identitas.
- **Penyelesaian**: RCA, Sub-RCA, Keterangan, Close by, Date closed.
- Bagian **Penugasan** dihapus — teknisi & PIC dibaca di tab Site/Summary.
- Satu istilah saja untuk teks bebas tiket: **Keterangan** (bukan "Deskripsi").
- Evidence dibuka sebagai lapisan di atas halaman (`#ttViewer`), bukan tab
  baru: tombol tutup di kanan atas, panah/geser bila lebih dari satu gambar,
  Esc & panah papan ketik. Penting untuk supervisor yang memeriksa bukti tanpa
  menutup modal approval. Tombolnya bernama **Close** (dulu "Approve & Close").
- Saat form dikirim, hanya tombol kirim yang berubah jadi pemutar; tombol lain
  dimatikan tanpa pemutar.
- Daftar di HP: bilah cari + chip tahap **ikut tergulir** (tidak menempel di
  atas — versi menempel menutupi kartu teratas); chip tahap **turun baris**, tidak digulir mendatar — versi
  bergulir menyisakan batang scroll yang menimpa chip di browser HP.
- Penugasan awal **tidak** ditulis ke log (13 Okt 2026): baris sesudah
  "Ticket created" adalah **"Ticket read"**, yaitu saat teknisi yang ditugaskan
  membuka halaman detail — titik berhentinya jam Response
  (`recordAccess()`, kejadian `responded`). Pengalihan teknisi sesudahnya tetap
  dicatat sebagai `reassigned`.
- Baris `reassigned` di tab **Log** menampilkan perpindahannya
  (`from_name` &rarr; `to_name` dari metadata kejadian), sama seperti gelembung
  di timeline chat.
- **Diskusi tidak pernah ditutup (19 Okt 2026).** Tiket `CLOSED` tetap bisa
  dikomentari, dan fitur **Stop Chat dihapus** pada hari yang sama: route
  `chat-lock`, `setChatLock()`, `canLockChat()`, `TroubleTicket::chatLocked()`
  dan `AccessRoles::TROUBLE_TICKET_CHAT_LOCK` tidak ada lagi.
  `canMessage()` kini sama dengan keterlihatan tiket. Kolom
  `chat_locked_at` / `chat_locked_by` (migrasi `2026_10_19_000200`) dibiarkan di
  basis data tanpa pengisi; label kejadian `chat_stopped` / `chat_reopened`
  dipertahankan supaya log lama tetap terbaca.
- **Label `PROGRESS_DONE` dibaca "Waiting Approve"** (19 Okt 2026) lewat
  `WorkflowStatus::TROUBLE_TICKET_LABELS`; kode statusnya tidak berubah. Panel
  tahapnya berjudul **"Waiting approval supervisor"**. Halaman detail juga
  **memuat ulang sendiri** begitu polling melihat status berpindah, supaya
  teknisi langsung melihat tahap Closed setelah supervisor approve.
- Label kejadian di tab **Log** dan timeline berbahasa **Inggris**
  (`TroubleTicketMessage::EVENTS`) — satu-satunya bagian modul ini yang tidak
  berbahasa Indonesia; permintaan pengguna 13 Okt 2026. Pengecualian:
  `reassigned` berlabel **Change Teknisi** mengikuti nama tombolnya
  (16 Okt 2026).
- Kolom **Action** di daftar punya tombol **Close** untuk tiket berstatus
  `PROGRESS_DONE` (14 Okt 2026): hanya tampil bagi pemegang
  `AccessRoles::TROUBLE_TICKET_APPROVE` yang bukan teknisi tiket itu, dan
  server tetap memeriksa ulang lewat `canApproveClose()`. Konfirmasinya memakai
  `data-confirm` bawaan tesys-ui.js. Evidence tidak ditampilkan di daftar —
  pemeriksaannya tetap di halaman detail.
- Kolom **Problem** di daftar diganti **RCA** (isi `rca_label` / `sub_rca_label`);
  filter kolomnya ikut berubah (`data-rca`).
- Form buat tiket mengirim satu blok Gangguan sebagai `tickets[0][...]`.
  Tombol **Tambah Tiket** (blok ganda di layar) sempat ada 13 Okt 2026 lalu
  dicabut atas permintaan pengguna; **server tetap menerima banyak blok**
  (maks. 20 per simpan, `MAX_TICKETS_PER_SUBMIT`) untuk integrasi. Bentuk datar
  lama (field tanpa indeks) juga diterima dan dibungkus jadi `tickets[0]`.
  Tiket dibuat berurutan, bukan satu transaksi: bila blok ke-N ditolak
  workflow, tiket sebelumnya tetap tersimpan dan nomornya disebut di pesan
  galat. Validasi bentuk (keterangan wajib, dll.) berjalan lebih dulu, jadi
  isian cacat tidak pernah menyimpan sebagian.
- Form buat tiket: tanpa Date Update; Teknisi bukan dropdown lagi melainkan
  kotak baca-saja + `<input type="hidden" name="assigned_to">` yang diisi dari
  endpoint teknisi site; tombol Batal/Simpan duduk di kolom kanan.
- Form buat tiket: kolom **Device dicabut** dari layar (15 Okt 2026); kolom
  `device_id` dan masternya tetap ada dan masih diterima dari integrasi.
  Tempatnya dipakai **No. Tiket Custom** (`tickets[*][ticket_number]`, opsional,
  maks. 50 karakter): bila diisi, `ticket_number` memakai nilai itu apa adanya;
  bila kosong, nomor otomatis `DocumentSequence` seperti biasa. Keunikan dijaga
  dua kali — `unique:trouble_tickets,ticket_number` + `distinct` antar blok di
  controller, dan pemeriksaan ulang di `TroubleTicketWorkflow::create()`.
- Setelah **Simpan Tiket**, pemakai kembali ke **daftar**, bukan ke halaman
  detail (15 Okt 2026). Daftar dibuka **apa adanya** — semua tahap, tanpa
  filter status — dan baris tiket baru diberi kelas `tt-row-baru`: stabilo
  merah (`--danger-bg`) yang **memudar sendiri** lewat keyframes
  `ttBaruLuntur` sepanjang **2 detik**, hanya latar tanpa garis tepi. Id
  tiketnya dikirim lewat session `tt_baru` → view var `$baruIds`; baris tabel
  dan kartu HP sama-sama dikenali lewat `data-id`.
- **Log aktivitas dipakai bersama** (19 Okt 2026): tab **Log** di halaman
  detail dan modal **Info** dari daftar sama-sama merender
  `trouble-ticket/_log.blade.php` (`$statusLog`, `$tautChat` hanya true di
  halaman detail), jadi keduanya tidak pernah bercerita berbeda.
- **Tinggi halaman detail** (6 Nov 2026): `fitDetail()` mengukur wadah gulir `.main > .content`
  dan membagi posisi dengan skala `--ts-zoom`; sebelumnya panel tergambar 80% dari tinggi yang
  dihitung dan menyisakan ruang kosong di bawah. Saat `tt-detail-open`, padding bawah wadah 12px.
- **Form buat tiket tanpa lampiran** (6 Nov 2026): lampiran ditambahkan lewat chat saat tiket
  ditangani. Server tetap menerima `tickets.*.attachments` untuk integrasi.
- **Scrollbar disembunyikan** di halaman detail (19 Okt 2026): `.tt-chat-list`,
  `.tt-tab-body`, dan `.tt-info-log` memakai `scrollbar-width:none` +
  `::-webkit-scrollbar{display:none}` — gulirannya tetap berjalan. Di desktop
  (≥992px) `fitDetail()` juga memasang kelas `tt-detail-open` pada `<body>`
  (`overflow-y:hidden`, 20 Okt 2026) supaya sisa pembulatan tinggi tidak
  memunculkan batang gulir kosong di tepi kanan halaman; di HP kelas itu
  dilepas dan halaman menggulir seperti biasa.
- Panel tahap **Plan**: swipe "Swipe for travel" (hanya pada tanggal plan;
  membuka modal evidence perjalanan, lalu tiket masuk tahap Trip),
  tombol **Form Close** (semua peran, lihat `canClose()`), dan **Ubah
  Tanggal** yang hanya tampil untuk supervisor. Di tahap **Trip** Ubah Tanggal
  **dicabut** (20 Okt 2026): jadwal diubah lewat Cancel Visit yang sekaligus
  menjadwalkan ulang.
- Panel tahap Trip: tombol **On Site / Cancel Visit / Form Close** memakai
  `tt-btn-wide` — selebar panel (bukan separuh baris) tapi tetap ramping,
  tinggi 38px (42px di HP). Sempat setinggi swipe (52px) pada 15 Okt 2026 dan
  langsung dikecilkan lagi karena terlalu besar.
- Form buat tiket: **Customer dipilih lebih dulu** (15 Okt 2026), tepat di atas
  Site; dropdown Site hanya memuat site aktif milik customer itu. Penyaringannya
  di browser (seluruh site aktif ikut dikirim bersama halaman), tanpa request
  tambahan. `customer_id` dikirim hanya supaya pilihan kembali setelah validasi
  gagal — server tetap memakai `site_id`. Catatan SLA/severity dan catatan
  "Teknisi = PIC internal site" dicabut dari layar ini.

## Halaman Pengaturan (`/trouble-ticket/settings`, superadmin)

Satu halaman bertab, dulu hanya SLA:

| Tab | Isi | Route (store / update / toggle) |
|---|---|---|
| SLA | tiga limit: Response / Travel / Resolution (menit) | `settings.sla.limit` |
| RCA | master RCA | `settings.rca.*` |
| Sub-RCA | master Sub-RCA (milik satu RCA; nama unik per RCA) | `settings.sub-rca.*` |
| Severity | master severity (kode unik, level 1 = paling parah, rentang aging) | `settings.severity.*` |

- Tab **SLA** tidak lagi mendaftar kebijakan: ia menampilkan **tiga baris**
  (Response time, Travel time, Resolution time) dengan limitnya masing-masing,
  bisa diubah satu per satu. Yang disimpan selalu kebijakan yang MEMANG dipakai
  tiket baru (`applicableForProblem(null)`); kalau belum ada, satu baris umum
  dibuat saat penyimpanan pertama. Limit **0 (atau kosong) = tidak terhingga**:
  disimpan NULL, durasinya tetap dicatat tetapi tiket tidak pernah "Lewat SLA".
  Tidak ada lagi tambah/nonaktifkan kebijakan,
  tidak ada lagi target per Problem/Severity dari layar — baris lama tetap ada
  di basis data. Kolom `name` masih ada tetapi tidak dipakai layar.
- Kolom `slm_target_value` / `slm_target_unit` (jam atau hari) sudah ada di
  `trouble_ticket_sla_policies` tetapi **belum dipakai dan belum ditampilkan**
  di mana pun; semua barisnya kosong (migrasi `2026_10_13_000100`).
- Tangga severity kini bisa diatur: kolom `aging_from`/`aging_to` di
  `trouble_ticket_severities` (migrasi `2026_10_13_000200`). Selama belum ada
  severity aktif yang mengisi `aging_from`, perhitungan tetap memakai tangga
  bawaan `TroubleTicket::SEVERITY_AGING` — lihat
  `TroubleTicketSeverity::agingSteps()` (di-cache per request).
- Seluruh baris **bisa diubah** di tempat (13 Okt 2026): tombol Ubah menukar
  baris baca dengan baris form, satu baris terbuka pada satu waktu.
- Mengubah **kebijakan SLA** hanya berlaku untuk tiket yang dibuat sesudahnya
  — tiket lama menyimpan snapshot targetnya sendiri.
- Mengubah **kode severity** ikut memperbarui tiket lama yang memakainya
  (`trouble_tickets.severity`, FK ON UPDATE CASCADE).
- **Tidak ada hapus** pada master mana pun: tiket lama menunjuk barisnya
  (FK RESTRICT) dan riwayatnya harus tetap terbaca. Yang tidak dipakai lagi
  dinonaktifkan — hilang dari pilihan form, tetap tampil di tiket lama.
- Severity master **bukan** sumber severity tiket (itu dihitung dari aging);
  ia dipakai sebagai label kebijakan SLA dan untuk tiket lama.
- Master Problem tetap tanpa layar — tidak lagi diisi dari form buat tiket.

## Hak akses (keputusan bisnis 9 Okt 2026)

| Aksi | Siapa |
|---|---|
| Buka menu / buat tiket | pemegang menu `trouble-ticket` (route) |
| Melihat tiket, pesan, lampiran | superadmin (`TROUBLE_TICKET_FULL_SCOPE`): semua tiket. **User biasa: tiket yang ditugaskan ke dirinya dan tiket yang ia buat sendiri** — tiket teknisi lain yang bukan buatannya tidak muncul (keputusan 15 Okt 2026; sejak 6 Nov 2026 pembuat kembali melihat tiket buatannya untuk mengirim bukti lewat chat, OD-7, tanpa akses dokumen uang). Pengawas (leader team ACTIVE, `TROUBLE_TICKET_SUPERVISE`, `TROUBLE_TICKET_APPROVE` — `TroubleTicket::supervises()`): pembuat, tiket yang ditugaskan, dan siapa pun yang **satu team** (anggota ACTIVE atau leader team ACTIVE) dengan PIC site: `site → site_pics → master_pics(ACTIVE) → user → team_members`, atau dengan Teknisi site: `site → sites.technician_user_id → team_members` (9 Nov 2026) |
| Teknisi yang bisa ditugaskan | akun ACTIVE yang menjadi **Teknisi site** (`sites.technician_user_id`, 9 Nov 2026 — tidak perlu Master PIC; `trouble_tickets.pic_id` lalu diisi PIC milik akun itu bila ada, selain itu kosong) atau milik PIC **internal** ACTIVE yang terdaftar di `site_pics` site itu, **kecuali leader team ACTIVE** (ia yang menyetujui plan). Berlaku untuk dropdown buat tiket maupun reassign — `TroubleTicketWorkflow::eligibleTechnicians()` |
| Respons, ajukan plan, Start Progress, tutup tiket | hanya teknisi yang sedang ditugaskan |
| Approve/reject plan | leader team ACTIVE tempat teknisi menjadi anggota ACTIVE, atau superadmin — **bukan** teknisi pengaju |
| Reassign, koreksi plan | leader team teknisi atau superadmin |
| Pengaturan SLA (Problem & master RCA: langsung di DB / seeder) | superadmin (`role:superadmin` = `AccessRoles::TROUBLE_TICKET_SETTINGS`) |

Keterlihatan dihitung **saat ini** (bukan snapshot): melepas PIC dari site atau
anggota dari team mengubah siapa yang melihat tiket lama.
Untuk user biasa ini berarti reassign tiket langsung memindahkan tiket itu dari
daftar teknisi lama ke teknisi baru.

## Lampiran

- Disk `secure`, folder `trouble_ticket_attachments/`, nama acak; metadata +
  sha256 + pengunggah di `trouble_ticket_attachments`.
- Maks 10 MB per berkas, 10 berkas per kirim (dicek browser **dan** server).
- Allowlist: pdf, jpg, jpeg, png, gif, webp, doc(x), xls(x), ppt(x), csv, txt,
  mp4, mov, zip. Validasi `mimes` (deteksi isi) **dan** `extensions` (nama berkas).
- Unduh lewat `GET /trouble-ticket/attachment/{id}` (cek keterlihatan tiket);
  hanya pdf/gambar/mp4 boleh inline, sisanya dipaksa unduh, `nosniff`.
  `/storage/{path}` juga dijaga `FileAccess::allowsTroubleTicket()`.

## Timeline & notifikasi

- Kejadian: created, assigned, reassigned, responded, plan_submitted,
  plan_corrected, plan_approved, plan_rejected, progress_started, closed.
  Pesan chat = `MESSAGE`.
- Tidak ada route ubah/hapus pesan. Pesan idempoten per `client_token`.
- Halaman detail mengambil pesan baru tiap 20 detik (hanya saat tab terlihat).
- **Lonceng tidak menunggu worker** (13 Okt 2026): `AppNotification::viaConnections()`
  menjalankan channel `database` pada koneksi `sync`, jadi notifikasi in-app
  muncul begitu aksinya selesai; WhatsApp (Fonnte) tetap lewat antrean dan
  tetap butuh `queue:work`. Berlaku untuk SEMUA modul yang memakai
  `NotifyService`, bukan hanya Trouble Ticket.
- Notifikasi tiket baru menautkan langsung ke halaman detail; teknisi yang
  mengkliknya otomatis tercatat **Ticket read** (jam Response berhenti).
- Notifikasi (`NotifyService`, kategori `trouble-ticket`): tiket baru/dialihkan
  → teknisi; plan diajukan → leader team (fallback superadmin); plan
  approve/reject → teknisi; tiket ditutup → leader team + pembuat.

## Integrasi Site

Master Site: kartu **Total TT** (+ jumlah terbuka), kolom **TT** (tiket terbuka
per site), tab **TT** di drawer (10 tiket terbaru) — semuanya mengikuti
keterlihatan user. Site yang punya tiket tidak bisa dihapus.

## Konfigurasi setelah deploy

1. `php artisan migrate` (migrasi `2026_10_09_200001..200004`; butuh migrasi Site
   `2026_10_09_1000xx` lebih dulu).
2. User & Access → beri menu **Trouble Ticket** ke role yang membuat/mengerjakan
   tiket (termasuk `staff` untuk akun teknisi).
3. Pastikan PIC internal site terhubung ke akun user, dan Team (leader + anggota) terisi.
4. Isi `trouble_ticket_severities` dan `trouble_ticket_problems` langsung di DB (wajib sebelum tiket bisa dibuat); target SLA di Trouble Ticket → Pengaturan (opsional).

## Needs confirmation

- Nilai target SLA kontraktual (belum ada).
- Notifikasi peringatan/pelanggaran SLA terjadwal belum dibuat (tidak ada scheduler di hosting).
- Tiket Closed/Cancelled belum bisa dibuka kembali (reopen).
- Belum masuk Todo List; belum ada dashboard analitik (rata-rata/median, compliance rate).
- Bolehkah lebih dari satu sesi kunjungan per tiket (mis. kunjungan ulang setelah penolakan)?

## Penyelarasan skema referensi telko (migrasi `200004`, 9 Okt 2026)

Mengikuti tabel referensi `slameet.trouble_ticket`. 200001–200003 sudah jalan
di dev, jadi perubahan ditulis sebagai migrasi baru (saat itu 0 tiket).

| Kolom | Diisi saat | Keterangan |
|---|---|---|
| `ticket_number` (dulu `tt_number`) | buat tiket | otomatis `T.1026008` sejak **15 Okt 2026** = `T.` + bulan (mm) + tahun (yy) + urutan (minimal 3 digit, melebar sendiri setelah 999); sebelumnya `T.0000011026` (urutan di depan bulan/tahun, 14 Okt 2026) dan nomor yang sudah terbit tidak diubah. Urutannya **berjalan terus** (tidak reset per bulan/tahun), satu rangkaian `DocumentSequence` bernama `trouble-ticket`, disemai dari jumlah tiket yang ada. Tiket lama tetap memakai format `TT-0001/10/26` dan tidak diubah |
| `external_ref` | — | **tidak lagi diisi**; dihapus dari form, daftar, detail & export 12 Okt 2026. Kolom dipertahankan untuk 12 tiket lama |
| `problem_id` (dulu kategori) | — | **tidak lagi diisi dari form buat tiket** (12 Okt 2026). Tetap diterima bila dikirim (integrasi) dan menentukan target SLA; tanpa Problem, SLA jatuh ke kebijakan umum (`problem_id` NULL) |
| `issue_id` | — | **Initial Problem**. Dihapus dari form buat tiket (12 Okt 2026); kolom & master tetap ada, nilainya masih ditampilkan di daftar/detail/export untuk tiket lama |
| `device_id` | buat tiket | Kolom **Device** dicabut dari daftar & modal Info (16 Okt 2026); dropdown `trouble_ticket_devices` (opsional); teks bebas `device` hanya untuk tiket lama |
| `rca_id`, `sub_rca_id` | buat tiket | dropdown bertingkat; Sub-RCA wajib milik RCA yang dipilih. Opsional, masih bisa diperbarui saat penutupan (`rca`/`sub_rca` teks) |
| `severity` | — | **tidak lagi diisi dari form** (nullable). Label & warna dihitung dari aging: 0 hari Rendah (level 4), 1–2 Minor (3), 3–7 Major (2), ≥8 Kritis (1) — `TroubleTicket::SEVERITY_AGING`, `severityFromAging()` |
| `flag_alarm` | — | dihapus dari form buat tiket (12 Okt 2026); masih ditampilkan di detail & export untuk tiket lama |
| `reported_at` | buat tiket | label layar **Date Open**: default sekarang, boleh diubah, tidak boleh di masa depan. Jadi titik awal **aging**; SLA Response tetap dari `created_at` (keputusan 9 Okt 2026) |
| `updated_at` | otomatis | hanya internal/export; kolom **Date Update** dan **Dibuat** (`created_at`) dicabut dari daftar 16 Okt 2026, urutan default daftar jadi Date Open terbaru |
| `pic_id` | buat tiket / reassign | baris `master_pics` teknisi; `assigned_to` (user) tetap dipakai untuk hak akses. Di form buat tiket teknisi pertama site langsung terpilih (12 Okt 2026), masih bisa diganti |
| `acknowledged_at`/`_by` (dulu `first_responded_*`) | teknisi membaca detail tiket | sekali seumur tiket; titik berhenti SLA Response |
| `visit_site`, `target_online` | modal Create Plan | `visit_site` wajib, batas bawah `TroubleTicket::planMinDate()`; target online opsional |
| `travel_started_at`, `travel_arrived_at`, `travel_seconds` | tahap Trip | jejak SLA Travel (migrasi `2026_10_12_000300`) |
| `travel_cancelled_at`, `travel_cancel_reason` | batal visit | pembatalan terakhir; SLA Travel dibatalkan tapi jejaknya tetap |
| `chat_locked_at`, `chat_locked_by` | — | peninggalan Stop Chat yang dihapus 19 Okt 2026; tidak diisi kode mana pun lagi |
| `resolved_at`, `progress_done_at` | kirim form close | saat teknisi mengirim hasil, sebelum approval |
| `approved_at`/`_by` | approve penutupan | supervisor yang menutup tiket |
| `plan_approved_at`/`_by` | LEGACY | jejak approval plan alur lama (migrasi `2026_10_10_000200`) |
| `rca` (40, wajib), `sub_rca` (150), `action` (150, wajib) | form close | dipilih dari master RCA/Sub-RCA; `resolution_summary` jadi keterangan opsional |
| `approval` | kirim/approve/tolak **penutupan** | PENDING / APPROVED / REJECTED |

**Master tanpa UI** (diisi langsung di basis data, tidak ada data awal):

- `trouble_ticket_problems`: `name` (unik), `requires_evidence` (bukti wajib saat Closed), `status`.
- `trouble_ticket_severities`: LEGACY. Tidak lagi dipakai tiket baru; hanya
  jadi label opsional pada kebijakan SLA lama.
- Kebijakan SLA tiket baru dicari **per Problem**; tiket tanpa Problem memakai
  kebijakan umum (`problem_id` NULL). Halaman Pengaturan hanya SLA.

**Master RCA (seeder `TroubleTicketRcaSeeder`, 12 Okt 2026)**

Sumber: "Daftar RCA Paramitra Iintimega" (77 kombinasi Action/Issue/Sub-RCA/RCA).

- `trouble_ticket_rcas` — 5 baris: POWER, HARDWARE, TRANSMISSION, CONFIGURATION, OTHERS.
- `trouble_ticket_sub_rcas` — 36 baris, masing-masing milik satu RCA.
- `trouble_ticket_issues` — 55 Initial Problem. Catatan: sumbernya memuat
  "VANDALISM / KEAMANAN" **dan** salah ketik "VANDALIMS / KEAMANAN"; keduanya
  diseed apa adanya agar sesuai sumber. Perlu konfirmasi apakah digabung.
- `trouble_ticket_devices` — 21 perangkat, **diturunkan** dari Sub-RCA yang
  berupa perangkat (bukan daftar resmi dari Excel). Perlu konfirmasi.
- Kolom "Action" pada sumber belum dijadikan master: penutupan tiket masih
  memakai teks bebas `action`.

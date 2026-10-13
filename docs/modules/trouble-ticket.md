# Module — Trouble Ticket (TESYS Operation)

Trouble Ticket **telekomunikasi**: gangguan layanan di site (link down, perangkat, power, dll.): penugasan teknisi, respons pertama,
pemilihan jalur penanganan (Remote / Plan Visit), perjalanan ke site,
pengerjaan, penutupan + approval supervisor, diskusi + lampiran, dan SLA per
tiket.

Sejak **12 Okt 2026** alur status menjadi **7 tahap** (TT Open → Remote /
Plan → Trip → In Progress → Progress Done → Closed). Approval plan dihapus;
satu-satunya approval ada di penutupan tiket, dan hanya role `supervisor`.

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
- Tanpa kolom `deleted_at` (tidak ada fitur hapus pada MVP).
- Menu `trouble-ticket` didaftarkan migrasi `200003` **tanpa** diberikan ke role.

## State machine

```
OPEN ─(teknisi pilih Remote)───────────────────→ REMOTE
     └─(teknisi buat Plan Visit)───────────────→ PLAN
PLAN ─(tanggal plan visit tiba, otomatis)──────→ TRIP
TRIP ─(mulai perjalanan → On Site → mulai progres)→ IN_PROGRESS
REMOTE / IN_PROGRESS ─(form close dikirim)─────→ PROGRESS_DONE
PROGRESS_DONE ─(supervisor approve)────────────→ CLOSED
              └─(supervisor tolak)─────────────→ REMOTE / IN_PROGRESS
```

Tidak ada approval plan dan tidak ada pembatalan tiket. Status lama `APPROVED`
dan `ON_PLAN` dipetakan ke `PLAN` oleh migrasi `2026_10_12_000300`.

`PLAN → TRIP` berjalan tanpa penjadwal: `promoteDueTrips()` dipanggil saat
daftar/detail dibuka dan menaikkan semua tiket `PLAN` yang `visit_site`-nya
sudah tiba (idempoten, satu transaksi + row lock per tiket).

Setiap transisi: transaksi + `lockForUpdate` baris tiket + cek ulang pelaku &
status asal + kejadian timeline di transaksi yang sama (gagal = batal semua).
Permintaan ulang gagal di cek status, jadi timestamp tidak pernah ditimpa dan
notifikasi tidak terkirim dua kali. Error keadaan = `ValidationException`
(`workflow`), hak akses = 403.

- **Respons pertama**: dicatat saat **teknisi yang ditugaskan** membuka detail
  (`recordAccess`). User lain tidak mencatat apa pun. `first_responded_at`
  sekali seumur tiket; `trouble_ticket_assignments.responded_at` sekali per penugasan.
- **Remote** (`POST {ticket}/remote`): teknisi memilih penyelesaian tanpa
  kunjungan. Status `REMOTE`, `visit_decision = NON_VISIT`, dan
  `progress_started_at` diisi — **SLA Resolution mulai di sini**. Lanjut ke
  form close.
- **Create Plan** (`POST {ticket}/plan`): teknisi mengisi tanggal plan visit
  (site & pelaksana mengikuti tiket, tidak bisa diubah di modal). Status
  `PLAN`, tanpa approval. Tanggal hari ini langsung naik ke `TRIP`.
  Endpoint yang sama dipakai dari tahap `REMOTE` (berbalik jadi kunjungan —
  SLA Resolution yang sempat berjalan dibatalkan) dan dari tahap `PLAN`
  (ubah tanggal; kejadian `plan_updated`). Target online tidak lagi diisi
  dari form (12 Okt 2026).
- **Trip**: tiga aksi teknisi, semuanya tetap di status `TRIP`:
  `POST {ticket}/travel` (geser "mulai perjalanan" → `travel_started_at`,
  **SLA Travel mulai**), `POST {ticket}/travel/cancel` (wajib alasan →
  `travel_started_at` dikosongkan sehingga SLA Travel tidak dihitung,
  `travel_cancelled_at` + alasan tetap tercatat di log; teknisi boleh
  berangkat lagi), dan `POST {ticket}/arrive` (konfirmasi On Site →
  `travel_arrived_at` + `travel_seconds`, **SLA Travel berhenti**).
- **Start Progress** (`POST {ticket}/start`): hanya dari `TRIP` dan hanya
  setelah On Site. Status `IN_PROGRESS`, `progress_started_at` diisi — **SLA
  Resolution mulai**.
- **Form close** (`POST {ticket}/close`): dari `REMOTE`, `PLAN`, maupun
  `IN_PROGRESS` (`TroubleTicket::CLOSABLE`). Wajib: RCA (dari master),
  keterangan, dan evidence — kolom Action tidak dipakai lagi. RCA/Sub-RCA
  tiket DIPERBARUI mengikuti isian form ini. Tiket yang ditutup langsung
  dari `PLAN` memakai waktu pengiriman form sebagai awal Resolution. Status `PROGRESS_DONE`, `approval = PENDING`,
  `progress_done_at`/`resolved_at` diisi. **Tiket belum ditutup.**
- **Approval penutupan**: hanya `AccessRoles::TROUBLE_TICKET_APPROVE`
  (`supervisor`, `superadmin`) dan **bukan** teknisi tiket itu.
  `POST {ticket}/close/approve` → `CLOSED` + `approved_at/by` + `closed_at`
  (`closed_by` tetap teknisi) dan `resolution_seconds` difinalkan.
  `POST {ticket}/close/reject` wajib alasan → kembali ke `IN_PROGRESS`
  (atau `REMOTE` untuk jalur non-visit); SLA Resolution tetap berjalan.
- **Reassign**: semua status yang belum final. Bila belum ada keputusan jalur,
  status kembali `OPEN`; bila sudah, status tetap.
- **Notifikasi tiket baru**: teknisi yang ditugaskan **dan seluruh PIC internal
  site** (`sitePicUsers()`).

## SLA

| Ukuran | Rumus | Catatan |
|---|---|---|
| Response | `first_responded_at − created_at` | Penugasan wajib saat dibuat, jadi = waktu penugasan awal. Reassign tidak me-restart jam. |
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

## Hak akses (keputusan bisnis 9 Okt 2026)

| Aksi | Siapa |
|---|---|
| Buka menu / buat tiket | pemegang menu `trouble-ticket` (route) |
| Melihat tiket, pesan, lampiran | superadmin; pembuat; teknisi; siapa pun yang **satu team** (anggota ACTIVE atau leader team ACTIVE) dengan PIC site: `site → site_pics → master_pics(ACTIVE) → user → team_members` |
| Teknisi yang bisa ditugaskan | akun ACTIVE milik PIC **internal** ACTIVE yang terdaftar di `site_pics` site itu, **kecuali leader team ACTIVE** (ia yang menyetujui plan). Berlaku untuk dropdown buat tiket maupun reassign — `TroubleTicketWorkflow::eligibleTechnicians()` |
| Respons, ajukan plan, Start Progress, tutup tiket | hanya teknisi yang sedang ditugaskan |
| Approve/reject plan | leader team ACTIVE tempat teknisi menjadi anggota ACTIVE, atau superadmin — **bukan** teknisi pengaju |
| Reassign, koreksi plan | leader team teknisi atau superadmin |
| Pengaturan SLA (Problem & master RCA: langsung di DB / seeder) | superadmin (`role:superadmin` = `AccessRoles::TROUBLE_TICKET_SETTINGS`) |

Keterlihatan dihitung **saat ini** (bukan snapshot): melepas PIC dari site atau
anggota dari team mengubah siapa yang melihat tiket lama.

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
| `ticket_number` (dulu `tt_number`) | buat tiket | otomatis `TT-0001/10/26` |
| `external_ref` | — | **tidak lagi diisi**; dihapus dari form, daftar, detail & export 12 Okt 2026. Kolom dipertahankan untuk 12 tiket lama |
| `problem_id` (dulu kategori) | — | **tidak lagi diisi dari form buat tiket** (12 Okt 2026). Tetap diterima bila dikirim (integrasi) dan menentukan target SLA; tanpa Problem, SLA jatuh ke kebijakan umum (`problem_id` NULL) |
| `issue_id` | — | **Initial Problem**. Dihapus dari form buat tiket (12 Okt 2026); kolom & master tetap ada, nilainya masih ditampilkan di daftar/detail/export untuk tiket lama |
| `device_id` | buat tiket | dropdown `trouble_ticket_devices` (opsional); teks bebas `device` hanya untuk tiket lama |
| `rca_id`, `sub_rca_id` | buat tiket | dropdown bertingkat; Sub-RCA wajib milik RCA yang dipilih. Opsional, masih bisa diperbarui saat penutupan (`rca`/`sub_rca` teks) |
| `severity` | — | **tidak lagi diisi dari form** (nullable). Label & warna dihitung dari aging: 0 hari Rendah (level 4), 1–2 Minor (3), 3–7 Major (2), ≥8 Kritis (1) — `TroubleTicket::SEVERITY_AGING`, `severityFromAging()` |
| `flag_alarm` | — | dihapus dari form buat tiket (12 Okt 2026); masih ditampilkan di detail & export untuk tiket lama |
| `reported_at` | buat tiket | label layar **Date Open**: default sekarang, boleh diubah, tidak boleh di masa depan. Jadi titik awal **aging**; SLA Response tetap dari `created_at` (keputusan 9 Okt 2026) |
| `updated_at` | otomatis | label layar **Date Update** |
| `pic_id` | buat tiket / reassign | baris `master_pics` teknisi; `assigned_to` (user) tetap dipakai untuk hak akses. Di form buat tiket teknisi pertama site langsung terpilih (12 Okt 2026), masih bisa diganti |
| `acknowledged_at`/`_by` (dulu `first_responded_*`) | teknisi membuka tiket | |
| `visit_site`, `target_online` | modal Create Plan | `visit_site` wajib (≥ hari ini); target online opsional |
| `travel_started_at`, `travel_arrived_at`, `travel_seconds` | tahap Trip | jejak SLA Travel (migrasi `2026_10_12_000300`) |
| `travel_cancelled_at`, `travel_cancel_reason` | batal visit | pembatalan terakhir; SLA Travel dibatalkan tapi jejaknya tetap |
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

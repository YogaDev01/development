# Module — Trouble Ticket (TESYS Operation)

Trouble Ticket **telekomunikasi**: gangguan layanan di site (link down, perangkat, power, dll.): penugasan teknisi, respons pertama,
pengajuan & approval plan (Visit / Non-Visit), troubleshooting, penutupan
tiket, diskusi + lampiran, dan SLA per tiket.

Sejak **10 Okt 2026** alur status diringkas menjadi **5 tahap**, disamakan
dengan funnel SLAmeet.

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
OPEN ─(teknisi mengajukan plan Visit/Non-Visit)→ APPROVED
APPROVED ─(leader team menyetujui plan)────────→ ON_PLAN
         └─(leader team menolak plan)──────────→ OPEN
ON_PLAN ─(Start Progress)──────────────────────→ IN_PROGRESS
IN_PROGRESS ─(teknisi menutup tiket)───────────→ CLOSED
```

`APPROVED` berarti plan **menunggu** approval, bukan sudah disetujui —
penamaannya mengikuti funnel SLAmeet. Tidak ada approval saat penutupan dan
tidak ada pembatalan tiket (status `CANCELLED` dihapus 10 Okt 2026).

Setiap transisi: transaksi + `lockForUpdate` baris tiket + cek ulang pelaku &
status asal + kejadian timeline di transaksi yang sama (gagal = batal semua).
Permintaan ulang gagal di cek status, jadi timestamp tidak pernah ditimpa dan
notifikasi tidak terkirim dua kali. Error keadaan = `ValidationException`
(`workflow`), hak akses = 403.

- **Respons pertama**: dicatat saat **teknisi yang ditugaskan** membuka detail
  (`recordAccess`). User lain tidak mencatat apa pun. `first_responded_at`
  sekali seumur tiket; `trouble_ticket_assignments.responded_at` sekali per penugasan.
- **Ajukan plan**: modal wajib (tanpa tombol tutup, backdrop static, Esc mati)
  selama status `OPEN`; server menolak aksi lain dan pesan. Non-Visit wajib
  alasan, Plan Visit wajib tanggal kunjungan. Tiket masuk `APPROVED`
  (`approval = PENDING`) dan belum boleh dikerjakan.
- **Approval plan**: leader team teknisi (atau superadmin), **bukan** teknisi
  pengaju. Approve → `ON_PLAN` + `plan_approved_at/by`. Reject wajib alasan →
  kembali `OPEN` dan plan lama dikosongkan supaya teknisi bisa mengajukan plan
  baru; plan lama tersimpan di metadata kejadian `plan_rejected`.
- **Koreksi plan**: pengawas, saat `APPROVED` atau `ON_PLAN`, wajib alasan;
  plan lama disimpan di metadata kejadian `plan_corrected`.
- **Reassign**: status `OPEN..IN_PROGRESS` (semua yang belum final). Bila belum
  ada plan, status kembali `OPEN` (teknisi baru merespons & mengajukan plan);
  bila sudah, status tetap.
- **Closed**: teknisi menutup sendiri, **tanpa approval**. RCA & Action wajib;
  problem dengan `requires_evidence` wajib lampiran. `resolution_seconds`
  difinalkan di sini.

## SLA

| Ukuran | Rumus | Catatan |
|---|---|---|
| Response | `first_responded_at − created_at` | Penugasan wajib saat dibuat, jadi = waktu penugasan awal. Reassign tidak me-restart jam. |
| Travel | `progress_started_at − assigned_at` | Definisi SLAmeet: dari tiket di-assign sampai pengerjaan dimulai (sejak 10 Okt 2026; sebelumnya `arrived_at − departed_at`). |
| Resolution | `closed_at − progress_started_at` | Final saat tiket ditutup. |
| Tunggu approval plan | `plan_approved_at (atau sekarang) − visit_decided_at` | Ditampilkan terpisah (`approvalWaitSeconds()`). |

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
| `visit_site`, `target_online` | modal Ajukan Plan | `visit_site` wajib untuk Plan Visit (≥ hari ini); target online opsional |
| `resolved_at` (dulu `closure_requested_at`) | tutup tiket | sama dengan `closed_at` |
| `plan_approved_at`/`_by` | approve plan | jejak approval plan (migrasi `2026_10_10_000200`) |
| `rca` (40, wajib), `sub_rca` (150), `action` (150, wajib) | tutup tiket | teks bebas; `resolution_summary` jadi catatan opsional |
| `approval` | ajukan/approve/reject **plan** | PENDING / APPROVED / REJECTED |

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

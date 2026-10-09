# Module — Trouble Ticket (TESYS Operation)

Trouble Ticket **telekomunikasi**: gangguan layanan di site (link down, perangkat, power, dll.): penugasan teknisi, respons pertama,
keputusan Visit / Non-Visit, perjalanan, troubleshooting, pengajuan & approval
Closed, diskusi + lampiran, dan SLA per tiket.

Menu: **TESYS Operation → Workflow → Trouble Ticket** (`config/navigation.php`,
key `trouble-ticket`). URL `/trouble-ticket`.

## Komponen

| Lapisan | Berkas |
|---|---|
| Controller | `TroubleTicketController` (validasi inline + pilih respons) |
| Workflow | `App\Services\TroubleTicketWorkflow` — **satu-satunya** tempat status/timestamp berubah; juga aturan hak akses |
| Model | `TroubleTicket`, `TroubleTicketMessage`, `TroubleTicketAttachment`, `TroubleTicketVisit`, `TroubleTicketAssignment`, `TroubleTicketClosureReview`, `TroubleTicketProblem`, `TroubleTicketSeverity`, `TroubleTicketSlaPolicy` |
| Presentasi status | `WorkflowStatus` context `trouble-ticket` (`<x-status>`, `<x-workflow-flow>`) |
| Peran | `AccessRoles::TROUBLE_TICKET_FULL_SCOPE / _SUPERVISE / _SETTINGS` (semua `['superadmin']`) |
| View | `resources/views/trouble-ticket/` (`index`, `create`, `show`, `settings` (SLA saja), `_messages`, `_attachments`, `_sla`, `_assets`) |
| Test | `tests/Feature/TroubleTicketTest.php` |

## Skema (migrasi `2026_10_09_200001..200004`)

```
sites ──< trouble_tickets >── users (created_by, assigned_to, first_responded_by,
 │                                    visit_decided_by, closed_by, cancelled_by)
 │   customer_id disalin dari site saat dibuat (filter/indeks)
trouble_ticket_problems ──< trouble_tickets, trouble_ticket_sla_policies   (tanpa UI)
trouble_ticket_severities ──< trouble_tickets.severity (kode), trouble_ticket_sla_policies   (tanpa UI)
trouble_ticket_sla_policies ──< trouble_tickets (sla_policy_id + snapshot menit)

trouble_tickets ──< trouble_ticket_messages     (MESSAGE | EVENT, immutable, client_token)
                ──< trouble_ticket_attachments  (>── messages; disk secure)
                ──< trouble_ticket_visits       (≤1 aktif: unique active_ticket_id generated)
                ──< trouble_ticket_assignments  (riwayat penugasan + responded_at per penugasan)
                ──< trouble_ticket_closure_reviews (attempt_no; ≤1 PENDING: unique pending_ticket_id)
```

- Semua FK `RESTRICT`; tiket tidak pernah dihapus (pembatalan = `CANCELLED`).
  `UserController::destroy` dan Site (dependents) menolak hapus dengan pesan.
- Durasi disimpan detik (`response_seconds`, `travel_seconds`, `resolution_seconds`)
  saat difinalkan. Timestamp selalu dari jam server; zona tampilan `Asia/Jakarta`.
- Tanpa kolom `deleted_at` (tidak ada fitur hapus pada MVP).
- Menu `trouble-ticket` didaftarkan migrasi `200003` **tanpa** diberikan ke role.

## State machine

```
OPEN ─(teknisi membuka tiket)→ AWAITING_PLAN ─(Visit/Non-Visit)→ PLANNED
PLANNED ─Visit→ TRAVELING ─(Tiba)→ ON_SITE ─(Start Progress)→ IN_PROGRESS
PLANNED ─Non-Visit─(Start Progress)──────────────────────→ IN_PROGRESS
IN_PROGRESS ─(Ajukan Closed)→ PENDING_CLOSURE ─approve→ CLOSED
                                         └─reject→ IN_PROGRESS
OPEN/AWAITING_PLAN/PLANNED/ON_SITE/IN_PROGRESS ─cancel→ CANCELLED
```

Setiap transisi: transaksi + `lockForUpdate` baris tiket + cek ulang pelaku &
status asal + kejadian timeline di transaksi yang sama (gagal = batal semua).
Permintaan ulang gagal di cek status, jadi timestamp tidak pernah ditimpa dan
notifikasi tidak terkirim dua kali. Error keadaan = `ValidationException`
(`workflow`), hak akses = 403.

- **Respons pertama**: dicatat saat **teknisi yang ditugaskan** membuka detail
  (`recordAccess`). User lain tidak mencatat apa pun. `first_responded_at`
  sekali seumur tiket; `trouble_ticket_assignments.responded_at` sekali per penugasan.
- **Visit / Non-Visit**: modal wajib (tanpa tombol tutup, backdrop static, Esc
  mati) selama status `AWAITING_PLAN` dan belum ada keputusan; server menolak
  aksi lain dan pesan. Non-Visit wajib alasan. Keputusan terkunci; koreksi
  hanya oleh pengawas saat `PLANNED`, wajib alasan, keputusan lama disimpan di
  metadata kejadian `visit_corrected`.
- **Kunjungan**: MVP satu sesi per tiket (skema mendukung banyak). Tiba tidak
  boleh sebelum berangkat. Koordinat GPS belum dicatat (kolom disiapkan).
- **Reassign**: status `OPEN..IN_PROGRESS` kecuali `TRAVELING`. Bila belum ada
  keputusan kunjungan, status kembali `OPEN` (teknisi baru merespons & memilih);
  bila sudah, status tetap.
- **Closed**: teknisi tidak bisa menutup sendiri; RCA & Action wajib. Problem dengan
  `requires_evidence` wajib lampiran saat mengajukan. Penolakan wajib alasan,
  kembali `IN_PROGRESS` tanpa mengubah `progress_started_at`. Setiap pengajuan
  baris baru di `trouble_ticket_closure_reviews`.

## SLA

| Ukuran | Rumus | Catatan |
|---|---|---|
| Response | `first_responded_at − created_at` | Penugasan wajib saat dibuat, jadi = waktu penugasan awal. Reassign tidak me-restart jam. |
| Travel | `arrived_at − departed_at` | Hanya Plan Visit. |
| Resolution | `closed_at − progress_started_at` | Final hanya saat approval; termasuk waktu tunggu approval. |
| Tunggu approval | `closed_at (atau sekarang) − closure_requested_at` | Ditampilkan terpisah. |

- Target per severity (opsional per problem) di `trouble_ticket_sla_policies`,
  dikelola superadmin di `/trouble-ticket/settings`. **Belum ada nilai** — diisi
  perusahaan. Target disalin ke tiket saat dibuat (snapshot).
- Level: tanpa target = `none` (tidak pernah "terlambat"); berjalan ≥75% =
  warning; ≥100% = critical. Filter "Hanya lewat SLA" = `TroubleTicket::scopeOverdue()`.
- Timer di layar dihitung dari jam server (offset jam perangkat dikoreksi).

## Hak akses (keputusan bisnis 9 Okt 2026)

| Aksi | Siapa |
|---|---|
| Buka menu / buat tiket | pemegang menu `trouble-ticket` (route) |
| Melihat tiket, pesan, lampiran | superadmin; pembuat; teknisi; siapa pun yang **satu team** (anggota ACTIVE atau leader team ACTIVE) dengan PIC site: `site → site_pics → master_pics(ACTIVE) → user → team_members` |
| Teknisi yang bisa ditugaskan | akun ACTIVE milik PIC **internal** ACTIVE yang terdaftar di `site_pics` site itu |
| Respons, Visit/Non-Visit, berangkat, tiba, Start Progress, Ajukan Closed | hanya teknisi yang sedang ditugaskan |
| Approve/reject Closed | leader team ACTIVE tempat teknisi menjadi anggota ACTIVE, atau superadmin — **bukan** pengaju |
| Reassign, cancel, koreksi keputusan | leader team teknisi atau superadmin |
| Pengaturan SLA (Problem & Severity: langsung di DB) | superadmin (`role:superadmin` = `AccessRoles::TROUBLE_TICKET_SETTINGS`) |

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

- Kejadian: created, assigned, reassigned, responded, visit_decided,
  visit_corrected, departed, arrived, progress_started, closure_requested,
  closure_approved, closure_rejected, cancelled. Pesan chat = `MESSAGE`.
- Tidak ada route ubah/hapus pesan. Pesan idempoten per `client_token`.
- Halaman detail mengambil pesan baru tiap 20 detik (hanya saat tab terlihat).
- Notifikasi (`NotifyService`, kategori `trouble-ticket`): tiket baru/dialihkan
  → teknisi; Ajukan Closed → leader team (fallback superadmin); approve/cancel →
  teknisi + pembuat; reject → teknisi.

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
| `external_ref` | buat tiket | nomor tiket NOC/customer, unik, opsional |
| `problem_id` (dulu kategori) | buat tiket | dropdown dari `trouble_ticket_problems` (wajib) |
| `severity` (dulu `priority`) | buat tiket | kode dari `trouble_ticket_severities` (wajib, FK ON UPDATE CASCADE) |
| `issue` (dulu `subject`), `device`, `flag_alarm` | buat tiket | issue wajib; device/alarm opsional |
| `reported_at` | buat tiket | default sekarang, tidak boleh di masa depan; **hanya informasi** — SLA Response tetap dari `created_at` (keputusan 9 Okt 2026) |
| `pic_id` | buat tiket / reassign | baris `master_pics` teknisi; `assigned_to` (user) tetap dipakai untuk hak akses |
| `acknowledged_at`/`_by` (dulu `first_responded_*`) | teknisi membuka tiket | |
| `visit_site`, `target_online` | modal Visit/Non-Visit | `visit_site` wajib untuk Plan Visit (≥ hari ini); target online opsional |
| `resolved_at` (dulu `closure_requested_at`) | Ajukan Closed | |
| `rca` (40, wajib), `sub_rca` (150), `action` (150, wajib) | Ajukan Closed | teks bebas; juga disimpan per pengajuan di `trouble_ticket_closure_reviews`; `resolution_summary` jadi catatan opsional |
| `approval` | Ajukan/approve/reject | PENDING / APPROVED / REJECTED (pengajuan terakhir) |

**Master tanpa UI** (diisi langsung di basis data, tidak ada data awal):

- `trouble_ticket_problems`: `name` (unik), `requires_evidence` (bukti wajib saat Closed), `status`.
- `trouble_ticket_severities`: `code` (unik, disimpan di tiket), `name`, `level` (1 = paling parah; urutan & warna), `status`.
- Form buat tiket tidak bisa disimpan sampai keduanya terisi (halaman menampilkan peringatan).
- Kebijakan SLA kini per **severity** (+ opsional per problem). Halaman Pengaturan hanya SLA.

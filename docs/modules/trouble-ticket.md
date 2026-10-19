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
  Tiket jalur **Remote (Non-Visit)** tidak diukur Travel maupun **Resolution**
  (16 Okt 2026): `slaMetric('resolution')` mengembalikan `na`, layar menulis
  "Tidak berlaku (Non-Visit)", dan `scopeOverdue()` tidak lagi menghitungnya
  Lewat SLA. Kolom `resolution_seconds` tetap diisi saat close untuk riwayat.
- Tanpa kolom `deleted_at` (tidak ada fitur hapus pada MVP).
- Menu `trouble-ticket` didaftarkan migrasi `200003` **tanpa** diberikan ke role.

## State machine

```
OPEN ─(teknisi pilih Remote)───────────────────→ REMOTE
REMOTE ─(teknisi buat Plan Visit)──────────────→ PLAN
PLAN ─(tanggal plan visit tiba, otomatis)──────→ TRIP
TRIP ─(mulai perjalanan → On Site → mulai progres)→ IN_PROGRESS
TRIP ─(reschedule, sebelum On Site)────────────→ PLAN
REMOTE / PLAN / TRIP / IN_PROGRESS ─(form close dikirim)→ PROGRESS_DONE
PROGRESS_DONE ─(supervisor approve)────────────→ CLOSED
              └─(supervisor tolak)─────────────→ REMOTE / IN_PROGRESS
```

Tidak ada approval plan dan tidak ada pembatalan tiket. Status lama `APPROVED`
dan `ON_PLAN` dipetakan ke `PLAN` oleh migrasi `2026_10_12_000300`.

`PLAN → TRIP` berjalan tanpa penjadwal: `promoteDueTrips()` dipanggil saat
daftar/detail dibuka dan menaikkan semua tiket `PLAN` yang `visit_site`-nya
sudah tiba (idempoten, satu transaksi + row lock per tiket). Teknisi tiket yang
naik menerima notifikasi "Hari kunjungan tiba" (dikirim setelah transaksi, 16 Okt
2026). Di daftar, tiket `PLAN`/`TRIP` yang `visit_site`-nya sudah lewat diberi
tanda seru merah di kolom Site dan tanggal Plan ditulis merah tebal.

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
  form close. Tombolnya **tanpa dialog konfirmasi** (15 Okt 2026): Remote tidak
  menutup tiket dan masih bisa berbalik ke Create Plan.
- **Create Plan** (`POST {ticket}/plan`): teknisi mengisi tanggal plan visit
  (site & pelaksana mengikuti tiket, tidak bisa diubah di modal). Status
  `PLAN`, tanpa approval. Tanggal hari ini langsung naik ke `TRIP`.
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
  yang memuat jam keberangkatan yang dibatalkan + tanggal plan baru. Tanggal
  hari ini → tiket tetap `TRIP` (lewat `promoteIfDue`); tanggal lain → kembali
  ke `PLAN`. **Inilah cara menjadwalkan ulang setelah perjalanan dimulai** —
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
- **Form close** (`POST {ticket}/close`): dari `REMOTE`, `PLAN`, `TRIP`,
  maupun `IN_PROGRESS` (`TroubleTicket::CLOSABLE`). Dari `TRIP` sebelum On
  Site, perjalanan yang sedang berjalan dibatalkan otomatis (kejadian
  `travel_cancelled`, SLA Travel tidak dihitung). **Setelah On Site form close
  ditutup** (19 Okt 2026): `TRIP` + `travel_arrived_at` terisi ditolak server
  dan tombolnya tidak dirender — progres dimulai dulu, penutupan dari
  `IN_PROGRESS`. Wajib: RCA (dari master),
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
- **Reassign** (tombol **Change Teknisi**, dulu "Swap Teknisi"): jendelanya
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
- **Diskusi tidak lagi ditutup oleh status (19 Okt 2026).** Tiket `CLOSED`
  tetap bisa dikomentari; yang menutup chat hanya **Stop Chat**
  (`POST {ticket}/chat-lock`, body `lock=1`/`lock=0`) oleh
  `AccessRoles::TROUBLE_TICKET_CHAT_LOCK` (`superadmin`, sama dengan gerbang
  `role:superadmin` di route). Jejaknya di `chat_locked_at` / `chat_locked_by`
  (migrasi `2026_10_19_000200`) dan kejadian `chat_stopped` / `chat_reopened`.
  `TroubleTicketWorkflow::canMessage()` kini hanya menguji `chatLocked()` +
  keterlihatan.
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
- Tombol **Remote** di tahap TT Open berdiri satu baris penuh (`tt-btn-wide`),
  tidak lagi di grid dua kolom `.tt-stage-acts` yang membuatnya separuh lebar.
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
| Melihat tiket, pesan, lampiran | superadmin (`TROUBLE_TICKET_FULL_SCOPE`): semua tiket. **User biasa: hanya tiket yang ditugaskan ke dirinya** — tiket teknisi lain tidak muncul, termasuk tiket yang ia buat sendiri lalu di-change ke teknisi lain (keputusan 15 Okt 2026). Pengawas (leader team ACTIVE, `TROUBLE_TICKET_SUPERVISE`, `TROUBLE_TICKET_APPROVE` — `TroubleTicket::supervises()`): pembuat, tiket yang ditugaskan, dan siapa pun yang **satu team** (anggota ACTIVE atau leader team ACTIVE) dengan PIC site: `site → site_pics → master_pics(ACTIVE) → user → team_members` |
| Teknisi yang bisa ditugaskan | akun ACTIVE milik PIC **internal** ACTIVE yang terdaftar di `site_pics` site itu, **kecuali leader team ACTIVE** (ia yang menyetujui plan). Berlaku untuk dropdown buat tiket maupun reassign — `TroubleTicketWorkflow::eligibleTechnicians()` |
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
| `acknowledged_at`/`_by` (dulu `first_responded_*`) | teknisi membuka tiket | |
| `visit_site`, `target_online` | modal Create Plan | `visit_site` wajib (≥ hari ini); target online opsional |
| `travel_started_at`, `travel_arrived_at`, `travel_seconds` | tahap Trip | jejak SLA Travel (migrasi `2026_10_12_000300`) |
| `travel_cancelled_at`, `travel_cancel_reason` | batal visit | pembatalan terakhir; SLA Travel dibatalkan tapi jejaknya tetap |
| `chat_locked_at`, `chat_locked_by` | Stop Chat (superadmin) | kosong = diskusi terbuka, termasuk pada tiket `CLOSED` |
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

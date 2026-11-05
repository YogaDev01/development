# Blueprint — Team sebagai Sumber Akses & Struktur Organisasi

- Tanggal: 2026-11-06 · Branch kerja: `update/v3.3.0`
- Disusun oleh: sesi utama berperan **Planner** (`.claude/roles/planner.md`), read-only.
- Status: **DISETUJUI pemilik** (keputusan di §2). Belum ada kode/DB yang diubah.
- Klasifikasi risiko keseluruhan: **HIGH** (otorisasi) + **CRITICAL** untuk satu migrasi drop kolom (sudah disetujui eksplisit, §2 K10).

---

## 1. Tujuan

Team menjadi sumber kebenaran untuk **akses data** (PO & cost center) dan **tampilan struktur
organisasi**, tanpa mengubah workflow approval apa pun. Perubahan organisasi **tidak boleh mengubah
histori transaksi** yang sudah berjalan atau sudah disetujui.

Prinsip akhir:
1. Akses = Access Group individu ∪ Access Group dari team ACTIVE tempat user menjadi anggota ACTIVE
   ∪ role full scope ∪ dokumen yang pernah ia tindak (read-only).
2. Struktur/hierarki (leader, induk team) **hanya tampilan** — tidak pernah menurunkan atau menaikkan akses.
3. Role + menu menentukan halaman & tindakan; workflow tetap seperti sekarang.
4. Setiap perubahan Team tercatat (log per-module, dalam transaksi yang sama).
5. Log status dokumen tidak pernah ditulis ulang; konteks organisasi pelaku dibekukan saat aksi.

## 2. Keputusan pemilik (final)

| # | Keputusan |
|---|---|
| K1 | **Workflow tidak berubah**: approval CR, Budget Plan, PO, Treasury, Collection, dll. tetap (status, tahap, role, notifikasi, Todo List). Fokus = akses. |
| K2 | Akses PO diatur **per team** (utama) **dan per individu** (`users.access_group_id`, tetap ada). |
| K3 | Hierarki tidak mewariskan akses. Contoh: Ketut leader Business Support, Ahmad leader Operation & bawahan Ketut → akses Ketut ≠ akses Ahmad, dan sebaliknya. Satu orang boleh leader di beberapa team. |
| K4 | `finance_manager`, `cost_control`, `collection`, `treasury`, `accounting` melihat **semua PO termasuk nilai uang PO**. Tetap dibatasi menu (`menu:list-po` dll.). Role `finance` umum **tidak** ditambahkan. |
| K5 | Jalur team juga meneruskan **cost center (overhead)** dari Access Group team. |
| K6 | Snapshot konteks organisasi: **setuju**, untuk audit (bukan routing). |
| K7 | Pelaku tetap bisa **membaca** dokumen yang pernah ia tindak setelah pindah team (read-only). Leader/anggota baru melihat dokumen lama sesuai akses team (otomatis karena akses berbasis PO). |
| K8 | User dengan >1 team aktif: team primary ditentukan manusia dari laporan dry-run. |
| K9 | Hard delete Team dimatikan; diganti **Deactivate**. |
| K10 | Drop `team_members.ca_limit`, `join_at`, `left_at` **langsung dalam satu rilis, tanpa backup** (data hilang permanen; disetujui pemilik 2026-11-06). |

## 3. Kondisi saat ini (audit, dengan bukti)

### 3.1 Skema
- `teams`: id, code (unik), name, department_id (FK nullOnDelete), leader_id (FK users nullOnDelete), customer_id, status ENUM ACTIVE/INACTIVE. (`2026_09_15_000008`, `2026_10_19_000300`)
- `team_members`: id, team_id (FK **cascadeOnDelete**), user_id (FK cascadeOnDelete), position, level (manual), ca_limit, join_at, left_at, status.
- `departments`: id, code, name, status.
- `access_group_details`: access_group_id, project_id | cost_center_id | **team_id** (satu baris = satu sumber).
- `users`: role_id (satu role), access_group_id (satu Access Group individu).
- `employees`: master payroll, **tidak terhubung** ke `users`. Identitas alur kerja = `users`.
- Tidak ada tabel transaksi yang menyimpan team/department.

### 3.2 Akses
- PO: `AccessRoles::PO_FULL_SCOPE = superadmin, approver, finance_manager` (`app/Support/AccessRoles.php:44`); lainnya `User::accessibleProjectIds()` = AG individu ∪ AG via team (`app/Models/User.php:390-404`). Dipakai `ProjectController` (index, detail JSON, export), `ProjectMonitoringController`, `InvoiceCashReceiptController:583,1476`, `TodoListController:1453`, `BuildsCrWorklist:51`.
- **Inkonsisten — hanya AG individu** (jalur team diabaikan):
  - `Concerns/ScopesBudgetPlanVisibility.php:73` (cost center) dan `:95-96` (project)
  - `Concerns/BuildsCrWorklist.php:112` (cost center overhead)
  - `BudgetRequestController.php:918` (pilihan Budget Plan saat buat CR)
  - `FinanceSummaryController.php:784`
  - `ProfileController.php:32-35` (daftar PO di Profil)
  - `BudgetRequest::isAccessibleBy()` (`app/Models/BudgetRequest.php:1030`) — tidak dipanggil di mana pun (lihat §12).
- Nilai uang PO: `AccessRoles::PO_VALUE` (harus identik dengan `FinanceSummaryController::PO_VALUE_ROLES`, `FinanceSummaryController.php:27`) dan `AccessRoles::PO_MONEY_COLUMNS`.
- CR: `BuildsCrWorklist::scopeByRole()` — role istimewa (`:40-43`) lihat semua; lainnya per AG proyek/cost center.

### 3.3 Team module
- Route: create/store/destroy = `role:superadmin`; edit/update = `menu:team` + `TeamController::authorizeEdit()` (superadmin atau leader team); index/show = `menu:team` (`routes/web.php:580-616`).
- `TeamController::syncMembers()` **menghapus semua anggota lalu membuat ulang** (`TeamController.php:287-302`) → id membership & riwayat hilang setiap simpan.
- `TeamController::destroy()` hard delete + cascade anggota (`:185-202`).
- `Team::orgChart()` = kelompok per level, bukan pohon (`app/Models/Team.php:96`).
- Validasi: leader wajib anggota ACTIVE; anggota ACTIVE tidak boleh ganda dalam team yang sama (`TeamController.php:266-281`).
- Pemakai `teams.leader_id` di luar modul Team: `TroubleTicketWorkflow` (`:205-260, 678-714`), `TroubleTicket::scopeVisibleTo` (`:395-405`), `TroubleTicketController:292`, `SiteController:509` → **kolom tetap ada**.

### 3.4 Histori
- Log status menyimpan pelaku sebagai teks: CR `acted_by/actor_name/actor_role` (`BudgetRequest.php:1144-1153`), PO `user_id/user_name/role` (`Project.php:1046-1054`), Budget Plan (`BudgetPlan.php:339-351`). Perubahan team tidak menyentuh baris ini.
- Tidak ada catatan konteks organisasi (team/department) pelaku saat aksi.
- `UserActivityLog::record()` **gagal-aman** (menelan error) dan mendukung `changes` JSON — cukup untuk jejak tambahan, tidak cukup sebagai satu-satunya jejak struktur organisasi.

## 4. Jaminan histori transaksi (wajib)

| Ancaman | Penanganan |
|---|---|
| Edit team menghapus & membuat ulang membership | Upsert by id: baris tidak pernah dihapus; keluar = `status=INACTIVE`; bergabung lagi = baris baru. |
| Hapus team menghapus anggota (cascade) | Hard delete dimatikan (K9). Route `DELETE team/{id}` menolak (atau dihapus dari UI + controller menolak). |
| Leader ditimpa tanpa jejak | Setiap perubahan leader/anggota/induk/akses dicatat di `team_logs` (before/after) **di dalam transaksi yang sama** + `UserActivityLog`. |
| Tidak ada konteks organisasi saat aksi | Kolom baru nullable `actor_org_context` (JSON) di `request_status_logs`, `project_logs`, `budget_plan_logs`, diisi oleh `addLog()` saat baris log dibuat. Baris log lama **tidak** di-backfill (tetap NULL). Log submit oleh pengaju = snapshot konteks pengaju (K6). |
| Baris log dokumen berubah | Tidak ada kode baru yang meng-update/menghapus baris log. Diuji dengan checksum sebelum/sesudah perubahan organisasi. |
| Pindah team → kehilangan akses ke dokumen yang pernah ditindak | Jalur baca read-only "pernah ditindak" (K7, §6.4). |

## 5. Arsitektur data (final)

### 5.1 Perubahan skema

| Tabel | Perubahan | Sifat |
|---|---|---|
| `teams` | + `parent_team_id` nullable, FK `teams` **RESTRICT**, index | additive |
| `team_members` | + `is_primary` boolean default false; + index (`user_id`,`status`), (`team_id`,`status`) | additive |
| `team_members` | − `ca_limit`, − `join_at`, − `left_at` | **destruktif (K10)** |
| `team_logs` (baru) | id, team_id (FK RESTRICT), action (string 40), changes JSON, notes text null, user_id (FK nullOnDelete), user_name, role, created_at | additive |
| `request_status_logs`, `project_logs`, `budget_plan_logs` | + `actor_org_context` JSON nullable | additive |

Catatan:
- `parent_team_id` **hanya untuk org chart** (K3). Tidak dibaca oleh fungsi akses mana pun.
- Tidak membuat tabel snapshot/approval terpisah: konteks dibekukan di baris log yang memang sudah immutable (lebih sedikit tabel, tanpa jalur tulis baru).
- FK `team_members.team_id` cascadeOnDelete **tidak diubah** (perubahan FK = HIGH); cukup karena hard delete team ditutup di aplikasi.
- Tidak menambah tabel `positions`; `team_members.position` (teks) tetap.

### 5.2 Isi `actor_org_context`
```json
{"primary_team": {"id": 3, "code": "TEAM-003", "name": "FINANCE"},
 "department": {"id": 2, "name": "FINANCE & ACCOUNTING"},
 "position": "Finance Manager", "level": 1,
 "teams": ["TEAM-003", "TEAM-007"]}
```
Primary = membership ACTIVE `is_primary=1`; bila tidak ada, `primary_team = null` (jangan menebak).

### 5.3 Aturan validasi Team
1. Leader wajib anggota ACTIVE team itu (aturan lama, tetap).
2. Satu user maksimal satu membership ACTIVE per team (lama, tetap) — dicek di server dengan `lockForUpdate` pada baris team.
3. Anggota baru hanya dari user `status=ACTIVE`; team INACTIVE tidak boleh menerima anggota baru.
4. Maksimal **satu** membership ACTIVE `is_primary=1` per user lintas team (cek server + lock baris membership user).
5. `parent_team_id` ≠ dirinya sendiri dan tidak membentuk siklus (telusuri induk ke atas sampai NULL; tolak bila bertemu team yang diedit). Induk harus team ACTIVE.
6. Deactivate team: anggota tetap tercatat; akses via team itu berhenti (fungsi akses hanya membaca team ACTIVE); team anak dengan induk INACTIVE diperingatkan di UI (tidak diblokir).
7. Pengaturan Access Group dari form Team: hanya Access Group yang ada; tanpa duplikat (`access_group_id`,`team_id`).

## 6. Akses (final)

### 6.1 Helper tunggal di `User`
- `accessibleProjectIds()` — **tetap**, tambahkan filter `teams.status = ACTIVE` pada jalur team.
- `accessibleCostCenterIds()` — **baru**, pasangan cost center: AG individu ∪ AG via team ACTIVE.
- Keduanya di-memo per instance (pola cache per-instance yang sudah ada, §12 CLAUDE.md) agar tidak menambah query per pemanggilan.

### 6.2 Pemakai yang wajib dialihkan ke helper
| File | Dari | Ke |
|---|---|---|
| `Concerns/ScopesBudgetPlanVisibility.php:73` | `accessGroup->costCenterIds()` | `accessibleCostCenterIds()` |
| `Concerns/ScopesBudgetPlanVisibility.php:95-96` | `accessGroup->details->pluck('project_id')` | `accessibleProjectIds()` |
| `Concerns/BuildsCrWorklist.php:112` | `accessGroup->costCenterIds()` | `accessibleCostCenterIds()` |
| `BudgetRequestController.php:918` | `accessGroup->details->pluck('project_id')` | `accessibleProjectIds()` |
| `FinanceSummaryController.php:784` | `accessGroup->details->pluck('project_id')` | `accessibleProjectIds()` |
| `BudgetPlanController.php:97-99, 136-139` | AG individu | `accessibleProjectIds()` (periksa konteks dulu) |
| `ProfileController.php:32-35` | `accessGroup->projects` | daftar PO dari `accessibleProjectIds()` |

Ini **memperluas** akses user yang selama ini hanya punya akses via team (HIGH, disetujui lewat K2/K5).

### 6.3 Role (K4)
`app/Support/AccessRoles.php`:
- `PO_FULL_SCOPE` += `cost_control`, `collection`, `treasury`, `accounting`
- `PO_VALUE` += sama → **wajib** samakan `FinanceSummaryController::PO_VALUE_ROLES`
- `PO_MONEY_COLUMNS` += sama
- Tidak menambah `finance`. Tidak mengubah `PO_FULL_EDIT`, `PO_BUDGET_BUNDLE`, gerbang route aksi.
- Cek konsistensi: route `role:`/`menu:` halaman PO dan kondisi Blade yang memakai konstanta ini (§8 CLAUDE.md). Menu tidak diberikan otomatis — tetap lewat Role & Menu Access.

### 6.4 Read-only "pernah ditindak" (K7)
- CR: `canViewCr()` (`BudgetRequestController.php:109`) mendapat varian **baca saja** `canReadCr()` = `canViewCr()` ∪ ada `request_status_logs.acted_by = user`. Dipakai **hanya** oleh: detail/JSON, cetak, unduh lampiran (`FileAccess`). `findVisibleCr()` untuk aksi **tidak berubah**.
- PO: `canViewProjectJson()` (`ProjectController.php:246`) ∪ ada `project_logs.user_id = user`; hanya detail JSON & lampiran. Daftar (index) **tidak** ikut menampilkan baris ini (hindari "data muncul di daftar tapi aksi 403"). **Needs confirmation saat Executor**: apakah perlu tab/penanda "Pernah saya tindak" — default: tidak, hanya akses lewat link (notifikasi, Todo history, log aktivitas).
- Budget Plan: tidak termasuk (K7 dinyatakan untuk dokumen approval; BP mengikuti scope biasa). **Needs confirmation** bila pemilik ingin BP ikut.

### 6.5 Matriks akses ringkas
| Role | Scope lihat PO | Nilai uang PO | Scope lihat CR | Aksi |
|---|---|---|---|---|
| superadmin, approver, finance_manager | Semua | Ya | Semua | Tetap |
| cost_control, collection, treasury, accounting | **Semua (baru)** | **Ya (baru)** | Semua (tetap) | Tetap |
| finance | AG individu ∪ AG team | Ya (PO_VALUE, tetap) | Semua (tetap) | Tetap |
| head, manager, lainnya | AG individu ∪ AG team | Sesuai daftar sekarang | AG individu ∪ AG team (+ milik sendiri) | Tetap |
| siapa pun | + read-only dokumen yang pernah ditindak | sesuai role | + read-only | tidak bertambah |

## 7. Fitur Team (UI)

Mengikuti pola Budget Plan / Cost Request terbaru, token `tesys-ui-tokens.css`, komponen `x-status`, `x-empty`, `x-drawer`. Tanpa warna/hex inline baru. Tidak ada build Tailwind kecuali halaman memang memakai `tw:` (default: tidak).

1. **Index** (`team/index`): kolom tetap (Code, Name, Department, Leader, Members, Member Count, Status) + kolom **Induk** + **Akses PO** (jumlah Access Group). Filter & search tetap. Tombol Hapus diganti **Deactivate/Activate**.
2. **Create/Edit**: field Induk Team (select, team ACTIVE selain dirinya & keturunannya); bagian **Akses PO (Access Group)** multi-select (`tesys-searchable-select.js`); tabel anggota: user, position, level, status, **Primary** (radio per user; validasi lintas team di server). Kolom CA Limit / Join / Left **dihapus**.
3. **Show**: Informasi umum, Department, Leader, Induk & sub-team, Anggota, Akses PO (daftar AG + jumlah PO), **Riwayat** (`team_logs`, terbaru di atas).
4. **Struktur Organisasi** (halaman baru `team/structure`, gate `menu:team`): pohon dari `parent_team_id`; node team berisi leader (foto `avatar_url`, nama, posisi) + anggota (kartu kecil, level); konektor garis CSS; expand/collapse per node; penanda "Anda" pada node user login; seorang leader multi-team tampil di tiap team-nya dengan badge "Leader di N team"; klik node → drawer detail team/anggota. Mobile ≤640px: pohon jadi daftar bertingkat (indentasi), tanpa scroll horizontal.
5. **Profil → tab Team**: hapus tampilan CA limit (`profile/_team.blade.php:54-55`).

## 8. Roadmap & file

### Fase 1 — Fondasi data & Team module (HIGH + 1 CRITICAL disetujui)
Migrasi (timestamp unik, setelah `2026_10_29_000700`):
- `2026_11_06_000100_add_parent_and_primary_to_teams.php` — `teams.parent_team_id`, `team_members.is_primary`, index. Guard `hasColumn`. `down()` lengkap.
- `2026_11_06_000200_create_team_logs_table.php`.
- `2026_11_06_000300_add_actor_org_context_to_status_logs.php` — 3 tabel log. Guard `hasTable/hasColumn`.
- `2026_11_06_000400_drop_legacy_columns_from_team_members.php` — drop `ca_limit`, `join_at`, `left_at` (guard `hasColumn` per kolom). `up()` menulis ke log jumlah baris non-null per kolom sebelum drop. `down()` menambah kembali kolom nullable kosong (komentar: data tidak dapat dipulihkan, keputusan K10).

Kode:
- `app/Models/Team.php` — `parent()`, `children()`, `logs()`, `scopeActive()`, `addLog()`; `generateNextCode()` tetap; `orgChart()` dipertahankan untuk Profil.
- `app/Models/TeamMember.php` — hapus 3 kolom dari fillable/casts + `getCaLimitFormattedAttribute`; tambah `is_primary`.
- `app/Models/TeamLog.php` (baru).
- `app/Http/Controllers/TeamController.php` — upsert anggota by id (pola `ProjectController::syncDetails` / `BudgetRequestController::syncDetails`), validasi §5.3, `lockForUpdate`, `team_logs` + `UserActivityLog` dengan `changes`, `destroy()` → menolak (atau route diganti `deactivate`), aksi `deactivate/activate`.
- `routes/web.php` — ganti/tutup `DELETE team/{id}`; tambah `PATCH team/{id}/status` (`role:superadmin`).
- Views: `team/index`, `team/create`, `team/edit`, `team/show`, `profile/_team`.
- Seeder: `database/seeders/TeamStrukturOrganisasiSeeder.php:135-136`, `DummySiteTroubleTicketSeeder.php:202` — hapus kunci kolom yang di-drop (seeder tidak dijalankan oleh Executor).
- Command dry-run `team:primary-report` (`app/Console/Commands/`) — **baca saja**: daftar user dengan >1 membership ACTIVE (CSV ke `storage/app/private/reports/`). Penetapan primary dilakukan manusia lewat form Edit Team. User dengan tepat 1 membership ACTIVE: **Needs confirmation** apakah boleh di-set primary otomatis oleh migrasi (default: tidak; lewat command `--apply` terpisah setelah disetujui).

### Fase 2 — Akses PO & cost center via Team (HIGH)
- `app/Models/User.php` — §6.1.
- `app/Models/AccessGroup.php` — tidak berubah (helper `projectIds/costCenterIds/teamIds` tetap).
- File di §6.2 dan §6.3.
- `TeamController` (form Akses PO) menulis `access_group_details (access_group_id, team_id)`: hanya tambah/hapus baris team ini (pola `ProjectController::syncAccessGroups`, `:344-363`). Catatan: `AccessGroupController::update()` menghapus & membuat ulang semua detail AG (`:490-530`) — tetap konsisten selama form AG menampilkan pilihan team yang sama; dua pintu edit = last-write-wins, keduanya tercatat.
- `app/Services/FileAccess.php` — lampiran PO/CR/BP mengikuti helper baru (cek jalur yang memakai AG individu).
- `InvoiceCashReceiptController`, `ProjectMonitoringController` — sudah pakai `accessibleProjectIds()`; cukup regresi.

### Fase 3 — Konteks organisasi di log + read-only (HIGH)
- `User::orgContext(): array` (memo per instance) — §5.2.
- `BudgetRequest::addLog()`, `Project::addLog()`, `BudgetPlan::addLog()` — isi `actor_org_context` bila kolom ada (guard `Schema::hasColumn` dihitung sekali per request, pola `Team::supportsCustomer()`).
- Jalur log yang **tidak** lewat `addLog()` (mis. bulk/Treasury yang membuat log langsung): inventarisasi dengan grep `statusLogs()->create`, `RequestStatusLog::create`, `logs()->create`; isi konteks di sana juga atau catat sebagai gap.
- §6.4: `BudgetRequestController` (`canReadCr`), `ProjectController` (`canViewProjectJson`), `FileAccess`.

### Fase 4 — Struktur Organisasi (MEDIUM)
- `TeamController::structure()` + route `GET team/structure` (`menu:team`), view `team/structure.blade.php`, CSS di `public/css/tesys-ui-pages.css` (atau file halaman yang sudah dipakai modul Team), JS kecil expand/collapse (tanpa library baru). Satu query: team ACTIVE + leader + anggota ACTIVE + user (eager load), dibangun jadi pohon di PHP.

### Fase 5 — Test, dokumentasi, kesiapan rilis
- Test (lihat §9) — dijalankan **hanya** atas permintaan pengguna setelah §4.3.
- Docs: `docs/database.md` (skema), `docs/security.md` / `docs/modules/approval.md` (matriks akses), `docs/architecture.md` (Team), `config/panduan.php` + topik Team bila ada. `CHANGELOG.md`/`config/changelog.php` hanya saat rilis diminta.

Dependensi: Fase 1 → 2 → 3; Fase 4 setelah Fase 1. Fase 2 dan 4 boleh paralel setelah Fase 1.

## 9. Acceptance criteria & test

File test baru (`tests/Feature`, helper `tests/Support/MakesTesysData.php`):
`TeamManagementTest`, `TeamAccessScopeTest`, `OrgHistoryImmutabilityTest`, `PoFinanceScopeTest`.

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| A1 | AG-X berisi PO-1 dipasang ke Team A. Anggota ACTIVE Team A (tanpa AG individu) membuka daftar PO, detail JSON PO-1, lampiran PO-1, export | Terlihat / 200 |
| A2 | Anggota Team B membuka detail JSON & lampiran PO-1 lewat URL/ID | 403 |
| A3 | Ketut leader Business Support; Team Operation `parent_team_id` = Business Support; AG-Y hanya di Operation | Ketut tidak melihat PO AG-Y; Ahmad tidak melihat PO AG Business Support |
| A4 | User dengan AG individu (tanpa team) | Akses sama seperti sebelum perubahan |
| A5 | Team A di-Deactivate | Anggotanya kehilangan akses PO-1 (bila tidak punya jalur lain); membership tetap tercatat |
| A6 | `cost_control`/`collection`/`treasury`/`accounting` membuka detail JSON PO apa pun | 200 + nilai uang tampil; `finance` di luar AG → 403 |
| A7 | Role pada A6 tanpa menu `list-po` membuka `/project` | Ditolak oleh `menu:` |
| A8 | Cost center overhead CC-1 di AG team → anggota melihat Budget Plan & CR overhead CC-1 | Terlihat; anggota team lain tidak |
| A9 | User akses-via-team membuka Budget Plan, Finance Summary, pilihan BP saat buat CR untuk PO-1 | Konsisten dengan daftar PO |
| H1 | Edit Team (ubah posisi satu anggota) | Jumlah & id baris `team_members` tidak berubah; `team_logs` bertambah 1 dengan before/after |
| H2 | CR APPROVED; approver lalu dipindah team, leader team diganti, team di-Deactivate | Baris `request_status_logs` CR itu identik (checksum sebelum = sesudah) |
| H3 | Aksi CR baru setelah migrasi | `actor_org_context` terisi sesuai §5.2; baris log lama tetap NULL |
| H4 | Approver pindah team lalu membuka detail CR yang pernah ia approve | 200 read-only; POST aksi apa pun → 403; CR tidak muncul di daftarnya bila di luar scope |
| H5 | `DELETE team/{id}` | Ditolak; tidak ada baris terhapus |
| V1 | Membuat siklus induk (A→B→A), induk = diri sendiri | Ditolak 422 |
| V2 | Dua membership primary ACTIVE untuk user yang sama | Ditolak |
| V3 | Menambah anggota ke team INACTIVE / user INACTIVE | Ditolak |
| V4 | Leader bukan anggota ACTIVE | Ditolak (aturan lama) |
| R1 | Regresi: `CostRequestWorkflowTest`, `CostRequestAccessTest`, test PO/Budget Plan yang ada | Lulus tanpa perubahan ekspektasi workflow |

## 10. Risiko

| Risiko | Level | Mitigasi |
|---|---|---|
| Drop 3 kolom tanpa backup — data hilang permanen | CRITICAL (disetujui K10) | Guard `hasColumn`; log jumlah baris; deploy kode **sebelum** `migrate` |
| Perluasan akses PO/nilai uang ke 4 role | HIGH | Tetap di balik `menu:`; test A6/A7 |
| Perluasan akses via team ke BP/Finance Summary/cost center | HIGH | Satu helper; test A8/A9 |
| Jalur read-only bocor jadi jalur aksi | HIGH | Fungsi terpisah (`canReadCr`), `findVisibleCr` tidak disentuh; test H4 |
| Query tambahan per request (shared hosting) | MEDIUM | Memo per instance; eager load; tanpa query di loop |
| Dua pintu edit akses (Team & Access Group) | MEDIUM | Tabel sama; log di kedua pintu |
| Urutan deploy salah (migrate sebelum upload) | HIGH | Form lama menulis kolom yang sudah hilang → error. Wajib: upload → `migrate` → `optimize:clear` (config + route + view; ada route baru `team.status`, `team.structure`) |
| Seeder lama memakai kolom yang di-drop | LOW | Diperbarui di Fase 1 |

## 11. Instruksi untuk Executor

1. Kerjakan per fase; tiap fase berakhir dengan laporan "File baru / File diubah" + "Pesan untuk Reviewer".
2. Ikuti pola yang ada: fat controller, validasi inline, `UserActivityLog::record()`, guard `Schema::has*`, upsert by id.
3. Bahasa komentar & UI: Indonesia.
4. Verifikasi tanpa DB dulu: `php -l` per file PHP, cek Blade (lihat memori `reference_js_syntax_check`), `git diff`.
5. `pint` hanya untuk file kecil yang diubah; **jangan** pada `BudgetRequestController`, `ProjectController`, `FinanceSummaryController` (file besar).

**Dilarang:**
- Mengubah status, transisi, `STAGE_ROLES`, notifikasi, Todo List, atau perhitungan uang apa pun.
- Mengubah/menghapus migrasi lama; mengubah FK lama.
- Menghapus `teams.leader_id` atau `users.access_group_id`.
- Membuat Policy/Repository/DTO; layanan baru hanya bila benar-benar perlu (default: helper di `User`/`Team`).
- Menjalankan `migrate`, seeder, `artisan test`, `composer test/setup`, `config:cache`, deploy — kecuali diminta pengguna dan lewat §4.3 CLAUDE.md.
- Membaca/menampilkan `.env`.
- Menetapkan `is_primary` otomatis tanpa persetujuan.

**Checklist validasi per fase:** route `role:`/`menu:` ↔ `AccessRoles` ↔ kondisi Blade sejalan; scope daftar = scope detail = scope lampiran = scope export; tidak ada `accessGroup->details` tersisa di jalur akses (grep); `git diff` ditinjau.

## 12. Potential future improvement (di luar scope, tidak dikerjakan)

- Notifikasi tahap Head dikirim ke **semua** Head (`RoleDirectory::heads()`, `BudgetRequestController.php:2278`, `BudgetPlanController.php:1566`) → kode & deskripsi CR terlihat Head unit lain. Menyentuh workflow notifikasi (K1).
- `BudgetRequest::isAccessibleBy()` tidak dipanggil di mana pun dan memakai `$user->role` (mengabaikan View As).
- Approval berbasis hierarki (rantai atasan, multi-level, delegasi) — ditunda sesuai K1; struktur `parent_team_id` + leader sudah memungkinkan fase berikutnya.
- Integrasi Master Employee (`employees` ↔ `users`) sebagai pengganti `join_at`/`left_at`.

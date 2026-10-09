{{--
    Papan Assignment — dipakai bersama oleh halaman /assignment dan section
    "Tugas / Assignment" di Todo List. Variabel yang dibutuhkan:
      $assignedToMe  koleksi Assignment (penerima = user ini)
      $createdByMe   koleksi Assignment (pemberi  = user ini)
      $assignees     koleksi User aktif (untuk dropdown penerima)

    Tidak ada layer/komponen baru: Bootstrap 4 + token tesys-ui yang sudah ada.
--}}
@php $me = auth()->id(); @endphp

@if (session('success'))
    <div class="alert alert-success alert-dismissible" style="border-radius:var(--radius-lg);">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible" style="border-radius:var(--radius-lg);">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-triangle mr-1"></i>{{ session('error') }}
    </div>
@endif

<div class="card shadow-sm mt-3" style="border-radius:var(--radius-lg);">
    <div class="card-body py-3 px-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px;">
            <div>
                <span style="font-size:15px;font-weight:700;color:var(--text);"><i class="fas fa-user-check mr-1"></i> Tugas / Assignment</span>
                <div style="font-size:12px;color:var(--text-faint);">Tugas yang diberikan ke Anda dan yang Anda berikan ke orang lain.</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mdlAssignmentCreate">
                <i class="fas fa-plus mr-1"></i> Buat Tugas
            </button>
        </div>

        <ul class="nav nav-tabs" id="assignmentTabs" style="font-size:13px;">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#asgToMe">
                    Ditugaskan ke Saya <span class="badge badge-warning">{{ $assignedToMe->whereIn('status', \App\Models\Assignment::OPEN_STATUSES)->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#asgByMe">
                    Dibuat oleh Saya <span class="badge badge-secondary">{{ $createdByMe->whereIn('status', \App\Models\Assignment::OPEN_STATUSES)->count() }}</span>
                </a>
            </li>
        </ul>

        <div class="tab-content pt-2">
            <div class="tab-pane fade show active" id="asgToMe">
                @include('assignment._table', ['items' => $assignedToMe, 'perspective' => 'assignee'])
            </div>
            <div class="tab-pane fade" id="asgByMe">
                @include('assignment._table', ['items' => $createdByMe, 'perspective' => 'assigner'])
            </div>
        </div>
    </div>
</div>

{{-- ============================ MODAL: Buat Tugas ============================ --}}
{{-- data-focus="false": matikan focus-trap Bootstrap supaya kotak "Cari user"
     dari searchable-select (dirender di <body>, di luar .modal) bisa diklik. --}}
<div class="modal fade" id="mdlAssignmentCreate" tabindex="-1" role="dialog" data-focus="false">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('assignment.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content" style="border-radius:var(--radius-lg);">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus mr-1"></i> Buat Tugas</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @include('assignment._form_fields', ['assignees' => $assignees])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane mr-1"></i> Kirim Tugas</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ============================ MODAL: Edit Tugas ============================ --}}
<div class="modal fade" id="mdlAssignmentEdit" tabindex="-1" role="dialog" data-focus="false">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="" id="formAssignmentEdit" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-content" style="border-radius:var(--radius-lg);">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-1"></i> Ubah Tugas</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @include('assignment._form_fields', ['assignees' => $assignees, 'edit' => true])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ======================= MODAL: Detail & Riwayat ========================== --}}
<div class="modal fade" id="mdlAssignmentDetail" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history mr-1"></i> Detail Tugas</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="assignmentDetailBody">
                <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>
            </div>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    .assignment-dropzone {
        border: 1.5px dashed var(--border);
        border-radius: var(--radius-lg);
        padding: 14px;
        text-align: center;
        transition: border-color .15s, background-color .15s;
        cursor: pointer;
    }
    .assignment-dropzone.dz-over {
        border-color: var(--accent);
        background: rgba(0, 0, 0, .03);
    }
    .assignment-dropzone .dz-prompt { color: var(--text-faint); font-size: 13px; }
    .assignment-dropzone .dz-list { margin-top: 8px; }
    .assignment-dropzone .dz-list li {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; font-size: 12px; padding: 3px 8px; border-radius: var(--radius-sm);
        background: rgba(0, 0, 0, .04); margin-top: 4px;
    }
    .assignment-dropzone .dz-remove { cursor: pointer; color: var(--danger); font-weight: 700; }

    /* ===== Modal Detail — tampilan "email thread" ===== */
    #assignmentDetailBody { max-height: 70vh; overflow-y: auto; }
    /* Bootstrap 4 tidak punya utility ini (ditambah di BS5) — dibutuhkan supaya
       anak flex (nama/subjek panjang) tidak melebarkan baris di luar modal. */
    .min-w-0 { min-width: 0; }

    .asg-chat-av {
        width: 30px; height: 30px; border-radius: 50%;
        background: var(--info-bg); color: var(--text-dim);
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; flex-shrink: 0;
    }
    .asg-av-lg { width: 38px; height: 38px; font-size: 13px; }

    .asg-mail-head { display: flex; gap: 12px; align-items: flex-start; }
    .asg-mail-body {
        margin-top: 12px; font-size: 13px; line-height: 1.5; color: var(--text);
        white-space: pre-line; background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius-lg); padding: 10px 12px;
    }
    .asg-mail-attachments { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .asg-attachment-chip {
        display: inline-flex; align-items: center; gap: 6px; font-size: 12px;
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm);
        padding: 4px 10px; max-width: 220px; color: var(--text);
    }
    .asg-attachment-chip:hover { border-color: var(--accent); text-decoration: none; color: var(--text); }

    .asg-chat { display: flex; flex-direction: column; gap: 12px; padding: 2px; }
    .asg-chat-row { display: flex; gap: 8px; max-width: 80%; }
    .asg-chat-row.mine { align-self: flex-end; flex-direction: row-reverse; text-align: right; }
    .asg-chat-row.mine .asg-chat-av { background: var(--charcoal); color: #fff; }
    .asg-chat-bubble {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
        padding: 7px 11px; font-size: 12.5px; line-height: 1.4; color: var(--text);
    }
    .asg-chat-meta { font-size: 10px; color: var(--text-faint); margin-bottom: 3px; }

    .asg-event-row { display: flex; align-items: center; justify-content: center; gap: 8px; }
    .asg-event-meta { font-size: 11px; color: var(--text-faint); }

    .asg-composer {
        display: flex; align-items: center; gap: 8px; margin-top: 14px;
        padding-top: 12px; border-top: 1px solid var(--border);
    }
    .asg-composer-input { border-radius: 999px; }
    .asg-composer-send { border-radius: 50%; width: 32px; height: 32px; padding: 0; flex-shrink: 0; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    // Dropdown penerima: jadikan searchable (pola sama dengan dropdown lain).
    if (window.TesysSearchableSelect) {
        TesysSearchableSelect.init('.js-assignee-select', {
            placeholder: '— Pilih user —',
            searchPlaceholder: 'Cari user...',
        });
    }

    // Isi modal Edit dari atribut data-* tombol yang ditekan.
    $(document).on('click', '.btn-assignment-edit', function () {
        var d   = $(this).data();
        var f   = document.getElementById('formAssignmentEdit');
        f.action = d.updateUrl;
        f.querySelector('[name="title"]').value       = d.title || '';
        f.querySelector('[name="description"]').value  = d.description || '';
        f.querySelector('[name="due_date"]').value     = d.dueDate || '';
        f.querySelector('[name="priority"]').value     = d.priority || 'MEDIUM';
        var sel = f.querySelector('[name="assignee_id"]');
        sel.value = d.assigneeId || '';
        // Perbarui label tombol searchable-select (value di-set lewat JS).
        $(sel).trigger('tss:sync');
    });

    var detailBody = document.getElementById('assignmentDetailBody');
    var detailUrlAktif = null; // url logs() tugas yang sedang dibuka di modal detail

    function muatDetail(url) {
        detailUrlAktif = url;
        detailBody.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>';
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (html) { detailBody.innerHTML = html; })
            .catch(function () { detailBody.innerHTML = '<div class="alert alert-danger m-0">Gagal memuat detail.</div>'; });
    }

    // Muat detail + riwayat via AJAX. Request pertama ke logs() oleh PENERIMA
    // tugas yang statusnya masih Pending otomatis memicu transisi ke Read di
    // server (AssignmentController::logs()) — badge status di tabel baru
    // terlihat berubah setelah modal ditutup / halaman dimuat ulang lagi,
    // jadi daftar tabel di-refresh juga begitu modal ini ditutup.
    var perluRefreshTabel = false;
    $(document).on('click', '.btn-assignment-detail', function () {
        perluRefreshTabel = false;
        muatDetail($(this).data('logsUrl'));
    });
    $('#mdlAssignmentDetail').on('hidden.bs.modal', function () {
        if (perluRefreshTabel) window.location.reload();
    });

    // Kirim komentar via AJAX supaya modal tidak ikut tertutup/reload penuh.
    $(document).on('submit', '.form-assignment-comment', function (e) {
        e.preventDefault();
        var form = this;
        var input = form.querySelector('[name="body"]');
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (r) { return r.ok ? muatDetail(detailUrlAktif) : Promise.reject(r.status); })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Gagal mengirim komentar', confirmButtonColor: tokenWarnaAssignment('--accent', '#92232b') });
            })
            .finally(function () { btn.disabled = false; });
    });

    // Konfirmasi SweetAlert untuk aksi yang mengubah status (selesai/batal/buka
    // kembali) — pola sama dengan Budget Plan (Swal.fire + form.submit()).
    function tokenWarnaAssignment(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    $(document).on('submit', '.form-assignment-confirm', function (e) {
        var form = this;
        if (form.dataset.confirmed === '1') return; // sudah dikonfirmasi, lanjutkan submit asli
        e.preventDefault();

        var danger = form.dataset.danger === '1';
        Swal.fire({
            title: form.dataset.title || 'Yakin?',
            text: form.dataset.text || '',
            icon: danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: tokenWarnaAssignment(danger ? '--danger' : '--accent', danger ? '#9a3030' : '#92232b'),
            cancelButtonColor: tokenWarnaAssignment('--text-muted', '#6a6e72'),
            confirmButtonText: form.dataset.confirm || 'Ya',
            cancelButtonText: 'Batal',
        }).then(function (r) {
            if (r.isConfirmed) {
                form.dataset.confirmed = '1';
                form.submit(); // full page submit -> redirect back, badge ikut ter-refresh
            }
        });
    });

    /* Dropzone lampiran: drag & drop + paste + klik. Tiap dropzone menyimpan
       daftar berkasnya di DataTransfer sendiri, lalu disalin ke input.files
       supaya ikut ter-submit sebagai attachments[]. */
    function humanSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024)    return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    document.querySelectorAll('.assignment-dropzone[data-dropzone]').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        var list  = zone.querySelector('.dz-list');
        var dt    = new DataTransfer();

        function render() {
            list.innerHTML = '';
            Array.prototype.forEach.call(dt.files, function (file, i) {
                var li = document.createElement('li');
                var name = document.createElement('span');
                name.className = 'text-truncate';
                name.textContent = file.name + ' (' + humanSize(file.size) + ')';
                var rm = document.createElement('span');
                rm.className = 'dz-remove';
                rm.innerHTML = '&times;';
                rm.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var keep = new DataTransfer();
                    Array.prototype.forEach.call(dt.files, function (f, j) { if (j !== i) keep.items.add(f); });
                    dt = keep;
                    input.files = dt.files;
                    render();
                });
                li.appendChild(name);
                li.appendChild(rm);
                list.appendChild(li);
            });
        }

        function addFiles(files) {
            Array.prototype.forEach.call(files, function (f) { dt.items.add(f); });
            input.files = dt.files;
            render();
        }

        function reset() { dt = new DataTransfer(); input.files = dt.files; render(); }

        // Klik area / tautan "pilih berkas" -> buka file picker.
        zone.addEventListener('click', function () { input.click(); });
        var browse = zone.querySelector('.dz-browse');
        if (browse) browse.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); input.click(); });

        // Pemilihan manual lewat dialog.
        input.addEventListener('change', function () {
            if (input.files && input.files.length) addFiles(input.files);
        });

        // Drag & drop.
        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dz-over'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dz-over'); });
        });
        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
        });

        // Paste (Ctrl+V) saat modal yang memuat dropzone ini sedang terbuka.
        document.addEventListener('paste', function (e) {
            var modal = zone.closest('.modal');
            if (modal && !modal.classList.contains('show')) return;
            if (!e.clipboardData || !e.clipboardData.files.length) return;
            addFiles(e.clipboardData.files);
        });

        // Reset saat modalnya dibuka supaya daftar tidak menumpuk antar-pembukaan.
        var modal = zone.closest('.modal');
        if (modal) $(modal).on('show.bs.modal', reset);
    });
})();
</script>
@endpush
@endonce

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
                    Ditugaskan ke Saya <span class="badge badge-warning">{{ $assignedToMe->where('status', 'OPEN')->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#asgByMe">
                    Dibuat oleh Saya <span class="badge badge-secondary">{{ $createdByMe->where('status', 'OPEN')->count() }}</span>
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

    // Muat detail + riwayat via AJAX.
    $(document).on('click', '.btn-assignment-detail', function () {
        var url  = $(this).data('logsUrl');
        var body = document.getElementById('assignmentDetailBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>';
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () { body.innerHTML = '<div class="alert alert-danger m-0">Gagal memuat detail.</div>'; });
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

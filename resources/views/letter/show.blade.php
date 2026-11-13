@extends('layouts.app')

@section('title', $letter->letter_no)

@section('content')
@php
    $st        = \App\Models\Letter::STATUSES;
    $flow      = \App\Models\Letter::FLOW[$letter->type];
    $bad       = in_array($letter->status, ['rejected', 'cancelled'], true);
    $cur       = array_search($letter->status, $flow, true);
    $allowed   = $letter->allowedActions();
    $d         = $letter->detail;
    $tabs      = ['surat' => 'Surat', 'info' => 'Informasi', 'isi' => 'Isi Surat',
                  'lampiran' => 'Lampiran (' . $letter->attachments->count() . ')',
                  'ttd' => 'Tanda Tangan (' . $letter->signatures->count() . ')',
                  'log' => 'Log (' . $letter->logs->count() . ')'];
    $categories = \App\Models\LetterCategory::orderBy('code')->get();
    $v = $letter;
    $me = auth()->id();
    $isSuper = auth()->user()->hasRole('superadmin');
    // Tombol aksi: label, kelas, perlu catatan (modal) atau konfirmasi biasa.
    $btn = [
        'submit'   => ['Submit', 'btn-primary', false, 'Ajukan surat ini untuk approval?'],
        'approve'  => ['Approve', 'btn-primary', false, 'Setujui surat ini?'],
        'reject'   => ['Reject', 'btn-outline-danger', true, 'Alasan penolakan'],
        'cancel'   => ['Batalkan', 'btn-outline-danger', true, 'Alasan pembatalan'],
        'sent'     => ['Tandai Terkirim', 'btn-primary', false, 'Tandai surat sudah dikirim?'],
        'complete' => ['Selesaikan', 'btn-primary', false, 'Tandai surat selesai?'],
        'redraft'  => ['Kembalikan ke Draft', 'btn-outline-secondary', false, 'Kembalikan surat ke Draft agar dapat diperbaiki?'],
        'delete'   => ['Hapus', 'btn-outline-danger', false, 'Hapus surat beserta isi, lampiran, tanda tangan, dan lognya?'],
    ];
@endphp
<div class="container-fluid">
    <a href="{{ route('letter.index') }}" class="small">&larr; Kembali ke daftar surat</a>

    <div class="d-flex align-items-start my-2" style="gap:12px">
        <div>
            <h4 class="mb-1">{{ $letter->letter_no }} <span class="badge badge-{{ $st[$letter->status][1] }}">{{ $st[$letter->status][0] }}</span></h4>
            <div class="text-muted small">{{ $letter->category->name }} &middot; {{ $letter->type === 'internal' ? 'Internal' : 'External' }} &middot; dibuat oleh {{ $letter->creator->name ?? '-' }}</div>
        </div>
        <div class="ml-auto d-flex flex-wrap justify-content-end" style="gap:6px">
            @foreach ($allowed as $a)
                @continue(in_array($a, ['edit'], true))
                @continue(in_array($a, ['approve', 'reject'], true) && ! $canApprove)
                <button type="button" class="btn btn-sm {{ $btn[$a][1] }}" data-toggle="modal" data-target="#modalAksi"
                        data-action="{{ route('letter.action', [$letter, $a]) }}" data-title="{{ $btn[$a][0] }}"
                        data-text="{{ $btn[$a][3] }}" data-note="{{ $btn[$a][2] ? 1 : 0 }}">{{ $btn[$a][0] }}</button>
            @endforeach
            <a href="{{ route('letter.print', $letter) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Cetak Surat</a>
        </div>
    </div>

    @if ($letter->status === 'submitted')
        <div class="alert alert-warning py-2 small">
            Menunggu persetujuan atasan pembuat dalam struktur team:
            <b>{{ $approvers->isNotEmpty() ? $approvers->implode(', ') : 'tidak ada leader team terdaftar (role HR / Finance Manager / Approver yang menyetujui)' }}</b>.
        </div>
    @endif

    <div class="card mb-3"><div class="card-body py-2 d-flex flex-wrap align-items-center" style="gap:8px">
        @foreach ($flow as $i => $s)
            <span class="badge {{ ! $bad && $cur !== false && $i < $cur ? 'badge-success' : (! $bad && $i === $cur ? 'badge-primary' : 'badge-light') }}">{{ $i + 1 }}. {{ $st[$s][0] }}</span>
            @if (! $loop->last)<span class="text-muted">&rsaquo;</span>@endif
        @endforeach
        @if ($bad)<span class="badge badge-danger">! {{ $st[$letter->status][0] }}</span>@endif
    </div></div>

    <div class="card">
        <ul class="nav nav-tabs px-3 pt-2">
            @foreach ($tabs as $k => $label)
                <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('letter.show', [$letter, 'tab' => $k]) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        <div class="card-body">

        @if ($tab === 'surat')
            <div style="background:#f4f5f7;margin:-1rem;padding:18px">@include('letter._paper')</div>

        @elseif ($tab === 'info')
            @if ($letter->status === 'draft')
                <form method="post" action="{{ route('letter.info.update', $letter) }}">
                    @csrf @method('PUT')
                    @include('letter._info-fields')
                    <div class="small text-muted mb-2">Nomor surat tidak berubah saat mengedit.</div>
                    <button class="btn btn-primary btn-sm">Simpan Perubahan</button>
                </form>
            @else
                <dl class="row mb-0">
                    <dt class="col-sm-3">No. Surat</dt><dd class="col-sm-9">{{ $letter->letter_no }}</dd>
                    <dt class="col-sm-3">No. Referensi</dt><dd class="col-sm-9">{{ $letter->reference_no ?: '-' }}</dd>
                    <dt class="col-sm-3">Kategori</dt><dd class="col-sm-9">{{ $letter->category->code }} &middot; {{ $letter->category->name }}</dd>
                    <dt class="col-sm-3">Tipe</dt><dd class="col-sm-9">{{ $letter->type === 'internal' ? 'Internal' : 'External' }}</dd>
                    <dt class="col-sm-3">{{ $letter->type === 'external' ? 'Customer' : 'Karyawan' }}</dt><dd class="col-sm-9">{{ $letter->recipientName() }}</dd>
                    <dt class="col-sm-3">Tim</dt><dd class="col-sm-9">{{ $letter->team->name ?? '-' }}</dd>
                    <dt class="col-sm-3">Departemen</dt><dd class="col-sm-9">{{ $letter->department->name ?? '-' }}</dd>
                    <dt class="col-sm-3">Dibuat</dt><dd class="col-sm-9">{{ $letter->created_at->format('d M Y H:i') }}</dd>
                    <dt class="col-sm-3">Diperbarui</dt><dd class="col-sm-9">{{ $letter->updated_at->format('d M Y H:i') }}</dd>
                </dl>
            @endif

        @elseif ($tab === 'isi')
            @php $ro = ! $letter->canEditContent(); $metaRows = $d->meta ?? []; $itemRows = $d->items ?? []; @endphp
            <form method="post" action="{{ route('letter.detail.save', $letter) }}">
                @csrf @method('PUT')
                <fieldset @disabled($ro)>
                    <h6>Kepala Surat</h6>
                    <div id="metaRows">
                        @foreach ($metaRows as $i => $m)
                            <div class="form-row mb-2 js-row">
                                <div class="col-4"><input name="meta[{{ $i }}][0]" class="form-control form-control-sm" value="{{ $m[0] ?? '' }}" placeholder="Label"></div>
                                <div class="col-7"><input name="meta[{{ $i }}][1]" class="form-control form-control-sm" value="{{ $m[1] ?? '' }}" placeholder="Isi"></div>
                                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    @unless ($ro)<button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addMeta">+ Baris</button>@endunless

                    <div class="form-group"><label>Perihal / judul di badan surat</label>
                        <textarea name="header" class="form-control" rows="2">{{ old('header', $d->header) }}</textarea></div>

                    @if ($tpl['items'])
                        <h6>Rincian Item</h6>
                        <table class="table table-sm"><thead><tr><th>Deskripsi</th><th style="width:140px">Harga Satuan</th><th style="width:90px">Qty</th><th style="width:100px">Satuan</th><th style="width:40px"></th></tr></thead>
                            <tbody id="itemRows">
                            @foreach ($itemRows as $i => $it)
                                <tr class="js-row">
                                    <td><input name="items[{{ $i }}][desc]" class="form-control form-control-sm" value="{{ $it['desc'] ?? '' }}"></td>
                                    <td><input name="items[{{ $i }}][price]" type="number" min="0" class="form-control form-control-sm text-right" value="{{ $it['price'] ?? 0 }}"></td>
                                    <td><input name="items[{{ $i }}][qty]" type="number" step="any" min="0" class="form-control form-control-sm text-right" value="{{ $it['qty'] ?? 1 }}"></td>
                                    <td><input name="items[{{ $i }}][unit]" class="form-control form-control-sm" value="{{ $it['unit'] ?? '' }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></td>
                                </tr>
                            @endforeach
                            </tbody></table>
                        @unless ($ro)<button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="addItem">+ Baris</button>@endunless
                        <div class="form-inline mb-3"><label class="mr-2">PPN (%)</label>
                            <input name="tax" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" style="width:90px" value="{{ old('tax', $d->tax) }}"></div>
                    @endif

                    <div class="form-group"><label>Isi / narasi surat</label>
                        <textarea name="body" class="form-control" rows="{{ $tpl['items'] ? 5 : 11 }}">{{ old('body', $d->body) }}</textarea></div>
                    <div class="form-group"><label>Catatan (dicetak miring)</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note', $d->note) }}</textarea></div>
                    <div class="form-group"><label>Kalimat penutup</label>
                        <input name="closing" class="form-control" value="{{ old('closing', $d->closing) }}"></div>
                </fieldset>
                @if ($ro)
                    <div class="alert alert-secondary small mb-0">Isi surat hanya dapat diubah saat status Draft atau Rejected.</div>
                @else
                    @if ($errors->any())<div class="text-danger small mb-2">{{ $errors->first() }}</div>@endif
                    <button class="btn btn-primary btn-sm">Simpan Isi Surat</button>
                @endif
            </form>

        @elseif ($tab === 'lampiran')
            @forelse ($letter->attachments as $a)
                <div class="d-flex align-items-center border-bottom py-2" style="gap:10px">
                    <div class="flex-grow-1">
                        {{ $a->original_name }} @if ($a->kind === 'balasan')<span class="badge badge-success">Surat balasan</span>@endif
                        <div class="small text-muted">{{ number_format($a->size / 1024, 1) }} KB &middot; {{ $a->created_at->format('d M Y H:i') }}</div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('letter.attachments.download', [$letter, $a]) }}">Buka</a>
                    <form method="post" action="{{ route('letter.attachments.destroy', [$letter, $a]) }}" onsubmit="return confirm('Hapus lampiran ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                </div>
            @empty
                <div class="text-muted text-center py-3">Belum ada lampiran</div>
            @endforelse
            <form method="post" action="{{ route('letter.attachments.store', $letter) }}" enctype="multipart/form-data" class="form-inline mt-3" style="gap:8px">
                @csrf
                <input type="file" name="file" class="form-control-file" required>
                <select name="kind" class="form-control form-control-sm"><option value="lampiran">Lampiran</option><option value="balasan">Surat balasan</option></select>
                <button class="btn btn-primary btn-sm">+ Tambah Lampiran</button>
            </form>
            @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

        @elseif ($tab === 'ttd')
            @if ($letter->signatures->isNotEmpty())
                <table class="table table-sm"><thead><tr><th>Penandatangan</th><th>Sign As</th><th>Status</th><th>Waktu</th><th></th></tr></thead><tbody>
                @foreach ($letter->signatures as $s)
                    <tr>
                        <td>{{ $s->signer->name ?? '-' }}</td><td>{{ $s->sign_as }}</td>
                        <td>@if ($s->signed_at)<span class="badge badge-success">Signed</span>@else<span class="badge badge-warning">Menunggu</span>@endif</td>
                        <td class="small text-muted">{{ $s->signed_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-right text-nowrap">
                            @if ($s->signer_id === $me || $isSuper)
                                @if ($s->signed_at)
                                    <form class="d-inline" method="post" action="{{ route('letter.signatures.unsign', [$letter, $s]) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Batalkan TTD</button></form>
                                @else
                                    <form class="d-inline" method="post" action="{{ route('letter.signatures.sign', [$letter, $s]) }}">@csrf<button class="btn btn-sm btn-primary">Tanda Tangani</button></form>
                                @endif
                            @endif
                            <form class="d-inline" method="post" action="{{ route('letter.signatures.destroy', [$letter, $s]) }}" onsubmit="return confirm('Hapus penandatangan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody></table>
            @else
                <div class="text-muted text-center py-3">Belum ada penandatangan. Tanda tangan bersifat opsional.</div>
            @endif
            <form method="post" action="{{ route('letter.signatures.store', $letter) }}" class="form-row mt-3">
                @csrf
                <div class="col-md-4"><select name="signer_id" class="form-control form-control-sm" required>
                    <option value="">- pilih user -</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select></div>
                <div class="col-md-4"><input name="sign_as" class="form-control form-control-sm" placeholder="Sign as (mis. Direktur)" maxlength="100" required></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary">+ Tambah</button></div>
            </form>
            @if ($errors->any())<div class="text-danger small mt-1">{{ $errors->first() }}</div>@endif

        @else
            @forelse ($letter->logs as $g)
                <div class="border-left pl-3 pb-3">
                    <b>{{ \App\Models\Letter::ACTION_LABELS[$g->action] ?? $g->action }}</b>
                    <div class="small text-muted">{{ $g->user->name ?? 'System' }} &middot; {{ $g->created_at->format('d M Y H:i') }}</div>
                    @if ($g->note)<div class="small">{{ $g->note }}</div>@endif
                </div>
            @empty
                <div class="text-muted text-center py-3">Belum ada log</div>
            @endforelse
        @endif

        </div>
    </div>
</div>

{{-- Konfirmasi aksi alur (dengan/ tanpa catatan) --}}
<div class="modal fade" id="modalAksi" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content" id="formAksi">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="aksiJudul"></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p id="aksiTeks" class="mb-2"></p>
                <textarea name="note" id="aksiNote" class="form-control" rows="3" placeholder="Wajib diisi"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Ya, lanjutkan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    $('#modalAksi').on('show.bs.modal', function (e) {
        var b = $(e.relatedTarget);
        $('#formAksi').attr('action', b.data('action'));
        $('#aksiJudul').text(b.data('title'));
        $('#aksiTeks').text(b.data('text'));
        var perluCatatan = String(b.data('note')) === '1';
        $('#aksiNote').toggle(perluCatatan).prop('required', perluCatatan).prop('disabled', !perluCatatan).val('');
    });

    document.addEventListener('click', function (e) {
        var del = e.target.closest('.js-del');
        if (del) del.closest('.js-row').remove();
    });

    function nextIndex(c) { return c.querySelectorAll('.js-row').length + Date.now() % 1000; }
    var am = document.getElementById('addMeta');
    if (am) am.addEventListener('click', function () {
        var c = document.getElementById('metaRows'), i = nextIndex(c), r = document.createElement('div');
        r.className = 'form-row mb-2 js-row';
        r.innerHTML = '<div class="col-4"><input name="meta[' + i + '][0]" class="form-control form-control-sm" placeholder="Label"></div>' +
            '<div class="col-7"><input name="meta[' + i + '][1]" class="form-control form-control-sm" placeholder="Isi"></div>' +
            '<div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></div>';
        c.appendChild(r);
    });
    var ai = document.getElementById('addItem');
    if (ai) ai.addEventListener('click', function () {
        var c = document.getElementById('itemRows'), i = nextIndex(c), r = document.createElement('tr');
        r.className = 'js-row';
        r.innerHTML = '<td><input name="items[' + i + '][desc]" class="form-control form-control-sm"></td>' +
            '<td><input name="items[' + i + '][price]" type="number" min="0" class="form-control form-control-sm text-right" value="0"></td>' +
            '<td><input name="items[' + i + '][qty]" type="number" step="any" min="0" class="form-control form-control-sm text-right" value="1"></td>' +
            '<td><input name="items[' + i + '][unit]" class="form-control form-control-sm" value="{{ $tpl['unit'] }}"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></td>';
        c.appendChild(r);
    });

    var type = document.querySelector('.js-letter-type');
    if (type) {
        var f = type.closest('form');
        var sync = function () {
            var c = f.querySelector('.js-wrap-customer'), e = f.querySelector('.js-wrap-employee');
            if (c) c.style.display = type.value === 'external' ? '' : 'none';
            if (e) e.style.display = type.value === 'internal' ? '' : 'none';
        };
        type.addEventListener('change', sync); sync();
    }
})();
</script>
@endpush

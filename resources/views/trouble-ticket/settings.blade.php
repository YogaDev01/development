@extends('layouts.app')

@section('title', 'Custom Rules Trouble Ticket')

@include('trouble-ticket._assets')

@section('content')
<div class="content tt-page">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="tt-title">Custom Rules</h1>
                <div class="tt-sub">Kebijakan SLA &amp; master Trouble Ticket. Target SLA disalin ke tiket saat tiket dibuat — mengubahnya tidak memengaruhi tiket lama.</div>
            </div>
            <a href="{{ route('trouble-ticket.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px;">{{ $errors->first() }}</div>
        @endif

        {{-- Setiap baris punya dua wujud: baris baca dan baris ubah (form).
             Tombol Ubah menukar keduanya di tempat — tanpa modal, tanpa
             halaman terpisah, dan nilai lama tetap terbaca sampai disimpan. --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header p-0">
                <ul class="nav nav-tabs ts-pill-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#stTabSla" role="tab"><i class="fas fa-stopwatch mr-1"></i> SLA</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stTabRca" role="tab"><i class="fas fa-sitemap mr-1"></i> RCA</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stTabSub" role="tab"><i class="fas fa-stream mr-1"></i> Sub-RCA</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stTabSev" role="tab"><i class="fas fa-exclamation-triangle mr-1"></i> Severity</a></li>
                </ul>
            </div>
            <div class="card-body tab-content">

                {{-- ================= SLA ================= --}}
                <div class="tab-pane fade show active" id="stTabSla" role="tabpanel">
                    @php
                        // Tiga ukuran SLA yang dipakai tiket: label, penjelasan, dan
                        // nilainya sekarang (menit). Sumbernya satu kebijakan — yang
                        // memang dipakai tiket baru.
                        $limitSla = [
                            ['response',   'Response time',   'Tiket dibuat → tiket dibaca teknisi', $slaPolicy?->response_minutes],
                            ['travel',     'Travel time',     'Mulai perjalanan → tiba di site',     $slaPolicy?->travel_minutes],
                            ['resolution', 'Resolution time', 'Mulai progres → tiket di-close',      $slaPolicy?->resolution_minutes],
                        ];
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-sm mb-0 tt-table">
                            <thead><tr><th>SLA</th><th style="width:170px;">Limit</th><th style="width:110px;"></th></tr></thead>
                            <tbody>
                                @foreach($limitSla as [$kode, $label, $rumus, $menit])
                                    <tr style="cursor:default;" data-st-read="sla-{{ $kode }}">
                                        <td>
                                            <strong>{{ $label }}</strong>
                                            <div class="text-muted" style="font-size:11.5px;">{{ $rumus }}</div>
                                        </td>
                                        <td>{{ $menit ? $menit . ' menit' : '0 — tidak terhingga' }}</td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-outline-secondary btn-xs" data-st-edit="sla-{{ $kode }}"><i class="fas fa-pen mr-1"></i> Ubah</button>
                                        </td>
                                    </tr>
                                    <tr data-st-form="sla-{{ $kode }}" hidden>
                                        <td colspan="3">
                                            <form method="POST" action="{{ route('trouble-ticket.settings.sla.limit') }}" class="st-form">
                                                @csrf
                                                <input type="hidden" name="metric" value="{{ $kode }}">
                                                <div class="form-row">
                                                    <div class="form-group col-lg-3 col-md-5 mb-2">
                                                        <label>Limit {{ $label }} (menit)</label>
                                                        <input type="number" name="minutes" value="{{ $menit }}" min="0" max="525600"
                                                               class="form-control form-control-sm" placeholder="0 = tidak terhingga">
                                                    </div>
                                                    <div class="form-group col-lg-3 col-md-5 mb-2 st-act d-flex" style="gap:6px;">
                                                        <button class="btn btn-success btn-sm flex-fill"><i class="fas fa-save mr-1"></i> Simpan</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" data-st-cancel="sla-{{ $kode }}">Batal</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted mt-2 mb-0" style="font-size:12px;">
                        Limit dalam menit. Isi <strong>0</strong> (atau kosongkan) berarti <strong>tidak terhingga</strong>:
                        durasinya tetap dicatat, tetapi tiket tidak pernah ditandai "Lewat SLA". Target disalin ke tiket
                        saat tiket dibuat, jadi mengubahnya di sini hanya berlaku untuk tiket yang dibuat sesudahnya.
                    </p>
                </div>

                {{-- ================= RCA ================= --}}
                <div class="tab-pane fade" id="stTabRca" role="tabpanel">
                    <form method="POST" action="{{ route('trouble-ticket.settings.rca.store') }}" class="st-form mb-3">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-lg-5 col-md-7 mb-2">
                                <label for="fldRcaName">Nama RCA</label>
                                <input type="text" name="name" id="fldRcaName" maxlength="60" required class="form-control form-control-sm" placeholder="Mis. POWER">
                            </div>
                            <div class="form-group col-lg-2 col-md-3 mb-2 st-act">
                                <button class="btn btn-success btn-sm btn-block"><i class="fas fa-plus mr-1"></i> Tambah</button>
                            </div>
                        </div>
                        <p class="text-muted mb-0" style="font-size:12px;">RCA tidak bisa dihapus — tiket lama menunjuk barisnya. Yang tidak dipakai lagi cukup dinonaktifkan; ia hilang dari pilihan form, tetapi tetap terbaca di tiket lama.</p>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0 tt-table">
                            <thead><tr><th>RCA</th><th>Sub-RCA</th><th>Status</th><th style="width:150px;"></th></tr></thead>
                            <tbody>
                                @forelse($rcas as $r)
                                    <tr style="cursor:default;" data-st-read="rca-{{ $r->id }}">
                                        <td><strong>{{ $r->name }}</strong></td>
                                        <td>{{ $subRcas->where('rca_id', $r->id)->count() }}</td>
                                        <td><x-status :status="$r->status" /></td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-outline-secondary btn-xs" data-st-edit="rca-{{ $r->id }}"><i class="fas fa-pen mr-1"></i> Ubah</button>
                                            <button type="submit" form="frmStRcaToggle{{ $r->id }}" class="btn btn-outline-secondary btn-xs">{{ $r->status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </td>
                                    </tr>
                                    <tr data-st-form="rca-{{ $r->id }}" hidden>
                                        <td colspan="4">
                                            <form method="POST" action="{{ route('trouble-ticket.settings.rca.update', $r) }}" class="st-form">
                                                @csrf
                                                <div class="form-row">
                                                    <div class="form-group col-lg-5 col-md-7 mb-2">
                                                        <label>Nama RCA</label>
                                                        <input type="text" name="name" value="{{ $r->name }}" maxlength="60" required class="form-control form-control-sm">
                                                    </div>
                                                    <div class="form-group col-lg-3 col-md-5 mb-2 st-act d-flex" style="gap:6px;">
                                                        <button class="btn btn-success btn-sm flex-fill"><i class="fas fa-save mr-1"></i> Simpan</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" data-st-cancel="rca-{{ $r->id }}">Batal</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-empty title="Belum ada RCA" message="Tambahkan kategori akar masalah lebih dulu." icon="fas fa-sitemap" compact /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @foreach($rcas as $r)
                        <form method="POST" action="{{ route('trouble-ticket.settings.rca.toggle', $r) }}" id="frmStRcaToggle{{ $r->id }}" class="d-none">@csrf</form>
                    @endforeach
                </div>

                {{-- ================= SUB-RCA ================= --}}
                <div class="tab-pane fade" id="stTabSub" role="tabpanel">
                    <form method="POST" action="{{ route('trouble-ticket.settings.sub-rca.store') }}" class="st-form mb-3">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-lg-3 col-md-5 mb-2">
                                <label for="fldSubRcaParent">RCA</label>
                                <select name="rca_id" id="fldSubRcaParent" class="form-control form-control-sm" required>
                                    <option value="">— Pilih RCA —</option>
                                    @foreach($rcas->where('status', 'ACTIVE') as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="form-group col-lg-5 col-md-7 mb-2">
                                <label for="fldSubRcaName">Nama Sub-RCA</label>
                                <input type="text" name="name" id="fldSubRcaName" maxlength="150" required class="form-control form-control-sm" placeholder="Mis. MODEM RUSAK">
                            </div>
                            <div class="form-group col-lg-2 col-md-3 mb-2 st-act">
                                <button class="btn btn-success btn-sm btn-block" @disabled($rcas->where('status', 'ACTIVE')->isEmpty())><i class="fas fa-plus mr-1"></i> Tambah</button>
                            </div>
                        </div>
                        <p class="text-muted mb-0" style="font-size:12px;">Satu Sub-RCA selalu milik satu RCA; namanya boleh sama asal RCA-nya berbeda.</p>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0 tt-table">
                            <thead><tr><th>RCA</th><th>Sub-RCA</th><th>Status</th><th style="width:150px;"></th></tr></thead>
                            <tbody>
                                @forelse($subRcas as $sr)
                                    <tr style="cursor:default;" data-st-read="sub-{{ $sr->id }}">
                                        <td>{{ $sr->rca?->name ?? '—' }}</td>
                                        <td><strong>{{ $sr->name }}</strong></td>
                                        <td><x-status :status="$sr->status" /></td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-outline-secondary btn-xs" data-st-edit="sub-{{ $sr->id }}"><i class="fas fa-pen mr-1"></i> Ubah</button>
                                            <button type="submit" form="frmStSubToggle{{ $sr->id }}" class="btn btn-outline-secondary btn-xs">{{ $sr->status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </td>
                                    </tr>
                                    <tr data-st-form="sub-{{ $sr->id }}" hidden>
                                        <td colspan="4">
                                            <form method="POST" action="{{ route('trouble-ticket.settings.sub-rca.update', $sr) }}" class="st-form">
                                                @csrf
                                                <div class="form-row">
                                                    <div class="form-group col-lg-3 col-md-5 mb-2">
                                                        <label>RCA</label>
                                                        <select name="rca_id" class="form-control form-control-sm" required>
                                                            @foreach($rcas as $r)<option value="{{ $r->id }}" @selected((int) $sr->rca_id === (int) $r->id)>{{ $r->name }}</option>@endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-lg-5 col-md-7 mb-2">
                                                        <label>Nama Sub-RCA</label>
                                                        <input type="text" name="name" value="{{ $sr->name }}" maxlength="150" required class="form-control form-control-sm">
                                                    </div>
                                                    <div class="form-group col-lg-3 col-md-5 mb-2 st-act d-flex" style="gap:6px;">
                                                        <button class="btn btn-success btn-sm flex-fill"><i class="fas fa-save mr-1"></i> Simpan</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" data-st-cancel="sub-{{ $sr->id }}">Batal</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-empty title="Belum ada Sub-RCA" message="Tambahkan turunan dari salah satu RCA." icon="fas fa-stream" compact /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @foreach($subRcas as $sr)
                        <form method="POST" action="{{ route('trouble-ticket.settings.sub-rca.toggle', $sr) }}" id="frmStSubToggle{{ $sr->id }}" class="d-none">@csrf</form>
                    @endforeach
                </div>

                {{-- ================= SEVERITY ================= --}}
                <div class="tab-pane fade" id="stTabSev" role="tabpanel">
                    <div class="alert alert-light border" style="font-size:12.5px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Severity tiket dihitung dari <strong>aging</strong>, dan tangganya diatur di sini lewat
                        kolom Aging. Selama belum ada satu pun severity aktif yang mengisi Aging dari,
                        dipakai tangga bawaan: 0 hari Rendah, 1–2 Minor, 3–7 Major, ≥8 Kritis.
                        Kosongkan <strong>Aging sampai</strong> untuk tingkat teratas (tanpa batas atas).
                        Mengubah <strong>kode</strong> ikut memperbarui tiket lama yang memakainya.
                    </div>
                    <form method="POST" action="{{ route('trouble-ticket.settings.severity.store') }}" class="st-form mb-3">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-lg-2 col-md-4 mb-2">
                                <label for="fldSevCode">Kode</label>
                                <input type="text" name="code" id="fldSevCode" maxlength="20" required class="form-control form-control-sm" placeholder="MAJOR">
                            </div>
                            <div class="form-group col-lg-3 col-md-5 mb-2">
                                <label for="fldSevName">Nama</label>
                                <input type="text" name="name" id="fldSevName" maxlength="50" required class="form-control form-control-sm" placeholder="Major">
                            </div>
                            <div class="form-group col-lg-1 col-md-3 mb-2">
                                <label for="fldSevLevel">Level</label>
                                <input type="number" name="level" id="fldSevLevel" min="1" max="9" value="1" required class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-lg-2 col-md-3 mb-2">
                                <label for="fldSevFrom">Aging dari</label>
                                <input type="number" name="aging_from" id="fldSevFrom" min="0" max="3650" class="form-control form-control-sm" placeholder="hari">
                            </div>
                            <div class="form-group col-lg-2 col-md-3 mb-2">
                                <label for="fldSevTo">Aging sampai</label>
                                <input type="number" name="aging_to" id="fldSevTo" min="0" max="3650" class="form-control form-control-sm" placeholder="kosong = tanpa batas">
                            </div>
                            <div class="form-group col-lg-2 col-md-3 mb-2 st-act">
                                <button class="btn btn-success btn-sm btn-block"><i class="fas fa-plus mr-1"></i> Tambah</button>
                            </div>
                        </div>
                        <p class="text-muted mb-0" style="font-size:12px;">Level 1 = paling parah; angka itu menentukan urutan dan warna di layar. Aging dihitung dalam hari sejak Date Open, kedua batas inklusif.</p>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0 tt-table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Level</th><th>Aging</th><th style="width:120px;">Status</th><th style="width:170px;"></th></tr></thead>
                            <tbody>
                                @forelse($severities as $sv)
                                    <tr style="cursor:default;" data-st-read="sev-{{ $sv->id }}">
                                        <td><code>{{ $sv->code }}</code></td>
                                        <td><span class="tt-sev tt-sev-{{ $sv->level }}">{{ $sv->name }}</span></td>
                                        <td>{{ $sv->level }}</td>
                                        <td>{{ $sv->aging_range_label }}</td>
                                        <td><x-status :status="$sv->status" /></td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-outline-secondary btn-xs" data-st-edit="sev-{{ $sv->id }}"><i class="fas fa-pen mr-1"></i> Ubah</button>
                                            <button type="submit" form="frmStSevToggle{{ $sv->id }}" class="btn btn-outline-secondary btn-xs">{{ $sv->status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </td>
                                    </tr>
                                    <tr data-st-form="sev-{{ $sv->id }}" hidden>
                                        <td colspan="6">
                                            <form method="POST" action="{{ route('trouble-ticket.settings.severity.update', $sv) }}" class="st-form">
                                                @csrf
                                                <div class="form-row">
                                                    <div class="form-group col-lg-2 col-md-4 mb-2">
                                                        <label>Kode</label>
                                                        <input type="text" name="code" value="{{ $sv->code }}" maxlength="20" required class="form-control form-control-sm">
                                                    </div>
                                                    <div class="form-group col-lg-3 col-md-5 mb-2">
                                                        <label>Nama</label>
                                                        <input type="text" name="name" value="{{ $sv->name }}" maxlength="50" required class="form-control form-control-sm">
                                                    </div>
                                                    <div class="form-group col-lg-1 col-md-3 mb-2">
                                                        <label>Level</label>
                                                        <input type="number" name="level" value="{{ $sv->level }}" min="1" max="9" required class="form-control form-control-sm">
                                                    </div>
                                                    <div class="form-group col-lg-2 col-md-3 mb-2">
                                                        <label>Aging dari</label>
                                                        <input type="number" name="aging_from" value="{{ $sv->aging_from }}" min="0" max="3650" class="form-control form-control-sm" placeholder="hari">
                                                    </div>
                                                    <div class="form-group col-lg-2 col-md-3 mb-2">
                                                        <label>Aging sampai</label>
                                                        <input type="number" name="aging_to" value="{{ $sv->aging_to }}" min="0" max="3650" class="form-control form-control-sm" placeholder="kosong = tanpa batas">
                                                    </div>
                                                    <div class="form-group col-lg-3 col-md-5 mb-2 st-act d-flex" style="gap:6px;">
                                                        <button class="btn btn-success btn-sm flex-fill"><i class="fas fa-save mr-1"></i> Simpan</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" data-st-cancel="sev-{{ $sv->id }}">Batal</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><x-empty title="Belum ada severity" icon="fas fa-exclamation-triangle" compact /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @foreach($severities as $sv)
                        <form method="POST" action="{{ route('trouble-ticket.settings.severity.toggle', $sv) }}" id="frmStSevToggle{{ $sv->id }}" class="d-none">@csrf</form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Form tambah/ubah di Pengaturan: satu pola untuk keempat tab.
   Labelnya kecil & huruf besar seperti filter tabel; tombol aksi disejajarkan
   dengan dasar kotak isian di barisnya (dulu form memakai .form-row +
   align-items-end langsung, sehingga kolom dengan teks bantuan membuat
   tingginya berbeda-beda dan tombolnya meleset). */
.st-form label { font-size:11px; color:var(--text-faint); text-transform:uppercase; margin-bottom:2px; }
.st-form .form-control, .st-form .btn { font-size:12.5px; }
.st-form .st-act { display:flex; align-items:flex-end; }
.st-form .st-act .btn { height:31px; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // Satu baris ubah terbuka pada satu waktu.
    function tutupSemua() {
        $('[data-st-form]').prop('hidden', true);
        $('[data-st-read]').prop('hidden', false);
    }
    $(document).on('click', '[data-st-edit]', function () {
        var key = this.dataset.stEdit;
        tutupSemua();
        $('[data-st-read="' + key + '"]').prop('hidden', true);
        $('[data-st-form="' + key + '"]').prop('hidden', false).find('input,select').first().trigger('focus');
    });
    $(document).on('click', '[data-st-cancel]', tutupSemua);
});
</script>
@endpush

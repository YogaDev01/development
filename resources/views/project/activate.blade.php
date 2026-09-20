@extends('layouts.app')

@section('title', 'Aktifkan PO')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Aktifkan PO</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $project->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $project->status_color }}">{{ $project->status_label }}</span>
                    </small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/project') }}">List PO</a></li>
                <li class="breadcrumb-item active">Aktifkan</li>
            </ol>
        </div>

        {{-- Alert revisi --}}
        @php
            $lastLog = $project->logs()->where('action', 'revised')->latest()->first();

            /* Nilai Kontrak (yang ditandatangani) vs Total Nilai PO (jumlah nominal
               No. PO yang dirilis customer). Keduanya boleh berbeda — tapi selisihnya
               harus disadari sebelum PO diaktifkan, jadi dihitung di satu tempat dan
               dipakai ulang oleh kartu Informasi PO, footer tabel No. PO, dan alert
               konfirmasi di atas tombol Aktifkan. */
            $nilaiKontrak = (int) ($project->contract ?? 0);
            $totalNilaiPo = (int) $project->poNumbers->sum('nominal');
            $selisihNilai = $nilaiKontrak - $totalNilaiPo;
            $adaSelisih   = $selisihNilai !== 0;
            $rp = fn ($v) => number_format((int) $v, 0, ',', '.');
        @endphp
        @if($lastLog)
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Catatan Revisi (Finance):</strong> {{ $lastLog->notes }}
        </div>
        @endif

        <form action="{{ url('/project/' . $project->id . '/activate') }}" method="POST"
              enctype="multipart/form-data" id="frmProject">
            @csrf
            @method('PUT')

            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                Halaman ini murni <strong>konfirmasi</strong> — cukup periksa data di bawah sudah benar, tambah No. PO
                kalau perlu, lalu Aktifkan. Scope of Work, Milestone, PIC, dan Lampiran tidak bisa diubah dari sini.
            </div>

            @if($errors->any())
            <div class="alert alert-danger">
                <strong>Terjadi kesalahan:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="card card-secondary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-contract mr-1"></i> Informasi PO</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Kode</label>
                            <div>{{ $project->code }}</div>
                        </div>
                        <div class="col-md-7 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Scope of Work</label>
                            <div>{{ $project->name }}</div>
                        </div>
                        @if(!Auth::user()->hasRole(['head']))
                        <div class="col-md-3 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Financing</label>
                            <div>
                                @if($project->financing)
                                    <span class="badge badge-{{ $project->financing_color }}">{{ $project->financing }} - {{ $project->financing_label }}</span>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        @endif
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Customer</label>
                            <div>{{ $project->customer->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Term of Payment</label>
                            <div>{{ $project->term_of_payment ?? '-' }}</div>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Nilai Kontrak</label>
                            <div>{{ $rp($nilaiKontrak) }}</div>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Total Nilai PO</label>
                            <div>{{ $rp($totalNilaiPo) }}</div>
                            <small class="text-muted">Jumlah nominal {{ $project->poNumbers->count() }} No. PO.</small>
                        </div>
                        <div class="col-md-4 form-group mb-2">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Selisih</label>
                            <div class="{{ $adaSelisih ? 'text-warning font-weight-bold' : 'text-success' }}">
                                {{ $selisihNilai < 0 ? '-' : '' }}{{ $rp(abs($selisihNilai)) }}
                            </div>
                            <small class="text-muted">Nilai Kontrak &minus; Total Nilai PO.</small>
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label class="font-weight-600 text-muted d-block" style="font-size:12px;">Notes</label>
                            <div>{{ $project->notes ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION PROJECT DETAIL (read-only) -->
            <div class="card card-secondary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i> Project & Milestone</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:4%;" class="text-center">No</th>
                                    <th scope="col" style="width:25%;">Project</th>
                                    <th scope="col" style="width:18%;">Milestone</th>
                                    <th scope="col" style="width:16%;">Plan Start</th>
                                    <th scope="col" style="width:16%;">Plan Finish</th>
                                    <th scope="col" style="width:16%;">Actual Start</th>
                                    <th scope="col">Actual Finish</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($project->details as $i => $det)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td>{{ $det->cluster->name ?? '-' }}</td>
                                    <td>{{ $det->stage->name ?? '-' }}</td>
                                    <td>{{ $det->plan_start?->format('d M Y') ?? '-' }}</td>
                                    <td>{{ $det->plan_end?->format('d M Y') ?? '-' }}</td>
                                    <td>{{ $det->actual_start?->format('d M Y') ?? '-' }}</td>
                                    <td>{{ $det->actual_end?->format('d M Y') ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada Milestone.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECTION PIC (read-only) -->
            <div class="card card-secondary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users mr-1"></i> PIC</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:4%;" class="text-center">No</th>
                                    <th scope="col" style="width:20%;">Tipe</th>
                                    <th scope="col" style="width:25%;">Nama PIC</th>
                                    <th scope="col" style="width:20%;">Jabatan / Posisi</th>
                                    <th scope="col" style="width:16%;">Whatsapp</th>
                                    <th scope="col">Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($project->pics as $i => $pic)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td><span class="badge badge-{{ $pic->type === 'internal' ? 'secondary' : 'primary' }}">{{ ucfirst($pic->type) }}</span></td>
                                    <td>{{ $pic->pic }}</td>
                                    <td>{{ $pic->position ?? '-' }}</td>
                                    <td>{{ $pic->phone ?? '-' }}</td>
                                    <td>{{ $pic->email ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada PIC.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- LAMPIRAN (read-only) -->
            @php $generalAtts = $project->attachments->where('type', 'general'); @endphp
            @if($generalAtts->count() > 0)
            <div class="card card-secondary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:80%;">Nama File</th>
                                    <th scope="col" style="width:15%;" class="text-center">Preview</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($generalAtts as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle"><small class="text-muted">{{ $att->title ?? $att->file_name }}</small></td>
                                    <td class="text-center align-middle">
                                        <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank"
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- DETAIL NO. PO -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Detail No. PO</h3>
                </div>
                <div class="card-body">
                    @if($project->poNumbers->isNotEmpty())
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:20%;">No. PO</th>
                                    <th scope="col" style="width:20%;">Nominal PO</th>
                                    <th scope="col" style="width:15%;">Tanggal Rilis</th>
                                    <th scope="col" style="width:15%;">Status</th>
                                    <th scope="col" style="width:25%;">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($project->poNumbers as $i => $p)
                                <tr>
                                    <td class="text-center">{{ $i + 1 }}</td>
                                    <td>{{ $p->po_number }}</td>
                                    <td>{{ $p->nominal_formatted }}</td>
                                    <td>{{ $p->release_date?->format('d M Y') ?? '-' }}</td>
                                    <td><span class="badge badge-{{ $p->status_color }}">{{ $p->status_label }}</span></td>
                                    <td>
                                        @forelse($p->attachments as $att)
                                            <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="d-block">
                                                <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? $att->file_name }}
                                            </a>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <th colspan="2" class="text-right">Subtotal</th>
                                    <th>{{ $rp($totalNilaiPo) }}</th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif

                    @if($project->poNumbers->isEmpty())
                    <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:13px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Belum ada No. PO — PO ini tidak bisa diaktifkan. Tambahkan minimal 1 No. PO dulu lewat halaman Edit.
                    </div>
                    @else
                    <small class="text-muted d-block mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        Halaman ini murni konfirmasi — kalau ada No. PO yang kurang/salah, tambah/perbaiki dulu lewat halaman Edit sebelum aktivasi.
                    </small>
                    @endif
                </div>
            </div>

            <!-- CATATAN AKTIVASI + ACTION -->
            <div class="card shadow-sm border-success">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-play-circle mr-1 text-success"></i> Aktivasi PO
                    </h3>
                </div>
                <div class="card-body">

                    <div class="form-group">
                        <label class="font-weight-600">Catatan Aktivasi (opsional)</label>
                        <textarea name="activation_notes" class="form-control" rows="2"
                                  placeholder="Catatan aktivasi...">{{ old('activation_notes') }}</textarea>
                        <small class="text-muted">Dicatat pada log perubahan status.</small>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size:13px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Konfirmasi ini akan mengubah status PO dari <strong>WAITING PO</strong> menjadi <strong>ACTIVE</strong>.
                        Tidak ada data yang ditambah/diubah dari halaman ini.
                    </div>

                    @if($adaSelisih && $project->poNumbers->isNotEmpty())
                    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:13px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Nilai Kontrak dan Total Nilai PO berbeda.</strong>
                        Kontrak {{ $rp($nilaiKontrak) }} vs Total PO {{ $rp($totalNilaiPo) }} &mdash;
                        selisih {{ $selisihNilai < 0 ? '-' : '' }}{{ $rp(abs($selisihNilai)) }}.
                        Pastikan ini memang disengaja (mis. PO bertahap); kalau bukan, perbaiki dulu lewat
                        halaman Edit sebelum diaktifkan.
                    </div>
                    @endif

                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/project') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-success" id="btnActivate" {{ $project->poNumbers->isEmpty() ? 'disabled' : '' }}>
                            <i class="fas fa-play mr-1"></i> Simpan &amp; Aktifkan
                        </button>
                    </div>

                </div>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-money')) {
            e.target.value = fmt(e.target.value);
        }
    });

    document.querySelectorAll('.inp-money').forEach(function (el) {
        el.value = fmt(el.value);
    });

    // Konfirmasi aktivasi
    var btnActivate = document.getElementById('btnActivate');
    if (btnActivate && typeof Swal !== 'undefined') {
        btnActivate.addEventListener('click', function (e) {
            var form = btnActivate.closest('form');

            e.preventDefault();

            Swal.fire({
                title: 'Simpan & Aktifkan PO?',
                text: 'Perubahan akan disimpan dan status PO berubah menjadi ACTIVE.',
                icon: 'question', showCancelButton: true,
                confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
                confirmButtonText: 'Ya, Aktifkan', cancelButtonText: 'Batal'
            }).then(function (r) {
                if (r.isConfirmed) form.submit();
            });
        });
    }

});
</script>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
table td { vertical-align:middle !important; }
</style>
@endpush
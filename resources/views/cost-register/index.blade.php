@extends('layouts.app')

@section('title', 'Cost Register')

@section('content')

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cost Register</h1>
                <small class="text-muted">Pencatatan manual Fixed Cost &amp; Overhead per No. PO — khusus Finance.</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cost Register</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Entri', $summary['total'], 'secondary', 'fas fa-list-ul'],
            ['Fixed Cost', number_format($summary['fixed'], 0, ',', '.'), 'warning', 'fas fa-lock'],
            ['Overhead', number_format($summary['overhead'], 0, ',', '.'), 'info', 'fas fa-layer-group'],
            ['Menunggu Treasury', $summary['submitted'] + $summary['queued'], 'primary', 'fas fa-hourglass-half'],
            ['Sudah Dibayar', number_format($summary['paid'], 0, ',', '.'), 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                <h3 class="card-title"><i class="fas fa-book mr-1"></i> Daftar Cost Register</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <a href="{{ route('cost-register.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Tambah Cost Register
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
                    <div class="toolbar-input" id="crSearch"></div>
                </div>

                {{-- Daftar dibatasi baris terbaru (Concerns\CapsLargeLists);
                     angka pada kartu ringkasan tetap dihitung dari SELURUH
                     data lewat query agregat, jadi ia tidak ikut terpotong. --}}
                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>@rupiah($maxRows)</strong> catatan terbaru dari total
                    <strong>@rupiah($total)</strong>.
                    Gunakan pencarian untuk mempersempit.
                </div>
                @endif

                <div class="table-responsive">
                    <table id="tblCostRegister" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="all">No. PO</th>
                                <th scope="col" class="text-nowrap fit-col">Tipe</th>
                                <th scope="col" class="text-right text-nowrap fit-col">Nominal</th>
                                <th scope="col" class="text-nowrap fit-col">Status</th>
                                <th scope="col" class="text-nowrap fit-col">Beneficiary</th>
                                <th scope="col" class="text-nowrap fit-col">Periode</th>
                                <th scope="col">Catatan</th>
                                <th scope="col" class="text-center text-nowrap fit-col">Lampiran</th>
                                <th scope="col" class="text-nowrap fit-col">Diinput Oleh</th>
                                <th scope="col" class="text-nowrap fit-col">Tanggal</th>
                                <th scope="col" class="text-center action-column fit-col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($registers as $cr)
                            <tr>
                                <td>
                                    <strong>{{ $cr->project->code ?? '-' }}</strong>
                                    <div style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                                        {{ $cr->project->customer->name ?? '-' }} — {{ $cr->project->name ?? '-' }}
                                    </div>
                                    @if($cr->batch_code)
                                    <span class="badge badge-light border" style="font-size:9px;" title="Bagian dari alokasi batch">
                                        <i class="fas fa-layer-group mr-1"></i>{{ $cr->batch_code }}
                                    </span>
                                    @endif
                                </td>
                                <td class="text-nowrap fit-col">
                                    <span class="badge badge-{{ $cr->type_color }} px-2 py-1">{{ $cr->type_label }}</span>
                                </td>
                                {{-- @rupiah: direktif terpusat (lihat AppServiceProvider). Modul ini
                                     dipakai sebagai acuan konversi; sisanya menyusul per modul,
                                     bukan diborong dalam satu commit. --}}
                                <td class="text-right fit-col"><strong>@rupiah($cr->amount)</strong></td>
                                <td class="text-nowrap fit-col">
                                    {{-- MN-09 — posisi baris ini di alur Non-CR menuju Treasury. --}}
                                    <span class="badge badge-{{ $cr->status_color }} px-2 py-1">{{ $cr->status }}</span>
                                </td>
                                <td class="text-nowrap fit-col">
                                    {{ $cr->beneficiary->nickname ?? '-' }}
                                </td>
                                <td class="text-nowrap fit-col">{{ $cr->period ? $cr->period->format('M Y') : '-' }}</td>
                                <td><small>{{ Str::limit($cr->notes, 80) }}</small></td>
                                <td class="text-center fit-col">
                                    @if($cr->attachments->isNotEmpty())
                                        @foreach($cr->attachments as $a)
                                        <a href="/storage/{{ $a->file_path }}" target="_blank" class="btn btn-info btn-sm mb-1" title="{{ $a->title }}">
                                            <i class="fas fa-paperclip"></i>
                                        </a>
                                        @endforeach
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $cr->inputBy->name ?? '-' }}</small></td>
                                <td class="text-nowrap fit-col">{{ $cr->created_at->format('d/m/Y') }}</td>
                                <td class="text-center action-column fit-col">
                                    <div class="action-buttons ts-actions">
                                        @if($cr->isDraft())
                                            {{-- Aksi utama saat DRAFT: mengajukannya ke
                                                 Treasury (MN-09). Menghapus jadi aksi
                                                 sekunder di menu. --}}
                                            <form action="{{ route('cost-register.submit', $cr->id) }}"
                                                  method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="ts-action-primary"
                                                        title="Ajukan ke Treasury" aria-label="Ajukan ke Treasury">
                                                    <i class="fas fa-paper-plane" aria-hidden="true"></i><span class="ts-al">Ajukan</span>
                                                </button>
                                            </form>
                                        @elseif($cr->isSubmitted())
                                            <span class="badge badge-warning px-2 py-1" title="Menunggu dimasukkan ke batch Treasury">
                                                <i class="fas fa-hourglass-half mr-1"></i>Di Treasury
                                            </span>
                                        @elseif($cr->status === 'QUEUED')
                                            <span class="badge badge-info px-2 py-1" title="Sudah masuk batch Treasury aktif">
                                                <i class="fas fa-layer-group mr-1"></i>Dalam Batch
                                            </span>
                                        @elseif($cr->isPaid())
                                            <span class="text-muted" style="font-size:11px;">
                                                {{ $cr->paid_at?->format('d/m/Y') }}
                                            </span>
                                        @endif

                                        @if($cr->isEditable())
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <form action="{{ route('cost-register.destroy', $cr->id) }}"
                                                  method="POST" class="frmDeleteCr">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11">
                                    <x-empty title="Belum ada Cost Register." icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
#tblCostRegister      { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; min-width:70px !important; width:1% !important; }
.fit-col             { white-space:nowrap !important; width:1% !important; }
#crSearch input      { width:220px; border-radius:var(--radius-sm); }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var table = $('#tblCostRegister').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [[7, 'desc']],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#tblCostRegister_filter').hide();
    $('#crSearch').html('<input type="search" id="crSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#crSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    $(document).on('submit', '.frmDeleteCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Cost Register?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#9a3030', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush

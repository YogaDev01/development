{{--
    Halaman uji Tesys UI.
    PENTING: markup di bawah sengaja ditulis dengan class Bootstrap 4 / AdminLTE
    PERSIS seperti blade lama (card, card-header, table table-bordered, badge-*,
    btn-*, form-control). Kalau halaman ini sudah tampil dengan desain baru,
    berarti seluruh blade existing juga akan ikut berubah hanya dengan mengganti
    @extends('layouts.app') menjadi @extends('ui.layouts.app').
--}}
@extends('ui.layouts.app')

@section('title', 'UI Preview')
@section('breadcrumb', 'Tesys UI / Preview')

@section('content')

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-vials mr-1"></i> Uji Compatibility — markup lama, tampilan baru</h3>
            <div class="card-tools">
                <button class="btn btn-secondary btn-sm"><i class="fas fa-sync"></i> Refresh</button>
                <button class="btn btn-info btn-sm"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah Data</button>
            </div>
        </div>
        <div class="card-body">

            <div class="form-group row">
                <div class="col-md-4">
                    <label>Cari :</label>
                    <input type="text" class="form-control" placeholder="Ketik kata kunci…">
                </div>
                <div class="col-md-3">
                    <label>Status</label>
                    <select class="form-control">
                        <option>Semua</option>
                        <option>Draft</option>
                        <option>Submitted</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Periode</label>
                    <input type="text" class="form-control" value="01 Sep 2026 – 16 Sep 2026" readonly>
                </div>
            </div>

            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>No</th><th>Code</th><th>Description</th>
                        <th class="text-right">Budget</th><th class="text-right">Used</th>
                        <th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><span class="badge badge-dark">BP-2026-014</span></td>
                        <td>Renovasi Kantor Cabang<br><small class="text-muted">PT Sumber Makmur · PM: Yoga A.</small></td>
                        <td class="text-right">Rp 2.100.000.000</td>
                        <td class="text-right">Rp 890.000.000</td>
                        <td><span class="badge badge-info px-2 py-1">CHECKED</span></td>
                        <td>
                            <button class="btn btn-warning btn-sm"><i class="fas fa-pen"></i></button>
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td><span class="badge badge-dark">BP-2026-013</span></td>
                        <td>Operasional Cabang Surabaya<br><small class="text-muted">CV Nusantara Jaya · PM: Sinta W.</small></td>
                        <td class="text-right">Rp 1.350.000.000</td>
                        <td class="text-right">Rp 1.100.000.000</td>
                        <td><span class="badge badge-success px-2 py-1">ACTIVE</span></td>
                        <td>
                            <button class="btn btn-warning btn-sm"><i class="fas fa-pen"></i></button>
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td><span class="badge badge-dark">BP-2026-012</span></td>
                        <td>Campaign Digital Q3<br><small class="text-muted">PT Cipta Boga · PM: Dinda P.</small></td>
                        <td class="text-right">Rp 640.000.000</td>
                        <td class="text-right">Rp 640.000.000</td>
                        <td><span class="badge badge-secondary px-2 py-1">CLOSED</span></td>
                        <td>
                            <button class="btn btn-info btn-sm"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="text-right">Rp 4.090.000.000</td>
                        <td class="text-right">Rp 2.630.000.000</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Semua varian badge</h3></div>
                <div class="card-body">
                    <span class="badge badge-secondary">Draft</span>
                    <span class="badge badge-warning">Submitted</span>
                    <span class="badge badge-info">Checked</span>
                    <span class="badge badge-primary">Verified</span>
                    <span class="badge badge-success">Approved</span>
                    <span class="badge badge-purple">Unpaid</span>
                    <span class="badge badge-indigo">Sent</span>
                    <span class="badge badge-danger">Rejected</span>
                    <span class="badge badge-dark">Closed</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Alert &amp; tombol</h3></div>
                <div class="card-body">
                    <div class="alert alert-success">Data berhasil disimpan.</div>
                    <div class="alert alert-warning">Periksa kembali isian yang ditandai.</div>
                    <button class="btn btn-primary">Primary</button>
                    <button class="btn btn-secondary">Secondary</button>
                    <button class="btn btn-danger">Danger</button>
                    <button class="btn btn-warning">Warning</button>
                    <button class="btn btn-info">Info</button>
                    <button class="btn btn-primary" disabled>Disabled</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Komponen baru (opsional, dipakai saat blade ditulis ulang)</h3></div>
        <div class="card-body">
            <div class="stat-row" style="margin-bottom:14px;">
                <div class="stat-card"><div class="stat-ic" style="background:var(--primary-bg);color:var(--primary);">📘</div><div><div class="stat-val num">14</div><div class="stat-lbl">Total Plan</div></div></div>
                <div class="stat-card"><div class="stat-ic" style="background:var(--success-bg);color:var(--success);">✔</div><div><div class="stat-val num">6</div><div class="stat-lbl">Active</div></div></div>
                <div class="stat-card"><div class="stat-ic" style="background:var(--warning-bg);color:var(--warning);">⏳</div><div><div class="stat-val num">3</div><div class="stat-lbl">Submitted</div></div></div>
            </div>

            <div class="stage-strip">
                <button type="button" class="stage-item"><div class="stage-num" style="background:var(--surface-2);color:var(--text-dim);">2</div><div class="stage-lbl">Draft</div></button>
                <button type="button" class="stage-item"><div class="stage-num" style="background:var(--warning-bg);color:var(--warning);">3</div><div class="stage-lbl">Submitted</div></button>
                <button type="button" class="stage-item"><div class="stage-num" style="background:var(--info-bg);color:var(--info);">1</div><div class="stage-lbl">Checked</div></button>
                <button type="button" class="stage-item"><div class="stage-num" style="background:var(--success-bg);color:var(--success);">6</div><div class="stage-lbl">Active</div></button>
                <button type="button" class="stage-item"><div class="stage-num" style="background:var(--darkbadge-bg);color:var(--darkbadge);">1</div><div class="stage-lbl">Closed</div></button>
            </div>

            <div style="margin-top:14px; display:flex; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="TesysUI.toast('success','Tersimpan','Contoh notifikasi toast gaya baru.')">Tes Toast</button>
                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#mdlUji">Tes Modal Bootstrap lama</button>
            </div>
        </div>
    </div>

    {{-- Modal Bootstrap 4 gaya lama — dibiarkan apa adanya, hanya di-restyle --}}
    <div class="modal fade" id="mdlUji" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modal bawaan Bootstrap</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    Modal ini memakai markup & JS Bootstrap 4 yang lama, tetapi tampilannya sudah mengikuti design system baru.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    </div>

@endsection

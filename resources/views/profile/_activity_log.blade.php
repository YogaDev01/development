{{--
    Activity Log — audit trail aktivitas bisnis user lintas module.

    Dipakai di:
      - profile/show.blade.php (log diri sendiri)
      - user/show.blade.php    (superadmin melihat log user lain)

    Variabel:
      $activityLogs    : LengthAwarePaginator UserActivityLog (page-name 'alog')
      $activityFilters : array filter aktif (module, action, date_from, date_to, q)

    Form filter submit GET ke URL halaman saat ini (request()->url()), jadi
    partial ini netral terhadap route pemanggil.
--}}
@php
    use App\Models\UserActivityLog;
    $activityFilters = $activityFilters ?? [];
@endphp

<p class="text-muted" style="font-size:12px;">
    Riwayat aktivitas di seluruh aplikasi: kapan, module apa, aksi apa, dan objek terkait.
</p>

<form method="GET" action="{{ request()->url() }}" class="form-row align-items-end mb-3" style="gap:6px 0;">
    {{-- Pertahankan anchor tab aktif agar tetap di tab Activity Log setelah submit --}}
    <div class="col-md-3 pr-2">
        <label class="font-weight-600" style="font-size:11px;">Module</label>
        <select name="module" class="form-control form-control-sm">
            <option value="">Semua Module</option>
            @foreach(UserActivityLog::MODULES as $key => $label)
                <option value="{{ $key }}" @selected(($activityFilters['module'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 pr-2">
        <label class="font-weight-600" style="font-size:11px;">Aksi</label>
        <select name="action" class="form-control form-control-sm">
            <option value="">Semua Aksi</option>
            @foreach(UserActivityLog::ACTIONS as $key => $label)
                <option value="{{ $key }}" @selected(($activityFilters['action'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 pr-2">
        <label class="font-weight-600" style="font-size:11px;">Dari Tanggal</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $activityFilters['date_from'] ?? '' }}">
    </div>
    <div class="col-md-2 pr-2">
        <label class="font-weight-600" style="font-size:11px;">Sampai Tanggal</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $activityFilters['date_to'] ?? '' }}">
    </div>
    <div class="col-md-3">
        <label class="font-weight-600" style="font-size:11px;">Kata Kunci</label>
        <div class="input-group input-group-sm">
            <input type="text" name="q" class="form-control" placeholder="Deskripsi / nomor dokumen" value="{{ $activityFilters['q'] ?? '' }}">
            <div class="input-group-append">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <a href="{{ request()->url() }}" class="btn btn-outline-secondary" title="Reset filter"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </div>
</form>

@if($activityLogs->isEmpty())
    <div class="text-center text-muted py-4">
        <i class="fas fa-stream d-block mb-2" style="font-size:22px;"></i>
        Belum ada aktivitas yang tercatat.
    </div>
@else
    @php $currentDate = null; @endphp
    <div style="max-height:560px;overflow-y:auto;">
        @foreach($activityLogs as $log)
            @php $logDate = $log->created_at?->format('d M Y'); @endphp
            @if($logDate !== $currentDate)
                @php $currentDate = $logDate; @endphp
                <div class="text-uppercase font-weight-bold text-muted mt-3 mb-1"
                     style="font-size:11px;letter-spacing:.5px;border-bottom:1px solid var(--border);padding-bottom:4px;">
                    {{ $logDate }}
                </div>
            @endif
            <div class="d-flex py-2" style="border-bottom:1px solid var(--border);">
                <div class="mr-3 text-center" style="width:52px;flex-shrink:0;">
                    <div style="font-size:12px;font-weight:700;color:var(--text);">{{ $log->created_at?->format('H:i') }}</div>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="mb-1">
                        <span class="badge badge-light border" style="font-size:10px;">{{ $log->module_label }}</span>
                        <span class="badge badge-{{ $log->action_color }}" style="font-size:10px;">{{ $log->action_label }}</span>
                    </div>
                    <div style="font-size:12.5px;color:var(--text);">{{ $log->description }}</div>
                    @if($log->notes)
                        <div style="font-size:11.5px;color:var(--text-dim);">
                            <i class="fas fa-sticky-note mr-1"></i>{{ $log->notes }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-3">
        {{ $activityLogs->links() }}
    </div>
@endif

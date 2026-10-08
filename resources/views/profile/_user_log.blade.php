{{--
    USER LOG — menggabungkan tiga riwayat dalam satu tab dengan sub-tab:
      1. Activity Log  (audit trail lintas module — profile/_activity_log)
      2. Request Log   (riwayat approval Cost Request — $requestLogs)
      3. Project Log   (aktivitas Project Monitoring — $projectLogs)

    Variabel dari ProfileController: $activityLogs, $activityFilters,
    $canSeeChanges, $requestLogs, $projectLogs.
--}}
<ul class="nav nav-pills mb-3" id="userLogTabs" role="tablist" style="gap:6px;">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="pill" href="#ulActivity" role="tab">
            <i class="fas fa-stream mr-1"></i> Activity Log
            <span class="badge badge-light border ml-1">{{ $activityLogs->total() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="pill" href="#ulRequest" role="tab">
            <i class="fas fa-file-invoice-dollar mr-1"></i> Request Log
            <span class="badge badge-light border ml-1">{{ $requestLogs->count() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="pill" href="#ulProject" role="tab">
            <i class="fas fa-binoculars mr-1"></i> Project Log
            <span class="badge badge-light border ml-1">{{ $projectLogs->count() }}</span>
        </a>
    </li>
</ul>

<div class="tab-content">

    {{-- SUB-TAB: ACTIVITY LOG --}}
    <div class="tab-pane fade show active" id="ulActivity" role="tabpanel">
        @include('profile._activity_log')
    </div>

    {{-- SUB-TAB: REQUEST LOG --}}
    <div class="tab-pane fade" id="ulRequest" role="tabpanel">
        <p class="text-muted" style="font-size:12px;">Riwayat pengajuan dan approval Cost Request yang Anda lakukan.</p>

        @if($requestLogs->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="fas fa-file-invoice-dollar d-block mb-2" style="font-size:22px;"></i>
            Belum ada aktivitas.
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                <thead class="bg-light">
                    <tr>
                        <th scope="col">Cost Request</th>
                        <th scope="col" style="width:20%;">Aksi</th>
                        <th scope="col">Catatan</th>
                        <th scope="col" style="width:15%;" class="text-center">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requestLogs as $log)
                    <tr>
                        <td>
                            @if($log->budgetRequest)
                            <a href="{{ route('budget-request.index') }}?open={{ $log->budgetRequest->id }}" target="_blank" rel="noopener" class="font-weight-bold">
                                {{ $log->budgetRequest->code }}
                            </a>
                            <div class="text-muted" style="font-size:11px;">{{ $log->budgetRequest->description }}</div>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td><span class="badge badge-{{ $log->action_color }}">{{ $log->action_label }}</span></td>
                        <td>{{ $log->notes ?: '-' }}</td>
                        <td class="text-center">{{ $log->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- SUB-TAB: PROJECT LOG --}}
    <div class="tab-pane fade" id="ulProject" role="tabpanel">
        <p class="text-muted" style="font-size:12px;">Aktivitas Anda di Project Monitoring (chat, update milestone, dll).</p>

        @if($projectLogs->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="fas fa-binoculars d-block mb-2" style="font-size:22px;"></i>
            Belum ada aktivitas.
        </div>
        @else
        <div style="max-height:480px;overflow-y:auto;">
            @foreach($projectLogs as $log)
            <div class="d-flex py-2" style="border-bottom:1px solid var(--border);">
                <div class="mr-3 text-center" style="width:28px;flex-shrink:0;">
                    <i class="fas {{ $log->type === 'SYSTEM' ? 'fa-cog text-secondary' : 'fa-comment text-primary' }}"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12.5px;">
                        <a href="{{ route('project-monitoring.show', $log->project_id) }}" target="_blank" rel="noopener" class="font-weight-bold">
                            {{ $log->project->name ?? '-' }}
                        </a>
                    </div>
                    <div style="font-size:12px;color:var(--text-dim);white-space:pre-line;">{{ $log->message }}</div>
                    <small class="text-muted">{{ $log->created_at->format('d M Y H:i') }}</small>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>

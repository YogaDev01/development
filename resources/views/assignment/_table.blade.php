{{-- Tabel daftar tugas. Variabel: $items (koleksi Assignment),
     $perspective ('assignee' = ditugaskan ke saya, 'assigner' = dibuat saya). --}}
@php $me = auth()->id(); $isSuper = auth()->user()?->hasRole('superadmin'); @endphp

@if ($items->isEmpty())
    <div class="text-center text-muted py-4" style="font-size:13px;">
        <i class="fas fa-inbox mr-1"></i>
        {{ $perspective === 'assignee' ? 'Belum ada tugas untuk Anda.' : 'Anda belum membuat tugas.' }}
    </div>
@else
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:13px;">
        <thead>
            <tr style="color:var(--text-faint);font-size:11px;text-transform:uppercase;">
                <th>Judul</th>
                <th style="width:90px;">Prioritas</th>
                <th style="width:110px;">Tenggat</th>
                <th style="width:150px;">{{ $perspective === 'assignee' ? 'Pemberi' : 'Penerima' }}</th>
                <th style="width:110px;">Status</th>
                <th style="width:1%;white-space:nowrap;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $a)
                @php
                    $canManage   = $isSuper || $me === $a->assigner_id;
                    $canComplete = $canManage || $me === $a->assignee_id;
                    $isOpen      = in_array($a->status, \App\Models\Assignment::OPEN_STATUSES, true);
                @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--text);">
                            {{ $a->title }}
                            @if ($a->attachments->isNotEmpty())
                                <i class="fas fa-paperclip text-muted ml-1" title="{{ $a->attachments->count() }} lampiran"></i>
                            @endif
                        </div>
                        @if ($a->description)
                            <div class="text-muted text-truncate" style="max-width:360px;font-size:12px;">{{ $a->description }}</div>
                        @endif
                    </td>
                    <td><span class="badge badge-{{ $a->priority_color }}">{{ $a->priority_label }}</span></td>
                    <td>
                        @if ($a->due_date)
                            <span class="{{ $a->is_overdue ? 'text-danger font-weight-bold' : '' }}">
                                {{ $a->due_date->format('d/m/Y') }}
                            </span>
                            @if ($a->is_overdue)<i class="fas fa-exclamation-circle text-danger ml-1" title="Lewat tenggat"></i>@endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $perspective === 'assignee' ? ($a->assigner->name ?? '—') : ($a->assignee->name ?? '—') }}</td>
                    <td><span class="badge badge-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-outline-secondary btn-xs btn-assignment-detail"
                                data-logs-url="{{ route('assignment.logs', $a) }}"
                                data-toggle="modal" data-target="#mdlAssignmentDetail" title="Detail & riwayat">
                            <i class="fas fa-info-circle"></i>
                        </button>

                        @if ($isOpen)
                            {{-- Mulai Dikerjakan: hanya penerima, hanya saat status READ --}}
                            @if ($me === $a->assignee_id && $a->status === 'READ')
                                <form method="POST" action="{{ route('assignment.start', $a) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-xs" title="Mulai dikerjakan">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </form>
                            @endif

                            @if ($canComplete)
                                <form method="POST" action="{{ route('assignment.done', $a) }}" class="d-inline form-assignment-confirm"
                                      data-title="Tandai tugas selesai?" data-text="Tugas &quot;{{ $a->title }}&quot; akan ditutup." data-confirm="Ya, Selesaikan">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-xs" title="Tandai selesai">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            @endif
                            @if ($canManage)
                                <button type="button" class="btn btn-outline-primary btn-xs btn-assignment-edit"
                                        data-toggle="modal" data-target="#mdlAssignmentEdit"
                                        data-update-url="{{ route('assignment.update', $a) }}"
                                        data-title="{{ $a->title }}"
                                        data-description="{{ $a->description }}"
                                        data-assignee-id="{{ $a->assignee_id }}"
                                        data-due-date="{{ $a->due_date?->format('Y-m-d') }}"
                                        data-priority="{{ $a->priority }}" title="Ubah">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('assignment.cancel', $a) }}" class="d-inline form-assignment-confirm"
                                      data-title="Batalkan tugas ini?" data-text="Tugas &quot;{{ $a->title }}&quot; akan dibatalkan." data-confirm="Ya, Batalkan" data-danger="1">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-xs" title="Batalkan">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            @endif
                        @elseif ($canManage)
                            <form method="POST" action="{{ route('assignment.reopen', $a) }}" class="d-inline form-assignment-confirm"
                                  data-title="Buka kembali tugas ini?" data-text="Status akan kembali ke Pending." data-confirm="Ya, Buka Kembali">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-xs" title="Buka kembali">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

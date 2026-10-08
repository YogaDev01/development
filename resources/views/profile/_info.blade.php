{{--
    Tab INFORMASI — read-only.
    Data akun yang tidak bisa diubah di sini + ringkasan seluruh data & log
    + changelog aplikasi. Perubahan data dilakukan di tab Settings.
--}}

{{-- RINGKASAN: kartu statistik lintas data & log --}}
<h6 class="font-weight-bold mb-2" style="font-size:13px;">Ringkasan Aktivitas</h6>
<div class="stat-row mb-4">
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--accent-tint);color:var(--accent-text);"><i class="fas fa-folder-open"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['projects'], 0, ',', '.') }}</div><div class="stat-lbl">PO Dapat Diakses</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--info-bg);color:var(--info);"><i class="fas fa-binoculars"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['project_log'], 0, ',', '.') }}</div><div class="stat-lbl">Project Log</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--success-bg);color:var(--success);"><i class="fas fa-file-invoice-dollar"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['request_log'], 0, ',', '.') }}</div><div class="stat-lbl">Request Log</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--purple-bg);color:var(--purple);"><i class="fas fa-stream"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['activity'], 0, ',', '.') }}</div><div class="stat-lbl">Activity Log</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--warning-bg);color:var(--warning);"><i class="fas fa-user-friends"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['teams'], 0, ',', '.') }}</div><div class="stat-lbl">Team Diikuti</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic" style="background:var(--danger-bg);color:var(--danger);"><i class="fas fa-bell"></i></div>
        <div><div class="stat-val num">{{ number_format($summary['unread'], 0, ',', '.') }}</div><div class="stat-lbl">Notifikasi Belum Dibaca</div></div>
    </div>
</div>

<div class="row">
    {{-- DATA AKUN (read-only) --}}
    <div class="col-lg-5 mb-3">
        <h6 class="font-weight-bold mb-2" style="font-size:13px;">Data Akun</h6>
        <div class="card" style="border:1px solid var(--border);border-radius:var(--radius-md);">
            <div class="card-body py-2">
                <div class="kv-row"><span>Nama</span><span class="font-weight-600">{{ $user->name }}</span></div>
                <div class="kv-row"><span>Username</span><span class="font-weight-600">{{ $user->username ?? '-' }}</span></div>
                <div class="kv-row"><span>Email</span><span class="font-weight-600">{{ $user->email }}</span></div>
                <div class="kv-row"><span>No. WhatsApp</span><span class="font-weight-600">{{ $user->phone ?: '-' }}</span></div>
                <div class="kv-row"><span>Role</span><span><span class="badge badge-warning">{{ Str::upper($user->role->name ?? '-') }}</span></span></div>
                <div class="kv-row"><span>Access Group</span><span class="font-weight-600">{{ $user->accessGroup->name ?? '-' }}</span></div>
                <div class="kv-row"><span>Status</span><span><span class="badge badge-{{ ($user->status ?? 'ACTIVE') === 'ACTIVE' ? 'success' : 'secondary' }}">{{ $user->status ?? 'ACTIVE' }}</span></span></div>
                <div class="kv-row"><span>Terdaftar Sejak</span><span class="font-weight-600">{{ $user->created_at?->format('d M Y') ?? '-' }}</span></div>
            </div>
        </div>
        <p class="text-muted mt-2 mb-0" style="font-size:11px;">
            <i class="fas fa-lock mr-1"></i> Ubah nama, nomor WhatsApp, atau password di tab <strong>Settings</strong>. Email hanya bisa diubah oleh Superadmin.
        </p>
    </div>

    {{-- CHANGELOG APLIKASI --}}
    <div class="col-lg-7 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="font-weight-bold mb-0" style="font-size:13px;">Changelog Aplikasi</h6>
            @if($changelogCurrent)
            <span class="badge badge-warning">v{{ $changelogCurrent }}</span>
            @endif
        </div>

        @if(empty($changelog))
        <div class="empty-state"><div class="ed">Belum ada riwayat rilis.</div></div>
        @else
        <div style="max-height:420px;overflow-y:auto;padding-right:4px;">
            @foreach(array_slice($changelog, 0, 8) as $rel)
            <div class="cl-item">
                <div class="cl-dot"></div>
                <div class="cl-body">
                    <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                        <span class="badge badge-light border">v{{ $rel['version'] ?? '-' }}</span>
                        <span class="font-weight-600" style="font-size:12.5px;">{{ $rel['title'] ?? '' }}</span>
                        <span class="text-muted" style="font-size:10.5px;">{{ $rel['date'] ?? '' }}</span>
                    </div>
                    @if(!empty($rel['summary']))
                    <div class="text-muted mt-1" style="font-size:11.5px;line-height:1.5;">{{ $rel['summary'] }}</div>
                    @endif
                    @foreach($rel['sections'] ?? [] as $sec)
                    <div class="mt-2">
                        <span class="cl-tag cl-tag-{{ $sec['type'] ?? 'added' }}">{{ $sec['label'] ?? ($sec['type'] ?? '') }}</span>
                        <ul class="cl-list">
                            @foreach($sec['items'] ?? [] as $it)
                            <li>{{ $it }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@php
    /*
     | Definisi menu sidebar Tesys UI.
     | - 'route'  : nama route Laravel. Kalau route belum ada, item otomatis di-skip
     |              (pakai Route::has), jadi aman dipasang bertahap.
     | - 'menu'   : (opsional) key untuk middleware 'menu:' yang sudah dipakai app.
     |              Dipakai di sini hanya untuk menyembunyikan item sesuai hak akses.
     | - 'icon'   : (opsional) nama ikon inline di ui.partials.icon
     */
    $navGroups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid'],
        ],
        'Finance' => [
            ['label' => 'Budgeting',              'route' => 'budget-plan.index',           'icon' => 'chart'],
            ['label' => 'Cost Request',           'route' => 'budget-request.index',        'icon' => 'file'],
            ['label' => 'Finance Summary',        'route' => 'finance-summary.index',       'icon' => 'bars'],
            ['label' => 'Invoice & Cash Receipt', 'route' => 'invoice-cash-receipt.index',  'icon' => 'receipt'],
            ['label' => 'Treasury',               'route' => 'daily-transaction.index',     'icon' => 'wallet'],
            ['label' => 'Cost Register',          'route' => 'cost-register.index',         'icon' => 'clipboard'],
            ['label' => 'Forecast',               'route' => 'forecast.index',              'icon' => 'trend'],
            ['label' => 'Surat Jalan',            'route' => 'delivery-note.index',         'icon' => 'truck'],
            ['label' => 'Reports',                'route' => 'reports.index',               'icon' => 'search'],
        ],
        'Project' => [
            ['label' => 'Purchase Order',     'route' => 'project.index',            'icon' => 'cart'],
            ['label' => 'Project Monitoring', 'route' => 'project-monitoring.index', 'icon' => 'pulse'],
        ],
        'Payroll' => [
            ['label' => 'Payroll Run', 'route' => 'payroll-run.index', 'icon' => 'users'],
            ['label' => 'Karyawan',    'route' => 'employee.index',    'icon' => 'user'],
        ],
        'Master Data' => [
            ['label' => 'Customer',       'route' => 'customer.index'],
            ['label' => 'Bank',           'route' => 'bank.index'],
            ['label' => 'Beneficiary',    'route' => 'beneficiaries.index'],
            ['label' => 'Source Fund',    'route' => 'source-fund.index'],
            ['label' => 'Cost Center',    'route' => 'cost-center.index'],
            ['label' => 'Account',        'route' => 'account.index'],
            ['label' => 'Project',        'route' => 'cluster.index'],
            ['label' => 'Business Group', 'route' => 'groups.index'],
            ['label' => 'PIC',            'route' => 'master-pic.index'],
            ['label' => 'Department',     'route' => 'department.index'],
            ['label' => 'Team',           'route' => 'team.index'],
            ['label' => 'Milestone',      'route' => 'stage.index'],
        ],
        'Administrasi' => [
            ['label' => 'User',         'route' => 'user.index',        'icon' => 'users'],
            ['label' => 'Role',         'route' => 'role.index'],
            ['label' => 'Access Group', 'route' => 'accessgroup.index'],
            ['label' => 'Todo List',    'route' => 'todo-list.index',   'icon' => 'list'],
            ['label' => 'Feedback',     'route' => 'feedback.index'],
        ],
    ];

    $currentUser = auth()->user();
    $initials = collect(explode(' ', (string) ($currentUser?->name ?? 'User')))
        ->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->implode('');
@endphp

<div class="sidebar">
    <div class="brand">
        <div class="brand-mark">T</div>
        <div>
            <div class="brand-name">Tesys</div>
            <div class="brand-sub">Finance &amp; Ops Console</div>
        </div>
    </div>

    <nav class="nav">
        @foreach ($navGroups as $groupLabel => $items)
            @php
                $visible = collect($items)->filter(fn ($i) => Route::has($i['route']));
            @endphp
            @if ($visible->isNotEmpty())
                <div class="nav-label">{{ $groupLabel }}</div>
                @foreach ($visible as $item)
                    @php
                        $isActive = request()->routeIs($item['route'])
                            || request()->routeIs(Str::beforeLast($item['route'], '.index') . '.*');
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="nav-item {{ isset($item['icon']) ? '' : 'noicon' }} {{ $isActive ? 'active' : '' }}"
                       style="text-decoration:none;">
                        @isset($item['icon'])
                            @include('ui.partials.icon', ['name' => $item['icon']])
                        @endisset
                        {{ $item['label'] }}
                    </a>
                @endforeach
            @endif
        @endforeach
    </nav>

    <div class="sidebar-foot">
        {{-- Dropdown akun: shortcut Profile + Logout --}}
        <div class="user-pop" id="userPop">
            <div class="user-pop-head">
                <div class="n">{{ $currentUser?->name ?? 'Pengguna' }}</div>
                <div class="e">{{ $currentUser?->email ?? '' }}</div>
            </div>
            <div class="user-pop-sep"></div>

            @if (Route::has('profile.show'))
                <a href="{{ route('profile.show') }}" class="user-pop-item" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    Profile Saya
                </a>
            @endif

            @if (Route::has('panduan.index'))
                <a href="{{ route('panduan.index') }}" class="user-pop-item" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 113.5 2.3c-.7.3-1 .9-1 1.7"/><path d="M12 17h.01"/></svg>
                    Panduan
                </a>
            @endif

            <div class="user-pop-sep"></div>

            <button type="button" class="user-pop-item danger" id="menuLogout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
                Logout
            </button>
        </div>

        <button type="button" class="user-btn" id="userBtn">
            <div class="avatar">
                @if (!empty($currentUser?->avatar))
                    <img src="{{ asset('storage/' . $currentUser->avatar) }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                @else
                    {{ $initials ?: 'U' }}
                @endif
            </div>
            <div style="min-width:0;">
                <div class="who-name">{{ Str::limit($currentUser?->name ?? 'Pengguna', 18) }}</div>
                <div class="who-role">{{ $currentUser?->role?->name ?? '—' }}</div>
            </div>
            <svg class="caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 15l6-6 6 6"/></svg>
        </button>
    </div>
</div>

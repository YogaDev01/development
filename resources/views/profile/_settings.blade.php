{{--
    Tab SETTINGS — pengaturan akun & preferensi.
    Sub-seksi: Profil & Keamanan · Preferensi Tampilan · Preferensi Notifikasi
    · View As (superadmin). Preferensi tampilan (tema & warna aksen) disimpan
    di localStorage per-perangkat, sama seperti pengalih tema di topbar.
--}}
@php
    $prefs    = $user->notification_prefs ?? [];
    $waOn     = ($prefs['wa_enabled'] ?? true) !== false;
    $rem      = $user->todoReminderPrefs();
@endphp

<div class="set-wrap">

    {{-- SUB-NAV --}}
    <div class="set-nav">
        <a href="#setProfil" class="set-nav-item active" data-set-target="setProfil"><i class="fas fa-user-shield mr-2"></i>Profil &amp; Keamanan</a>
        <a href="#setTampilan" class="set-nav-item" data-set-target="setTampilan"><i class="fas fa-palette mr-2"></i>Preferensi Tampilan</a>
        <a href="#setNotif" class="set-nav-item" data-set-target="setNotif"><i class="fas fa-bell mr-2"></i>Preferensi Notifikasi</a>
        @if($user->isRealSuperadmin())
        <a href="#setViewAs" class="set-nav-item" data-set-target="setViewAs"><i class="fas fa-user-secret mr-2"></i>View As</a>
        @endif
    </div>

    <div class="set-body">

        {{-- ============ PROFIL & KEAMANAN ============ --}}
        <section id="setProfil" class="set-section active">
            <h6 class="font-weight-bold mb-3">Profil &amp; Keamanan</h6>
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-600">Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-600">No. WhatsApp</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
                            @error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-600">Email</label>
                            <input type="text" class="form-control" value="{{ $user->email }}" disabled>
                            <small class="text-muted">Hubungi Superadmin untuk mengubah email.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label class="font-weight-600">App Default</label>
                            <select name="default_app" class="form-control @error('default_app') is-invalid @enderror">
                                @foreach($apps as $key => $cfg)
                                <option value="{{ $key }}" {{ $user->defaultApp() === $key ? 'selected' : '' }}>
                                    {{ $cfg['label'] }}{{ ($cfg['ready'] ?? false) ? '' : ' (Segera Hadir)' }}
                                </option>
                                @endforeach
                            </select>
                            @error('default_app')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            <small class="text-muted">Menu/sidebar yang muncul saat Anda login.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>

            <hr>

            <h6 class="font-weight-bold mb-1">Keamanan</h6>
            <p class="text-muted" style="font-size:12px;">Penggantian password diverifikasi lewat kode yang dikirim ke email terdaftar Anda.</p>
            <div class="set-card">
                <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
                    <div>
                        <div class="font-weight-600"><i class="fas fa-key mr-1 text-muted"></i> Password</div>
                        <small class="text-muted">Minimal 10 karakter, kombinasi huruf &amp; angka.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modalUbahPassword">
                        <i class="fas fa-lock mr-1"></i> Ubah Password
                    </button>
                </div>
            </div>

            {{-- Info sesi login (read-only) --}}
            <div class="set-card mt-3">
                <div class="d-flex flex-wrap" style="gap:24px;">
                    <div><div class="stat-lbl">Sesi Login Aktif</div><div class="font-weight-bold">{{ $sessionInfo['active'] ?? 0 }}</div></div>
                    <div><div class="stat-lbl">Aktivitas Terakhir</div><div class="font-weight-bold">{{ !empty($sessionInfo['last']) ? \Carbon\Carbon::createFromTimestamp($sessionInfo['last'])->format('d M Y H:i') : '-' }}</div></div>
                </div>
            </div>
        </section>

        {{-- ============ PREFERENSI TAMPILAN ============ --}}
        <section id="setTampilan" class="set-section">
            <h6 class="font-weight-bold mb-3">Preferensi Tampilan</h6>

            <div class="form-group">
                <label class="font-weight-600 d-block mb-2">Mode Tema</label>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-theme-set="light"><i class="fas fa-sun mr-1"></i> Terang</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-theme-set="dark"><i class="fas fa-moon mr-1"></i> Gelap</button>
                </div>
                <small class="text-muted d-block mt-1">Pilihan tema tersimpan di perangkat ini.</small>
            </div>

            <hr>

            <div class="form-group mb-0">
                <label class="font-weight-600 d-block mb-1">Warna Aksen Aplikasi</label>
                <small class="text-muted d-block mb-2">Warna aksi utama (tombol, badge, penanda aktif). Default: Burgundy TESYS.</small>

                <div class="accent-swatches mb-3">
                    @foreach([
                        ['name'=>'Burgundy','hex'=>'#92232b'],
                        ['name'=>'Navy','hex'=>'#1f3a5f'],
                        ['name'=>'Teal','hex'=>'#0f6e66'],
                        ['name'=>'Forest','hex'=>'#2f6d43'],
                        ['name'=>'Indigo','hex'=>'#3d3a8c'],
                        ['name'=>'Slate','hex'=>'#3f4a59'],
                        ['name'=>'Rust','hex'=>'#9a4a23'],
                        ['name'=>'Plum','hex'=>'#6a2a63'],
                    ] as $sw)
                    <button type="button" class="accent-swatch" data-accent="{{ $sw['hex'] }}" title="{{ $sw['name'] }}"
                            style="background:{{ $sw['hex'] }};"><i class="fas fa-check"></i></button>
                    @endforeach
                </div>

                <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
                    <label class="mb-0 d-flex align-items-center" style="gap:8px;font-size:12.5px;">
                        Warna kustom:
                        <input type="color" id="accentCustom" value="#92232b" style="width:44px;height:30px;padding:0;border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;">
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="accentReset">
                        <i class="fas fa-undo mr-1"></i> Reset ke Default
                    </button>
                </div>
            </div>
        </section>

        {{-- ============ PREFERENSI NOTIFIKASI ============ --}}
        <section id="setNotif" class="set-section">
            <h6 class="font-weight-bold mb-1">Preferensi Notifikasi</h6>
            <p class="text-muted" style="font-size:12px;">Satu-satunya notifikasi yang bisa Anda atur: <strong>Reminder To-Do List</strong>. Bila Anda punya tugas (approval, verifikasi, realisasi, dll.) yang belum diproses, sistem mengirim pengingat sesuai jadwal di bawah. Jika tidak ada tugas, tidak ada pengingat.</p>

            <form action="{{ route('profile.notifications') }}" method="POST">
                @csrf

                <div class="custom-control custom-switch mb-3">
                    <input type="checkbox" class="custom-control-input" id="reminder_enabled" name="reminder_enabled" value="1" {{ $rem['enabled'] ? 'checked' : '' }}>
                    <label class="custom-control-label font-weight-600" for="reminder_enabled">Aktifkan Reminder To-Do List</label>
                    <small class="text-muted d-block">Pengingat lonceng in-app dikirim saat masih ada item di To-Do List Anda.</small>
                </div>

                <div id="reminderOptions" class="set-card mb-3" style="{{ $rem['enabled'] ? '' : 'display:none;' }}">
                    <label class="font-weight-600 d-block mb-2">Jadwal Pengingat</label>

                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="mode_fixed" name="reminder_mode" value="fixed" {{ $rem['mode'] === 'fixed' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="mode_fixed">Pada jam tertentu setiap hari</label>
                    </div>
                    <div id="fixedHours" class="mb-3 pl-4" style="{{ $rem['mode'] === 'fixed' ? '' : 'display:none;' }}">
                        <small class="text-muted d-block mb-2">Centang jam pengiriman (bisa lebih dari satu):</small>
                        <div class="d-flex flex-wrap" style="gap:6px 14px;">
                            @for($h = 0; $h < 24; $h++)
                            <label class="mb-0 d-inline-flex align-items-center" style="gap:4px;font-size:12px;width:56px;">
                                <input type="checkbox" name="reminder_hours[]" value="{{ $h }}" {{ in_array($h, $rem['hours'], true) ? 'checked' : '' }}>
                                {{ sprintf('%02d:00', $h) }}
                            </label>
                            @endfor
                        </div>
                    </div>

                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="mode_interval" name="reminder_mode" value="interval" {{ $rem['mode'] === 'interval' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="mode_interval">Setiap beberapa jam sekali</label>
                    </div>
                    <div id="intervalBox" class="mb-2 pl-4" style="{{ $rem['mode'] === 'interval' ? '' : 'display:none;' }}">
                        <div class="d-flex align-items-center" style="gap:8px;font-size:12.5px;">
                            Setiap
                            <select name="reminder_interval" class="form-control form-control-sm" style="width:80px;">
                                @foreach([1,2,3,4,6,8,12,24] as $n)
                                <option value="{{ $n }}" {{ (int)$rem['interval_hours'] === $n ? 'selected' : '' }}>{{ $n }}</option>
                                @endforeach
                            </select>
                            jam sekali
                        </div>
                        <small class="text-muted d-block mt-1">Dihitung dari jam 00:00 (mis. tiap 3 jam = 00:00, 03:00, 06:00, …).</small>
                    </div>

                    <hr class="my-2">

                    <div class="custom-control custom-switch mb-1">
                        <input type="checkbox" class="custom-control-input" id="reminder_whatsapp" name="reminder_whatsapp" value="1" {{ $rem['whatsapp'] ? 'checked' : '' }}>
                        <label class="custom-control-label" for="reminder_whatsapp">Kirim juga lewat WhatsApp</label>
                        <small class="text-muted d-block">Hanya jika nomor WhatsApp terdaftar & master switch WhatsApp di bawah aktif.</small>
                    </div>
                </div>

                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="wa_enabled" name="wa_enabled" value="1" {{ $waOn ? 'checked' : '' }}>
                    <label class="custom-control-label font-weight-600" for="wa_enabled">Terima notifikasi WhatsApp</label>
                    <small class="text-muted d-block">Master switch WhatsApp. Mematikan ini membungkam SEMUA WhatsApp (termasuk reminder); lonceng in-app tetap aktif.</small>
                </div>

                <div class="d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Preferensi</button>
                </div>
            </form>
        </section>

        {{-- ============ VIEW AS (superadmin) ============ --}}
        @if($user->isRealSuperadmin())
        <section id="setViewAs" class="set-section">
            <h6 class="font-weight-bold mb-1">View As — Pratinjau Role</h6>
            <p class="text-muted" style="font-size:12px;">Lihat tampilan &amp; menu aplikasi seolah Anda memegang role lain (pratinjau, tidak mengubah role asli). Juga tersedia dari menu akun di pojok kanan atas.</p>

            @if($viewAsActive)
            <div class="alert alert-warning py-2" style="font-size:12.5px;">
                <i class="fas fa-eye mr-1"></i> Sedang melihat sebagai role lain.
                <form action="{{ route('view-as.reset') }}" method="POST" class="d-inline ml-2">@csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark py-0"><i class="fas fa-undo mr-1"></i>Kembali ke Superadmin</button>
                </form>
            </div>
            @endif

            <form action="{{ route('view-as') }}" method="POST">
                @csrf
                <div class="d-flex flex-wrap" style="gap:8px;">
                    @foreach($viewAsRoles as $r)
                    <button type="submit" name="role_id" value="{{ $r->id }}"
                            class="btn btn-sm {{ (int)$viewAsActive === (int)$r->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ Str::upper($r->name) }}
                        @if((int)$viewAsActive === (int)$r->id)<i class="fas fa-check ml-1"></i>@endif
                    </button>
                    @endforeach
                </div>
            </form>
        </section>
        @endif

    </div>
</div>

{{-- ============ MODAL UBAH PASSWORD (verifikasi kode email) ============ --}}
<div class="modal fade" id="modalUbahPassword" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title font-weight-bold"><i class="fas fa-lock mr-1"></i> Ubah Password</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <form action="{{ route('profile.password') }}" method="POST" id="frmUbahPassword">
                @csrf
                <div class="modal-body">
                    <p class="text-muted" style="font-size:12px;">
                        Demi keamanan, penggantian password diverifikasi dengan kode yang dikirim ke email Anda.
                        Klik <strong>Kirim Kode</strong>, lalu masukkan kode beserta password baru.
                    </p>

                    <div class="form-group">
                        <label class="font-weight-600">Kode Verifikasi</label>
                        <div class="input-group">
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" placeholder="6 digit kode dari email">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btnKirimKode">Kirim Kode</button>
                            </div>
                        </div>
                        @error('code')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        <small class="text-muted d-block" id="kodeStatus"></small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Password Baru</label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password')<small class="text-danger d-block">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Alert saat MENGAKTIFKAN notifikasi WhatsApp — ingatkan agar chat nomor
// system dulu supaya tidak terindikasi spam.
(function () {
    var wa = document.getElementById('wa_enabled');
    if (!wa) return;
    wa.addEventListener('change', function () {
        if (!this.checked) return;
        var pesan = 'Pastikan chat nomor system terlebih dahulu di 62822-7770-0572 agar tidak terindikasi spam.';
        if (window.Swal) {
            Swal.fire({ icon: 'info', title: 'Aktifkan Notifikasi WhatsApp', text: pesan, confirmButtonText: 'Mengerti' });
        } else {
            alert(pesan);
        }
    });
})();

// Reminder To-Do List — tampilkan/sembunyikan opsi sesuai status aktif & mode.
(function () {
    var en = document.getElementById('reminder_enabled');
    if (!en) return;
    var opts = document.getElementById('reminderOptions');
    var fixedBox = document.getElementById('fixedHours');
    var intBox = document.getElementById('intervalBox');
    var mFixed = document.getElementById('mode_fixed');
    var mInt = document.getElementById('mode_interval');

    function syncEnabled() { opts.style.display = en.checked ? '' : 'none'; }
    function syncMode() {
        fixedBox.style.display = (mFixed && mFixed.checked) ? '' : 'none';
        intBox.style.display = (mInt && mInt.checked) ? '' : 'none';
    }
    en.addEventListener('change', syncEnabled);
    if (mFixed) mFixed.addEventListener('change', syncMode);
    if (mInt) mInt.addEventListener('change', syncMode);
    syncEnabled(); syncMode();
})();

(function () {
    // Buka tab Settings + modal otomatis jika ada error validasi ganti password.
    @if($errors->has('code') || $errors->has('password'))
    if (window.jQuery) {
        var t = document.querySelector('a[href="#paneSettings"]');
        if (t) window.jQuery(t).tab('show');
        document.querySelectorAll('.set-nav-item').forEach(function (x){ x.classList.remove('active'); });
        document.querySelectorAll('.set-section').forEach(function (s){ s.classList.toggle('active', s.id === 'setProfil'); });
        var pn = document.querySelector('.set-nav-item[data-set-target="setProfil"]'); if (pn) pn.classList.add('active');
        window.jQuery('#modalUbahPassword').modal('show');
    }
    @endif

    var btn = document.getElementById('btnKirimKode');
    var status = document.getElementById('kodeStatus');
    if (!btn) return;

    var cooldown = 0, timer = null;
    function tick() {
        if (cooldown <= 0) { btn.disabled = false; btn.textContent = 'Kirim Kode'; clearInterval(timer); return; }
        btn.disabled = true; btn.textContent = 'Kirim ulang (' + cooldown + ')'; cooldown--;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true; btn.textContent = 'Mengirim…';
        status.className = 'text-muted d-block'; status.textContent = '';
        fetch('{{ route('profile.password.token') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        }).then(function (r) { return r.json().then(function (d){ return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            if (res.ok && res.d.ok) {
                status.className = 'text-success d-block'; status.textContent = res.d.message || 'Kode terkirim.';
                cooldown = 60; timer = setInterval(tick, 1000); tick();
            } else {
                status.className = 'text-danger d-block'; status.textContent = (res.d && res.d.message) || 'Gagal mengirim kode.';
                btn.disabled = false; btn.textContent = 'Kirim Kode';
            }
          }).catch(function () {
            status.className = 'text-danger d-block'; status.textContent = 'Gagal menghubungi server.';
            btn.disabled = false; btn.textContent = 'Kirim Kode';
          });
    });
})();
</script>
@endpush

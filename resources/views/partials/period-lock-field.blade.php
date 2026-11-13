{{-- Kunci periode (App\Support\PeriodLock): hanya Superadmin asli (bukan View As).
     Isi hanya bila tanggal dokumen jatuh di periode Closing yang sudah CLOSED;
     controller menolak tanpa alasan ini dan mencatat penembusannya di log. --}}
@if(\App\Support\PeriodLock::canOverride())
<div class="form-group {{ $class ?? '' }}">
    <label class="small font-weight-bold">Alasan menembus periode tertutup <span class="text-muted font-weight-normal">(isi bila tanggal di periode yang sudah ditutup)</span></label>
    <input type="text" name="alasan_periode" class="form-control form-control-sm" maxlength="255" autocomplete="off">
</div>
@endif

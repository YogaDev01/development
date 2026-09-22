{{--
    Disclaimer standar General Feedback — dipakai di index/create/show/inbox.
    Props: $compact (bool, default false) — versi ringkas untuk halaman detail.
--}}
@php $compact = $compact ?? false; @endphp
<div class="alert alert-light border feedback-disclaimer {{ $compact ? 'py-2' : '' }}" style="font-size:{{ $compact ? '11px' : '12.5px' }};">
    <i class="fas fa-lock mr-1 text-muted"></i>
    <strong>Rahasia &amp; Tidak Memengaruhi Karir Anda.</strong>
    Modul ini adalah kanal resmi bagi seluruh karyawan untuk menyampaikan kritik, saran, ide, concern, atau masukan
    langsung kepada CEO. Seluruh submission bersifat rahasia dan hanya dapat diakses oleh pihak berwenang.
    <strong>Submission di sini TIDAK memengaruhi KPI, penilaian kinerja, performance appraisal, maupun karir Anda</strong>
    dengan cara apa pun — modul ini terpisah total dari sistem KPI/Performance Management, dan semata digunakan untuk
    meningkatkan komunikasi, transparansi, dan perbaikan organisasi.
</div>

{{--
    Disclaimer standar General Feedback — kartu panduan/privasi (F4-b), dipakai di create/show.
    Props: $compact (bool, default false) — versi ringkas (satu paragraf, tanpa judul besar) untuk halaman detail.
    Teks hukum tidak diubah; hanya dipecah menjadi dua paragraf. Penanda `.feedback-disclaimer` dipertahankan
    untuk pemeriksaan QA. Gaya Tailwind (prefix tw:) — halaman pemakai yang memuat tesys-tw.css.
--}}
@php $compact = $compact ?? false; @endphp
<div role="note" class="feedback-disclaimer tw:bg-surface tw:text-text tw:border tw:border-border tw:rounded-md tw:shadow-xs">
    <div class="tw:flex tw:items-center tw:gap-3 tw:border-b tw:border-border {{ $compact ? 'tw:px-card-sm tw:py-2' : 'tw:px-card tw:py-3' }}">
        <span class="tw:flex tw:h-8 tw:w-8 tw:shrink-0 tw:items-center tw:justify-center tw:rounded-md tw:bg-accent-tint tw:text-accent-text">
            <i class="fas fa-lock" aria-hidden="true"></i>
        </span>
        <div role="heading" aria-level="2" class="tw:min-w-0 tw:text-body tw:font-section tw:leading-tight tw:text-text">Rahasia &amp; Tidak Memengaruhi Karir Anda</div>
    </div>
    <div class="tw:flex tw:flex-col tw:gap-3 tw:text-caption tw:leading-body tw:text-text-dim {{ $compact ? 'tw:px-card-sm tw:py-3' : 'tw:p-card' }}">
        <div>
            Modul ini adalah kanal resmi bagi seluruh karyawan untuk menyampaikan kritik, saran, ide, concern, atau masukan
            langsung kepada CEO. Seluruh submission bersifat rahasia dan hanya dapat diakses oleh pihak berwenang.
        </div>
        <div>
            <strong class="tw:text-text">Submission di sini TIDAK memengaruhi KPI, penilaian kinerja, performance appraisal, maupun karir Anda</strong>
            dengan cara apa pun — modul ini terpisah total dari sistem KPI/Performance Management, dan semata digunakan untuk
            meningkatkan komunikasi, transparansi, dan perbaikan organisasi.
        </div>
    </div>
</div>

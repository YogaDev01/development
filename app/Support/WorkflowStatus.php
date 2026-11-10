<?php

namespace App\Support;

use App\Models\BudgetRequest;
use App\Models\CostRegister;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;

/**
 * Lapisan presentasi untuk status workflow.
 *
 * Ini BUKAN sumber aturan bisnis. Transisi status, siapa yang boleh
 * menjalankannya, dan validasinya tetap berada di controller serta model
 * masing-masing modul — kelas ini hanya menjawab "bagaimana status ini
 * ditampilkan kepada pengguna".
 *
 * Label untuk Cost Request sengaja didelegasikan ke
 * BudgetRequest::statusLabelFor() supaya tetap satu sumber; kelas ini
 * menambahkan keterangan ("menunggu siapa") dan nada warna di atasnya.
 */
class WorkflowStatus
{
    /**
     * Nada warna yang tersedia. Dipetakan ke token di
     * public/css/tesys-ui-components.css — bukan ke kelas Bootstrap,
     * supaya satu status tampil sama di seluruh aplikasi.
     */
    public const TONES = ['neutral', 'attention', 'progress', 'positive', 'done', 'danger'];

    /**
     * Keterangan diambil dari alur yang benar-benar ada di kode:
     * gerbang peran pada tiap tombol aksi di budget-request/index.blade.php
     * dan BudgetRequestController. Tidak ada tahap yang dikarang.
     */
    private const MAP = [
        'DRAFT' => [
            'description' => 'Belum diajukan',
            'tone'        => 'neutral',
            'icon'        => 'fa-edit',
        ],
        'SUBMITTED' => [
            'description' => 'Menunggu pemeriksaan Head',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'CHECKED' => [
            'description' => 'Menunggu verifikasi Finance',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'VERIFIED' => [
            'description' => 'Menunggu persetujuan CEO',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'APPROVED' => [
            'description' => 'Siap dibatch Treasury',
            'tone'        => 'progress',
            'icon'        => 'fa-check-circle',
        ],
        'QUEUED' => [
            'description' => 'Masuk batch pembayaran',
            'tone'        => 'progress',
            'icon'        => 'fa-exchange-alt',
        ],
        'PAID' => [
            'description' => 'Menunggu settlement dari pengaju',
            'tone'        => 'attention',
            'icon'        => 'fa-receipt',
        ],
        'REALIZED' => [
            'description' => 'Settlement menunggu pemeriksaan Head',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'SETTLE_CHECKED' => [
            'description' => 'Settlement menunggu verifikasi Finance',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'SETTLE_VERIFIED' => [
            'description' => 'Menunggu penutupan Finance Manager',
            'tone'        => 'attention',
            'icon'        => 'fa-hourglass-half',
        ],
        'SETTLE_APPROVED' => [
            'description' => 'Settlement sudah disetujui',
            'tone'        => 'positive',
            'icon'        => 'fa-check-circle',
        ],
        'SECOND_APPROVED' => [
            'description' => 'Second Payment sudah disetujui',
            'tone'        => 'positive',
            'icon'        => 'fa-check-circle',
        ],
        'CLOSED' => [
            'description' => 'Selesai',
            'tone'        => 'done',
            'icon'        => 'fa-lock',
        ],
        'REJECTED' => [
            'description' => 'Menunggu revisi atau pembatalan pengaju',
            'tone'        => 'danger',
            'icon'        => 'fa-undo',
        ],
        'SETTLE_REJECTED' => [
            'description' => 'Settlement ditolak — revisi lalu ajukan ulang',
            'tone'        => 'danger',
            'icon'        => 'fa-undo',
        ],
        'CANCELLED' => [
            'description' => 'Dibatalkan',
            'tone'        => 'neutral',
            'icon'        => 'fa-ban',
        ],
    ];

    /**
     * Status yang kodenya sama tetapi artinya berbeda antar modul. SUBMITTED
     * pada Cost Request menunggu Head, sedangkan pada Purchase Order menunggu
     * Finance — keterangannya tidak boleh dipakai bergantian.
     *
     * Entri di sini menimpa MAP untuk konteks bersangkutan; status yang tidak
     * disebut tetap memakai MAP.
     *
     * Sama seperti MAP, keterangan diambil dari gerbang peran yang benar-benar
     * ada di kode (project/index.blade.php, ProjectController, dan transisi
     * otomatis di Project::syncStatusFromPoNumbers()).
     */
    private const CONTEXT_MAP = [
        'project' => [
            'SUBMITTED' => [
                'description' => 'Menunggu verifikasi Finance',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'VERIFIED' => [
                'description' => 'Terverifikasi; menunggu approval Approver',
                'tone'        => 'attention',
                'icon'        => 'fa-user-check',
            ],
            'EARLY_START' => [
                'description' => 'Early Start — baru diketahui scope-nya; sah & bisa dibuatkan budget',
                'tone'        => 'progress',
                'icon'        => 'fa-seedling',
            ],
            'WAITING_PO' => [
                'description' => 'Menunggu nomor PO dilengkapi lalu diaktifkan',
                'tone'        => 'attention',
                'icon'        => 'fa-file-medical',
            ],
            'ACTIVE' => [
                'description' => 'Berjalan; menunggu BAST seluruh No. PO',
                'tone'        => 'progress',
                'icon'        => 'fa-play-circle',
            ],
            'PROGRESS_DONE' => [
                'description' => 'BAST lengkap; menunggu invoice & pembayaran seluruh No. PO',
                'tone'        => 'progress',
                'icon'        => 'fa-file-invoice-dollar',
            ],
            'PARTIALLY_INVOICED' => [
                'description' => 'Sebagian No. PO sudah ditagih; menunggu invoice & pembayaran sisanya',
                'tone'        => 'progress',
                'icon'        => 'fa-file-invoice',
            ],
            'PARTIALLY_PAID' => [
                'description' => 'Sebagian No. PO sudah Paid; menunggu pembayaran sisanya',
                'tone'        => 'progress',
                'icon'        => 'fa-coins',
            ],
            'PAID' => [
                'description' => 'Menunggu pengajuan MoM Closing oleh Head',
                'tone'        => 'attention',
                'icon'        => 'fa-file-contract',
            ],
            'CLOSING' => [
                'description' => 'Menunggu verifikasi closing Finance',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
        ],

        /*
          Batch pembayaran Treasury. PAID dan REJECTED wajib ditimpa: pada Cost
          Request artinya menunggu settlement / menunggu revisi pengaju,
          sedangkan di sini batch-nya sendiri yang sudah dibayar atau ditolak.
        */
        /*
          Tutup Periode (Closing). Statusnya milik langkah closing, bukan dokumen:
          satu langkah bisa selesai, menunggu PIC, tertahan langkah lain, atau
          tidak berlaku pada bulan berjalan. LOCKED dan OPEN menggambarkan
          periodenya sendiri, bukan langkahnya.
        */
        'closing' => [
            'OPEN' => [
                'description' => 'Periode masih terbuka; transaksi bisa berubah',
                'tone'        => 'progress',
                'icon'        => 'fa-lock-open',
            ],
            'PENDING' => [
                'description' => 'Menunggu tindakan PIC',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'BLOCKED' => [
                'description' => 'Tertahan langkah lain yang belum selesai',
                'tone'        => 'danger',
                'icon'        => 'fa-exclamation-triangle',
            ],
            'DONE' => [
                'description' => 'Langkah selesai',
                'tone'        => 'positive',
                'icon'        => 'fa-check-circle',
            ],
            'SKIPPED' => [
                'description' => 'Tidak berlaku pada periode ini',
                'tone'        => 'neutral',
                'icon'        => 'fa-minus',
            ],
            'LOCKED' => [
                'description' => 'Periode sudah ditutup dan dikunci',
                'tone'        => 'done',
                'icon'        => 'fa-lock',
            ],
        ],

        /*
          Budget Plan. Rantai approval-nya sama dengan Cost Request (Draft →
          Submitted → Checked → Verified), jadi keempat status itu memakai MAP
          apa adanya. Yang khas modul ini hanya tiga di bawah.
        */
        'budget-plan' => [
            'ACTIVE' => [
                'description' => 'Berjalan; closing diajukan oleh Manager',
                'tone'        => 'progress',
                'icon'        => 'fa-play-circle',
            ],
            'CLOSING' => [
                'description' => 'Menunggu verifikasi closing Finance',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'REJECTED' => [
                'description' => 'Menunggu dikembalikan ke Draft oleh Manager',
                'tone'        => 'danger',
                'icon'        => 'fa-undo',
            ],
        ],

        /*
          Invoice & Cash Receipt. Statusnya memakai display_status, jadi OVERDUE
          dan PARTIALLY_PAID ikut di sini meski bukan kolom status di basis data.
          APPROVED -> SENT tidak terjadi di modul ini melainkan saat invoice
          dimasukkan ke Surat Jalan (DeliveryNoteController::store).
        */
        'invoice' => [
            'SUBMITTED' => [
                // Route invoice-cash-receipt.verify: finance_manager & accounting.
                'description' => 'Menunggu verifikasi Accounting / Finance Manager',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'VERIFIED' => [
                // Route invoice-cash-receipt.approve: finance_manager saja
                // (bukan Approver/CEO - lihat InvoiceApprovalOwnerTest).
                'description' => 'Menunggu persetujuan Finance Manager',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'APPROVED' => [
                'description' => 'Menunggu dimasukkan ke Surat Jalan',
                'tone'        => 'attention',
                'icon'        => 'fa-truck',
            ],
            'SENT' => [
                'description' => 'Sudah dikirim; menunggu ditandai diterima customer',
                'tone'        => 'progress',
                'icon'        => 'fa-paper-plane',
            ],
            'RECEIVED' => [
                'description' => 'Diterima customer; menunggu pembayaran',
                'tone'        => 'progress',
                'icon'        => 'fa-file-invoice-dollar',
            ],
            'PARTIALLY_PAID' => [
                'description' => 'Dibayar sebagian; menunggu sisa pembayaran',
                'tone'        => 'progress',
                'icon'        => 'fa-coins',
            ],
            'OVERDUE' => [
                'description' => 'Lewat jatuh tempo dan belum lunas',
                'tone'        => 'danger',
                'icon'        => 'fa-exclamation-triangle',
            ],
            'PAID' => [
                'description' => 'Lunas',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
        ],

        /*
          Cost Register: alur Cost Payment (Non-CA) tanpa tahap Checked — Draft langsung
          VERIFIED (label "Submitted"). Gerbang peran: CostRegisterController
          (submit/settle: finance_manager; approve/revise/approve settle: approver) dan
          Treasury (batch). PAID/SETTLE_* hanya untuk Expense Cash Advance.
        */
        'cost-register' => [
            'DRAFT' => [
                'description' => 'Belum diajukan',
                'tone'        => 'neutral',
                'icon'        => 'fa-edit',
            ],
            'VERIFIED' => [
                'description' => 'Menunggu persetujuan CEO',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'APPROVED' => [
                'description' => 'Siap dibatch Treasury',
                'tone'        => 'progress',
                'icon'        => 'fa-check-circle',
            ],
            'QUEUED' => [
                'description' => 'Masuk batch pembayaran',
                'tone'        => 'progress',
                'icon'        => 'fa-exchange-alt',
            ],
            // Expense Cash Advance (Fase 2, 2026-11-10).
            'PAID' => [
                'description' => 'Sudah dibayar — menunggu Finance Manager input realisasi',
                'tone'        => 'attention',
                'icon'        => 'fa-receipt',
            ],
            'SETTLE_SUBMITTED' => [
                'description' => 'Realisasi menunggu persetujuan CEO',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            // Fase 3: ditolak CEO (keluar alur; FM revisi ke Draft atau batalkan).
            'REJECTED' => [
                'description' => 'Ditolak CEO — revisi ke Draft atau batalkan',
                'tone'        => 'danger',
                'icon'        => 'fa-times-circle',
            ],
            'SETTLE_REJECTED' => [
                'description' => 'Realisasi ditolak CEO — Finance Manager memperbaiki & mengajukan ulang',
                'tone'        => 'danger',
                'icon'        => 'fa-times-circle',
            ],
            'SETTLE_APPROVED' => [
                'description' => 'Settlement disetujui — menunggu kekurangan bayar lewat Treasury',
                'tone'        => 'progress',
                'icon'        => 'fa-exchange-alt',
            ],
            'CLOSED' => [
                'description' => 'Sudah dibayar',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
            'CANCELLED' => [
                'description' => 'Dibatalkan',
                'tone'        => 'neutral',
                'icon'        => 'fa-ban',
            ],
        ],

        'daily-transaction' => [
            'DRAFT' => [
                'description' => 'Draft Treasury — masih bisa diubah, belum diajukan ke CEO',
                'tone'        => 'neutral',
                'icon'        => 'fa-pen',
            ],
            'PENDING_APPROVER' => [
                'description' => 'Menunggu approval CEO',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'DONE' => [
                'description' => 'Menunggu Treasury menandai Paid',
                'tone'        => 'attention',
                'icon'        => 'fa-money-check-alt',
            ],
            'PAID' => [
                'description' => 'Batch sudah dibayar',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
            'REJECTED' => [
                'description' => 'Batch ditolak; CR kembali ke Approved',
                'tone'        => 'danger',
                'icon'        => 'fa-undo',
            ],
        ],

        /*
          Cash Receipt (header penerimaan). Transisi: CashReceiptController —
          store/allocate (DRAFT -> ALLOCATED), cancel (-> CANCELLED).
        */
        'cash-receipt' => [
            'DRAFT' => [
                'description' => 'Belum seluruhnya dialokasikan ke invoice',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'ALLOCATED' => [
                'description' => 'Sudah dialokasikan ke invoice',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
        ],

        /*
          Cash Management (dokumen Cash Flow manual). Transisi:
          CashManagementController::post() (DRAFT -> POSTED), cancel().
        */
        'cash-flow' => [
            'DRAFT' => [
                'description' => 'Belum masuk buku kas',
                'tone'        => 'neutral',
            ],
            'POSTED' => [
                'description' => 'Sudah masuk buku kas',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
        ],

        /*
          Tutup Periode — status DOKUMEN closing per bulan (Closing::STATUSES),
          berbeda dari konteks 'closing' di atas yang menggambarkan langkah.
          'BELUM DIREKAP' = periode tanpa baris closing (tampilan saja).
        */
        'period-closing' => [
            'BELUM DIREKAP' => [
                'description' => 'Rekap periode belum disusun',
                'tone'        => 'neutral',
            ],
            'DRAFT' => [
                'description' => 'Rekap tersusun; periode belum ditutup',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'CLOSED' => [
                'description' => 'Periode ditutup',
                'tone'        => 'done',
                'icon'        => 'fa-lock',
            ],
            'REOPENED' => [
                'description' => 'Periode dibuka kembali',
                'tone'        => 'progress',
                'icon'        => 'fa-lock-open',
            ],
        ],

        /* Forecast. Realisasi hanya bisa diubah selama ACTIVE (ForecastController). */
        'forecast' => [
            'DRAFT' => [
                'description' => 'Belum diaktifkan',
                'tone'        => 'neutral',
            ],
            'ACTIVE' => [
                'description' => 'Berjalan; realisasi bisa dicatat',
                'tone'        => 'progress',
            ],
            'CLOSED' => [
                'description' => 'Ditutup',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
        ],

        /* Payroll Run. Gerbang peran: PayrollRunController::*_ROLES. */
        'payroll-run' => [
            'DRAFT' => [
                'description' => 'Disiapkan HR; belum diajukan',
                'tone'        => 'neutral',
            ],
            'SUBMITTED' => [
                'description' => 'Menunggu verifikasi Finance Manager',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'VERIFIED' => [
                'description' => 'Menunggu persetujuan CEO',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'APPROVED' => [
                'description' => 'Menunggu pembayaran Finance Manager',
                'tone'        => 'progress',
            ],
            'PAID' => [
                'description' => 'Sudah dibayar; menunggu penutupan',
                'tone'        => 'positive',
            ],
            'CLOSED' => [
                'description' => 'Selesai',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
            'REJECTED' => [
                'description' => 'Ditolak; HR membuka kembali ke Draft',
                'tone'        => 'danger',
                'icon'        => 'fa-undo',
            ],
        ],

        /*
          Trouble Ticket. Transisinya milik App\Services\TroubleTicketWorkflow;
          keterangan di sini mengikuti gerbang di sana.
        */
        'trouble-ticket' => [
            'OPEN' => [
                'description' => 'Menunggu teknisi memilih Remote atau membuat Plan',
                'tone'        => 'attention',
                'icon'        => 'fa-hourglass-half',
            ],
            'REMOTE' => [
                'description' => 'Diselesaikan tanpa kunjungan; menunggu form close',
                'tone'        => 'progress',
                'icon'        => 'fa-satellite-dish',
            ],
            'PLAN' => [
                'description' => 'Plan visit dibuat; menunggu tanggal plan visit',
                'tone'        => 'attention',
                'icon'        => 'fa-calendar-check',
            ],
            'TRIP' => [
                'description' => 'Tanggal plan visit tiba; perjalanan menuju site',
                'tone'        => 'progress',
                'icon'        => 'fa-route',
            ],
            'IN_PROGRESS' => [
                'description' => 'Pengerjaan di site berjalan',
                'tone'        => 'progress',
                'icon'        => 'fa-tools',
            ],
            'PROGRESS_DONE' => [
                'description' => 'Form close dikirim; menunggu approval supervisor',
                'tone'        => 'attention',
                'icon'        => 'fa-user-check',
            ],
            'CLOSED' => [
                'description' => 'Disetujui supervisor; tiket selesai',
                'tone'        => 'done',
                'icon'        => 'fa-check-circle',
            ],
        ],
    ];

    /**
     * URUTAN TAHAP per modul.
     *
     * Ini murni urutan TAMPILAN. Daftarnya disalin dari konstanta STATUSES
     * masing-masing model (BudgetRequest, BudgetPlan, Invoice,
     * DailyTransaction) dengan status di luar alur — REJECTED/CANCELLED —
     * dikeluarkan. Tidak ada tahap yang dikarang, dan kelas ini tetap tidak
     * memutuskan transisi apa pun: perpindahan status seluruhnya milik
     * controller & model seperti sebelumnya.
     */
    private const FLOWS = [
        'budget-request' => [
            'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED', 'QUEUED',
            'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED',
            'SETTLE_APPROVED', 'SECOND_APPROVED', 'CLOSED',
        ],
        'budget-plan' => [
            'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'ACTIVE', 'CLOSING', 'CLOSED',
        ],
        'invoice' => [
            'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT', 'RECEIVED',
            'PARTIALLY_PAID', 'PAID',
        ],
        'daily-transaction' => [
            'DRAFT', 'PENDING_APPROVER', 'DONE', 'PAID',
        ],
        'cost-register' => [
            'DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'PAID', 'SETTLE_SUBMITTED', 'SETTLE_APPROVED', 'CLOSED',
        ],
        'trouble-ticket' => [
            'OPEN', 'REMOTE', 'PLAN', 'TRIP', 'IN_PROGRESS', 'PROGRESS_DONE', 'CLOSED',
        ],
    ];

    /** Label khusus Trouble Ticket — sisanya ikut ucwords(kode). */
    public const TROUBLE_TICKET_LABELS = [
        'PROGRESS_DONE' => 'Waiting Approve',
    ];

    /** Status yang menghentikan perjalanan dokumen, bukan salah satu tahapnya. */
    private const OFF_FLOW = ['REJECTED', 'CANCELLED', 'SETTLE_REJECTED'];

    /** Daftar kode tahap sebuah modul, urut dari awal ke akhir. */
    public static function flow(string $context): array
    {
        return self::FLOWS[$context] ?? [];
    }

    /** Tahap sebuah modul lengkap dengan labelnya — untuk dikirim ke browser. */
    public static function flowSteps(string $context): array
    {
        return array_map(fn ($kode) => [
            'key'   => $kode,
            'label' => self::label($kode, $context),
        ], self::flow($context));
    }

    /**
     * Posisi sebuah dokumen di dalam alurnya.
     *
     * Mengembalikan tiap tahap beserta keadaannya (done / current / todo),
     * sehingga komponen tampilan tidak perlu menghitung sendiri:
     *
     *   state = 'normal'  dokumen berada di salah satu tahap
     *           'off'     dokumen ditolak/dibatalkan — alurnya berhenti
     *           'unknown' status di luar peta (mis. OVERDUE pada invoice)
     */
    public static function progress(?string $status, string $context = 'default'): array
    {
        $tahap = self::flow($context);
        $indeks = $status === null ? false : array_search($status, $tahap, true);
        $mati = $status !== null && in_array($status, self::OFF_FLOW, true);

        $keadaan = $indeks !== false ? 'normal' : ($mati ? 'off' : 'unknown');

        $langkah = [];
        foreach ($tahap as $i => $kode) {
            $langkah[] = [
                'key'   => $kode,
                'label' => self::label($kode, $context),
                'state' => $indeks === false
                    ? 'todo'
                    : ($i < $indeks ? 'done' : ($i === $indeks ? 'current' : 'todo')),
            ];
        }

        return [
            'context' => $context,
            'status'  => $status,
            'state'   => $keadaan,
            'index'   => $indeks === false ? null : $indeks,
            'total'   => count($tahap),
            'steps'   => $langkah,
            'next'    => ($indeks !== false && isset($tahap[$indeks + 1]))
                ? self::label($tahap[$indeks + 1], $context)
                : null,
        ];
    }

    /** Metadata satu status untuk sebuah konteks, dengan MAP sebagai dasar. */
    private static function entry(?string $status, string $context): ?array
    {
        return self::CONTEXT_MAP[$context][$status] ?? self::MAP[$status] ?? null;
    }

    /**
     * Label tampilan. Untuk konteks 'budget-request' didelegasikan ke sumber
     * yang sudah ada agar istilah internal (Process = QUEUED, Settle =
     * REALIZED) tidak bercabang.
     */
    public static function label(?string $status, string $context = 'default'): string
    {
        if ($status === null || $status === '') {
            return '-';
        }

        if ($context === 'budget-request') {
            return BudgetRequest::statusLabelFor($status);
        }

        if ($context === 'project') {
            return Project::statusLabelFor($status);
        }

        if ($context === 'cost-register') {
            return CostRegister::statusLabelFor($status);
        }

        /*
          Kata-katanya tetap dari model (PENDING_APPROVER = SUBMITTED); yang
          diseragamkan di sini hanya kapitalisasinya, supaya chip status
          terbaca sama dengan modul lain. Accessor status_label dibiarkan apa
          adanya karena dipakai juga oleh ekspor Excel dan bell Todo.
        */
        if ($context === 'invoice') {
            return ucwords(strtolower(Invoice::displayStatusLabelFor($status)));
        }

        if ($context === 'daily-transaction') {
            return ucwords(strtolower(DailyTransaction::statusLabelFor($status)));
        }

        // Trouble Ticket: PROGRESS_DONE dibaca "Waiting Approve" (keputusan
        // 19 Okt 2026) — tahapnya memang menunggu approval supervisor, bukan
        // pekerjaan yang sudah tuntas. Kode statusnya tidak berubah.
        if ($context === 'trouble-ticket' && isset(self::TROUBLE_TICKET_LABELS[$status])) {
            return self::TROUBLE_TICKET_LABELS[$status];
        }

        return ucwords(strtolower(str_replace('_', ' ', $status)));
    }

    /** Keterangan singkat: pekerjaan ini sedang menunggu siapa. */
    public static function description(?string $status, string $context = 'default'): ?string
    {
        return self::entry($status, $context)['description'] ?? null;
    }

    public static function tone(?string $status, string $context = 'default'): string
    {
        return self::entry($status, $context)['tone'] ?? 'neutral';
    }

    public static function icon(?string $status, string $context = 'default'): ?string
    {
        return self::entry($status, $context)['icon'] ?? null;
    }

    /** Seluruh metadata sebuah status dalam satu panggilan. */
    public static function meta(?string $status, string $context = 'default'): array
    {
        return [
            'key'         => $status,
            'label'       => self::label($status, $context),
            'description' => self::description($status, $context),
            'tone'        => self::tone($status, $context),
            'icon'        => self::icon($status, $context),
        ];
    }

    /** Apakah status ini sudah dikenal peta presentasi. */
    public static function known(?string $status, string $context = 'default'): bool
    {
        return self::entry($status, $context) !== null;
    }
}

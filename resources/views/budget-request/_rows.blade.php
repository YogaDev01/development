{{-- Baris tabel Cash Advance ($mode='ca') & Cost Payment ($mode='cp'). Dimuat lewat BudgetRequestController::tab(). --}}
@foreach($rows as $cr)
                            @php
                                // NOB global: total pengajuan CR melebihi sisa Budget Plan -> deskripsi berwarna merah.
                                // Dibaca dari kolom is_nob (dulu: awalan teks '[NOB]' pada deskripsi).
                                $isNob = (bool) $cr->is_nob;
                                // NOB sub-item: hanya salah satu baris rincian yang melebihi sisa sub-item budgetnya -> kuning.
                                $isNobItem = !$isNob && $cr->details->contains(fn ($d) => $d->is_nob);
                                // Settlement/Refund hanya ada di tab Cash Advance (Cost Payment tanpa settlement,
                                // dan relasi realizations tidak di-eager-load di sana — jangan dihitung).
                                $realisasi = $mode === 'ca' ? $cr->total_realization : 0;
                                /* Refund baru nyata SETELAH settle. Sebelumnya kolom ini
                                   memakai selisih mentah (amount - realisasi), jadi CR yang
                                   belum disettle tampil "refund" sebesar nilai pengajuannya
                                   sendiri — angka yang tidak pernah benar. refundAmount()
                                   sudah menjaga aturan itu di satu tempat. */
                                $refund    = $mode === 'ca' ? $cr->refundAmount() : 0;
                                /* Expense Claim = kebalikan Refund: settlement lebih besar dari
                                   pengajuan, jadi masih ada kekurangan bayar yang ditagihkan balik.
                                   Aturan settle-nya sama dengan refund. */
                                $claim     = $mode === 'ca' ? $cr->expenseClaimAmount() : 0;
                                /* Bulk Approve/Reject: baris boleh dicentang bila status di tahap
                                   approval DAN peran efektif berwenang di tahap itu (STAGE_ROLES —
                                   sumber sama dengan tombol per baris & applyReviewApprove). */
                                $bulkOk = ! empty($canBulk) && $cr->isInBulkStage()
                                    && Auth::user()->hasRole(array_merge(['superadmin'], \App\Models\BudgetRequest::bulkStageRoles()[$cr->status]));
                            @endphp
                            <tr data-id="{{ $cr->id }}"
                                data-status="{{ $cr->status }}"
                                data-type="{{ $cr->type?->name ?? '' }}"
                                data-pminternal="{{ strtoupper($cr->pm_internal_name) }}"
                                data-po="{{ $cr->budgetPlan?->project ? '[' . $cr->budgetPlan->project->code . '] ' . $cr->budgetPlan->project->name : '' }}"
                                data-beneficiary="{{ $cr->beneficiary?->nickname ?? '' }}"
                                {{-- PIC = pemegang beneficiary-nya. Dipakai filter "PIC" dan
                                     tautan dari kartu Active Cash Advance di halaman Summary,
                                     yang mengelompokkan CA per PIC, bukan per beneficiary. --}}
                                data-pic="{{ $cr->beneficiary?->pic?->pic ?? '' }}"
                                data-requestor="{{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '' }}"
                                data-nob="{{ ($isNob || $isNobItem) ? '1' : '0' }}"
                                data-amount="{{ (int) $cr->amount }}"
                                data-takeout="{{ $cr->details->contains(fn ($d) => $d->isTakenOut()) ? '1' : '0' }}"
                                data-date="{{ $cr->created_at->format('Y-m-d') }}"
                                class="{{ $isNob ? 'row-nob' : ($isNobItem ? 'row-nob-item' : '') }}">
                                @if(! empty($canBulk))
                                <td class="text-center cr-col-check">
                                    <input type="checkbox" class="cr-check-row" value="{{ $cr->id }}"
                                           data-elig="{{ $bulkOk ? '1' : '0' }}"
                                           aria-label="Pilih {{ $cr->code }}"
                                           @if(! $bulkOk)
                                           disabled title="{{ $cr->isInBulkStage() ? 'Peran Anda tidak berwenang pada tahap ' . $cr->status : 'Status ' . $cr->status . ' bukan tahap approval (Submitted/Checked/Verified atau Settle/Settle Checked/Settle Verified/Settle Approved)' }}"
                                           @endif>
                                </td>
                                @endif
                                <td class="text-nowrap"><span class="tw:tabular-nums tw:text-text-faint">{{ $loop->iteration }}</span></td>

                                <td class="text-nowrap cr-col-code">
                                    {{-- Klik kode = salin ke clipboard; detail dibuka lewat klik baris,
                                         sel nominal dan status tetap membuka tab masing-masing. --}}
                                    <button type="button" class="cr-number-btn cr-copy-code" data-code="{{ $cr->code }}" title="Klik untuk menyalin kode">
                                        {{ $cr->code }}
                                    </button>
                                </td>

                                <td class="cr-col-desc" data-lbl="Description">
                                    {{-- Satu baris: deskripsi (tebal; merah bila NOB, kuning bila NOB sub-item), dipotong 60 karakter. --}}
                                    <div title="{{ $cr->description }}" class="tw:flex tw:flex-nowrap tw:max-sm:flex-wrap tw:items-center tw:gap-x-2 tw:gap-y-1 tw:max-sm:justify-end tw:text-control tw:font-heading tw:leading-tight {{ $isNob ? 'tw:text-danger' : ($isNobItem ? 'tw:text-warning' : 'tw:text-text') }}">
                                        <span>{{ Str::limit($cr->description, 60) }}</span>
                                    </div>
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d') }}" data-lbl="Tanggal">
                                    <span class="tw:text-data tw:tabular-nums tw:text-text-dim">{{ $cr->created_at->format('d/m/Y') }}</span>
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->paid_at?->format('Y-m-d') ?? '' }}" data-lbl="Tanggal Paid">
                                    <span class="tw:text-data tw:tabular-nums {{ $cr->paid_at ? 'tw:text-text-dim' : 'tw:text-text-faint' }}">{{ $cr->paid_at?->format('d/m/Y') ?? '-' }}</span>
                                </td>

                                <td data-lbl="Requestor">
                                    <span class="tw:text-data tw:text-text">{{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '-' }}</span>
                                </td>

                                <td data-lbl="PIC">
                                    <div class="tw:max-sm:text-right">
                                        {{-- Satu nilai: PIC bila ada, kalau kosong/"-" pakai beneficiary. --}}
                                        @php
                                            $picNama = trim((string) ($cr->beneficiary?->pic?->pic ?? ''));
                                            $picNama = ($picNama === '' || $picNama === '-') ? trim((string) ($cr->beneficiary?->nickname ?? '')) : $picNama;
                                        @endphp
                                        <span class="tw:block tw:text-data tw:leading-tight tw:text-text">{{ ($picNama === '' || $picNama === '-') ? '-' : $picNama }}</span>
                                    </div>
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-lbl="Request" data-raw="{{ $cr->amount }}" data-order="{{ $cr->amount }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr tw:tabular-nums" data-id="{{ $cr->id }}" data-tab="#crPaneDetails" title="Detail Request">{{ $cr->amount > 0 ? $cr->amount_formatted : '-' }}</button>
                                </td>

                                @if($mode === 'ca')
                                <td class="text-right text-nowrap col-raw-realisasi" data-lbl="Settlement" data-raw="{{ $realisasi }}" data-order="{{ $realisasi }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr tw:tabular-nums" data-id="{{ $cr->id }}" data-tab="#crPaneRealization" title="Detail Settlement">{{ $realisasi != 0 ? number_format($realisasi, 0, ',', '.') : '-' }}</button>
                                </td>

                                <td class="text-right text-nowrap tw:tabular-nums col-raw-var {{ $refund > 0 ? 'text-var-pos' : '' }}" data-lbl="Refund" data-raw="{{ $refund }}" data-order="{{ $refund }}">
                                    {{ $refund != 0 ? number_format($refund, 0, ',', '.') : '-' }}
                                </td>

                                {{-- Expense Claim lahir dari settlement juga, jadi selnya bisa
                                     diklik ke tab Settlement seperti kolom Request & Settlement. --}}
                                <td class="text-right text-nowrap col-raw-claim" data-lbl="Expense Claim" data-raw="{{ $claim }}" data-order="{{ $claim }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr tw:tabular-nums"
                                            data-id="{{ $cr->id }}" data-tab="#crPaneRealization" title="Detail Settlement">{{ $claim != 0 ? number_format($claim, 0, ',', '.') : '-' }}</button>
                                </td>
                                @endif

                                <td class="text-nowrap" data-card="status">
                                    {{-- Label, keterangan, dan warna berasal dari App\Support\WorkflowStatus.
                                         Kelas pemicu (cr-status-link btn-detail-cr) dipertahankan supaya
                                         klik pada badge tetap membuka tab Log seperti sebelumnya. --}}
                                    <x-status :status="$cr->status"
                                              context="budget-request"
                                              class="cr-status-link btn-detail-cr"
                                              data-id="{{ $cr->id }}"
                                              data-tab="#crPaneLogs"
                                              role="button"
                                              tabindex="0" />
                                </td>

                                <td class="text-center action-column">
                                    @php
                                        /*
                                          Gerbang peran di bawah ini SALINAN PERSIS dari kondisi
                                          sebelumnya — tidak ada peran yang ditambah maupun
                                          dikurangi. Yang berubah hanya cara menampilkannya:
                                          semua aksi yang berhak dikumpulkan ke satu daftar ($aksi);
                                          1 aksi = satu tombol bernama, lebih dari 1 = satu tombol
                                          titik tiga berisi semuanya (aksi mundur/merusak di bawah
                                          pemisah). Detail dibuka lewat klik baris, jadi tidak ada
                                          tombol/menu "Tinjau" maupun "Lihat detail".
                                        */
                                        $u = Auth::user();
                                        $st = $cr->status;

                                        $bolehSubmit    = $st === 'DRAFT' && $u->hasRole(['superadmin','manager']);
                                        $bolehUbahDraft = $st === 'DRAFT' && $u->hasRole(['superadmin','manager','supervisor']);
                                        /* Peran tiap tahap approval diambil dari BudgetRequest::STAGE_ROLES —
                                           sumber yang sama dengan controller & middleware route, supaya
                                           tombol tidak hilang untuk peran yang sebenarnya berhak
                                           (mis. cost_control di tahap Checked -> Verified). */
                                        $stageRoles     = fn ($s) => array_merge(['superadmin'], \App\Models\BudgetRequest::STAGE_ROLES[$s] ?? []);
                                        $bolehHead      = $st === 'SUBMITTED' && $u->hasRole($stageRoles('SUBMITTED'));
                                        $bolehFinance   = $st === 'CHECKED' && $u->hasRole($stageRoles('CHECKED'));
                                        $bolehCeo       = $st === 'VERIFIED' && $u->hasRole($stageRoles('VERIFIED'));
                                        $bolehRevisi    = $st === 'REJECTED' && $u->hasRole(['superadmin','manager','supervisor']);
                                        $bolehBatal     = $st === 'REJECTED' && $u->hasRole(['superadmin','manager']);
                                        // SETTLE_REJECTED ikut tombol "Settle" yang sama dengan
                                        // PAID — form realize.blade.php menangani keduanya.
                                        $bolehSettle    = in_array($st, ['PAID', 'SETTLE_REJECTED'], true) && $u->hasRole(['superadmin','manager','supervisor']);
                                        $bolehHeadSet   = $st === 'REALIZED' && $u->hasRole(['superadmin','head']);
                                        $bolehFinSet    = $st === 'SETTLE_CHECKED' && $u->hasRole(['superadmin','finance','finance_manager','cost_control']);
                                        /* Cabang Expense Claim (settlement > pengajuan): CR wajib lewat
                                           Settle Approve (Finance Manager) lalu Second Approve (Approver)
                                           sebelum boleh di-Close. Tanpa klaim, Close tetap langsung dari
                                           SETTLE_VERIFIED seperti sebelumnya. */
                                        $adaClaim       = $claim > 0;
                                        $bolehSetApp    = $st === 'SETTLE_VERIFIED' && $adaClaim && $u->hasRole(['superadmin','finance_manager']);
                                        $boleh2ndApp    = $st === 'SETTLE_APPROVED' && $u->hasRole(['superadmin','approver']);
                                        $bolehTutup     = $st === 'SETTLE_VERIFIED' && !$adaClaim
                                                            && $u->hasRole(['superadmin','finance_manager']);

                                        // kelas = kelas pemicu JS (dipertahankan persis); kode = ikut data-code.
                                        $it = fn (string $kelas, string $ikon, string $label, string $judul, bool $kode = true, string $nada = '') => [
                                            'jenis' => 'btn', 'kelas' => $kelas, 'ikon' => $ikon, 'label' => $label,
                                            'judul' => $judul, 'kode' => $kode, 'nada' => $nada,
                                        ];
                                        $aksi = [];

                                        // ── aksi maju ──
                                        if ($bolehSubmit) {
                                            $aksi[] = ['jenis' => 'form', 'url' => url('/budget-request/'.$cr->id.'/submit'), 'metode' => 'POST',
                                                'form' => 'frmSubmitCR', 'ikon' => 'fa-paper-plane', 'label' => 'Submit', 'judul' => 'Submit ke Head', 'nada' => ''];
                                        }
                                        if ($bolehUbahDraft) {
                                            $aksi[] = ['jenis' => 'link', 'url' => url('/budget-request/'.$cr->id.'/edit'),
                                                'ikon' => 'fa-pen', 'label' => 'Edit', 'judul' => 'Edit', 'nada' => 'is-neutral'];
                                        }
                                        if ($bolehHead)    $aksi[] = $it('btn-head-approve', 'fa-check', 'Approve', 'Approve');
                                        if ($bolehFinance) $aksi[] = $it('btn-finance-verify', 'fa-check-double', 'Verify', 'Verify');
                                        if ($bolehCeo)     $aksi[] = $it('btn-ceo-approve', 'fa-check', 'Approve', 'Approve');
                                        if ($bolehRevisi)  $aksi[] = $it('btn-manager-revise', 'fa-undo', 'Revise', 'Revise → Draft', false, 'is-neutral');
                                        if ($bolehSettle) {
                                            $aksi[] = ['jenis' => 'link', 'url' => route('budget-request.realize-form', $cr->id),
                                                'ikon' => 'fa-receipt', 'label' => 'Settle', 'judul' => 'Settle', 'nada' => ''];
                                        }
                                        if ($bolehHeadSet) $aksi[] = $it('btn-head-check-settlement', 'fa-check-double', 'Check', 'Check → Settle Checked');
                                        if ($bolehFinSet)  $aksi[] = $it('btn-finance-verify-settlement', 'fa-check-double', 'Verify', 'Verify → Settle Verified');
                                        if ($bolehSetApp)  $aksi[] = $it('btn-settle-approve', 'fa-check-double', 'Settle Approve', 'Approve → Second Approve');
                                        if ($boleh2ndApp)  $aksi[] = $it('btn-second-approve', 'fa-hand-holding-usd', 'Second Approve', 'Approve → Treasury');
                                        if ($bolehTutup)   $aksi[] = $it('btn-ceo-close-settlement', 'fa-lock', 'Close', 'Close');

                                        // ── aksi mundur / merusak ──
                                        if ($bolehHead)    $aksi[] = $it('btn-head-reject', 'fa-times', 'Reject', 'Reject', false, 'is-danger');
                                        if ($bolehFinance) $aksi[] = $it('btn-finance-reject', 'fa-times', 'Reject', 'Reject', false, 'is-danger');
                                        if ($bolehCeo)     $aksi[] = $it('btn-ceo-reject', 'fa-times', 'Reject', 'Reject', false, 'is-danger');
                                        if ($bolehSetApp)  $aksi[] = $it('btn-settle-reject', 'fa-times', 'Reject', 'Reject → Paid', true, 'is-danger');
                                        if ($boleh2ndApp)  $aksi[] = $it('btn-second-reject', 'fa-times', 'Reject', 'Reject → Paid', true, 'is-danger');
                                        if ($bolehHeadSet) $aksi[] = $it('btn-head-reject-settlement', 'fa-times', 'Tolak settlement', 'Tolak settlement', true, 'is-danger');
                                        if ($bolehFinSet)  $aksi[] = $it('btn-finance-reject-settlement', 'fa-times', 'Tolak settlement', 'Tolak settlement', true, 'is-danger');
                                        if ($bolehTutup)   $aksi[] = $it('btn-manager-reject-settlement', 'fa-times', 'Tolak settlement', 'Tolak settlement', true, 'is-danger');
                                        if ($bolehBatal)   $aksi[] = $it('btn-manager-cancel', 'fa-ban', 'Cancel', 'Cancel', false, 'is-danger');
                                        if ($bolehUbahDraft) {
                                            $aksi[] = ['jenis' => 'form', 'url' => url('/budget-request/'.$cr->id), 'metode' => 'DELETE',
                                                'form' => 'frmDeleteCR', 'ikon' => 'fa-trash', 'label' => 'Hapus', 'judul' => 'Hapus', 'nada' => 'is-danger'];
                                        }

                                        /* Satu penyaji untuk tombol tunggal ($menu=false) dan butir menu
                                           ($menu=true); atribut data-id/data-code dan kelas pemicu sama
                                           persis dengan markup sebelumnya. */
                                        $gambar = function (array $a, bool $menu) use ($cr) {
                                            $kelasDasar = $menu ? 'dropdown-item' : 'ts-action-primary';
                                            $kelas = trim($kelasDasar.' '.($a['nada'] ?? '').' '.($a['kelas'] ?? ''));
                                            $isi = '<i class="fas '.e($a['ikon']).'" aria-hidden="true"></i><span class="ts-al">'.e($a['label']).'</span>';
                                            if ($a['jenis'] === 'link') {
                                                return '<a href="'.e($a['url']).'" class="'.e($kelas).'" title="'.e($a['judul']).'">'.$isi.'</a>';
                                            }
                                            if ($a['jenis'] === 'form') {
                                                $m = $a['metode'] === 'DELETE' ? method_field('DELETE') : '';
                                                return '<form action="'.e($a['url']).'" method="POST" class="'.e($a['form']).($menu ? '' : ' d-inline').'">'
                                                    .csrf_field().$m
                                                    .'<button type="submit" class="'.e($kelas).'" title="'.e($a['judul']).'">'.$isi.'</button></form>';
                                            }
                                            $data = ' data-id="'.e($cr->id).'"'.(! empty($a['kode']) ? ' data-code="'.e($cr->code).'"' : '');
                                            return '<button type="button" class="'.e($kelas).'"'.$data.' title="'.e($a['judul']).'">'.$isi.'</button>';
                                        };
                                    @endphp

                                    <div class="action-buttons ts-actions">
                                        @if(count($aksi) === 1)
                                            {!! $gambar($aksi[0], false) !!}
                                        @elseif(count($aksi) > 1)
                                            <button type="button" class="ts-action-more"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                            </button>

                                            <div class="ts-action-menu dropdown-menu" hidden>
                                                @php $pemisah = false; @endphp
                                                @foreach($aksi as $a)
                                                    @if(! $pemisah && $a['nada'] === 'is-danger' && $loop->index > 0)
                                                        <div class="dropdown-divider"></div>
                                                        @php $pemisah = true; @endphp
                                                    @endif
                                                    {!! $gambar($a, true) !!}
                                                @endforeach
                                            </div>
                                        @endif
                                        {{-- Tanpa aksi: sel dibiarkan kosong; detail dibuka lewat klik baris. --}}
                                    </div>
                                </td>
                            </tr>
@endforeach

{{-- Baris tabel Cash Advance ($mode='ca') & Cost Payment ($mode='cp'). Dimuat lewat BudgetRequestController::tab(). --}}
@foreach($rows as $cr)
                            @php
                                // NOB global: total pengajuan CR melebihi sisa Budget Plan -> merah + badge.
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
                                data-nob="{{ ($isNob || $isNobItem) ? '1' : '0' }}"
                                data-date="{{ $cr->created_at->format('Y-m-d') }}"
                                class="{{ $isNob ? 'row-nob' : ($isNobItem ? 'row-nob-item' : '') }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap cr-col-code">
                                    {{-- Kode membuka drawer tinjau; sel nominal dan status tetap
                                         membuka modal pada tab masing-masing seperti sebelumnya. --}}
                                    <button type="button" class="cr-number-btn btn-review-cr" data-id="{{ $cr->id }}">
                                        {{ $cr->code }}
                                    </button>
                                </td>

                                <td class="cr-col-desc">
                                    <div style="font-size:13px;font-weight:600;">
                                        @if($isNob)
                                            <span class="badge badge-danger px-1 py-1 mr-1" style="font-size:9px;">NOB</span>
                                        @endif
                                        {{ Str::limit($cr->description, 200) }}
                                    </div>
                                    <div style="font-size:10px;color:var(--text-faint);margin-top:2px;">
                                        {{ $cr->po_code ?? '-' }} - {{ $cr->type?->name ?? '-' }}, {{ strtoupper($cr->pm_internal_name) }}
                                    </div>
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d') }}">
                                    {{ $cr->created_at->format('d/m/Y') }}
                                </td>

                                <td class="text-nowrap" data-order="{{ $cr->paid_at?->format('Y-m-d') ?? '' }}">
                                    {{ $cr->paid_at?->format('d/m/Y') ?? '-' }}
                                </td>

                                <td class="text-nowrap">
                                    {{ $cr->applicant?->username ? strtoupper($cr->applicant->username) : '-' }}
                                </td>

                                <td class="text-nowrap">
                                    {{ $cr->beneficiary?->pic?->pic ?? '-' }}
                                    <div style="font-size:10px;color:var(--text-faint);margin-top:2px;">{{ $cr->beneficiary?->nickname ?? '-' }}</div>
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-raw="{{ $cr->amount }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr" data-id="{{ $cr->id }}" data-tab="#crPaneDetails" title="Detail Request">{{ $cr->amount_formatted }}</button>
                                </td>

                                @if($mode === 'ca')
                                <td class="text-right text-nowrap col-raw-realisasi" data-raw="{{ $realisasi }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr" data-id="{{ $cr->id }}" data-tab="#crPaneRealization" title="Detail Settlement">{{ number_format($realisasi, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-var {{ $refund > 0 ? 'text-var-pos' : '' }}" data-raw="{{ $refund }}">
                                    {{ number_format($refund, 0, ',', '.') }}
                                </td>

                                {{-- Expense Claim lahir dari settlement juga, jadi selnya bisa
                                     diklik ke tab Settlement seperti kolom Request & Settlement. --}}
                                <td class="text-right text-nowrap col-raw-claim" data-raw="{{ $claim }}">
                                    <button type="button" class="cr-cell-link btn-detail-cr"
                                            data-id="{{ $cr->id }}" data-tab="#crPaneRealization" title="Detail Settlement">{{ number_format($claim, 0, ',', '.') }}</button>
                                </td>
                                @endif

                                <td class="text-nowrap">
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
                                          satu aksi utama bernama + menu untuk sisanya, sehingga
                                          aksi mundur (Reject, Hapus, Cancel) tidak lagi sejajar
                                          dan sewarna dengan aksi maju.
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
                                        $bolehSettle    = $st === 'PAID' && $u->hasRole(['superadmin','manager','supervisor']);
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

                                        $adaAksi = $bolehSubmit || $bolehUbahDraft || $bolehHead || $bolehFinance
                                            || $bolehCeo || $bolehRevisi || $bolehSettle || $bolehHeadSet
                                            || $bolehFinSet || $bolehSetApp || $boleh2ndApp || $bolehTutup;
                                    @endphp

                                    <div class="action-buttons ts-actions">

                                        {{-- ── AKSI UTAMA — satu per baris, selalu bernama ── --}}

                                        {{-- DRAFT: manager mengajukan; supervisor hanya menyunting --}}
                                        @if($bolehSubmit)
                                        <form action="{{ url('/budget-request/'.$cr->id.'/submit') }}"
                                              method="POST" class="frmSubmitCR d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Submit ke Head">
                                                <i class="fas fa-paper-plane"></i><span class="ts-al">Submit</span>
                                            </button>
                                        </form>
                                        @elseif($bolehUbahDraft)
                                        <a href="{{ url('/budget-request/'.$cr->id.'/edit') }}"
                                           class="ts-action-primary is-neutral" title="Edit">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @endif

                                        @if($bolehHead)
                                        <button type="button" class="ts-action-primary btn-head-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @endif

                                        @if($bolehFinance)
                                        <button type="button" class="ts-action-primary btn-finance-verify"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Verify</span>
                                        </button>
                                        @endif

                                        @if($bolehCeo)
                                        <button type="button" class="ts-action-primary btn-ceo-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @endif

                                        @if($bolehRevisi)
                                        <button type="button" class="ts-action-primary is-neutral btn-manager-revise"
                                                data-id="{{ $cr->id }}" title="Revise → Draft">
                                            <i class="fas fa-undo"></i><span class="ts-al">Revise</span>
                                        </button>
                                        @endif

                                        @if($bolehSettle)
                                        <a href="{{ route('budget-request.realize-form', $cr->id) }}"
                                           class="ts-action-primary" title="Settle">
                                            <i class="fas fa-receipt" aria-hidden="true"></i><span class="ts-al">Settle</span>
                                        </a>
                                        @endif

                                        @if($bolehHeadSet)
                                        <button type="button" class="ts-action-primary btn-head-check-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Check → Settle Checked">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Check</span>
                                        </button>
                                        @endif

                                        @if($bolehFinSet)
                                        <button type="button" class="ts-action-primary btn-finance-verify-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify → Settle Verified">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Verify</span>
                                        </button>
                                        @endif

                                        @if($bolehSetApp)
                                        <button type="button" class="ts-action-primary btn-settle-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve &rarr; Second Approve">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Settle Approve</span>
                                        </button>
                                        <button type="button" class="ts-action-primary is-danger btn-settle-reject"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Reject &rarr; Paid">
                                            <i class="fas fa-times"></i><span class="ts-al">Reject</span>
                                        </button>
                                        @endif

                                        @if($boleh2ndApp)
                                        <button type="button" class="ts-action-primary btn-second-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve &rarr; Treasury">
                                            <i class="fas fa-hand-holding-usd"></i><span class="ts-al">Second Approve</span>
                                        </button>
                                        <button type="button" class="ts-action-primary is-danger btn-second-reject"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Reject &rarr; Paid">
                                            <i class="fas fa-times"></i><span class="ts-al">Reject</span>
                                        </button>
                                        @endif

                                        @if($bolehTutup)
                                        <button type="button" class="ts-action-primary btn-ceo-close-settlement"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Close">
                                            <i class="fas fa-lock"></i><span class="ts-al">Close</span>
                                        </button>
                                        @endif

                                        {{-- ── AKSI LAIN — termasuk semua aksi mundur/merusak ── --}}
                                        @php
                                            $adaMenu = $bolehUbahDraft || $bolehHead || $bolehFinance || $bolehCeo
                                                || $bolehBatal || $bolehHeadSet || $bolehFinSet || $bolehTutup;
                                        @endphp

                                        @if($adaMenu)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $cr->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            {{-- Kode CR membuka drawer ringkas; "Tinjau" membuka
                                                 modal rincian lengkap. Keduanya sengaja berbeda:
                                                 satu untuk melirik, satu untuk memeriksa. --}}
                                            <button type="button" class="dropdown-item btn-detail-cr"
                                                    data-id="{{ $cr->id }}" data-tab="#crPaneInfo">
                                                <i class="fas fa-eye"></i>Tinjau
                                            </button>

                                            @if($bolehSubmit && $bolehUbahDraft)
                                            <a href="{{ url('/budget-request/'.$cr->id.'/edit') }}" class="dropdown-item">
                                                <i class="fas fa-pen"></i>Edit
                                            </a>
                                            @endif

                                            @if($bolehHead)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-head-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehFinance)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-finance-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehCeo)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-ceo-reject"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehHeadSet)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-head-reject-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                <i class="fas fa-times"></i>Tolak settlement
                                            </button>
                                            @endif

                                            @if($bolehFinSet)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-finance-reject-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                <i class="fas fa-times"></i>Tolak settlement
                                            </button>
                                            @endif

                                            @if($bolehTutup)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-manager-reject-settlement"
                                                    data-id="{{ $cr->id }}" data-code="{{ $cr->code }}">
                                                <i class="fas fa-times"></i>Tolak settlement
                                            </button>
                                            @endif

                                            @if($bolehBatal)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-manager-cancel"
                                                    data-id="{{ $cr->id }}">
                                                <i class="fas fa-ban"></i>Cancel
                                            </button>
                                            @endif

                                            @if($bolehUbahDraft)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ url('/budget-request/'.$cr->id) }}"
                                                  method="POST" class="frmDeleteCR">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                        @unless($adaAksi)
                                        <button type="button" class="ts-action-more btn-detail-cr"
                                                data-id="{{ $cr->id }}" data-tab="#crPaneDetails"
                                                title="Lihat detail" aria-label="Lihat detail {{ $cr->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        @endunless

                                    </div>
                                </td>
                            </tr>
@endforeach

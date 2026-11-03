{{--
 | Isi modal Surat Jalan untuk tab Log detail invoice.
 |
 | Hanya baca, dan sengaja TIDAK memuat nilai tagihan: dari sudut pandang
 | Collection yang perlu dipastikan di sini adalah invoice mana saja yang ikut
 | dikirim dan bukti terimanya — bukan angkanya, yang sudah ada di halaman
 | invoice itu sendiri.
 |
 | Potongan ini dirender lewat invoice-cash-receipt.delivery-note, jadi tidak
 | boleh memakai @extends/@section.
--}}

<div class="dnm">

    <div class="dnm-head">
        <div>
            <span class="dnm-code">{{ $deliveryNote->code }}</span>
            <span class="badge badge-{{ $deliveryNote->status_color }} px-2 py-1 align-middle ml-1" style="font-size:10px;">
                {{ $deliveryNote->status_label }}
            </span>
        </div>
        <small class="text-muted">{{ $deliveryNote->customer->name ?? '-' }}</small>
    </div>

    {{-- INFORMASI SURAT JALAN --}}
    <div class="dnm-section">
        <div class="dnm-section-title"><i class="fas fa-info-circle mr-1"></i> Informasi Surat Jalan</div>
        <div class="dnm-grid">
            <div class="dnm-label">Customer</div>
            <div class="dnm-value">{{ $deliveryNote->customer->name ?? '-' }}</div>

            <div class="dnm-label">Alamat</div>
            <div class="dnm-value" style="white-space:pre-line;">{{ $deliveryNote->customer->address ?? '-' }}</div>

            <div class="dnm-label">Dibuat Oleh</div>
            <div class="dnm-value">{{ $deliveryNote->createdBy->name ?? '-' }}</div>

            <div class="dnm-label">Tanggal Kirim</div>
            <div class="dnm-value">{{ $deliveryNote->sent_at ? $deliveryNote->sent_at->format('d/m/Y H:i') : '-' }}</div>

            @if($deliveryNote->status === 'RECEIVED')
            <div class="dnm-label">Diterima Oleh</div>
            <div class="dnm-value">{{ $deliveryNote->receivedBy->name ?? '-' }}</div>

            <div class="dnm-label">Tanggal Diterima</div>
            <div class="dnm-value">{{ $deliveryNote->received_at ? $deliveryNote->received_at->format('d/m/Y H:i') : '-' }}</div>
            @endif

            <div class="dnm-label">Catatan</div>
            <div class="dnm-value" style="white-space:pre-line;">{{ $deliveryNote->notes ?: '-' }}</div>
        </div>
    </div>

    {{-- DAFTAR INVOICE (tanpa nilai tagihan) --}}
    <div class="dnm-section">
        <div class="dnm-section-title">
            <i class="fas fa-file-invoice mr-1"></i> Invoice dalam Surat Jalan ({{ $deliveryNote->items->count() }})
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 dnm-table">
                <thead>
                    <tr>
                        <th style="width:34%;">Kode Invoice</th>
                        <th style="width:18%;">No. PO</th>
                        <th>Purchase Order</th>
                        <th style="width:18%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveryNote->items as $item)
                        @php
                            $inv = $item->invoice;
                            $ini = $inv && $inv->id === $invoice->id;
                        @endphp
                        <tr class="{{ $ini ? 'dnm-row-current' : '' }}">
                            <td>
                                <span class="badge badge-dark px-2 py-1" style="font-size:10px;">{{ $inv->code ?? '-' }}</span>
                                @if($ini)
                                    <span class="dnm-tag-ini">invoice ini</span>
                                @endif
                            </td>
                            <td>
                                @if($inv?->poNumber?->po_number)
                                    <span style="font-family:monospace;font-size:11px;">{{ $inv->poNumber->po_number }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                {{ $inv->po->name ?? '-' }}
                                @if($inv?->po?->code)
                                    <div class="text-muted" style="font-size:10px;">{{ $inv->po->code }}</div>
                                @endif
                            </td>
                            <td><x-status :status="$inv->status ?? null" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- LAMPIRAN SURAT JALAN (semua berkas, termasuk yang tidak terikat ke satu log) --}}
    <div class="dnm-section">
        <div class="dnm-section-title">
            <i class="fas fa-paperclip mr-1"></i> Lampiran Surat Jalan ({{ $deliveryNote->attachments->count() }})
        </div>
        @if($deliveryNote->attachments->isNotEmpty())
        <div class="dnm-chips" style="margin-top:0;">
            @foreach($deliveryNote->attachments as $att)
            <a href="/storage/{{ $att->file_path }}" data-evidence="dnm-sj" data-evidence-name="{{ $att->title ?? 'File' }}" target="_blank" class="dnm-chip">
                <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? 'File' }}
            </a>
            @endforeach
        </div>
        @else
        <div class="text-muted" style="font-size:12px;">Belum ada lampiran.</div>
        @endif
    </div>

    {{-- LOG & LAMPIRAN --}}
    <div class="dnm-section">
        <div class="dnm-section-title"><i class="fas fa-history mr-1"></i> Log &amp; Lampiran</div>
        <div class="dnm-timeline">
            @forelse($deliveryNote->logs as $log)
            <div class="dnm-timeline-item">
                <span class="dnm-timeline-dot badge-{{ $log->action_color }}"></span>
                <div class="dnm-timeline-body">
                    <span class="badge badge-{{ $log->action_color }} px-2 py-1 mr-2" style="font-size:9px;">{{ strtoupper($log->action_label) }}</span>
                    <strong>{{ $log->actor_name ?? '-' }}</strong>
                    <div class="dnm-timeline-date">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                    @if($log->notes)
                    <div class="dnm-timeline-notes">{{ $log->notes }}</div>
                    @endif
                    @if($log->attachments->isNotEmpty())
                    <div class="dnm-chips">
                        @foreach($log->attachments as $att)
                        <a href="/storage/{{ $att->file_path }}" data-evidence="dnm-log-{{ $log->id }}" data-evidence-name="{{ $att->title ?? 'File' }}" target="_blank" class="dnm-chip">
                            <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? 'File' }}
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-3">Belum ada log.</div>
            @endforelse
        </div>
    </div>

</div>

{{-- Satu baris modal settlement tiket (Settlement Custom COA, 25 Okt 2026).
     Dipakai untuk baris dari server DAN <template> baris baru (Pecah / Tambah),
     supaya markup-nya satu sumber. Variabel:
       $k         indeks form (angka, atau '__K__' untuk template)
       $row       baris dari BudgetRequest::settlementFormRows() (null = template)
       $subItems  pilihan Sub COA (BudgetRequest::settlementSubItemOptions())
       $sudahTtd  baris tersimpan ini sudah punya tanda tangan
     Request & Variance dihitung JavaScript PER ITEM ASAL dan hanya ditulis di
     baris pertama item itu, supaya Request tidak terhitung ganda. --}}
@php
    $d        = $row['detail'] ?? null;
    $rowAtts  = $row['attachments'] ?? collect();
    // Baris yatim (item asal sudah tidak ikut) tetap membawa item asalnya.
    $originId = $d->id ?? ($row['orphan_detail_id'] ?? null);
@endphp
<tr class="tt-settle-row"
    data-origin="{{ $originId ?? '' }}"
    data-origin-sub="{{ $d->budget_detail_id ?? '' }}"
    data-req="{{ (int) ($d->total ?? 0) }}"
    data-qty="{{ $d ? (int) $d->qty : '' }}">
    <td>
        <input type="hidden" name="realizations[{{ $k }}][id]" value="{{ $row['id'] ?? '' }}">
        <input type="hidden" name="realizations[{{ $k }}][request_detail_id]" class="tt-settle-origin" value="{{ $originId ?? '' }}">
        <select name="realizations[{{ $k }}][budget_detail_id]" class="form-control form-control-sm tt-settle-sub">
            <option value="">— Pilih Sub COA —</option>
            @foreach($subItems as $si)
                <option value="{{ $si['id'] }}" data-label="{{ $si['label'] }}" @selected((int) ($row['budget_detail_id'] ?? 0) === (int) $si['id'])>{{ $si['label'] }}</option>
            @endforeach
        </select>
        <div class="tt-settle-origin-label text-muted" style="font-size:11px;">
            @if($d)
                {{ $d->description }}
            @elseif($originId)
                <span class="badge badge-danger" style="font-size:9px;">Item asal sudah tidak ikut (takeout)</span>
                <div class="text-danger" style="font-size:10px;">Hapus baris ini sebelum menyimpan.</div>
            @else
                <span class="badge badge-info" style="font-size:9px;">Baris tambahan</span>
            @endif
        </div>
        {{-- Wajib bila baris tambahan, pindah Sub COA, atau total Settle item melebihi Request; diisi lewat kolom Description Settle. --}}
        <input type="hidden" name="realizations[{{ $k }}][change_note]" class="tt-settle-change" value="{{ $row['change_note'] ?? '' }}">
    </td>
    <td>
        <input type="text" name="realizations[{{ $k }}][notes]" class="form-control form-control-sm" maxlength="255" placeholder="Catatan settle..." value="{{ $row['notes'] ?? '' }}">
        <div class="d-flex mt-1" style="gap:4px;">
            <input type="date" name="realizations[{{ $k }}][transaction_date]" class="form-control form-control-sm" aria-label="Tanggal transaksi" title="Tanggal transaksi" value="{{ $row['transaction_date'] ?? '' }}">
            <input type="text" name="realizations[{{ $k }}][vendor_name]" class="form-control form-control-sm" maxlength="150" placeholder="Vendor" aria-label="Vendor" value="{{ $row['vendor_name'] ?? '' }}">
        </div>
    </td>
    <td class="text-right align-middle tt-settle-qty"></td>
    <td class="text-right align-middle tt-settle-req"></td>
    <td><input type="text" inputmode="numeric" name="realizations[{{ $k }}][amount]" class="form-control form-control-sm text-right tt-settle-amount" value="{{ number_format((int) ($row['amount'] ?? 0), 0, ',', '.') }}"></td>
    <td class="text-right align-middle tt-settle-var">0</td>
    {{-- Kolom aksi ikon saja: kotak input berkas bawaan peramban dan tombol
         berteks membuat baris melebar jauh melebihi isinya. --}}
    <td class="text-center">
        <input type="file" id="ttAtt{{ $k }}" name="realizations[{{ $k }}][attachments][]" class="d-none tt-settle-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
        <button type="button" class="btn btn-outline-secondary btn-sm tt-icon-btn tt-att-btn" title="Lampiran item" data-target="ttAtt{{ $k }}">
            <i class="fas fa-paperclip"></i>
        </button>
        <div class="tt-file-count text-muted" style="font-size:10px;">{{ $rowAtts->count() ? $rowAtts->count() . ' tersimpan' : '' }}</div>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm tt-icon-btn tt-esign-btn {{ ($sudahTtd ?? false) ? 'btn-success' : 'btn-outline-secondary' }}" title="E-sign penerima dana">
            <i class="fas fa-signature"></i>
        </button>
        {{-- Indeks sama dengan realizations[k]: server menyimpan tanda tangan ke
             ID baris realisasi hasil simpanan baris ini, bukan ke nomor urut. --}}
        <input type="hidden" name="signatures[{{ $k }}]" class="tt-esign-value">
        <input type="hidden" name="recipient_names[{{ $k }}]" class="tt-esign-name">
        <div class="tt-esign-state text-muted" style="font-size:10px;">{{ ($sudahTtd ?? false) ? 'tertanda' : '' }}</div>
    </td>
    <td class="text-center text-nowrap">
        <button type="button" class="btn btn-outline-secondary btn-sm tt-icon-btn tt-split-row" title="Pecah: tambah baris Sub COA lain untuk item ini" style="{{ $d ? '' : 'display:none;' }}">
            <i class="fas fa-code-branch"></i>
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm tt-icon-btn tt-remove-row" title="Hapus baris ini">
            <i class="fas fa-trash"></i>
        </button>
    </td>
</tr>

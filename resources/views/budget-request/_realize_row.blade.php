{{-- Satu baris form Submit Settlement (Settlement Custom COA, 25 Okt 2026).

     Dipakai untuk baris dari server DAN sebagai <template> baris baru (tombol
     Pecah / Tambah Baris), supaya markup-nya satu sumber. Variabel:
       $k           indeks form (angka, atau '__K__' untuk template)
       $rowId       id baris realisasi tersimpan (null = baris baru)
       $detail      item pengajuan asal (null = baris tambahan / template)
       $bdId        Sub COA terpilih
       $orphanId    request_detail_id baris yatim (item asal sudah tidak ikut), null bila bukan
       $notes, $changeNote, $qty, $amount, $attachments
       $subItems    pilihan Sub COA (BudgetRequest::settlementSubItemOptions())
       $isPrimary   baris pertama item pengajuan — tidak bisa dihapus (4 Nov 2026)

     Baris item pengajuan ($detail) dan pecahannya: Sub COA DIKUNCI ke Sub COA
     item asal (hidden input .realize-sub-select, bukan <select>); hanya
     deskripsi, qty dan nilai yang disunting. Baris tambahan tetap memilih.

     Kolom Qty / Request / Variance diisi JavaScript per ITEM ASAL: hanya baris
     pertama tiap item yang menampilkan angkanya, supaya Request tidak
     terhitung ganda saat item dipecah.

     Redesain visual (M4): markup tombol/penanda memakai komponen Tailwind
     (x-tw.*); kelas hook JS (realize-*, btn-note-item, btn-split-row,
     btn-remove-row, btn-detail-att, detail-att-*, att-lama*) dan data-*
     TIDAK berubah. --}}
@php
    $originSub = $detail?->budget_detail_id;
    // Baris yatim tetap membawa item asalnya supaya tautannya tidak hilang diam-diam.
    $originId  = $detail->id ?? ($orphanId ?? null);
    $isPrimary = $isPrimary ?? false;
    $subLabel  = $detail
        ? ($subItems->firstWhere('id', (int) $originSub)['label']
            ?? trim(($detail->account?->name ?? '-') . ' — ' . ($detail->budgetDetail?->deskripsi ?: '-')))
        : null;
@endphp
<tr class="realize-row"
    data-row="{{ $k }}"
    data-primary="{{ $isPrimary ? '1' : '' }}"
    data-origin="{{ $originId ?? '' }}"
    data-origin-sub="{{ $originSub ?? '' }}"
    data-req="{{ (int) ($detail->total ?? 0) }}"
    data-qty="{{ $detail ? rtrim(rtrim(number_format($detail->qty, 2, ',', '.'), '0'), ',') : '' }}">
    <td class="align-middle realize-desc-request-cell">
        <input type="hidden" name="realizations[{{ $k }}][id]" class="realize-row-id" value="{{ $rowId ?? '' }}">
        <input type="hidden" name="realizations[{{ $k }}][request_detail_id]" class="realize-origin-input" value="{{ $originId ?? '' }}">
        @if($detail)
        <input type="hidden" name="realizations[{{ $k }}][budget_detail_id]" class="realize-sub-select" value="{{ $originSub ?? '' }}">
        <div class="realize-sub-locked" title="Sub COA item pengajuan tidak dapat diganti">
            <span class="text-truncate">{{ $subLabel }}</span>
            <i class="fas fa-lock" aria-hidden="true"></i>
        </div>
        @else
        <select name="realizations[{{ $k }}][budget_detail_id]" class="form-control form-control-sm realize-sub-select" aria-label="Sub COA">
            <option value="">— Pilih Sub COA —</option>
            {{-- Dikelompokkan per akun: judul akun (tebal, tidak bisa diklik), lalu nama Sub COA.
                 Label penuh "Akun — Sub COA" tetap dipakai untuk kotak terpilih (data-label). --}}
            @foreach($subItems->groupBy('account') as $akun => $grup)
                <optgroup label="{{ $akun }}">
                    @foreach($grup as $si)
                        <option value="{{ $si['id'] }}" data-label="{{ $si['label'] }}" data-item-label="{{ $si['sub_coa'] }}" data-search="{{ $akun }}" @selected((int) $bdId === (int) $si['id'])>{{ $si['sub_coa'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @endif
        {{-- Penanda pecahan: tampil (JS refreshSplitBadges) hanya bila item asal punya >1 baris. --}}
        <div class="realize-split-badge tw:mt-1" style="display:none;">
            @if($isPrimary)
                <x-tw.badge tone="positive">Sub Utama</x-tw.badge>
            @else
                <x-tw.badge tone="attention">Sub Pecahan</x-tw.badge>
            @endif
        </div>
        {{-- Keterangan item asal tidak ditampilkan di bawah Sub COA; rincian lengkap lewat tombol "View Request". --}}
        @if(! $detail)
        <div class="realize-origin-label tw:mt-1 tw:text-caption tw:leading-body tw:text-text-dim">
            @if($originId)
                <x-tw.badge tone="danger">Item asal sudah tidak ikut (takeout)</x-tw.badge>
                <div class="tw:mt-1 tw:text-danger">Hapus baris ini sebelum menyimpan.</div>
            @else
                <x-tw.badge tone="progress">Baris tambahan</x-tw.badge>
            @endif
        </div>
        @endif
        <div class="realize-moved-label tw:mt-1 tw:text-caption tw:text-warning" style="display:none;"><i class="fas fa-random tw:mr-1" aria-hidden="true"></i>Pindah Sub COA</div>
        {{-- Peringatan saja (tidak memblokir): total Settle di Sub COA ini melebihi sisa budget-nya. --}}
        <div class="realize-budget-warn tw:mt-1 tw:text-caption tw:text-danger" style="display:none;"></div>
    </td>
    <td>
        <div class="desc-wrap">
            <input type="text" name="realizations[{{ $k }}][notes]"
                   class="form-control form-control-sm realize-notes-input"
                   maxlength="255" aria-label="Description Settle"
                   value="{{ $notes ?? '' }}" placeholder="Catatan settle...">
            <button type="button" class="desc-expand" title="Lihat / ubah deskripsi lengkap" aria-label="Lihat / ubah deskripsi lengkap" tabindex="-1">
                <i class="fas fa-expand-alt" aria-hidden="true"></i>
            </button>
        </div>
    </td>
    <td class="text-nowrap">
        <input type="text" inputmode="numeric"
               name="realizations[{{ $k }}][amount]"
               class="form-control form-control-sm text-right realize-amount-input"
               aria-label="Harga Satuan"
               value="{{ (int) ($amount ?? 0) }}" placeholder="0">
    </td>
    <td class="align-middle realize-qty-cell">
        <input type="text" inputmode="numeric" maxlength="3"
               name="realizations[{{ $k }}][qty]"
               class="form-control form-control-sm text-center realize-qty-input"
               aria-label="Qty Settle"
               value="{{ max(1, (int) ($qty ?? ($detail->qty ?? 1))) }}">
    </td>
    {{-- Settle = Harga x Qty (dihitung JS; server menghitung ulang saat simpan). --}}
    <td class="text-right text-nowrap align-middle tw:font-heading tw:tabular-nums realize-total-cell">0</td>
    <td class="text-nowrap detail-att-cell" tabindex="0">
        <input type="file" name="realizations[{{ $k }}][attachments][]" class="d-none detail-att-input" multiple
               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
        <button type="button" class="btn-detail-att detail-att-drop"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><span class="detail-att-label">Lampiran</span></button>
        {{-- Lampiran dari simpanan sebelumnya pada baris INI (mis. settlement
             yang sempat ditolak) tetap ditampilkan supaya tidak perlu diunggah
             ulang. Centang untuk membuangnya. --}}
        @if(($attachments ?? collect())->isNotEmpty())
        <div class="realize-att-existing tw:mt-1 tw:text-caption tw:leading-body">
            @foreach($attachments as $att)
            <div class="att-lama tw:text-text-dim">
                <input type="checkbox" name="delete_realization_attachments[]"
                       value="{{ $att->id }}" class="del-realize-att d-none">
                <i class="fas fa-file tw:mr-1" aria-hidden="true"></i>
                <a href="#" class="att-lama-open"
                   data-url="{{ asset('storage/' . $att->path) }}"
                   data-nama="{{ $att->original_name }}">{{ $att->original_name }}</a>
                <i class="fas fa-times text-danger att-lama-del ml-1" style="cursor:pointer;" title="Hapus lampiran ini"></i>
            </div>
            @endforeach
        </div>
        @endif
        <div class="detail-att-list tw:mt-1 tw:text-caption tw:leading-body"></div>
        {{-- Settle > 0 tanpa lampiran: wajib (ditoggle JS nomorTanpaLampiran). --}}
        <div class="att-wajib-badge tw:mt-1 text-center" style="display:none;"><x-tw.badge tone="danger" title="Lampiran wajib karena nilai Settle baris ini lebih dari 0. Unggah bukti belanja (nota/kuitansi/foto) sebelum Submit.">Wajib</x-tw.badge></div>
    </td>
    {{-- Catatan perubahan. Ikon saja supaya kolomnya tidak melebar; isinya dibuka
         di pop-up. Wajib untuk baris tambahan, baris yang pindah Sub COA, dan item
         yang total Settle-nya melebihi Request (lihat validasi submit). --}}
    <td class="text-center align-middle realize-note-cell">
        <input type="hidden" name="realizations[{{ $k }}][change_note]"
               class="realize-change-note" value="{{ $changeNote ?? '' }}">
        <x-tw.button size="sm" variant="secondary" class="btn-note-item" title="Isi catatan perubahan"><i class="fas fa-sticky-note" aria-hidden="true"></i> Note</x-tw.button>
        <div class="note-item-preview tw:mx-auto tw:mt-1 tw:max-w-[120px] tw:text-caption tw:leading-tight tw:text-text-dim"></div>
    </td>
    <td class="text-center text-nowrap align-middle">
        <x-tw.button size="sm" variant="secondary" class="btn-split-row" title="Pecah: tambah baris untuk item ini (Sub COA sama)" aria-label="Pecah baris ini"
                     style="{{ $detail ? '' : 'display:none;' }}"><i class="fas fa-code-branch" aria-hidden="true"></i></x-tw.button>
        @unless($isPrimary)
        <x-tw.button size="sm" variant="danger" class="btn-remove-row" title="Hapus baris ini" aria-label="Hapus baris ini"><i class="fas fa-trash" aria-hidden="true"></i></x-tw.button>
        @endunless
    </td>
</tr>

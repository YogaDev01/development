{{--
    Filter multi-pilih bergaya tombol-dropdown (seperti filter kolom di halaman lain).
    Param:
      $name     string  — nama field GET (dikirim sebagai $name[])
      $label    string  — teks tombol
      $options  iterable — item {value|id, label|name}
      $selected iterable — daftar value terpilih
--}}
@php
    $mfOpts = collect($options)->map(fn ($o) => [
        'value' => (string) ($o['value'] ?? $o['id']),
        'label' => $o['label'] ?? $o['name'],
    ]);
    $mfSelected = collect($selected ?? [])->map(fn ($v) => (string) $v);
    $mfCount = $mfSelected->count();
@endphp
<div class="col-mf" data-name="{{ $name }}">
    <button type="button" class="btn btn-sm btn-outline-secondary col-mf-btn {{ $mfCount ? 'col-mf-active' : '' }}">
        {{ $label }}
        <span class="col-mf-badge" @if(!$mfCount) hidden @endif>{{ $mfCount }}</span>
        <i class="fas fa-caret-down ml-1"></i>
    </button>
    <div class="col-mf-menu" hidden>
        {{-- Kotak cari bisa dimatikan ($search = false) untuk daftar pilihan yang
             pendek & tetap (mis. ember Aging): di daftar lima baris, kotak cari
             justru menambah langkah sebelum pilihannya terlihat. --}}
        @if($search ?? true)
        <input type="text" class="form-control form-control-sm col-mf-search" placeholder="Cari...">
        @endif
        <div class="col-mf-list">
            @forelse($mfOpts as $opt)
            <label class="col-mf-item">
                <input type="checkbox" value="{{ $opt['value'] }}" @checked($mfSelected->contains($opt['value']))>
                <span>{{ $opt['label'] }}</span>
            </label>
            @empty
            <div class="text-muted" style="font-size:12px;padding:4px 6px;">Tidak ada data</div>
            @endforelse
        </div>
    </div>
    <span class="col-mf-inputs">
        @foreach($mfSelected as $v)
        <input type="hidden" name="{{ $name }}[]" value="{{ $v }}">
        @endforeach
    </span>
</div>

{{--
    Rekap SLA site × bulan (hanya baca) untuk Summary Operations.
    Variabel: $sites, $matrix [site_id => [bulan => nilai]], $year, $tableId.
--}}
@php
    $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
@endphp
<div class="table-responsive">
<table id="{{ $tableId }}" class="table table-sm table-bordered table-hover mb-0" style="font-size:12px">
    <thead class="thead-light">
        <tr>
            <th class="text-center">No</th>
            <th>Customer</th>
            <th>Site ID</th>
            <th>Nama Lokasi</th>
            <th>Provinsi</th>
            <th>Kabupaten/Kota</th>
            <th>Supervisor</th>
            <th>Teknisi</th>
            @foreach (\App\Models\SiteSlaPeriod::MONTHS as $label)
                <th class="text-center bg-primary text-white">{{ $label }}</th>
            @endforeach
            <th class="text-center text-nowrap">Average {{ $year }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sites as $i => $s)
            @php $row = $matrix[$s->id] ?? []; $avg = \App\Models\SiteSla::average($row); @endphp
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $s->customer?->initial ?: ($s->customer?->name ?? '-') }}</td>
                <td class="text-nowrap">{{ $s->tid }}</td>
                <td class="text-nowrap">{{ $s->sitename }}</td>
                <td class="text-nowrap">{{ $s->provinsi->name ?? '-' }}</td>
                <td class="text-nowrap">{{ $s->kota->name ?? '-' }}</td>
                <td class="text-nowrap">{{ $s->supervisorPic()?->pic }}</td>
                <td class="text-nowrap">{{ $s->technicianName() }}</td>
                @foreach (\App\Models\SiteSlaPeriod::MONTHS as $m => $label)
                    @php $v = $row[$m] ?? null; @endphp
                    <td class="text-right {{ $v !== null && $v < 100 ? 'text-danger' : '' }}" data-order="{{ $v ?? -1 }}">{{ $fmt($v) }}</td>
                @endforeach
                <td class="text-right font-weight-bold {{ $avg !== null && $avg < 100 ? 'text-danger' : '' }}" data-order="{{ $avg ?? -1 }}">{{ $fmt($avg) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>

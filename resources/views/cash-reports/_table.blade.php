@php
    $cell = function ($col, $val) {
        if ($val === null || $val === '') {
            return '';
        }

        return match ($col['type']) {
            'money' => ($val < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $val), 0, ',', '.'),
            'int'   => number_format((int) $val, 0, ',', '.'),
            default => $val,
        };
    };
@endphp
<div class="table-responsive">
    <table id="tblCashReport" class="table table-bordered table-hover mb-0 ts-idx-tbl" style="font-size:13px;">
        <thead class="bg-light">
            <tr>
                @foreach($data['columns'] as $c)
                    <th scope="col" class="{{ in_array($c['type'], ['money', 'int'], true) ? 'text-right' : '' }} text-nowrap">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @forelse($data['rows'] as $r)
            <tr>
                @foreach($data['columns'] as $c)
                    @php $v = $r[$c['key']] ?? ''; @endphp
                    <td class="{{ in_array($c['type'], ['money', 'int'], true) ? 'text-right' : '' }} {{ ($c['type'] === 'money' && is_numeric($v) && $v < 0 && $c['key'] !== 'out') ? 'text-danger' : '' }}"
                        @if(in_array($c['type'], ['money', 'int'], true) && is_numeric($v)) data-order="{{ (int) $v }}" @endif>{{ $cell($c, $v) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($data['columns']) }}"><x-empty title="Tidak ada data" message="Tidak ada data untuk filter ini." /></td></tr>
        @endforelse
        </tbody>
        @if($data['totals'])
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    @foreach($data['columns'] as $c)
                        <td class="{{ in_array($c['type'], ['money', 'int'], true) ? 'text-right' : '' }}">{{ $cell($c, $data['totals'][$c['key']] ?? '') }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@if(! empty($data['notes']))
    <ul class="text-muted mt-2 mb-0 pl-3" style="font-size:12px;">@foreach($data['notes'] as $n)<li>{{ $n }}</li>@endforeach</ul>
@endif

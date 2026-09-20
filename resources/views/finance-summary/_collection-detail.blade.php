{{--
    Tabel detail collapsible di bawah chart Collection.
    Param:
      $id           string  — id unik panel
      $groups       array   — [ tabLabel => [ ['customer','manager','count','amount'], ... ] ]
      $amountLabel  string  — header kolom nominal
      $amountColor  string  — inline style opsional untuk sel nominal (mis. "color:var(--danger);")
--}}
@php
    $groups = is_array($groups) ? $groups : collect($groups)->all();
    $tabs = array_keys($groups);
@endphp
<div class="col-detail">
    <button type="button" class="col-detail-toggle js-col-detail-toggle" aria-expanded="false" data-target="{{ $id }}">
        <i class="fas fa-table mr-2"></i> Detail data
        <i class="fas fa-chevron-down col-detail-caret ml-auto"></i>
    </button>
    <div class="col-detail-body" id="{{ $id }}" hidden>
        <div class="col-detail-tabs">
            @foreach($tabs as $ti => $t)
            <button type="button" class="col-detail-tab js-col-detail-tab {{ $ti === 0 ? 'active' : '' }}"
                    data-detail-group="{{ $id }}" data-detail-idx="{{ $ti }}">
                {{ $t }} <span class="col-detail-tab-count">{{ count($groups[$t]) }}</span>
            </button>
            @endforeach
        </div>
        @foreach($tabs as $ti => $t)
        @php
            $rows     = $groups[$t];
            $sub      = collect($rows)->sum('amount');
            $subCount = collect($rows)->sum('count');
        @endphp
        <div class="col-detail-pane {{ $ti === 0 ? '' : 'd-none' }}" data-detail-group="{{ $id }}" data-detail-idx="{{ $ti }}">
            <div class="table-responsive">
                <table class="table table-sm mb-0 col-detail-table js-sortable-table">
                    <thead>
                        <tr>
                            <th scope="col" class="js-sort" data-sort-type="text">Customer</th>
                            <th scope="col" class="js-sort" data-sort-type="text">Manager</th>
                            <th scope="col" class="text-right js-sort" data-sort-type="num">Qty</th>
                            <th scope="col" class="text-right js-sort" data-sort-type="num">{{ $amountLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['customer'] }}</td>
                            <td>{{ $r['manager'] }}</td>
                            <td class="text-right">{{ $r['count'] }}</td>
                            <td class="text-right" style="font-weight:600;{{ $amountColor ?? '' }}">{{ number_format($r['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($rows))
                    <tfoot>
                        <tr class="col-detail-subtotal">
                            <td colspan="2">Subtotal</td>
                            <td class="text-right">{{ $subCount }}</td>
                            <td class="text-right">{{ number_format($sub, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
        @endforeach
    </div>
</div>

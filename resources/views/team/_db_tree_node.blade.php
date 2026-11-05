{{--
    Satu baris pohon team (rekursif). Angka = orang unik termasuk sub-team.
    Variabel: $node (TeamController::buildTeamTree).
--}}
@php $t = $node['team']; @endphp
<li>
    <div class="td-row">
        @if($node['children'])
        <button type="button" class="td-fold" aria-expanded="true" title="Ciutkan / bentangkan {{ $t->name }}"><i class="fas fa-minus" aria-hidden="true"></i></button>
        @else
        <span class="td-fold-gap"></span>
        @endif
        <button type="button" class="td-pick {{ $t->status === 'ACTIVE' ? '' : 'is-off' }}" data-team="{{ $t->id }}">
            <span>{{ $t->name }}</span>
            <span class="td-count">{{ count($node['orang']) }} org</span>
        </button>
    </div>
    @if($node['children'])
    <ul>
        @foreach($node['children'] as $anak)
            @include('team._db_tree_node', ['node' => $anak])
        @endforeach
    </ul>
    @endif
</li>

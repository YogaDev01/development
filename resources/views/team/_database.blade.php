{{--
    Isi tab "Database" pada daftar Team: pohon team (kiri) + detail team
    terpilih (kanan). Filter tetap di server; team yang tidak lolos filter
    tidak ikut, dan team yang atasannya tersaring tampil sebagai akar.

    Variabel: $teamTree (TeamController::buildTeamTree), $denganInduk.
--}}
@push('styles')
<style>
    .td-layout { display: grid; grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); gap: var(--sp-4); align-items: start; }
    .td-tree-box { border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); padding: var(--sp-2); max-height: 75vh; overflow: auto; }
    .td-tree, .td-tree ul { list-style: none; margin: 0; padding: 0; }
    .td-tree ul { margin-left: 13px; padding-left: 8px; border-left: 1px solid var(--border); }
    .td-tree ul[hidden] { display: none; }
    .td-row { display: flex; align-items: center; gap: 4px; }
    .td-fold { flex: 0 0 20px; width: 20px; height: 20px; padding: 0; border: 1px solid var(--border-strong); border-radius: 4px; background: var(--surface); color: var(--text-dim); font-size: 9px; display: inline-flex; align-items: center; justify-content: center; }
    .td-fold-gap { flex: 0 0 20px; }
    .td-pick { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: var(--sp-2); padding: 6px 8px; border: 0; border-radius: var(--radius-md); background: transparent; color: var(--text); font-size: var(--fs-body); font-weight: 600; text-align: left; }
    .td-pick:hover { background: var(--surface-2); }
    .td-pick.is-active { background: var(--accent-tint); color: var(--accent-text); }
    .td-pick.is-off { color: var(--text-faint); font-weight: 500; }
    .td-count { flex: none; font-size: var(--fs-caption); font-weight: 500; color: var(--text-dim); font-variant-numeric: tabular-nums; white-space: nowrap; }

    .td-panel { display: flex; flex-direction: column; gap: var(--sp-4); }
    .td-panel[hidden] { display: none; }
    .td-box { border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); }
    .td-box-head { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sp-2); padding: var(--sp-3) var(--sp-4); border-bottom: 1px solid var(--border); }
    .td-title { font-size: var(--fs-body); font-weight: 700; color: var(--text); }
    .td-sub { font-size: var(--fs-caption); color: var(--text-dim); }
    .td-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-left: auto; }
    .td-actions form { margin: 0; }
    .td-info { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: var(--sp-2) var(--sp-4); margin: 0; padding: var(--sp-4); font-size: var(--fs-label); }
    .td-info dt { font-weight: 400; color: var(--text-dim); }
    .td-info dd { margin: 0; color: var(--text); }
    .td-link { padding: 0; border: 0; background: none; color: var(--accent-text); font-weight: 600; }
    .td-link:hover { text-decoration: underline; }
    .td-person { display: flex; align-items: center; gap: var(--sp-2); }
    .td-avatar { flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; background: var(--accent-tint); color: var(--accent-text); font-size: var(--fs-caption); font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .td-person strong { display: block; font-size: var(--fs-label); color: var(--text); }
    .td-person span span, .td-person small { font-size: var(--fs-caption); color: var(--text-dim); }

    @media (max-width: 900px) {
        .td-layout { grid-template-columns: minmax(0, 1fr); }
        .td-tree-box { max-height: 45vh; }
    }
    @media (max-width: 640px) {
        .td-info { grid-template-columns: minmax(0, 1fr); gap: 2px; }
        .td-info dd { margin-bottom: var(--sp-2); }
        .td-actions { margin-left: 0; }
    }
</style>
@endpush

@if(empty($teamTree))
    <x-empty icon="fas fa-sitemap" title="Tidak ada team" message="Tidak ada team yang cocok dengan filter ini." />
@else
<div class="td-layout">
    <div class="td-tree-box">
        <ul class="td-tree" aria-label="Struktur team">
            @foreach($teamTree as $node)
                @include('team._db_tree_node', ['node' => $node])
            @endforeach
        </ul>
    </div>
    <div>
        @foreach($teamTree as $node)
            @include('team._db_panel', ['node' => $node, 'atas' => null])
        @endforeach
    </div>
</div>
@endif

@push('scripts')
<script>
$(function () {
    var KUNCI = 'tesys.team.terpilih';

    function pilih(id, gulir) {
        var panel = document.getElementById('tdPanel-' + id);
        if (!panel) return false;
        $('.td-panel').prop('hidden', true);
        panel.hidden = false;
        $('.td-pick').removeClass('is-active').removeAttr('aria-current');
        var $btn = $('.td-pick[data-team="' + id + '"]').addClass('is-active').attr('aria-current', 'true');
        $btn.parents('ul[hidden]').each(function () { lipat($(this).prev('.td-row').find('.td-fold'), true); });
        try { sessionStorage.setItem(KUNCI, id); } catch (e) {}
        if (gulir && window.matchMedia('(max-width: 900px)').matches) panel.scrollIntoView({ block: 'start' });
        return true;
    }

    function lipat($btn, buka) {
        $btn.closest('.td-row').next('ul').prop('hidden', !buka);
        $btn.attr('aria-expanded', buka ? 'true' : 'false')
            .html('<i class="fas fa-' + (buka ? 'minus' : 'plus') + '" aria-hidden="true"></i>');
    }

    $('.td-layout').on('click', '.td-pick, .td-link[data-team]', function () { pilih(this.dataset.team, true); })
        .on('click', '.td-fold', function () { lipat($(this), this.getAttribute('aria-expanded') !== 'true'); });

    var tersimpan = null;
    try { tersimpan = sessionStorage.getItem(KUNCI); } catch (e) {}
    if (!tersimpan || !pilih(tersimpan, false)) pilih($('.td-pick').first().data('team'), false);
});
</script>
@endpush

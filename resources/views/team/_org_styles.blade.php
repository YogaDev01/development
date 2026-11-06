{{--
    Gaya kartu orang dan tampilan satu team, dipakai bersama oleh tab Organisasi
    (team._organization) dan tab Team di profil (profile._team).
--}}
@push('styles')
<style>
    .oc-empty { font-size: var(--fs-caption); color: var(--text-faint); padding: var(--sp-2); }
    .oc-card { width: 150px; display: flex; flex-direction: column; align-items: center; background: transparent; border: 0; padding: 0; color: var(--text); position: relative; z-index: 1; }
    .oc-photo { width: 64px; height: 64px; border-radius: 50%; border: 3px solid var(--accent); background: var(--surface-2); color: var(--text-dim); display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 16px; font-weight: 700; box-shadow: 0 0 0 3px var(--surface); }
    .oc-photo img { width: 100%; height: 100%; object-fit: cover; }
    /* Label pill di bawah foto: jabatan tebal + nama. Putih di atas aksen = pola yang sama dengan tombol primer TESYS. */
    .oc-label { margin-top: -10px; width: 100%; background: var(--accent); color: #fff; border-radius: 12px; padding: 5px 8px 6px; line-height: 1.2; display: flex; flex-direction: column; text-align: center; box-shadow: 0 0 0 2px var(--surface); }
    .oc-label strong { font-size: 11.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-label span { font-size: 11px; opacity: .92; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-card.is-leader .oc-photo { width: 76px; height: 76px; }
    .oc-card:not(.is-leader) .oc-label { background: var(--surface); color: var(--text); border: 1.5px solid var(--accent); }
    .oc-card:not(.is-leader) .oc-label span { color: var(--text-dim); opacity: 1; }
    .oc-card:hover .oc-photo, .oc-card:focus-visible .oc-photo { box-shadow: 0 0 0 3px var(--accent-tint-2); }
    .oc-card.is-me .oc-photo { box-shadow: 0 0 0 3px var(--accent-tint-2), 0 0 0 6px var(--accent-soft); }
    .oc-card.is-hit .oc-label, .oc-card.is-twin .oc-label { outline: 2px solid var(--warning); outline-offset: 2px; }
    .oc-tags { display: flex; flex-wrap: wrap; justify-content: center; gap: 3px; margin-top: 4px; max-width: 150px; }
    .oc-tag { display: inline-block; padding: 0 6px; border-radius: var(--radius-pill); background: var(--surface-2); border: 1px solid var(--border); color: var(--text-dim); font-size: 9.5px; font-weight: 600; line-height: 15px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-tag.is-me { background: var(--accent-tint); border-color: var(--accent-tint-2); color: var(--accent-text); }
    .oc-tag.is-lead { background: var(--info-bg); border-color: var(--info-bg); color: var(--info); }
    .oc-tag.is-dup { background: var(--warning-bg); border-color: var(--warning-bg); color: var(--warning); }

    /* ---------- Detail Member: satu team, anggota membungkus ke bawah (tanpa scroll mendatar). ---------- */
    .ot-view { border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface); padding: var(--sp-4); }
    .ot-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: var(--sp-2); }
    .ot-title { font-size: var(--fs-body); font-weight: 700; color: var(--text); }
    .ot-sub { font-size: var(--fs-caption); color: var(--text-dim); }
    .ot-nav { display: flex; flex-wrap: wrap; gap: var(--sp-1) var(--sp-4); margin-top: var(--sp-2); }
    .ot-nav-group { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .ot-nav-label { font-size: var(--fs-caption); color: var(--text-faint); }
    .ot-body { display: flex; flex-direction: column; align-items: center; margin-top: var(--sp-4); }
    .ot-tier { position: relative; width: 100%; display: flex; flex-direction: column; align-items: center; padding-top: 28px; margin-top: 6px; }
    .ot-tier::before { content: ''; position: absolute; top: 0; left: 50%; height: 28px; border-left: 2px solid var(--accent); }
    .ot-tier-label { padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-pill); background: var(--surface-2); color: var(--text-dim); font-size: var(--fs-caption); font-weight: 600; line-height: 20px; }
    .ot-cards { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--sp-4) 20px; margin-top: var(--sp-3); }
    .ot-view .oc-card { width: 170px; }
    .ot-view .oc-tags, .ot-view .oc-tag { max-width: 170px; }
    /* Orang yang menyambungkan team di atas dengan team terpilih. */
    .oc-card.is-link .oc-label { outline: 2px solid var(--accent-soft); outline-offset: 2px; }
    .ot-body[data-above] > .ot-tier-label { margin-bottom: var(--sp-3); }
    .ot-tier + .ot-body { margin-top: var(--sp-3); }
    .os-code { display: inline-block; margin-left: 6px; padding: 0 8px; border-radius: var(--radius-pill); border: 1px solid var(--border); color: var(--text-dim); font-size: var(--fs-caption); font-weight: 600; }
</style>
@endpush

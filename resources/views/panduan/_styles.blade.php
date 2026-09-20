<style>
/* ── Panduan Penggunaan ── */
.pnd-layout { display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start; }
@media (max-width: 991.98px) { .pnd-layout { grid-template-columns: 1fr; } }

.pnd-side {
    position: sticky; top: 16px;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 10px; max-height: calc(100vh - 90px); overflow-y: auto;
}
@media (max-width: 991.98px) { .pnd-side { position: static; max-height: none; } }

.pnd-side-group { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
    color: var(--text-faint); padding: 12px 10px 6px; }
.pnd-side a {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; border-radius: var(--radius-md); font-size: 13px; color: var(--text-dim); text-decoration: none;
}
.pnd-side a:hover { background: var(--info-bg); color: var(--text); }
.pnd-side a.active { background: var(--warning-bg); color: var(--warning); font-weight: 600; }
.pnd-side a i { width: 16px; text-align: center; color: var(--text-faint); font-size: 12px; }
.pnd-side a.active i { color: var(--warning); }

.pnd-content { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 26px 30px; }
@media (max-width: 575.98px) { .pnd-content { padding: 18px; } }

.pnd-breadcrumb { font-size: 12px; color: var(--text-faint); margin-bottom: 6px; }
.pnd-content h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: var(--text); }
.pnd-lead { font-size: 14px; color: var(--text-dim); margin-bottom: 22px; line-height: 1.6; }

.pnd-content h2 {
    font-size: 15px; font-weight: 700; color: var(--text);
    margin: 26px 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border);
}
.pnd-content h2:first-of-type { margin-top: 0; }
.pnd-content h3 { font-size: 13.5px; font-weight: 700; color: var(--info); margin: 16px 0 6px; }
.pnd-content p { font-size: 13.5px; line-height: 1.7; color: var(--text-dim); margin: 0 0 10px; }
.pnd-content ul, .pnd-content ol { font-size: 13.5px; line-height: 1.75; color: var(--text-dim); padding-left: 22px; margin: 0 0 12px; }
.pnd-content li { margin-bottom: 4px; }
.pnd-content strong { color: var(--text); }
.pnd-content code {
    background: var(--info-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 1px 5px; font-size: 12px; color: var(--warning);
}

.pnd-callout {
    display: flex; gap: 10px; align-items: flex-start;
    background: var(--info-bg); border: 1px solid var(--border-strong); border-left: 3px solid var(--info);
    border-radius: var(--radius-md); padding: 12px 14px; margin: 14px 0; font-size: 13px; color: var(--text); line-height: 1.6;
}
.pnd-callout.warn { background: var(--warning-bg); border-color: var(--border-strong); border-left-color: #f59e0b; }
.pnd-callout.tip  { background: var(--success-bg); border-color: var(--border-strong); border-left-color: #22c55e; }
.pnd-callout i { margin-top: 2px; }
.pnd-callout.warn i { color: var(--warning); }
.pnd-callout.tip i  { color: var(--success); }
.pnd-callout i { color: var(--info); }

.pnd-roles { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 14px; }
.pnd-role {
    font-size: 11.5px; font-weight: 600; background: var(--info-bg); color: var(--text-dim);
    border-radius: var(--radius-pill); padding: 3px 11px;
}

.pnd-steps { counter-reset: pnd; list-style: none; padding-left: 0; }
.pnd-steps > li {
    position: relative; padding-left: 34px; margin-bottom: 12px;
    font-size: 13.5px; line-height: 1.7; color: var(--text-dim);
}
.pnd-steps > li::before {
    counter-increment: pnd; content: counter(pnd);
    position: absolute; left: 0; top: 0;
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--warning-bg); color: var(--warning); font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}

.pnd-status-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 8px 0 16px; }
.pnd-status-flow .pnd-chip {
    font-size: 11.5px; font-weight: 700; letter-spacing: .3px;
    background: var(--info-bg); color: var(--info); border-radius: var(--radius-sm); padding: 4px 9px;
}
.pnd-status-flow .pnd-arrow { color: var(--text-faint); font-size: 11px; }

/* Index cards */
.pnd-index-group { margin-bottom: 26px; }
.pnd-index-group h2 {
    font-size: 14px; font-weight: 700; color: var(--text); margin: 0 0 12px;
    display: flex; align-items: center; gap: 8px;
}
.pnd-index-group h2 i { color: var(--warning); }
.pnd-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
.pnd-card {
    display: flex; gap: 12px; align-items: flex-start;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px;
    text-decoration: none; transition: border-color .15s, box-shadow .15s, transform .15s;
}
.pnd-card:hover { border-color: #f5c877; box-shadow: 0 6px 18px rgba(0,0,0,.06); transform: translateY(-1px); text-decoration: none; }
.pnd-card-ico {
    flex-shrink: 0; width: 38px; height: 38px; border-radius: var(--radius-lg);
    background: var(--warning-bg); color: var(--warning); display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.pnd-card-txt strong { display: block; font-size: 13.5px; color: var(--text); margin-bottom: 3px; }
.pnd-card-txt span { font-size: 12px; color: var(--text-faint); line-height: 1.5; }

.pnd-pager { display: flex; justify-content: space-between; gap: 12px; margin-top: 28px; padding-top: 16px; border-top: 1px solid var(--border); }
.pnd-pager a { font-size: 12.5px; color: var(--info); text-decoration: none; font-weight: 600; }
.pnd-pager a:hover { text-decoration: underline; }
.pnd-pager .disabled { visibility: hidden; }
</style>

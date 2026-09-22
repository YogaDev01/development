{{--
    Modal aksi single-CR yang dipakai bersama oleh seluruh worklist di halaman
    ini — dipicu dari tombol di dalam _cr_action_buttons.blade.php (list CR
    nested pada modal View Budget) maupun dari section flat (Reject Handling,
    Realize, Close). Persis pola yang sudah ada di budget-request/index.blade.php,
    diduplikasi di sini karena ini halaman/DOM terpisah.
--}}

{{-- APPROVE (Head / Cost Control / CEO) --}}
<div class="modal fade" id="mdlHeadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Head Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmHeadApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="headApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="mdlFinanceVerify" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Cost Control Verify</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmFinanceVerify" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="financeVerifyText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Verify</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="mdlCeoApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> CEO Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmCeoApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="ceoApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- GENERIC REASON MODAL — Head/Finance/CEO Reject, Manager Revise/Cancel, Reject Realisasi --}}
<div class="modal fade" id="mdlNotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white" id="mdlNotesTitle"></h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmNotes" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <div id="mdlNotesAlert" class="alert alert-warning mb-3" style="font-size:12px;display:none;"></div>
                <div class="form-group mb-0">
                    <label class="font-weight-600" id="mdlNotesLabel">Catatan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control" rows="3" required placeholder="Tuliskan catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="mdlNotesSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div></div>
</div>

{{-- SETTLEMENT — approve modal bersama (Head Check / Finance Verify / CEO Close), action & teks disesuaikan JS per tahap --}}
<div class="modal fade" id="mdlVerifyRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Settlement</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmVerifyRealization" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="verifyRealText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" id="btnVerifyRealSubmit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- BULK REJECT — dipakai section Head/Cost Control/CEO --}}
<div class="modal fade" id="mdlBulkRejectCR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white">Reject <span id="bulkRejectCount"></span> CR</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div class="form-group mb-0">
                <label class="font-weight-600">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea id="bulkRejectNotes" class="form-control" rows="3" required placeholder="Tuliskan alasan..."></textarea>
            </div>
        </div>
        <div class="modal-footer" style="background:var(--surface-2);">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger" id="btnBulkRejectConfirm">Tolak Semua</button>
        </div>
    </div></div>
</div>

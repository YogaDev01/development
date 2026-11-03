{{--
    Modal aksi batch yang dipakai halaman show dan drawer daftar Treasury:
    Tandai PAID (unggah bukti bayar) dan Takeout/Reject satu baris.
    Action form diisi JS saat dibuka (public/js/tesys-treasury-batch.js):
      - .btn-mark-paid        data-action, data-info
      - .btn-takeout-cr-item  data-item-id, data-code, data-mode, [data-batch-id] terdekat
--}}

<!-- MODAL MARK PAID -->
<div class="modal fade" id="mdlMarkPaid" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-money-bill-wave mr-2" style="color:var(--accent);"></i> Tandai Batch sebagai PAID
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmMarkPaid" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-info mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span id="markPaidInfo"></span>
                        Upload minimal 1 file bukti pembayaran.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Charge (Biaya Admin Transfer)</label>
                        <input type="text" name="charge" id="inpMarkPaidCharge" class="form-control text-right"
                               value="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Catatan Pembayaran</label>
                        <input type="text" name="payment_notes" class="form-control"
                               placeholder="Nomor referensi / catatan transfer...">
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-600">
                            Lampiran Bukti Pembayaran <span class="text-danger">*</span>
                        </label>
                        {{-- Satu kotak: klik untuk pilih, tarik-lepas, atau tempel (Ctrl+V).
                             File terkumpul di #inpAttachments (DataTransfer), jadi form
                             tetap mengirim attachments[] seperti sebelumnya. --}}
                        <input type="file" name="attachments[]" id="inpAttachments" multiple
                               accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                        <div id="attDropzone" tabindex="0" role="button"
                             style="border:2px dashed var(--border);border-radius:var(--radius-lg);padding:22px 14px;text-align:center;font-size:12px;color:var(--text-muted);cursor:pointer;">
                            <i class="fas fa-cloud-upload-alt mr-1" style="font-size:18px;display:block;margin-bottom:6px;"></i>
                            Klik, tarik &amp; lepas, atau tempel (Ctrl+V) bukti pembayaran di sini
                        </div>
                        <ul id="attFileList" class="list-unstyled mb-0 mt-2"></ul>
                        <small class="text-muted d-block mt-1">Format: pdf, jpg, png. Maks 10 MB per file.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-money-bill-wave mr-1"></i> Tandai PAID
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT ITEM (TAKEOUT) -->
<div class="modal fade" id="mdlRejectItem" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> <span id="rejectItemTitle">Takeout CR</span> <span id="rejectItemCode"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectItem" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <small id="rejectItemHint" class="text-muted d-block mb-2"></small>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan takeout..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnRejectItemSubmit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Takeout</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Skrip bersama: dropdown aset searchable & modal tolak (data-reject-url). --}}
@once
@push('scripts')
<script>
(function () {
    if (window.TesysSearchableSelect) {
        document.querySelectorAll('select.js-asset-select').forEach(function (el) {
            TesysSearchableSelect.initOne($(el), { placeholder: '— Pilih aset —', searchPlaceholder: 'Cari kode / nama…' });
        });
    }
    $(document).on('click', '[data-reject-url]', function () {
        $('#astRejectForm').attr('action', $(this).data('reject-url'));
        $('#astRejectTitle').text($(this).data('reject-title') || 'Tolak pengajuan');
        $('#astRejectModal').modal('show');
    });
})();
</script>
@endpush
@endonce

<div class="modal fade" id="astRejectModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="astRejectForm" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="astRejectTitle">Tolak pengajuan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <label class="small font-weight-bold">Alasan <span class="text-danger">*</span></label>
                <textarea name="reject_reason" class="form-control form-control-sm" rows="3" maxlength="500" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button class="btn btn-danger btn-sm">Tolak</button>
            </div>
        </form>
    </div>
</div>

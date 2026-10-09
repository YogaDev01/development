@push('scripts')
<script>
$(document).on('submit', '.frmConfirmDelete', function (e) {
    e.preventDefault();
    var form = this;
    Swal.fire({
        title: $(form).data('title') || 'Hapus data?',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
        confirmButtonText: 'Ya, Lanjutkan', cancelButtonText: 'Batal'
    }).then(function (r) { if (r.isConfirmed) form.submit(); });
});
</script>
@endpush

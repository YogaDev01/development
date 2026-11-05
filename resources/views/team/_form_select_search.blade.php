{{--
    Dropdown bersearchbox untuk form Tambah/Edit Team. Disertakan di awal
    @push('scripts') supaya teamPasangCari() siap dipakai skrip form.
--}}
<script>
// Select tidak wajib tetap bisa dikosongkan lewat opsi placeholder-nya.
function teamPasangCari(wadah) {
    $(wadah).find('select:not([multiple])').each(function () {
        TesysSearchableSelect.initOne($(this), { searchPlaceholder: 'Cari...', allowEmpty: !this.required });
    });
}

// Select asli disembunyikan, jadi browser tidak bisa menampilkan pesan "wajib
// diisi" di situ: tandai tombolnya supaya submit tidak gagal diam-diam.
document.addEventListener('invalid', function (e) {
    var btn = $(e.target).prev('.tss-btn').addClass('border-danger').get(0);
    if (btn) btn.scrollIntoView({ block: 'center' });
}, true);
$(document).on('change', 'select', function () { $(this).prev('.tss-btn').removeClass('border-danger'); });
</script>

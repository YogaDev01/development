<?php

namespace App\Support;

/**
 * Pencegah CSV/formula injection: sel teks yang diawali karakter pemicu rumus
 * Excel/Sheets (= + - @, tab, CR, LF — juga setelah spasi di depan) diberi tanda
 * kutip tunggal di depan sehingga dibaca sebagai teks. HANYA untuk sel teks;
 * angka (AccountingMoney::plain) tidak boleh dilewatkan karena bisa bertanda minus.
 */
final class CsvSafe
{
    public static function cell(mixed $value): string
    {
        $s = (string) ($value ?? '');
        // Hilangkan karakter kontrol selain tab/spasi yang bisa menyamarkan pemicu.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);

        return preg_match('/^[\s]*[=+\-@\t\r\n]/', $s) ? "'" . $s : $s;
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Penulisan ditolak karena tanggalnya jatuh di periode Closing berstatus CLOSED.
 * Dilempar App\Support\PeriodLock::assertOpen(); pesannya sudah siap tampil.
 */
class PeriodLockedException extends RuntimeException
{
    /** Penolakan bisnis yang wajar, bukan galat sistem: jangan menuh-menuhi log. */
    public function report(): bool
    {
        return true;
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}

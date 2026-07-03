<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pencatat jejak/timeline tiket ke tabel ticket_activities.
 * Komentar/balasan TIDAK dicatat di sini (ada di ticket_comments) — timeline
 * menggabungkan keduanya saat ditampilkan.
 */
class TicketLog
{
    public static function log(int $ticketId, string $action, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        DB::table('ticket_activities')->insert([
            'ticket_id'  => $ticketId,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'from_value' => $from,
            'to_value'   => $to,
            'note'       => $note,
            'created_at' => now(),
        ]);
    }
}

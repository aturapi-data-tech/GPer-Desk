<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Kolom pelacak aktivitas untuk menu "User Online".
     * Di-update middleware TrackUserActivity (throttled 1 menit per user).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD (last_seen_at TIMESTAMP, last_seen_route VARCHAR2(255))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP (last_seen_at, last_seen_route)');
    }
};

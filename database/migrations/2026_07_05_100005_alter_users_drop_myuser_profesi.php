<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus kolom myuser_profesi — tidak dipakai di GPER-Desk (warisan template
     * SIMRS untuk TTD CPPT/SBAR). Identitas user cukup dari role + sektor.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN myuser_profesi');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ADD (myuser_profesi VARCHAR2(30))');
    }
};

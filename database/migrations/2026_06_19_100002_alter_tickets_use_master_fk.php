<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti kolom string (category/priority/status/location) di tickets dengan
     * FK ke tabel master + tambah department_id. Dilakukan saat tabel masih kosong.
     */
    public function up(): void
    {
        // Oracle: drop index dulu sebelum drop kolom yg ter-index.
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tic_status_idx');
            $table->dropIndex('tic_category_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['category', 'priority', 'status', 'location']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('description');
            $table->unsignedBigInteger('priority_id')->nullable()->after('category_id');
            $table->unsignedBigInteger('status_id')->nullable()->after('priority_id');
            $table->unsignedBigInteger('location_id')->nullable()->after('status_id');
            $table->unsignedBigInteger('department_id')->nullable()->after('location_id');

            $table->index('status_id', 'tic_status_idx');
            $table->index('category_id', 'tic_category_idx');

            $table->foreign('category_id', 'tic_category_fk')->references('id')->on('ticket_categories')->nullOnDelete();
            $table->foreign('priority_id', 'tic_priority_fk')->references('id')->on('priorities')->nullOnDelete();
            $table->foreign('status_id', 'tic_status_fk')->references('id')->on('ticket_statuses')->nullOnDelete();
            $table->foreign('location_id', 'tic_location_fk')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('department_id', 'tic_department_fk')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign('tic_category_fk');
            $table->dropForeign('tic_priority_fk');
            $table->dropForeign('tic_status_fk');
            $table->dropForeign('tic_location_fk');
            $table->dropForeign('tic_department_fk');
            $table->dropIndex('tic_status_idx');
            $table->dropIndex('tic_category_idx');
            $table->dropColumn(['category_id', 'priority_id', 'status_id', 'location_id', 'department_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('category', 50)->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->string('location')->nullable();
            $table->index('status', 'tic_status_idx');
            $table->index('category', 'tic_category_idx');
        });
    }
};

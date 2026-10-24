<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every calendar month from January 2016 to December 2096, keyed by its
     * first day. The rows are fixed system data; which of them are open for
     * memories is the application's call, not the table's.
     */
    public function up(): void
    {
        Schema::create('months', function (Blueprint $table) {
            $table->date('id')->primary();
        });

        DB::statement('ALTER TABLE months ADD CONSTRAINT months_id_is_first_of_month CHECK (EXTRACT(DAY FROM id) = 1)');

        DB::statement(<<<'SQL'
            insert into months (id)
            select generate_series('2016-01-01'::date, '2096-12-01'::date, interval '1 month')::date
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('months');
    }
};

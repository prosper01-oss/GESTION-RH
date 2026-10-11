<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_balance_entries', function (Blueprint $table) {
            $table->foreignId('request_id')->nullable()->after('leave_balance_id')->constrained('requests')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_balance_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_id');
        });
    }
};

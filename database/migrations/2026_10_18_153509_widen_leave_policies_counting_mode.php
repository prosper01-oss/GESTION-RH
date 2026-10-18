<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le mode « calendar_excluding_holidays » (règle R3) dépasse les 20 caractères d'origine.
     */
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->string('counting_mode', 40)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->string('counting_mode', 20)->change();
        });
    }
};

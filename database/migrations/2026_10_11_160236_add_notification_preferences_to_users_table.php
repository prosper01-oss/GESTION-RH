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
        // Préférences de notification (EF-63) : les alertes dans l'application restent toujours actives.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_by_email')->default(true)->after('must_change_password');
            $table->boolean('notify_by_sms')->default(true)->after('notify_by_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_by_email', 'notify_by_sms']);
        });
    }
};

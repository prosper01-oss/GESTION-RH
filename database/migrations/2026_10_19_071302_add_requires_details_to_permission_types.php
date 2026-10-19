<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catégorie dont l'agent doit préciser la nature, et création de « Autres (à préciser) ».
     */
    public function up(): void
    {
        Schema::table('permission_types', function (Blueprint $table) {
            $table->boolean('requires_details')->default(false)->after('max_duration_hours');
        });

        if (! DB::table('permission_types')->where('name', 'Autres (à préciser)')->exists()) {
            DB::table('permission_types')->insert([
                'name' => 'Autres (à préciser)',
                'is_paid' => true,
                'requires_attachment' => false,
                'is_medical' => false,
                'max_duration_hours' => null,
                'requires_details' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * La catégorie créée est conservée (des demandes peuvent l'utiliser) ; seule la colonne est retirée.
     */
    public function down(): void
    {
        Schema::table('permission_types', function (Blueprint $table) {
            $table->dropColumn('requires_details');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_nearby_places', function (Blueprint $table): void {
            $table->string('locality', 160)->nullable()->after('name');
            $table->index('locality');
        });
    }

    public function down(): void
    {
        Schema::table('facility_nearby_places', function (Blueprint $table): void {
            $table->dropIndex(['locality']);
            $table->dropColumn('locality');
        });
    }
};

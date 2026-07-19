<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->foreignId('geo_municipality_id')
                ->nullable()
                ->after('state_slug')
                ->constrained('geo_municipalities')
                ->nullOnDelete();
            $table->string('geo_match_status', 30)->nullable()->after('geo_municipality_id');
            $table->string('geo_match_method', 50)->nullable()->after('geo_match_status');
            $table->string('geo_match_confidence', 20)->nullable()->after('geo_match_method');
            $table->boolean('geo_requires_manual_review')->default(true)->after('geo_match_confidence');

            $table->index('geo_municipality_id');
            $table->index('geo_requires_manual_review');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropForeign(['geo_municipality_id']);
            $table->dropIndex(['geo_municipality_id']);
            $table->dropIndex(['geo_requires_manual_review']);
            $table->dropColumn([
                'geo_municipality_id',
                'geo_match_status',
                'geo_match_method',
                'geo_match_confidence',
                'geo_requires_manual_review',
            ]);
        });
    }
};

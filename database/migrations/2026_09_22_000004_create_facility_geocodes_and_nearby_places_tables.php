<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_geocodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('confidence', 10);
            $table->string('source', 100);
            $table->string('external_id', 100)->nullable();
            $table->timestamp('geocoded_at');
            $table->timestamps();
            $table->index('confidence');
        });

        Schema::create('facility_nearby_places', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('name', 255)->nullable();
            $table->unsignedInteger('distance_meters');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source', 100);
            $table->string('external_id', 100);
            $table->string('confidence', 10);
            $table->timestamp('discovered_at');
            $table->timestamp('updated_at');
            $table->unique(['facility_id', 'category', 'external_id'], 'facility_nearby_place_unique');
            $table->index(['facility_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_nearby_places');
        Schema::dropIfExists('facility_geocodes');
    }
};

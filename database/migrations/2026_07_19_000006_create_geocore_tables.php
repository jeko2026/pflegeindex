<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_countries', function (Blueprint $table): void {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('geo_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained('geo_countries')->restrictOnDelete();
            $table->char('ags', 2);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['country_id', 'ags']);
            $table->unique(['country_id', 'slug']);
        });

        Schema::create('geo_districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('state_id')->constrained('geo_states')->restrictOnDelete();
            $table->char('ags', 5);
            $table->string('name');
            $table->string('slug');
            $table->string('type', 30);
            $table->timestamps();

            $table->unique(['state_id', 'ags']);
            $table->unique(['state_id', 'slug']);
            $table->index(['state_id', 'type']);
        });

        Schema::create('geo_municipalities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained('geo_districts')->restrictOnDelete();
            $table->char('ags', 8)->unique();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('slug');
            $table->string('municipality_type', 30)->nullable();
            $table->char('postal_code_official', 5)->nullable();
            $table->string('source_name');
            $table->date('source_date')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['district_id', 'slug']);
            $table->index('district_id');
            $table->index('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_municipalities');
        Schema::dropIfExists('geo_districts');
        Schema::dropIfExists('geo_states');
        Schema::dropIfExists('geo_countries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id')->unique();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('postal_code', 10);
            $table->string('street')->nullable();
            $table->string('house_number', 30)->nullable();
            $table->string('address');
            $table->string('type');
            $table->string('source_sector')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('website')->nullable();
            $table->text('contact_source')->nullable();
            $table->string('contact_status', 30)->nullable();
            $table->timestamp('contact_checked_at')->nullable();
            $table->json('care_types')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
            $table->index(['city_id', 'type']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};

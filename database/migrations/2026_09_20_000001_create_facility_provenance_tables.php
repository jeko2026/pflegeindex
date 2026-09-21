<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->string('source_name');
            $table->string('source_record_id')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_file')->nullable();
            $table->string('search_keyword')->nullable();
            $table->string('query_type', 40)->nullable();
            $table->string('raw_name')->nullable();
            $table->text('raw_address')->nullable();
            $table->string('raw_phone')->nullable();
            $table->string('raw_email')->nullable();
            $table->text('raw_website')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('imported_at');
            $table->json('raw_payload_json')->nullable();
            $table->timestamps();
            $table->index('facility_id');
            $table->index('source_type');
            $table->unique(['facility_id', 'source_type', 'source_record_id'], 'facility_source_identity');
        });

        Schema::create('facility_social_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->text('url');
            $table->string('source_type', 30);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['facility_id', 'platform', 'url']);
            $table->index('facility_id');
            $table->index('platform');
        });

        Schema::create('facility_opening_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('hours_text')->nullable();
            $table->json('hours_json')->nullable();
            $table->string('source_type', 30);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('facility_service_type', function (Blueprint $table): void {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['facility_id', 'service_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_service_type');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('facility_opening_hours');
        Schema::dropIfExists('facility_social_links');
        Schema::dropIfExists('facility_sources');
    }
};

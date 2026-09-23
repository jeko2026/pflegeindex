<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('attribute_key', 100);
            $table->string('attribute_value', 255);
            $table->string('normalized_value', 255);
            $table->string('confidence', 10);
            $table->string('review_status', 20);
            $table->foreignId('source_id')->nullable()->constrained('facility_sources')->nullOnDelete();
            $table->text('source_url');
            $table->string('source_type', 40);
            $table->string('source_title')->nullable();
            $table->text('source_excerpt');
            $table->text('source_context')->nullable();
            $table->string('relation_reason', 120);
            $table->timestamp('discovered_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'attribute_key', 'normalized_value', 'source_url'], 'facility_attribute_evidence_unique');
            $table->index(['facility_id', 'review_status']);
            $table->index(['attribute_key', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_attributes');
    }
};

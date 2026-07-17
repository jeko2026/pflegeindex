<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('parser_status', 30);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('website')->nullable();
            $table->text('phone_source')->nullable();
            $table->text('email_source')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('decision', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['decision', 'parser_status']);
            $table->index(['facility_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_suggestions');
    }
};

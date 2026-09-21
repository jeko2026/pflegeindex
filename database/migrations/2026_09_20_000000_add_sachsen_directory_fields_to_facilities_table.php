<?php

use Illuminate\Database\Migrations\migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table): void {
            $table->string('website_domain')->nullable()->after('website');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('google_rating', 2, 1)->nullable();
            $table->unsignedInteger('google_review_count')->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('google_cid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('verification_status', 30)->default('unverified');
            $table->timestamp('last_verified_at')->nullable();
            $table->index('slug');
            $table->index('google_place_id');
            $table->index('google_cid');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table): void {
            $table->dropIndex(['slug']);
            $table->dropIndex(['google_place_id']);
            $table->dropIndex(['google_cid']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['website_domain', 'latitude', 'longitude', 'google_rating', 'google_review_count', 'google_place_id', 'google_cid', 'is_active', 'verification_status', 'last_verified_at']);
        });
    }
};

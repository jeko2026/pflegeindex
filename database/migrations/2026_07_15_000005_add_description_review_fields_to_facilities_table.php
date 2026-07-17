<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingColumns = array_map(
            static fn (object $column): string => (string) $column->name,
            DB::select('PRAGMA table_info("facilities")'),
        );
        $columns = [
            'description_draft' => 'ALTER TABLE "facilities" ADD COLUMN "description_draft" TEXT NULL',
            'description_draft_sources' => 'ALTER TABLE "facilities" ADD COLUMN "description_draft_sources" TEXT NULL',
            'description_draft_checked_at' => 'ALTER TABLE "facilities" ADD COLUMN "description_draft_checked_at" DATETIME NULL',
            'description_sources' => 'ALTER TABLE "facilities" ADD COLUMN "description_sources" TEXT NULL',
            'description_checked_at' => 'ALTER TABLE "facilities" ADD COLUMN "description_checked_at" DATETIME NULL',
            'description_ai_assisted' => 'ALTER TABLE "facilities" ADD COLUMN "description_ai_assisted" INTEGER NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $column => $statement) {
            if (! in_array($column, $existingColumns, true)) {
                DB::statement($statement);
            }
        }
    }

    public function down(): void
    {
        // Kept intentionally for compatibility with the older production SQLite version.
    }
};

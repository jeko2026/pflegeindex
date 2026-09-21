<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $country = DB::table('geo_countries')->where('iso2', 'DE')->first();

        if (! $country) {
            return;
        }

        if (! DB::table('geo_states')->where('country_id', $country->id)->where('ags', '14')->exists()) {
            DB::table('geo_states')->insert([
                'country_id' => $country->id,
                'ags' => '14',
                'name' => 'Sachsen',
                'slug' => 'sachsen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('geo_states')->where('ags', '14')->where('slug', 'sachsen')->delete();
    }
};

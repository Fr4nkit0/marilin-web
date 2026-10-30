<?php

namespace Database\Seeders;

use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Database\Seeder;

/**
 * Loads the Salta neighborhoods the mobile app ships with. Safe to run in production
 * (`db:seed --class=NeighborhoodSeeder`): it only adds missing names and leaves existing rows,
 * enabled flag and updated_at included, untouched so a re-run does not resend them in pull.
 */
class NeighborhoodSeeder extends Seeder
{
    public function run(): void
    {
        $names = json_decode(file_get_contents(database_path('data/salta_neighborhoods.json')), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        $rows = array_map(fn (string $name): array => [
            'name' => $name,
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names);

        foreach (array_chunk($rows, 200) as $chunk) {
            Neighborhood::query()->insertOrIgnore($chunk);
        }
    }
}

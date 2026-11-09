<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The admin user always; the sample comps and winners everywhere except
     * production, so local and staging look like the design preview.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (app()->isProduction()) {
            $this->command->warn('Production: sample comps and winners not seeded.');

            return;
        }

        $this->call([SampleCompetitionSeeder::class, SampleWinnerSeeder::class]);
    }
}

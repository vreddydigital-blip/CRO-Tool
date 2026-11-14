<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only safe demo data is created: one account and one project. No scans,
     * findings or scores are fabricated — run a scan to populate the app.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}

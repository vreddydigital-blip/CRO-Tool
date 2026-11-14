<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Safe demo data.
 *
 * Creates one demo account and one project. It deliberately does NOT create
 * fake scans, findings or scores: a seeded dashboard must never show numbers
 * that look like a real analysis. Run a scan to populate it.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@cro.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        Project::firstOrCreate(
            ['user_id' => $user->id, 'url' => 'https://example.com'],
            [
                'name' => 'Example Site',
                'primary_url' => 'https://example.com',
                'description' => 'Demo project. Run a scan to generate real findings.',
                'is_primary' => true,
            ]
        );
    }
}

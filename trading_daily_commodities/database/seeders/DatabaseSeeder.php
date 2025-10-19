<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);

        // Create some test users
        User::factory()->create([
            'name' => 'John Seller',
            'email' => 'seller@example.com',
            'role' => 'seller'
        ]);

        User::factory()->create([
            'name' => 'Jane Buyer',
            'email' => 'buyer@example.com',
            'role' => 'buyer'
        ]);
    }
}

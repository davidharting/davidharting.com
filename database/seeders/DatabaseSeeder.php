<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Upclick;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $frodo = User::factory()->create([
            'name' => 'Frodo Baggins',
            'email' => 'frodo@example.com',
        ]);

        User::factory(10)->create();

        // Anonymous clicks
        Upclick::factory(500)->create();
        // Frodos clicks
        Upclick::factory(200)->create(['user_id' => $frodo->id]);
    }
}

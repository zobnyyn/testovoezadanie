<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Петр Петров',
            'email' => 'petr@example.com',
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Мария Сидорова',
            'email' => 'maria@example.com',
            'password' => Hash::make('password'),
        ]);
    }
}


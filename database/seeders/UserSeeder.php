<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'first_name' => 'Admin',
            'last_name' => 'Account',
            'email' => 'admin@test.com',
            'password' => Hash::make('12345678'),
            'is_admin' => 1
        ]);
        User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'jon@test.com',
            'password' => Hash::make('12345678'),
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Admin user
        DB::table('users')->insert([
            'name' => 'Admin Master',
            'email' => 'admin@mastercolor.com',
            'password' => Hash::make('admin1234'),
            'dni' => 12345678,
            'role_id' => 1, // Suponiendo que 1 es Admin
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default roles
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator with full access',
        ]);

        $sellerRole = Role::create([
            'name' => 'seller',
            'description' => 'Salesperson with limited access',
        ]);

        $warehouseRole = Role::create([
            'name' => 'warehouse',
            'description' => 'Warehouse staff with inventory access',
        ]);

        // Create default admin user
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'dni' => '12345678',
        ]);

        // Create a sample seller
        User::create([
            'name' => 'John Seller',
            'email' => 'seller@example.com',
            'password' => Hash::make('password'),
            'role_id' => $sellerRole->id,
            'dni' => '23456789',
        ]);

        // Create a sample warehouse staff
        User::create([
            'name' => 'Jane Warehouse',
            'email' => 'warehouse@example.com',
            'password' => Hash::make('password'),
            'role_id' => $warehouseRole->id,
            'dni' => '34567890',
        ]);
    }
}

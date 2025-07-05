<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Product;
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
            'name' => 'Admin',
            'description' => 'Acceso total al sistema',
        ]);

        $sellerRole = Role::create([
            'name' => 'Vendedor',
            'description' => 'Gestión de pedidos y clientes',
        ]);

        $warehouseRole = Role::create([
            'name' => 'Almacen',
            'description' => 'Gestión de inventario y stock',
        ]);

        // Create default admin user compatible with Postman collection
        User::create([
            'name' => 'Admin Master',
            'email' => 'admin@mastercolor.com',
            'password' => Hash::make('admin1234'),
            'role_id' => $adminRole->id,
            'dni' => '12345678',
            'phone' => '912345678',
        ]);

        // Create a sample seller
        User::create([
            'name' => 'John Seller',
            'email' => 'seller@example.com',
            'password' => Hash::make('password'),
            'role_id' => $sellerRole->id,
            'dni' => '23456789',
            'phone' => '923456789',
        ]);

        // Create a sample warehouse staff
        User::create([
            'name' => 'Daniel Moran',
            'email' => 'daniel.moranv94@gmail.com',
            'password' => Hash::make('admin1234'),
            'role_id' => $adminRole->id,
            'dni' => '70315050',
            'phone' => '987654321',
        ]);

        // Seed products with stock and initial movement
        $this->call(ProductsWithStockSeeder::class);
    }
}

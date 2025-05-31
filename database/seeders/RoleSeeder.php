<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::table('roles')->insert([
            ['name' => 'Admin', 'description' => 'Acceso total al sistema', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Vendedor', 'description' => 'Gestión de pedidos y clientes', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Almacén', 'description' => 'Gestión de inventario y stock', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
    }
}

<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(SettingsSeeder::class);
        $this->call(ShippingZonesSeeder::class);
        $this->call(CategoriesSeeder::class);
        $this->call(BrandsSeeder::class);
        $this->call(ProductsSeeder::class);
        $this->call(AdminSeeder::class);

        $env = getenv('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'development');
        if (strtolower($env) !== 'production') {
            $this->call(DevSeeder::class);
        }
    }
}

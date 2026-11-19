<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BrandsSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');

        $brands = [
            ['name' => 'Dell',     'slug' => 'dell'],
            ['name' => 'HP',       'slug' => 'hp'],
            ['name' => 'Lenovo',   'slug' => 'lenovo'],
            ['name' => 'Acer',     'slug' => 'acer'],
            ['name' => 'Asus',     'slug' => 'asus'],
            ['name' => 'Apple',    'slug' => 'apple'],
            ['name' => 'Samsung',  'slug' => 'samsung'],
            ['name' => 'Logitech', 'slug' => 'logitech'],
            ['name' => 'Kingston', 'slug' => 'kingston'],
        ];

        foreach ($brands as $brand) {
            $existing = $this->db->table('brands')->where('slug', $brand['slug'])->get()->getRow();
            if ($existing) {
                $this->db->table('brands')->where('id', $existing->id)->update([
                    'name'       => $brand['name'],
                    'is_active'  => 1,
                    'updated_at' => $now,
                ]);
            } else {
                $this->db->table('brands')->insert([
                    'name'       => $brand['name'],
                    'slug'       => $brand['slug'],
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}

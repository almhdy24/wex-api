<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');

        $categories = [
            [
                'name_ar'    => 'لابتوبات',
                'name_en'    => 'Laptops',
                'slug'       => 'laptops',
                'sort_order' => 1,
                'is_active'  => 1,
            ],
            [
                'name_ar'    => 'تلفونات',
                'name_en'    => 'Phones',
                'slug'       => 'phones',
                'sort_order' => 2,
                'is_active'  => 1,
            ],
            [
                'name_ar'    => 'إكسسوارات',
                'name_en'    => 'Accessories',
                'slug'       => 'accessories',
                'sort_order' => 3,
                'is_active'  => 1,
            ],
        ];

        foreach ($categories as $cat) {
            $existing = $this->db->table('categories')->where('slug', $cat['slug'])->get()->getRow();
            if ($existing) {
                $this->db->table('categories')->where('id', $existing->id)->update([
                    'name_ar'    => $cat['name_ar'],
                    'name_en'    => $cat['name_en'],
                    'sort_order' => $cat['sort_order'],
                    'is_active'  => $cat['is_active'],
                    'updated_at' => $now,
                ]);
            } else {
                $this->db->table('categories')->insert([
                    'name_ar'    => $cat['name_ar'],
                    'name_en'    => $cat['name_en'],
                    'slug'       => $cat['slug'],
                    'sort_order' => $cat['sort_order'],
                    'is_active'  => $cat['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}

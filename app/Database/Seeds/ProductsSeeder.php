<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Wex;

class ProductsSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');

        // Look up category IDs
        $catMap = [];
        $cats = $this->db->table('categories')->get()->getResult();
        foreach ($cats as $c) {
            $catMap[$c->slug] = (int) $c->id;
        }

        // Look up brand IDs
        $brandMap = [];
        $brands = $this->db->table('brands')->get()->getResult();
        foreach ($brands as $b) {
            $brandMap[$b->slug] = (int) $b->id;
        }

        $laptopsCategory     = $catMap['laptops'] ?? 1;
        $accessoriesCategory = $catMap['accessories'] ?? 3;

        // 8 Plain Laptops
        $laptops = [
            [
                'name'              => 'Dell Latitude 7490',
                'slug'              => 'dell-latitude-7490',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['dell'] ?? null,
                'condition'         => Wex::CONDITION_USED_IMPORTED,
                'price_aed'         => 750,
                'stock_quantity'    => 5,
                'is_active'         => 1,
                'is_featured'       => 1,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'لابتوب مكتبي خفيف للأعمال اليومية',
                'description'       => 'مناسب للشغل المكتبي والتصفح والدراسة، هيكل متين ومريح في التنقل.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-8350U',
                    'ram'     => '8 GB',
                    'storage' => '256 GB SSD',
                    'gpu'     => 'Intel UHD Graphics 620',
                    'screen'  => '14 بوصة FHD',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'Lenovo ThinkPad T480',
                'slug'              => 'lenovo-thinkpad-t480',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['lenovo'] ?? null,
                'condition'         => Wex::CONDITION_USED_IMPORTED,
                'price_aed'         => 900,
                'stock_quantity'    => 4,
                'is_active'         => 1,
                'is_featured'       => 1,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'جهاز عملي ومناسب للمبرمجين والمحاسبين',
                'description'       => 'أداء مستقر وبطارية مزدوجة ولوحة مفاتيح مريحة جداً للكتابة الطويلة.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-8250U',
                    'ram'     => '16 GB',
                    'storage' => '256 GB SSD',
                    'gpu'     => 'Intel UHD Graphics 620',
                    'screen'  => '14 بوصة HD',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'HP EliteBook 840 G5',
                'slug'              => 'hp-elitebook-840-g5',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['hp'] ?? null,
                'condition'         => Wex::CONDITION_USED_IMPORTED,
                'price_aed'         => 800,
                'stock_quantity'    => 3,
                'is_active'         => 1,
                'is_featured'       => 1,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'هيكل ألمنيوم أنيق وخفيف للعمل والدراسة',
                'description'       => 'تصميم عصري وصوت نقي من Bang & Olufsen مناسب للاجتماعات والمكتب.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-8250U',
                    'ram'     => '8 GB',
                    'storage' => '256 GB SSD',
                    'gpu'     => 'Intel UHD Graphics 620',
                    'screen'  => '14 بوصة FHD',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'HP ProBook 450 G6',
                'slug'              => 'hp-probook-450-g6',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['hp'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 1350,
                'stock_quantity'    => 2,
                'is_active'         => 1,
                'is_featured'       => 1,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'شاشة كبيرة مع لوحة أرقام جانبية',
                'description'       => 'مناسب للحسابات والأعمال المكتبية التي تحتاج شاشة عريضة ومساحة مريحة.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-8265U',
                    'ram'     => '8 GB',
                    'storage' => '256 GB SSD',
                    'gpu'     => 'Intel UHD Graphics 620',
                    'screen'  => '15.6 بوصة FHD',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'Dell Inspiron 15 3580',
                'slug'              => 'dell-inspiron-15-3580',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['dell'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 1250,
                'stock_quantity'    => 6,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'سعة تخزين كبيرة ومناسب للطلاب',
                'description'       => 'جهاز مناسب للأبحاث والدراسة الجامعية والاستخدام المنزلي بسعة 1 تيرابايت.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-8265U',
                    'ram'     => '8 GB',
                    'storage' => '1 TB HDD',
                    'gpu'     => 'Intel UHD Graphics 620',
                    'screen'  => '15.6 بوصة HD',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'Acer Aspire 5',
                'slug'              => 'acer-aspire-5',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['acer'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 1650,
                'stock_quantity'    => 4,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'معالج الجيل العاشر مع هارد سريع',
                'description'       => 'أداء سريع وإقلاع فوري للنظام والبرامج مع شاشة واضحة الألوان.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5-10210U',
                    'ram'     => '8 GB',
                    'storage' => '512 GB SSD',
                    'gpu'     => 'Intel UHD Graphics',
                    'screen'  => '15.6 بوصة FHD IPS',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'MacBook Air 2019',
                'slug'              => 'macbook-air-2019',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['apple'] ?? null,
                'condition'         => Wex::CONDITION_USED_IMPORTED,
                'price_aed'         => 2300,
                'stock_quantity'    => 2,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'شاشة ريتنا عالية الدقة ووزن خفيف جداً',
                'description'       => 'مناسب للتصفح والكتابة والتنقل مع شاشة ريتنا وبصمة Touch ID.',
                'specs'             => json_encode([
                    'cpu'     => 'Intel Core i5 Dual-Core',
                    'ram'     => '8 GB',
                    'storage' => '128 GB SSD',
                    'gpu'     => 'Intel UHD Graphics 617',
                    'screen'  => '13.3 بوصة Retina',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'Asus TUF FX505',
                'slug'              => 'asus-tuf-fx505',
                'type'              => Wex::PRODUCT_TYPE_LAPTOP,
                'category_id'       => $laptopsCategory,
                'brand_id'          => $brandMap['asus'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 2450,
                'stock_quantity'    => 3,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'كرت شاشة منفصل للألعاب والتصميم',
                'description'       => 'تبريد مزدوج وشاشة سريعة 144 هيرتز مناسبة للألعاب والمونتاج والهندسة.',
                'specs'             => json_encode([
                    'cpu'     => 'AMD Ryzen 5-3550H',
                    'ram'     => '8 GB',
                    'storage' => '512 GB SSD',
                    'gpu'     => 'NVIDIA GeForce GTX 1650 4GB',
                    'screen'  => '15.6 بوصة 144Hz FHD',
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        $laptopProductIds = [];
        foreach ($laptops as $laptop) {
            $existing = $this->db->table('products')->where('slug', $laptop['slug'])->get()->getRow();
            if ($existing) {
                $this->db->table('products')->where('id', $existing->id)->update(array_merge($laptop, [
                    'updated_at' => $now,
                ]));
                $laptopProductIds[$laptop['slug']] = (int) $existing->id;
            } else {
                $this->db->table('products')->insert(array_merge($laptop, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $laptopProductIds[$laptop['slug']] = (int) $this->db->insertID();
            }
        }

        // 6 Accessories
        $accessories = [
            [
                'name'              => 'ماوس لاسلكي لوجيتك B170',
                'slug'              => 'logitech-wireless-mouse-b170',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => $brandMap['logitech'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 35,
                'stock_quantity'    => 20,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'ماوس لاسلكي مريح وعملي',
                'description'       => 'مدى اتصال 10 أمتار وبطارية تدوم طويلاً، متوافق مع كافة الأنظمة.',
                'specs'             => json_encode([
                    'connectivity' => 'Wireless 2.4GHz USB Nano',
                    'dpi'          => '1000 DPI',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'شنطة ظهر لابتوب مقاومة للماء 15.6',
                'slug'              => 'laptop-backpack-15-waterproof',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 50,
                'stock_quantity'    => 15,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'فحص واستلام',
                'short_description' => 'شنطة ظهر مبطنة لحماية الجهاز',
                'description'       => 'بطانة سميكة مضادة للصدمات مع جيوب متعددة للشاحن والملحقات.',
                'specs'             => json_encode([
                    'capacity' => 'لأجهزة حتى 15.6 بوصة',
                    'material' => 'قماش بوليستر مقاوم للماء',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'شاحن لابتوب أصلي 65 واط',
                'slug'              => 'original-laptop-charger-65w',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => $brandMap['dell'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 60,
                'stock_quantity'    => 12,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'شاحن بديل عالي الجودة مع كابل الكهرباء',
                'description'       => 'شاحن أصلي لحماية بطارية الجهاز من تذبذب التيار.',
                'specs'             => json_encode([
                    'power'  => '65 Watt',
                    'output' => '19.5V / 3.34A',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'هارد SSD كينغستون 256 جيجابايت',
                'slug'              => 'kingston-ssd-256gb',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => $brandMap['kingston'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 70,
                'stock_quantity'    => 15,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'ترقية سرعة الإقلاع والبرامج',
                'description'       => 'هارد سريع لتحسين أداء اللابتوبات القديمة والجديدة بمعدل قراءة عالي.',
                'specs'             => json_encode([
                    'capacity'  => '256 GB',
                    'interface' => 'SATA III 2.5 inch',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'رام 8 جيجابايت DDR4 للابتوب',
                'slug'              => 'laptop-ram-8gb-ddr4',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => $brandMap['kingston'] ?? null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 65,
                'stock_quantity'    => 10,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'رام لتسريع الجهاز وتعدد المهام',
                'description'       => 'شريحة رام أصلية لرفع كفاءة اللابتوب وسلاسة فتح البرامج المتزامنة.',
                'specs'             => json_encode([
                    'capacity' => '8 GB',
                    'speed'    => 'DDR4 2666 MHz',
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'              => 'قاعدة تبريد لابتوب بمروحتين',
                'slug'              => 'laptop-cooling-pad-dual-fans',
                'type'              => Wex::PRODUCT_TYPE_ACCESSORY,
                'category_id'       => $accessoriesCategory,
                'brand_id'          => null,
                'condition'         => Wex::CONDITION_NEW,
                'price_aed'         => 45,
                'stock_quantity'    => 8,
                'is_active'         => 1,
                'is_featured'       => 0,
                'warranty_text'     => 'معاينة وفحص عند الاستلام',
                'short_description' => 'قاعدة مريحة للتبريد وتقليل الحرارة',
                'description'       => 'مروحتان هادئتان تعملان عبر منفذ USB مع زوايا ارتفاع قابلة للتعديل.',
                'specs'             => json_encode([
                    'fans'  => '2 Silent Fans with LED',
                    'power' => 'USB 5V',
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        $accessoryProductIds = [];
        foreach ($accessories as $acc) {
            $existing = $this->db->table('products')->where('slug', $acc['slug'])->get()->getRow();
            if ($existing) {
                $this->db->table('products')->where('id', $existing->id)->update(array_merge($acc, [
                    'updated_at' => $now,
                ]));
                $accessoryProductIds[] = (int) $existing->id;
            } else {
                $this->db->table('products')->insert(array_merge($acc, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $accessoryProductIds[] = (int) $this->db->insertID();
            }
        }

        // Link accessories to each laptop
        foreach ($laptopProductIds as $laptopId) {
            $sortOrder = 1;
            foreach ($accessoryProductIds as $accId) {
                $hasLink = $this->db->table('product_accessories')
                    ->where('product_id', $laptopId)
                    ->where('accessory_id', $accId)
                    ->countAllResults();

                if ($hasLink === 0) {
                    $this->db->table('product_accessories')->insert([
                        'product_id'   => $laptopId,
                        'accessory_id' => $accId,
                        'sort_order'   => $sortOrder++,
                    ]);
                }
            }
        }
    }
}

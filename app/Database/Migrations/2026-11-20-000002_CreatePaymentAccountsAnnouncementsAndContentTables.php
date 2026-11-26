<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentAccountsAnnouncementsAndContentTables extends Migration
{
    public function up()
    {
        // 1. payment_accounts table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'bank_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'account_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'branch' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('payment_accounts', true);

        // Migrate existing bank details from settings into payment_accounts row 1 if present
        $now = gmdate('Y-m-d H:i:s');
        $bankNameSetting = $this->db->table('settings')->where('key', 'bank_name')->get()->getRow();
        $accountNameSetting = $this->db->table('settings')->where('key', 'bank_account_name')->get()->getRow();
        $accountNumSetting = $this->db->table('settings')->where('key', 'bank_account_number')->get()->getRow();

        $bankName = $bankNameSetting ? trim($bankNameSetting->value) : '';
        $accountName = $accountNameSetting ? trim($accountNameSetting->value) : '';
        $accountNumber = $accountNumSetting ? trim($accountNumSetting->value) : '';

        if (!empty($bankName) && !empty($accountNumber)) {
            $this->db->table('payment_accounts')->insert([
                'bank_name'      => $bankName,
                'account_name'   => $accountName,
                'account_number' => $accountNumber,
                'branch'         => 'الفرع الرئيسي - أم درمان',
                'notes'          => 'يرجى كتابة رقم الطلب في خانة الملاحظات عند التحويل عبر تطبيق بنكك.',
                'is_active'      => 1,
                'sort_order'     => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // General payment instructions setting
        $hasNotes = $this->db->table('settings')->where('key', 'payment_general_notes')->countAllResults();
        if ($hasNotes === 0) {
            $this->db->table('settings')->insert([
                'key'        => 'payment_general_notes',
                'value'      => 'يرجى التحويل إلى أحد حسابات المتجر أدناه وإرفاق صورة واضحة من إشعار التحويل البنكي قبل انتهاء مهلة تثبيت السعر.',
                'updated_at' => $now,
            ]);
        }

        // 2. announcements table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'kind' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'info', // info, promo, warning
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'body' => [
                'type' => 'TEXT',
            ],
            'link_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'link_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'placement' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'banner', // banner, toast, home
            ],
            'starts_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ends_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'is_dismissible' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('announcements', true);

        // 3. Add ticker_hidden to orders table
        if (!$this->db->fieldExists('ticker_hidden', 'orders')) {
            $this->forge->addColumn('orders', [
                'ticker_hidden' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
            ]);
        }

        // 4. hero_slides table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'subtitle' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'image' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'button_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'link' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('hero_slides', true);

        // Seed 3 default slides
        $defaultSlides = [
            [
                'title'        => 'أجهزة لابتوب لأعمالك ودراستك',
                'subtitle'     => 'أجهزة مستوردة متوفرة في فرعنا بأم درمان',
                'image'        => '/assets/hero/hero-1.webp',
                'button_label' => 'استعرض الأجهزة',
                'link'         => '/catalog',
                'sort_order'   => 1,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'title'        => 'تثبيت السعر والدفع عبر بنكك',
                'subtitle'     => 'استقرار السعر من لحظة الطلب وحتى إتمام التحويل البنكي خلال المهلة المحددة',
                'image'        => '/assets/hero/hero-2.webp',
                'button_label' => 'كيفية الطلب',
                'link'         => '/#how-to-order',
                'sort_order'   => 2,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'title'        => 'ملحقات ومستلزمات تقنية أساسية مع كل جهاز',
                'subtitle'     => 'حقائب وشواحن ومحولات متوافقة مع جهازك',
                'image'        => '/assets/hero/hero-3.webp',
                'button_label' => 'الملحقات',
                'link'         => '/catalog?type=accessory',
                'sort_order'   => 3,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];
        $this->db->table('hero_slides')->insertBatch($defaultSlides);

        // 5. home_sections table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'newest', // newest, featured, category, type, accessories
            ],
            'source_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 8,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('home_sections', true);

        // Seed default home sections
        $defaultSections = [
            [
                'key'          => 'newest_laptops',
                'title'        => 'أحدث أجهزة اللابتوب',
                'source'       => 'type',
                'source_value' => 'laptop',
                'limit'        => 8,
                'sort_order'   => 1,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'key'          => 'essential_accessories',
                'title'        => 'ملحقات ومستلزمات تقنية',
                'source'       => 'type',
                'source_value' => 'accessory',
                'limit'        => 6,
                'sort_order'   => 2,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];
        $this->db->table('home_sections')->insertBatch($defaultSections);

        // 6. faq_items table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'question' => [
                'type' => 'TEXT',
            ],
            'answer' => [
                'type' => 'TEXT',
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('faq_items', true);

        // Seed default FAQ
        $defaultFaqs = [
            [
                'question'   => 'كيف يعمل نظام تثبيت السعر في ويكس تكنولوجي؟',
                'answer'     => 'عند تقديم الطلب، يتم حجز الجهاز وتثبيت سعره بـ ج.س لمدة {{lock_duration}}، مما يحميك من أي تقلبات في سعر الصرف أثناء إتمام التحويل عبر تطبيق بنكك.',
                'sort_order' => 1,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question'   => 'كيف يتم تأكيد الدفع بعد التحويل البنكي؟',
                'answer'     => 'بعد التحويل عبر تطبيق بنكك إلى الحساب الموضح في صفحة الطلب، قم برفع صورة الإشعار البنكي وكتابة اسم المحوِّل، وسيقوم فريق العمل بمطابقة العملية وتأكيد الطلب.',
                'sort_order' => 2,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question'   => 'أين يقع المحل وما هي أوقات العمل؟',
                'answer'     => 'عنوان المحل: {{shop_address}}. أوقات العمل: {{working_hours}}.',
                'sort_order' => 3,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question'   => 'هل يتوفر خيار التوصيل للولايات؟',
                'answer'     => 'نعم، يتوفر الشحن والتوصيل للمدن والولايات المحددة عبر شركات النقل وبأسعار محددة عند إتمام الطلب.',
                'sort_order' => 4,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        $this->db->table('faq_items')->insertBatch($defaultFaqs);

        // 7. pages table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'body' => [
                'type' => 'TEXT',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('pages', true);

        // Seed 4 draft pages
        $defaultPages = [
            [
                'slug'       => 'privacy-policy',
                'title'      => 'سياسة الخصوصية',
                'body'       => "نحن في ويكس تكنولوجي نقدر خصوصيتك ونلتزم بحماية بياناتك الشخصية.\n\nتجمع المنصة فقط البيانات الضرورية لتنفيذ وتوصيل الطلبات، وتشمل الاسم ورقم الهاتف والبريد الإلكتروني وعنوان التوصيل.\n\nلا نقوم بمشاركة بياناتك الشخصية أو بيعها لأي أطراف ثالثة، وتستخدم فقط لإدارة حسابك ومتابعة مدفوعاتك وطلباتك.\n\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).",
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'terms',
                'title'      => 'شروط الاستخدام',
                'body'       => "باستخدامك لمنصة ويكس تكنولوجي، فإنك توافق على الشروط والأحكام الآتية:\n\n1. تثبيت السعر سارٍ لمدة {{lock_duration}} من لحظة إنشاء الطلب، ويجب رفع إشعار التحويل البنكي قبل انقضاء هذه المهلة.\n2. يتم تأكيد الطلب بعد التحقق من وصول المبلغ كاملاً في حساب المتجر.\n3. يحتفظ المحل بحق إلغاء الطلب في حال عدم إتمام التحويل خلال المهلة المحددة.\n\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).",
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'returns',
                'title'      => 'سياسة الاستلام والتسليم',
                'body'       => "نحرص على الشفافية:\n\n1. الأجهزة المعروضة مستوردة.\n2. يتأكد المشتري من مطابقة الجهاز ومواصفاته الفنية عند الاستلام.\n3. يتم استلام الطلبات من مقرنا أو عبر مندوب التوصيل.\n\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).",
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug'       => 'about',
                'title'      => 'عن ويكس تكنولوجي',
                'body'       => "ويكس تكنولوجي - متجر لأجهزة اللابتوب ومستلزمات الحواسيب في أم درمان والسودان.\n\nنقدم أجهزة لابتوب لتناسب احتياجات المهندسين والطلاب ورواد الأعمال، مع وضوح في الأسعار بـ ج.س ونظام تثبيت السعر.\n\nمقرنا: مول الواحة، الشهداء، أم درمان.",
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        $this->db->table('pages')->insertBatch($defaultPages);

        // 8. Extended settings
        $extendedSettings = [
            ['key' => 'orders_enabled',           'value' => '1'],
            ['key' => 'orders_disabled_message',  'value' => 'استقبال الطلبات متوقف مؤقتاً في الوقت الحالي، نعتذر عن الإزعاج ونرحب باستفساراتكم عبر الواتساب.'],
            ['key' => 'ticker_enabled',           'value' => '0'],
            ['key' => 'shop_map_link',            'value' => 'https://maps.google.com/?q=Wex+Technology+Omdurman'],
            ['key' => 'social_facebook',          'value' => ''],
            ['key' => 'social_instagram',         'value' => ''],
            ['key' => 'footer_text',              'value' => 'ويكس تكنولوجي - أجهزة لابتوب ومستلزمات تقنية في السودان.'],
            ['key' => 'delivery_notes',           'value' => 'التوصيل متاح داخل ولاية الخرطوم والولايات المحددة عبر مناديب وشركات شحن.'],
        ];

        foreach ($extendedSettings as $item) {
            $exists = $this->db->table('settings')->where('key', $item['key'])->countAllResults();
            if ($exists === 0) {
                $this->db->table('settings')->insert([
                    'key'        => $item['key'],
                    'value'      => $item['value'],
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('payment_accounts', true);
        $this->forge->dropTable('announcements', true);
        $this->forge->dropColumn('orders', 'ticker_hidden');
        $this->forge->dropTable('hero_slides', true);
        $this->forge->dropTable('home_sections', true);
        $this->forge->dropTable('faq_items', true);
        $this->forge->dropTable('pages', true);

        $keys = [
            'payment_general_notes',
            'orders_enabled',
            'orders_disabled_message',
            'ticker_enabled',
            'shop_map_link',
            'social_facebook',
            'social_instagram',
            'footer_text',
            'delivery_notes',
        ];
        $this->db->table('settings')->whereIn('key', $keys)->delete();
    }
}

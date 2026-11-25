<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePushNotificationTables extends Migration
{
    public function up()
    {
        // 1. push_subscriptions
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'platform' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'web',
            ],
            'provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'webpush',
            ],
            'endpoint_or_token' => [
                'type' => 'TEXT',
            ],
            'endpoint_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'keys_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'device_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'ar',
            ],
            'app_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'topics_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_success_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_failure_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'failure_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        $this->forge->addUniqueKey('endpoint_hash');
        $this->forge->addKey(['user_id', 'is_active']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_subscriptions', true);

        // 2. notification_preferences
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'order_updates' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'payment_updates' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'price_alerts' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'announcements' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'new_orders' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'receipts' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'low_stock' => [
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
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_preferences', true);

        // 3. push_outbox
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'notification_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'subscription_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending',
            ],
            'attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'next_attempt_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'payload_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'next_attempt_at']);
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subscription_id', 'push_subscriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('push_outbox', true);

        // 4. Extend notifications table
        $fields = [];
        if (!$this->db->fieldExists('data_json', 'notifications')) {
            $fields['data_json'] = [
                'type' => 'TEXT',
                'null' => true,
            ];
        }
        if (!$this->db->fieldExists('channel_flags', 'notifications')) {
            $fields['channel_flags'] = [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ];
        }
        if (!empty($fields)) {
            $this->forge->addColumn('notifications', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('data_json', 'notifications')) {
            $this->forge->dropColumn('notifications', 'data_json');
        }
        if ($this->db->fieldExists('channel_flags', 'notifications')) {
            $this->forge->dropColumn('notifications', 'channel_flags');
        }
        $this->forge->dropTable('push_outbox', true);
        $this->forge->dropTable('notification_preferences', true);
        $this->forge->dropTable('push_subscriptions', true);
    }
}

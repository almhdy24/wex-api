<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEventTypeAndMetaToOrderEvents extends Migration
{
    public function up()
    {
        $fields = [
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => 'status_changed',
                'after'      => 'actor_user_id',
            ],
            'meta' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'event_type',
            ],
        ];

        $fieldsToAdd = [];
        if (!$this->db->fieldExists('event_type', 'order_events')) {
            $fieldsToAdd['event_type'] = $fields['event_type'];
        }
        if (!$this->db->fieldExists('meta', 'order_events')) {
            $fieldsToAdd['meta'] = $fields['meta'];
        }
        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('order_events', $fieldsToAdd);
        }

        // Migrate existing events safely and clean up any literal UTC text
        $db = \Config\Database::connect();

        // 1. Clean up notes containing UTC
        $events = $db->table('order_events')->get()->getResultArray();
        foreach ($events as $e) {
            $update = [];
            $note = $e['note'] ?? '';
            $toStatus = $e['to_status'] ?? '';

            // Clean note
            if (str_contains($note, 'UTC')) {
                $cleanedNote = trim(str_replace([' UTC', 'UTC'], '', $note));
                $update['note'] = $cleanedNote;
            }

            // Infer event_type if not set
            if (empty($e['event_type']) || $e['event_type'] === 'status_changed') {
                if (str_contains($note, 'تم إنشاء الطلب')) {
                    $update['event_type'] = 'order_created';
                } elseif (str_contains($note, 'قام العميل برفع إشعار') || $toStatus === 'payment_review') {
                    $update['event_type'] = 'payment_proof_uploaded';
                } elseif (str_contains($note, 'تحديث الإشعار') || str_contains($note, 'إشعار جديد')) {
                    $update['event_type'] = 'payment_proof_replaced';
                } elseif (str_contains($note, 'الموافقة عليه') || $toStatus === 'confirmed') {
                    $update['event_type'] = 'payment_approved';
                } elseif (str_contains($note, 'رفض') || str_contains($note, 'مرفوض')) {
                    $update['event_type'] = 'payment_rejected';
                } elseif ($toStatus === 'cancelled') {
                    $update['event_type'] = 'order_cancelled';
                } elseif ($toStatus === 'expired') {
                    $update['event_type'] = 'order_expired';
                } elseif (str_contains($note, 'تمديد فترة حجز السعر')) {
                    $update['event_type'] = 'lock_extended';
                }
            }

            if (!empty($update)) {
                $db->table('order_events')->where('id', $e['id'])->update($update);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('order_events', ['event_type', 'meta']);
    }
}

<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * D-7: orders.created_at must be indexed (dashboard/report date scans).
 *
 * @internal
 */
final class SchemaIndexTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testOrdersCreatedAtIsIndexed(): void
    {
        $indexed = array_filter(
            \Config\Database::connect()->getIndexData('orders'),
            static fn ($i) => ($i->fields ?? []) === ['created_at'],
        );
        $this->assertNotEmpty($indexed);
    }
}

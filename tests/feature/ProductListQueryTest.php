<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * D-8: GET /products ran one product_images query per product (N+1).
 *
 * @internal
 */
final class ProductListQueryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testProductListLoadsImagesInOneQueryAndKeepsPrimary(): void
    {
        $db = \Config\Database::connect();
        $p  = $db->table('products')->where('is_active', 1)->orderBy('id')->get(2)->getResult();
        $this->assertCount(2, $p);
        $now = gmdate('Y-m-d H:i:s');
        foreach ($p as $prod) {
            $db->table('product_images')->where('product_id', $prod->id)->delete();
            $db->table('product_images')->insertBatch([
                ['product_id' => $prod->id, 'path' => "uploads/products/second-{$prod->id}.jpg", 'thumb_path' => null, 'is_primary' => 0, 'sort_order' => 0, 'created_at' => $now],
                ['product_id' => $prod->id, 'path' => "uploads/products/primary-{$prod->id}.jpg", 'thumb_path' => null, 'is_primary' => 1, 'sort_order' => 5, 'created_at' => $now],
            ]);
        }

        $imageQueries = 0;
        Events::on('DBQuery', static function ($query) use (&$imageQueries) {
            if (str_contains((string) $query->getQuery(), 'product_images')) {
                $imageQueries++;
            }
        });

        $res = $this->get('/api/v1/products?per_page=20');
        $res->assertStatus(200);
        $items = json_decode($res->getJSON(), true)['data'];
        $this->assertGreaterThan(2, count($items));
        $this->assertLessThanOrEqual(1, $imageQueries, "product_images queried {$imageQueries} times for one list page");

        $byId = array_column($items, null, 'id');
        foreach ($p as $prod) {
            $this->assertSame("uploads/products/primary-{$prod->id}.jpg", $byId[(int) $prod->id]['image']['path']);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\AuditLogModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * API-TAX-01: deleting a category in use returned 500 (FK RESTRICT).
 * API-TAX-02: duplicate slug returned 500 (unique index).
 * API-TAX-03: category/brand mutations were unaudited.
 *
 * @internal
 */
final class TaxonomyAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $admin         = (new UserModel())->where('email', 'admin@wextechnology.com')->first();
        $this->authHeaders = [
            'Authorization' => 'Bearer ' . (new TokenService())->generateAccessToken($admin)['access_token'],
            'Content-Type'  => 'application/json',
        ];
    }

    public function testDeletingCategoryInUseReturnsConflictNot500(): void
    {
        $categoryId = (int) (new ProductModel())->first()->category_id;

        $res = $this->withHeaders($this->authHeaders)->delete("/api/v1/admin/categories/{$categoryId}");
        $res->assertStatus(409);
        $this->assertNotNull((new CategoryModel())->find($categoryId));
    }

    public function testDuplicateSlugReturns422AndCreateIsAudited(): void
    {
        $slug = 'cat-' . bin2hex(random_bytes(3));
        $first = $this->withHeaders($this->authHeaders)->withBody(json_encode(['name_ar' => 'فئة', 'slug' => $slug]))
            ->post('/api/v1/admin/categories');
        $first->assertStatus(201);
        $id = (int) json_decode($first->getJSON(), true)['data']['id'];

        $dup = $this->withHeaders($this->authHeaders)->withBody(json_encode(['name_ar' => 'فئة أخرى', 'slug' => $slug]))
            ->post('/api/v1/admin/categories');
        $dup->assertStatus(422);

        $brandSlug = 'brand-' . bin2hex(random_bytes(3));
        $this->withHeaders($this->authHeaders)->withBody(json_encode(['name' => 'Brand A', 'slug' => $brandSlug]))
            ->post('/api/v1/admin/brands')->assertStatus(201);
        $this->withHeaders($this->authHeaders)->withBody(json_encode(['name' => 'Brand B', 'slug' => $brandSlug]))
            ->post('/api/v1/admin/brands')->assertStatus(422);

        $this->assertNotNull((new AuditLogModel())->where('action', 'category_created')->where('entity_id', $id)->first());
    }
}

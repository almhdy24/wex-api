<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Round 2 contract: list cards get specs; validation errors are field-level and Arabic,
 * even when the browser asks for English (the admin panel used to show
 * "Validation failed, check submitted fields").
 *
 * @internal
 */
final class ProductFormContractTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private function adminToken(): string
    {
        $users = new UserModel();
        $admin = $users->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            (new AdminSeeder())->run();
            $admin = $users->where('email', 'admin@wextechnology.com')->first();
        }
        return (new TokenService())->generateAccessToken($admin)['access_token'];
    }

    public function testListItemsCarrySpecsForCards(): void
    {
        $res  = $this->get('/api/v1/products?per_page=5');
        $res->assertStatus(200);
        $body = json_decode($res->getJSON(), true);
        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $item) {
            $this->assertArrayHasKey('specs', $item);
            $this->assertIsArray($item['specs']);
        }
    }

    public function testValidationIsArabicAndFieldLevelEvenForEnglishBrowsers(): void
    {
        $res = $this->withHeaders([
            'Authorization'   => 'Bearer ' . $this->adminToken(),
            'Content-Type'    => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->withBody(json_encode(['name' => '', 'condition' => 'used']))->post('/api/v1/admin/products');

        $res->assertStatus(422);
        $body = json_decode($res->getJSON(), true);
        $this->assertStringNotContainsString('Validation failed', (string) $body['message']);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', (string) $body['message']);
        foreach (['name', 'type', 'condition', 'price_aed', 'category_id'] as $field) {
            $this->assertArrayHasKey($field, $body['errors'], "missing field error: {$field}");
        }
    }

    private function adminJson(array $body, string $method = 'post', string $path = '/api/v1/admin/products')
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken(),
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($body))->{$method}($path);
    }

    public function testSpecSchemaEndpointIsPublic(): void
    {
        $res  = $this->get('/api/v1/spec-schema');
        $res->assertStatus(200);
        $types = array_column(json_decode($res->getJSON(), true)['data'], 'type');
        $this->assertSame(['laptop', 'phone', 'accessory'], $types);
    }

    public function testCreateWithStructuredSpecsDerivesCustomerSpecs(): void
    {
        $cat = (new \App\Models\CategoryModel())->first();
        $res = $this->adminJson([
            'name' => 'HP ProBook 440 G7 (test)', 'type' => 'laptop', 'condition' => 'refurbished',
            'category_id' => $cat->id, 'price_aed' => 1500, 'stock_quantity' => 2,
            'spec_data' => ['model' => 'ProBook 440 G7', 'cpu_family' => 'i5', 'cpu_gen' => '10', 'ram_gb' => '8', 'ram_type' => 'DDR4',
                'storage_gb' => '256', 'storage_type' => 'SSD', 'screen_inch' => '14', 'gpu_kind' => 'integrated'],
        ]);
        $res->assertStatus(201);
        $data = json_decode($res->getJSON(), true)['data'];
        $this->assertSame('8 GB DDR4', $data['specs']['الرام']);
        $this->assertSame('i5', $data['spec_data']['cpu_family']);
        $this->assertFalse($data['needs_review']);
    }

    public function testSchemaValidationReturnsPerFieldArabicErrors(): void
    {
        $cat = (new \App\Models\CategoryModel())->first();
        $res = $this->adminJson([
            'name' => 'x', 'type' => 'laptop', 'condition' => 'new', 'category_id' => $cat->id, 'price_aed' => 100,
            'spec_data' => ['ram_gb' => '7'],
        ]);
        $res->assertStatus(422);
        $errors = json_decode($res->getJSON(), true)['errors'];
        $this->assertArrayHasKey('spec_data.ram_gb', $errors);
        $this->assertArrayHasKey('spec_data.storage_gb', $errors);
    }

    public function testDraftSavesWithoutRequiredSpecsAndStaysHidden(): void
    {
        $cat = (new \App\Models\CategoryModel())->first();
        $res = $this->adminJson(['name' => 'مسودة', 'type' => 'phone', 'category_id' => $cat->id, 'is_draft' => true, 'spec_data' => new \stdClass()]);
        $res->assertStatus(201);
        $data = json_decode($res->getJSON(), true)['data'];
        $this->assertTrue($data['is_draft']);
        $this->assertFalse($data['is_active']);
    }

    public function testCountsEndpoint(): void
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken()])->get('/api/v1/admin/products/counts');
        $res->assertStatus(200);
        $d = json_decode($res->getJSON(), true)['data'];
        foreach (['all', 'laptop', 'phone', 'accessory', 'needs_review', 'draft'] as $k) {
            $this->assertArrayHasKey($k, $d);
        }
    }
}

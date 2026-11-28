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
}

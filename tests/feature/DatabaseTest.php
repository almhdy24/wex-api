<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\SettingModel;
use App\Models\ShippingZoneModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class DatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testDatabaseSeedingPopulatesCoreTables(): void
    {
        $userModel = new UserModel();
        $admin = $userModel->where('role', Wex::ROLE_ADMIN)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);

        $catModel = new CategoryModel();
        $cats = $catModel->findAll();
        $this->assertCount(3, $cats);

        $brandModel = new BrandModel();
        $brands = $brandModel->findAll();
        $this->assertGreaterThanOrEqual(8, count($brands));

        $productModel = new ProductModel();
        $products = $productModel->findAll();
        $this->assertCount(14, $products); // 8 laptops + 6 accessories

        $zoneModel = new ShippingZoneModel();
        $zones = $zoneModel->findAll();
        $this->assertCount(20, $zones);

        $settingModel = new SettingModel();
        $rateSetting = $settingModel->where('key', 'exchange_rate_sdg')->first();
        $this->assertNotNull($rateSetting);
        $this->assertSame('650', $rateSetting->value);
    }

    public function testProductEntityCastsSpecsToJsonArray(): void
    {
        $productModel = new ProductModel();
        $laptop = $productModel->where('slug', 'dell-latitude-7490')->first();

        $this->assertNotNull($laptop);
        $this->assertSame(750, $laptop->price_aed);
        $this->assertTrue($laptop->is_active);
        $this->assertTrue($laptop->is_featured);

        $specs = $laptop->getSpecs();
        $this->assertIsArray($specs);
        $this->assertArrayHasKey('cpu', $specs);
        $this->assertSame('Intel Core i5-8350U', $specs['cpu']);
        $this->assertSame(Wex::STOCK_STATUS_IN_STOCK, $laptop->getStockStatus(2));
    }
}

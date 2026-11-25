<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\ArabicSearch;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * UX-SEARCH-01: Arabic-normalised, cross-script catalogue search.
 *
 * @internal
 */
final class ArabicSearchTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private function names(string $q): array
    {
        $res = $this->get('/api/v1/products?per_page=50&q=' . rawurlencode($q));
        $res->assertStatus(200);

        return array_column(json_decode($res->getJSON(), true)['data'], 'name');
    }

    public function testNormalizer(): void
    {
        $this->assertSame('اجهزه مستعمله 8gb', ArabicSearch::normalize('أجهزةٌ مُستعمَلة ٨GB'));
        $this->assertSame('ي', ArabicSearch::normalize('ى'));
    }

    public function testArabicSpellingFindsLatinBrandAndViceVersa(): void
    {
        $latin = $this->names('Dell');
        $this->assertNotEmpty($latin, 'seed has Dell products');
        $this->assertSame($latin, $this->names('ديل'));
        $this->assertSame($latin, $this->names('دِيل'));        // diacritics ignored
        $lenovo = $this->names('لينوفو');
        $this->assertNotEmpty($lenovo);
        foreach ($lenovo as $n) {
            $this->assertMatchesRegularExpression('/lenovo|thinkpad|لينوفو/i', $n);
        }
    }

    public function testEveryWordMustMatchAndDigitsAreNormalised(): void
    {
        $both = $this->names('ديل 7490');
        $this->assertNotEmpty($both);
        foreach ($both as $n) {
            $this->assertStringContainsString('7490', $n);
        }
        $this->assertSame($both, $this->names('ديل ٧٤٩٠'));
        $this->assertSame([], $this->names('ديل لينوفو'));
    }

    public function testNewProductIsIndexedOnSave(): void
    {
        $admin = (new UserModel())->where('email', 'admin@wextechnology.com')->first();
        $token = (new TokenService())->generateAccessToken($admin)['access_token'];
        $catId = (int) \Config\Database::connect()->table('categories')->get(1)->getRow()->id;
        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'])
            ->withBody(json_encode([
                'name' => 'شاحن سامسونج سريع', 'type' => 'accessory', 'condition' => 'new',
                'price_aed' => 20, 'category_id' => $catId, 'stock_quantity' => 3,
            ]))->post('/api/v1/admin/products')->assertStatus(201);

        try {
            $this->assertContains('شاحن سامسونج سريع', $this->names('samsung charger'));
            $this->assertContains('شاحن سامسونج سريع', $this->names('سامسونغ'));
        } finally {
            // the suite shares one database: do not change other tests' product counts
            \Config\Database::connect()->table('products')->where('name', 'شاحن سامسونج سريع')->delete();
        }
    }
}

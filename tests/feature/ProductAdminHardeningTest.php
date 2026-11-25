<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\AuditLogModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * API-PRD-01: PATCH /admin/products must validate every field it accepts.
 * API-PRD-02: product mutations must be audited with actor and diff.
 *
 * @internal
 */
final class ProductAdminHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private string $token;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $admin         = (new UserModel())->where('email', 'admin@wextechnology.com')->first();
        $this->adminId = (int) $admin->id;
        $this->token   = (new TokenService())->generateAccessToken($admin)['access_token'];
    }

    private function patchProduct(int $id, array $body)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json'])
            ->withBody(json_encode($body))->patch("/api/v1/admin/products/{$id}");
    }

    public function testInvalidUpdatesAreRejectedAndNothingIsWritten(): void
    {
        $before = (new ProductModel())->find(4);

        foreach ([
            ['price_aed' => 0],
            ['price_aed' => -50],
            ['price_aed' => 'abc'],
            ['stock_quantity' => -5],
            ['type' => 'tablet'],
            ['condition' => 'broken'],
            ['category_id' => 999999],
            ['brand_id' => 999999],
            ['name' => ''],
        ] as $body) {
            $this->patchProduct(4, $body)->assertStatus(422);
        }

        $after = (new ProductModel())->find(4);
        $this->assertSame((int) $before->price_aed, (int) $after->price_aed);
        $this->assertSame((int) $before->stock_quantity, (int) $after->stock_quantity);
        $this->assertSame($before->type, $after->type);
    }

    public function testValidUpdateIsAuditedWithDiff(): void
    {
        $before   = (new ProductModel())->find(4);
        $newPrice = (int) $before->price_aed + 7;

        $this->patchProduct(4, ['price_aed' => $newPrice])->assertStatus(200);

        $log = (new AuditLogModel())->where('action', 'product_updated')->where('entity_id', 4)->orderBy('id', 'DESC')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->adminId, (int) $log->user_id);
        $details = is_string($log->details) ? json_decode($log->details, true) : (array) $log->details;
        $this->assertSame($newPrice, (int) $details['changes']['price_aed']['to']);
    }
}

<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class PingTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testPingReturnsSuccessEnvelope(): void
    {
        $result = $this->get('api/v1/ping');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('خدمة ويكس تكنولوجي تعمل بنجاح', $body['message']);
        $this->assertSame('wex-api', $body['data']['service']);
        $this->assertNull($body['errors']);
    }

    public function testRouteNotFoundReturnsStandardJsonError(): void
    {
        $result = $this->get('api/v1/nonexistent-route-xyz');

        $result->assertStatus(404);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('الرابط المطلوب غير موجود', $body['message']);
    }

    // CORS-01: Date must be readable cross-origin (price-lock countdown server clock).
    public function testCorsExposesDateHeader(): void
    {
        $result = $this->withHeaders(['Origin' => 'http://localhost:5173'])->get('api/v1/ping');
        $expose = $result->response()->getHeaderLine('Access-Control-Expose-Headers');
        $this->assertStringContainsString('Date', $expose);
    }
}

<?php

namespace Tests\Unit;

use App\Libraries\ApiExceptionHandler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * D-5: error responses from the global exception handler skipped after-filters,
 * so they had no CORS or security headers.
 *
 * @internal
 */
final class ExceptionHeadersTest extends CIUnitTestCase
{
    public function testUnhandledErrorResponseCarriesCorsAndSecurityHeaders(): void
    {
        $request = Services::incomingrequest(null, false);
        $request->setHeader('Origin', 'http://localhost:5173');
        $response = Services::response(null, false);

        ob_start();
        (new ApiExceptionHandler())->handle(new \RuntimeException('boom'), $request, $response, 500, 1);
        ob_end_clean();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }
}

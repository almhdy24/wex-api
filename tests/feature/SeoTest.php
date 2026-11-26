<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * SEO-01: server-side meta for crawlers and WhatsApp/social link previews.
 *
 * @internal
 */
final class SeoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private function ld(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(static fn ($j) => json_decode($j, true), $m[1]);
    }

    public function testProductPageCarriesMetaAndProductJsonLd(): void
    {
        $p = \Config\Database::connect()->table('products')->where('is_active', 1)->get(1)->getRow();
        $res = $this->get('/products/' . $p->slug);
        $res->assertStatus(200);
        $html = $res->getBody();

        $this->assertStringContainsString('<title>' . htmlspecialchars($p->name, ENT_QUOTES | ENT_HTML5), $html);
        $this->assertMatchesRegularExpression('#<meta property="og:title" content="[^"]+"#', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:locale" content="ar_AR"\s*/?>#', $html);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^"]+/products/#', $html);
        $this->assertStringContainsString('id="root"', $html);
        $this->assertSame(1, substr_count($html, '<title>'));

        $product = array_values(array_filter($this->ld($html), static fn ($d) => ($d['@type'] ?? '') === 'Product'))[0] ?? null;
        $this->assertNotNull($product);
        $this->assertSame($p->name, $product['name']);
        $this->assertSame('SDG', $product['offers']['priceCurrency']);
        $this->assertGreaterThan(0, (int) $product['offers']['price']);
        $this->assertStringStartsWith('https://schema.org/', $product['offers']['availability']);

        // customer surface: never AED or the exchange rate
        $this->assertDoesNotMatchRegularExpression('/AED|درهم|سعر الصرف/u', $html);
        // HTML keeps the app CSP, not the API's default-src 'none'
        $this->assertStringContainsString("script-src 'self'", $res->response()->getHeaderLine('Content-Security-Policy'));
    }

    /** HEAD must carry GET's caching policy (the security filter used to force no-store). */
    public function testHeadKeepsTheShellCachePolicy(): void
    {
        $p = \Config\Database::connect()->table('products')->where('is_active', 1)->get(1)->getRow();
        $get = $this->get('/products/' . $p->slug);
        $head = $this->call('HEAD', '/products/' . $p->slug);
        $cc = $get->response()->getHeaderLine('Cache-Control');
        $this->assertStringStartsWith('public, max-age=60', $cc);
        $this->assertStringNotContainsString('no-store', $cc);
        $etag = $get->response()->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);
        // Revalidation with the ETag is a 304 with no body.
        $again = $this->withHeaders(['If-None-Match' => $etag])->get('/products/' . $p->slug);
        $again->assertStatus(304);
        // (the test harness appends its buffered '\n'; a real 304 is sent without a body)
        $this->assertSame('', trim((string) $again->getBody()));
        $this->assertSame($get->response()->getHeaderLine('Cache-Control'), $head->response()->getHeaderLine('Cache-Control'));
    }

    public function testValuesAreEscaped(): void
    {
        $db = \Config\Database::connect();
        $id = $db->table('products')->where('is_active', 1)->get(1)->getRow()->id;
        $db->table('products')->where('id', $id)->update(['name' => 'X"><script>alert(1)</script>', 'short_description' => '</script><b>x</b>']);
        try {
            $slug = $db->table('products')->where('id', $id)->get()->getRow()->slug;
            $html = $this->get('/products/' . $slug)->getBody();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('"><script>', $html);
            $this->assertStringNotContainsString('</script><b>', $html);
        } finally {
            $db->table('products')->where('id', $id)->update(['name' => 'Dell Latitude 7490']);
        }
    }

    public function testUnknownProductIs404NoIndex(): void
    {
        $res = $this->get('/products/no-such-product-xyz');
        $res->assertStatus(404);
        $this->assertMatchesRegularExpression('#<meta name="robots" content="noindex"\s*/?>#', $res->getBody());
    }

    public function testHomeHasStoreJsonLdWithoutEmptyFields(): void
    {
        $html = $this->get('/')->getBody();
        $store = array_values(array_filter($this->ld($html), static fn ($d) => ($d['@type'] ?? '') === 'Store'))[0] ?? null;
        $this->assertNotNull($store);
        $this->assertArrayNotHasKey('telephone', array_filter($store, static fn ($v) => $v === ''));
    }

    public function testSitemapAndRobots(): void
    {
        $slug = \Config\Database::connect()->table('products')->where('is_active', 1)->get(1)->getRow()->slug;
        $xml = $this->get('/sitemap.xml')->getBody();
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('/products/' . rawurlencode($slug) . '</loc>', $xml);

        $robots = $this->get('/robots.txt')->getBody();
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /api/', $robots);
        $this->assertStringContainsString('Sitemap: ', $robots);
    }
}

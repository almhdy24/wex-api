<?php

namespace App\Controllers;

use App\Services\SeoService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * HTML shell with server-side meta for public storefront pages + sitemap/robots.
 * Routed by the root .htaccess only for: /, /catalog, /faq, /pages/*, /products/*,
 * /sitemap.xml, /robots.txt. Everything else is served as static index.html.
 */
class SeoController extends BaseController
{
    /** Same policy as the static app shell in the root .htaccess. */
    public const APP_CSP = "default-src 'self'; script-src 'self' https://accounts.google.com/gsi/client; style-src 'self' 'unsafe-inline' https://accounts.google.com/gsi/style; img-src 'self' data: blob: https://*.googleusercontent.com; font-src 'self' data:; connect-src 'self' https://accounts.google.com/gsi/; frame-src https://accounts.google.com/gsi/; worker-src 'self'; manifest-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'";

    public function page(...$segments): ResponseInterface
    {
        $path = '/' . implode('/', array_map('strval', $segments));
        $out = (new SeoService())->render($path);

        // Short shared caching with validators: a deploy reaches visitors within a minute,
        // repeat views revalidate cheaply (304), and nothing is ever no-store.
        $etag = '"' . substr(hash('sha256', $out['html']), 0, 32) . '"';
        $response = $this->response
            ->removeHeader('Cache-Control')->setHeader('Cache-Control', 'public, max-age=60, must-revalidate')
            ->setHeader('ETag', $etag)
            ->setHeader('Content-Security-Policy', self::APP_CSP);
        $shell = FCPATH . '../index.html';
        if (is_file($shell)) {
            $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', (int) filemtime($shell)) . ' GMT');
        }

        $inm = trim($this->request->getHeaderLine('If-None-Match'));
        if ($out['status'] === 200 && $inm !== '' && in_array($etag, array_map('trim', explode(',', $inm)), true)) {
            return $response->setStatusCode(304)->setBody('');
        }

        return $response
            ->setStatusCode($out['status'])
            ->setContentType('text/html', 'UTF-8')
            ->setBody($out['html']);
    }

    public function sitemap(): ResponseInterface
    {
        return $this->response->setContentType('application/xml', 'UTF-8')
            ->removeHeader('Cache-Control')->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody((new SeoService())->sitemap());
    }

    public function robots(): ResponseInterface
    {
        $base = (new SeoService())->baseUrl();
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /account\nDisallow: /checkout\nDisallow: /orders\nDisallow: /cart\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n";

        return $this->response->setContentType('text/plain', 'UTF-8')->removeHeader('Cache-Control')->setHeader('Cache-Control', 'public, max-age=86400')->setBody($body);
    }
}

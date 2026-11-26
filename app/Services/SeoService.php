<?php

namespace App\Services;

/**
 * Server-side meta for crawlers and link previews (WhatsApp, Facebook, Google) that do
 * not run JavaScript. Injects title/description/canonical/Open Graph/JSON-LD into the
 * built SPA shell (FCPATH/index.html) for public pages, and builds sitemap.xml.
 * Every value is HTML-escaped; JSON-LD is encoded with JSON_HEX_TAG.
 */
class SeoService
{
    protected SettingService $settings;
    protected PricingService $pricing;

    public function __construct(?SettingService $settings = null, ?PricingService $pricing = null)
    {
        $this->settings = $settings ?? new SettingService();
        $this->pricing  = $pricing ?? new PricingService();
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('App')->baseURL, '/');
    }

    /**
     * @return array{status: int, html: string}
     */
    public function render(string $path): array
    {
        $path = '/' . trim($path, '/');
        $cacheKey = 'seo_' . md5($path);
        if (ENVIRONMENT !== 'testing' && ($hit = cache($cacheKey))) {
            return $hit;
        }

        $meta = $this->metaFor($path);
        $result = ['status' => $meta['status'], 'html' => $this->inject($this->shell(), $meta)];
        if (ENVIRONMENT !== 'testing') {
            cache()->save($cacheKey, $result, 600);
        }

        return $result;
    }

    /**
     * @return array{status:int,title:string,description:string,canonical:string,image:?string,type:string,jsonld:list<array>}
     */
    public function metaFor(string $path): array
    {
        $shopName = (string) ($this->settings->get('shop_name', '') ?: 'ويكس تكنولوجي');
        $base     = $this->baseUrl();
        $default  = [
            'status'      => 200,
            'title'       => $shopName . ' | أجهزة لابتوب وهواتف وملحقات',
            'description' => 'متجر أجهزة لابتوب وهواتف وملحقات. الأسعار بالجنيه السوداني، ويُثبَّت السعر بعد تأكيد الطلب. استلام من المحل أو توصيل.',
            'canonical'   => $base . ($path === '/' ? '/' : $path),
            'image'       => $base . '/icons/icon-512.png',
            'type'        => 'website',
            'jsonld'      => [],
        ];

        if ($path === '/') {
            $default['jsonld'][] = $this->organization($shopName, $base);

            return $default;
        }
        if ($path === '/catalog') {
            return ['title' => 'تصفّح الأجهزة | ' . $shopName, 'description' => 'تصفّح أجهزة اللابتوب والهواتف والملحقات المتوفرة مع الأسعار بالجنيه السوداني.'] + $default;
        }
        if ($path === '/faq') {
            return ['title' => 'الأسئلة الشائعة | ' . $shopName, 'description' => 'كيف تطلب، وكيف يعمل تثبيت السعر والتحويل البنكي، والاستلام والتوصيل.'] + $default;
        }
        if (preg_match('#^/pages/([A-Za-z0-9_-]{1,100})$#', $path, $m)) {
            $page = (new \App\Models\PageModel())->where('slug', $m[1])->where('is_active', 1)->first();
            if (!$page) {
                return ['status' => 404, 'title' => 'الصفحة غير موجودة | ' . $shopName] + $default;
            }
            return ['title' => $page->title . ' | ' . $shopName, 'description' => $this->excerpt(\App\Libraries\ArabicDuration::render((string) $page->body))] + $default;
        }
        if (preg_match('#^/products/([^/]{1,200})$#', $path, $m)) {
            return $this->productMeta(rawurldecode($m[1]), $shopName, $base, $default);
        }

        return $default;
    }

    protected function productMeta(string $slug, string $shopName, string $base, array $default): array
    {
        try {
            $p = (new ProductService())->getPublicProductDetail($slug);
        } catch (\App\Exceptions\NotFoundException) {
            return ['status' => 404, 'title' => 'الجهاز غير موجود | ' . $shopName] + $default;
        }

        $img = null;
        foreach ($p['images'] ?? [] as $i) {
            if (!empty($i['path'])) {
                $img = $base . '/' . ltrim($i['path'], '/');
                break;
            }
        }
        $condition = $p['condition'] === 'new' ? 'جديد' : 'مستعمل';
        $price = number_format((int) $p['price_sdg']) . "\u{00A0}ج.س";
        $desc = trim(($p['short_description'] ?: '') . ' ') ?: $p['name'];
        $availability = match ($p['stock_status']) {
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'low_stock'    => 'https://schema.org/LimitedAvailability',
            default        => 'https://schema.org/InStock',
        };

        return [
            'title'       => $p['name'] . ' - ' . $price . ' | ' . $shopName,
            'description' => $this->excerpt("{$condition} · {$price} · {$desc}"),
            'canonical'   => $base . '/products/' . rawurlencode($p['slug']),
            'image'       => $img ?? $default['image'],
            'type'        => 'product',
            'jsonld'      => [[
                '@context'    => 'https://schema.org',
                '@type'       => 'Product',
                'name'        => $p['name'],
                'image'       => $img ? [$img] : [],
                'description' => $this->excerpt($desc, 300),
                'brand'       => !empty($p['brand']['name']) ? ['@type' => 'Brand', 'name' => $p['brand']['name']] : null,
                'itemCondition' => $p['condition'] === 'new' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition',
                'offers'      => [
                    '@type'         => 'Offer',
                    'price'         => (string) (int) $p['price_sdg'],
                    'priceCurrency' => 'SDG',
                    'availability'  => $availability,
                    'url'           => $base . '/products/' . rawurlencode($p['slug']),
                ],
            ]],
        ] + $default;
    }

    protected function organization(string $name, string $base): array
    {
        $org = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $name, 'url' => $base . '/'];
        if ($addr = trim((string) $this->settings->get('shop_address', ''))) {
            $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $addr, 'addressCountry' => 'SD'];
        }
        if ($phone = trim((string) $this->settings->get('shop_phone', ''))) {
            $org['telephone'] = $phone;
        }
        if ($hours = trim((string) $this->settings->get('working_hours', ''))) {
            $org['description'] = 'ساعات العمل: ' . $hours;
        }

        return $org;
    }

    protected function excerpt(string $text, int $max = 160): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)) . '…' : $t;
    }

    protected function shell(): string
    {
        $file = FCPATH . 'index.html';
        $html = is_file($file) ? (string) file_get_contents($file) : '';

        return $html !== '' && str_contains($html, '</head>')
            ? $html
            : '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title></title></head><body><div id="root"></div></body></html>';
    }

    public function inject(string $html, array $meta): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove the shell's own title/description/og so each tag appears once.
        $html = preg_replace('#<title>.*?</title>#su', '', $html);
        $html = preg_replace('#<meta\s+(?:name="description"|property="og:[^"]+"|name="twitter:[^"]+")[^>]*>\s*#su', '', $html);
        $html = preg_replace('#<link\s+rel="canonical"[^>]*>\s*#su', '', $html);

        $tags = [
            '<title>' . $e($meta['title']) . '</title>',
            '<meta name="description" content="' . $e($meta['description']) . '">',
            '<link rel="canonical" href="' . $e($meta['canonical']) . '">',
            '<meta property="og:site_name" content="' . $e((string) ($this->settings->get('shop_name', '') ?: 'ويكس تكنولوجي')) . '">',
            '<meta property="og:locale" content="ar_AR">',
            '<meta property="og:type" content="' . $e($meta['type']) . '">',
            '<meta property="og:title" content="' . $e($meta['title']) . '">',
            '<meta property="og:description" content="' . $e($meta['description']) . '">',
            '<meta property="og:url" content="' . $e($meta['canonical']) . '">',
            '<meta name="twitter:card" content="summary_large_image">',
        ];
        if (!empty($meta['image'])) {
            $tags[] = '<meta property="og:image" content="' . $e($meta['image']) . '">';
        }
        if ($meta['status'] === 404) {
            $tags[] = '<meta name="robots" content="noindex" />';
        }
        foreach ($meta['jsonld'] as $ld) {
            $ld = array_filter($ld, static fn ($v) => $v !== null && $v !== []);
            $tags[] = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
        }

        return str_replace('</head>', '    ' . implode("\n    ", $tags) . "\n  </head>", $html);
    }

    public function sitemap(): string
    {
        $base = $this->baseUrl();
        $urls = [['/', 'daily'], ['/catalog', 'daily'], ['/faq', 'monthly']];
        $db = \Config\Database::connect();
        foreach ($db->table('products')->select('slug, updated_at')->where('is_active', 1)->where('deleted_at', null)->get()->getResultArray() as $p) {
            $urls[] = ['/products/' . rawurlencode($p['slug']), 'weekly', $p['updated_at']];
        }
        foreach ($db->table('pages')->select('slug, updated_at')->where('is_active', 1)->get()->getResultArray() as $pg) {
            $urls[] = ['/pages/' . rawurlencode($pg['slug']), 'monthly', $pg['updated_at']];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $u[0], ENT_XML1) . '</loc>'
                . (!empty($u[2]) ? '<lastmod>' . gmdate('Y-m-d', strtotime($u[2])) . '</lastmod>' : '')
                . '<changefreq>' . $u[1] . "</changefreq></url>\n";
        }

        return $xml . '</urlset>' . "\n";
    }
}

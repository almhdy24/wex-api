<?php

namespace Tests\Feature;

use App\Libraries\ArabicDuration;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Lock duration and shop facts in customer content come from settings, never hard-coded
 * ("4 ساعات" was baked into the default FAQ and terms).
 *
 * @internal
 */
final class ContentTokensTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = \App\Database\Seeds\DatabaseSeeder::class;

    public function testArabicHourForms(): void
    {
        $this->assertSame('ساعة واحدة', ArabicDuration::hours(1));
        $this->assertSame('ساعتين', ArabicDuration::hours(2));
        $this->assertSame('3 ساعات', ArabicDuration::hours(3));
        $this->assertSame('10 ساعات', ArabicDuration::hours(10));
        $this->assertSame('11 ساعة', ArabicDuration::hours(11));
        $this->assertSame('24 ساعة', ArabicDuration::hours(24));
    }

    private function setSetting(string $key, string $value): void
    {
        $db = \Config\Database::connect();
        if ($db->table('settings')->where('key', $key)->countAllResults() === 0) {
            $db->table('settings')->insert(['key' => $key, 'value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        } else {
            $db->table('settings')->where('key', $key)->update(['value' => $value]);
        }
        cache()->clean();
    }

    public function testFaqAndTermsRenderTheConfiguredDuration(): void
    {
        $this->setSetting('lock_hours', '1');
        $this->setSetting('shop_address', 'عنوان تجريبي');
        $this->setSetting('working_hours', 'يومياً 9 ص - 9 م');

        $faq = json_encode(json_decode($this->get('/api/v1/content/faq')->getJSON(), true)['data'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('لمدة ساعة واحدة', $faq);
        $this->assertStringContainsString('عنوان تجريبي', $faq);
        $this->assertStringNotContainsString('{{', $faq);
        $this->assertStringNotContainsString('4 ساعات', $faq);

        $terms = json_decode($this->get('/api/v1/content/pages/terms')->getJSON(), true)['data']['body'];
        $this->assertStringNotContainsString('ملاحظة للإدارة', $terms, 'internal admin note shown to customers');
        foreach (['about', 'terms', 'privacy', 'returns'] as $slug) {
            $res = json_decode($this->get('/api/v1/content/pages/' . $slug)->getJSON(), true);
            $this->assertStringNotContainsString('مول الواحة', (string) ($res['data']['body'] ?? ''), "hard-coded location in page {$slug}");
        }
        $hero = (string) $this->get('/api/v1/content/hero-slides')->getJSON();
        $this->assertStringNotContainsString('أم درمان', $hero, 'hard-coded location in hero slides');
        $this->assertStringContainsString('لمدة ساعة واحدة', $terms);
        $this->assertStringNotContainsString('4 ساعات', $terms);

        // Server-rendered page meta too.
        $html = $this->get('/pages/terms')->getBody();
        $this->assertStringNotContainsString('{{lock_duration}}', $html);
    }

    public function testRewriteMigrationTouchesOnlyUnmodifiedDefaults(): void
    {
        $db = \Config\Database::connect();
        $old = 'عند تقديم الطلب، يتم حجز الجهاز وتثبيت سعره بـ ج.س لمدة 4 ساعات، مما يحميك من أي تقلبات في سعر الصرف أثناء إتمام التحويل عبر تطبيق بنكك.';
        $edited = 'نص عدّله صاحب المحل لمدة 4 ساعات.';
        $db->table('faq_items')->insert(['question' => 'س1', 'answer' => $old, 'sort_order' => 90, 'is_active' => 1]);
        $db->table('faq_items')->insert(['question' => 'س2', 'answer' => $edited, 'sort_order' => 91, 'is_active' => 1]);

        require_once APPPATH . 'Database/Migrations/2026-11-26-000005_TokeniseDefaultContentDurations.php';
        (new \App\Database\Migrations\TokeniseDefaultContentDurations(\Config\Database::forge()))->up();

        $this->assertStringContainsString('{{lock_duration}}', $db->table('faq_items')->where('question', 'س1')->get()->getRow()->answer);
        $this->assertSame($edited, $db->table('faq_items')->where('question', 'س2')->get()->getRow()->answer);
    }
}

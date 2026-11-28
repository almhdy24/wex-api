<?php

namespace Tests\Unit;

use App\Libraries\SpecSchema;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class SpecSchemaTest extends CIUnitTestCase
{
    public function testEveryTypeHasUniqueFieldKeysAndDisplayRows(): void
    {
        foreach (SpecSchema::all() as $type => $schema) {
            $keys = array_column($schema['fields'], 'key');
            $this->assertSame($keys, array_values(array_unique($keys)), "dup keys in {$type}");
            $this->assertNotEmpty($schema['display']);
            foreach ($schema['fields'] as $f) {
                if (in_array($f['input'], ['select', 'segmented', 'multi'], true)) {
                    $this->assertNotEmpty($f['options'], "{$type}.{$f['key']} options");
                    foreach ($f['options'] as $o) {
                        $this->assertIsString($o['value']);
                    }
                }
            }
        }
    }

    public function testValidationFlagsRequiredUnknownAndBadOptions(): void
    {
        $errors = SpecSchema::validate('laptop', ['ram_gb' => '7', 'cpu_family' => 'i5', 'foo' => 1]);
        $this->assertArrayHasKey('spec_data.ram_gb', $errors);
        $this->assertArrayHasKey('spec_data.foo', $errors);
        $this->assertArrayHasKey('spec_data.storage_gb', $errors);
        $this->assertArrayNotHasKey('spec_data.cpu_family', $errors);
        foreach ($errors as $msg) {
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $msg);
        }
    }

    public function testDraftSkipsRequiredButStillChecksValues(): void
    {
        $this->assertSame([], SpecSchema::validate('laptop', [], true));
        $this->assertArrayHasKey('spec_data.ram_gb', SpecSchema::validate('laptop', ['ram_gb' => '7'], true));
    }

    public function testConditionalFieldsAreSkippedWhenHidden(): void
    {
        $data = ['gpu_kind' => 'integrated', 'gpu_model' => 'anything'];
        $this->assertSame(['gpu_kind' => 'integrated'], SpecSchema::clean('laptop', $data));
    }

    public function testLabelMapAndChips(): void
    {
        $d = ['cpu_family' => 'i5', 'cpu_gen' => '8', 'ram_gb' => '8', 'ram_type' => 'DDR4', 'storage_gb' => '256', 'storage_type' => 'SSD'];
        $map = SpecSchema::labelMap('laptop', $d);
        $this->assertSame('8 GB DDR4', $map['الرام']);
        $this->assertSame('256 GB SSD', $map['التخزين']);
        $this->assertSame(['i5', '8GB', '256GB SSD'], SpecSchema::chips('laptop', $d));
    }

    public function testLegacyNormalizerFixesTransliterationAndSwappedScreenGpu(): void
    {
        $r = SpecSchema::fromLegacy('laptop', [
            'cpu'     => 'Intel Core i5-8350U',
            'ram'     => '8 قيقا دسديارفور',
            'storage' => '256 اس اسدي',
            'gpu'     => '14 بوصة FHD',            // swapped on purpose
            'screen'  => 'Intel UHD Graphics 620',
        ]);
        $this->assertFalse($r['needs_review']);
        $this->assertSame('8', $r['data']['ram_gb']);
        $this->assertSame('DDR4', $r['data']['ram_type']);
        $this->assertSame('256', $r['data']['storage_gb']);
        $this->assertSame('SSD', $r['data']['storage_type']);
        $this->assertSame('14', $r['data']['screen_inch']);
        $this->assertSame('integrated', $r['data']['gpu_kind']);
    }

    public function testUnparseableLegacyIsFlaggedNotDropped(): void
    {
        $r = SpecSchema::fromLegacy('laptop', ['x' => 'y']);
        $this->assertTrue($r['needs_review']);
        $this->assertNotEmpty($r['unparsed']);
    }
}

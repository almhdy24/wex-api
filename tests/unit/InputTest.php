<?php

namespace Tests\Unit;

use App\Libraries\Input;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * API-INP-01: string "false" from form/raw input must not become true.
 *
 * @internal
 */
final class InputTest extends CIUnitTestCase
{
    public function testParsesBooleansStrictly(): void
    {
        foreach ([true, 1, '1', 'true', 'on', 'yes'] as $v) {
            $this->assertTrue(Input::bool($v), var_export($v, true));
        }
        foreach ([false, 0, '0', 'false', 'off', 'no', '', null, 'garbage'] as $v) {
            $this->assertFalse(Input::bool($v), var_export($v, true));
        }
    }
}

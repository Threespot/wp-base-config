<?php

namespace Threespot\Wp\Tests\MuPlugins;

use Brain\Monkey\Functions;
use Threespot\Wp\MuPlugins\BlockConfig;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

/**
 * The deprecated stripEmptyParagraphs() alias. Remove with the alias in the
 * next major version.
 */
class BlockConfigDeprecationTest extends BrainMonkeyTestCase
{
    public function test_strip_empty_paragraphs_warns_and_calls_strip_empty_elements(): void
    {
        Functions\expect('_deprecated_function')
            ->once()
            ->with(BlockConfig::class . '::stripEmptyParagraphs', '0.2.16', BlockConfig::class . '::stripEmptyElements');

        $this->assertSame('<p>Keep</p>', BlockConfig::stripEmptyParagraphs('<p>Keep</p><p></p><h2></h2>'));
    }
}

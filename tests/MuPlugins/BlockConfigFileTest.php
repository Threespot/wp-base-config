<?php

namespace Threespot\Wp\Tests\MuPlugins;

use Brain\Monkey\Functions;
use Threespot\Wp\MuPlugins\BlockConfig;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

/**
 * Behavior tests for the core/file branch of BlockConfig::customizeBlockMarkup().
 */
class BlockConfigFileTest extends BrainMonkeyTestCase
{
    // The File block's markup with "Show inline embed" on, as WordPress saves it.
    private const WITH_PREVIEW = '<div class="wp-block-file"><object class="wp-block-file__embed" data="https://example.com/report.pdf" type="application/pdf" style="width:100%;height:600px" aria-label="Embed of Report."></object><a id="wp-block-file--media-1" href="https://example.com/report.pdf">Report</a><a href="https://example.com/report.pdf" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-1">Download</a></div>';

    private const WITHOUT_PREVIEW = '<div class="wp-block-file"><a id="wp-block-file--media-1" href="https://example.com/report.pdf">Report</a><a href="https://example.com/report.pdf" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-1">Download</a></div>';

    protected function setUp(): void
    {
        parent::setUp();

        // No local file, so the size/type suffix branch is skipped
        Functions\when('get_attached_file')->justReturn(false);
    }

    private static function render(string $content): string
    {
        return BlockConfig::customizeBlockMarkup($content, ['blockName' => 'core/file', 'attrs' => ['id' => 1]]);
    }

    public function test_removes_the_inline_pdf_preview(): void
    {
        $this->assertSame(self::WITHOUT_PREVIEW, self::render(self::WITH_PREVIEW));
    }

    public function test_removes_a_preview_whatever_the_attribute_order(): void
    {
        $html = str_replace('<object class="wp-block-file__embed" data=', '<object type="application/pdf" class="wp-block-file__embed" data=', self::WITH_PREVIEW);

        $this->assertSame(self::WITHOUT_PREVIEW, self::render($html));
    }

    public function test_keeps_a_file_block_without_a_preview(): void
    {
        $this->assertSame(self::WITHOUT_PREVIEW, self::render(self::WITHOUT_PREVIEW));
    }
}

<?php

namespace Threespot\Wp\Tests\Helpers;

use PHPUnit\Framework\TestCase;

use function Threespot\Wp\Helpers\bis_get_sizes;
use function Threespot\Wp\Helpers\blank_gif;
use function Threespot\Wp\Helpers\buildAttributes;
use function Threespot\Wp\Helpers\calculate_height;
use function Threespot\Wp\Helpers\get_registered_image_sizes;

class ImageTest extends TestCase
{
    public function test_calculate_height_16_9(): void
    {
        $this->assertSame(360, calculate_height(640, '16:9'));
    }

    public function test_calculate_height_1_1(): void
    {
        $this->assertSame(500, calculate_height(500, '1:1'));
    }

    public function test_calculate_height_3_2(): void
    {
        $this->assertSame(400, calculate_height(600, '3:2'));
    }

    public function test_calculate_height_invalid_ratio_falls_back_to_width(): void
    {
        $this->assertSame(640, calculate_height(640, 'not-a-ratio'));
    }

    public function test_blank_gif_returns_base64_data_uri(): void
    {
        $this->assertStringStartsWith('data:image/gif;base64,', blank_gif());
    }

    public function test_get_registered_image_sizes_expands_widths(): void
    {
        $sizes = get_registered_image_sizes();

        // IMAGE_SIZES.square has 3 widths cropped at 1:1
        $this->assertArrayHasKey('square_360', $sizes);
        $this->assertSame([360, 360, 1], $sizes['square_360']);

        $this->assertArrayHasKey('square_1080', $sizes);
        $this->assertSame([1080, 1080, 1], $sizes['square_1080']);
    }

    public function test_get_registered_image_sizes_uses_zero_height_when_not_cropped(): void
    {
        $sizes = get_registered_image_sizes();

        // square_scaled has crop=0, so height should be 0 (preserve aspect ratio)
        $this->assertSame([360, 0, 0], $sizes['square_scaled_360']);
    }

    public function test_get_registered_image_sizes_calculates_16_9_heights(): void
    {
        $sizes = get_registered_image_sizes();

        $this->assertSame([640, 360, 1], $sizes['sixteen_nine_640']);
        $this->assertSame([1600, 900, 1], $sizes['sixteen_nine_1600']);
    }

    public function test_bis_get_sizes_returns_matching_sizes(): void
    {
        $result = bis_get_sizes(123, 'square');

        $this->assertIsArray($result);
        // Should match square_360, square_750, square_1080 — but NOT square_scaled_*
        $this->assertCount(3, $result);
        $this->assertContains('square_360', $result);
        $this->assertContains('square_750', $result);
        $this->assertContains('square_1080', $result);

        foreach ($result as $name) {
            $this->assertStringStartsNotWith('square_scaled_', $name);
        }
    }

    public function test_bis_get_sizes_does_not_match_prefix_overlaps(): void
    {
        // 'square' must not match 'square_scaled_*' because the regex requires
        // base name + underscore + digits only.
        $matched = bis_get_sizes(123, 'square');

        foreach ($matched as $name) {
            $this->assertMatchesRegularExpression('/^square_\d+$/', $name);
        }
    }

    public function test_bis_get_sizes_returns_false_for_unknown_base(): void
    {
        $this->assertFalse(bis_get_sizes(123, 'nonexistent'));
    }

    public function test_bis_get_sizes_returns_false_for_empty_inputs(): void
    {
        $this->assertFalse(bis_get_sizes(0, 'square'));
        $this->assertFalse(bis_get_sizes(123, ''));
    }

    public function test_buildAttributes_empty_returns_empty(): void
    {
        $this->assertSame('', buildAttributes([]));
    }

    public function test_buildAttributes_formats_simple_attrs(): void
    {
        $this->assertSame(
            ' class="hero" loading="lazy"',
            buildAttributes(['class' => 'hero', 'loading' => 'lazy'])
        );
    }

    public function test_buildAttributes_preserves_data_attributes(): void
    {
        $this->assertSame(
            ' data-src="image.jpg"',
            buildAttributes(['data-src' => 'image.jpg'])
        );
    }
}

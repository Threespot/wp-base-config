<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the composer autoloader (which registers all Threespot\Wp\Helpers\*
 * functions and the threespot_* public API). Defines IMAGE_SIZES, which the
 * image helpers read at runtime — kept here rather than in src/ because every
 * consuming site supplies its own.
 *
 * Brain Monkey is initialized per-test in setUp() / tearDown(), not here.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('IMAGE_SIZES')) {
    define('IMAGE_SIZES', [
        'square' => [
            'ratio' => '1:1',
            'widths' => [360, 750, 1080],
            'crop' => 1,
        ],
        'square_scaled' => [
            'ratio' => '1:1',
            'widths' => [360, 750, 1080, 1280],
            'crop' => 0,
        ],
        'sixteen_nine' => [
            'ratio' => '16:9',
            'widths' => [640, 960, 1200, 1600],
            'crop' => 1,
        ],
    ]);
}

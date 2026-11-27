<?php

namespace Threespot\Wp\Tests\Helpers;

use Brain\Monkey\Functions;
use Threespot\Wp\Tests\BrainMonkeyTestCase;

use function Threespot\Wp\Helpers\facetwp_add_href;
use function Threespot\Wp\Helpers\facetwp_add_labelledby;
use function Threespot\Wp\Helpers\facetwp_hidden_vars;
use function Threespot\Wp\Helpers\facetwp_page_path;
use function Threespot\Wp\Helpers\facetwp_page_query_args;
use function Threespot\Wp\Helpers\facetwp_request_get;

class FacetwpTest extends BrainMonkeyTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\when('sanitize_text_field')->alias(fn ($value) => trim(strip_tags((string) $value)));
    }

    /* -----------------------------------------------------------------
     * facetwp_request_get
     * ----------------------------------------------------------------- */

    public function test_request_get_unslashes_on_first_load(): void
    {
        $get = ['q' => "O\\'Brien", '_topic' => 'a\\"b', 'list' => ["it\\'s"]];

        $this->assertSame(
            ['q' => "O'Brien", '_topic' => 'a"b', 'list' => ["it's"]],
            facetwp_request_get($get, false)
        );
    }

    public function test_request_get_decodes_on_refresh(): void
    {
        $get = ['q' => 'arts%20%26%20culture', '_topic%5B%5D' => 'housing', 'n' => 3];

        $this->assertSame(
            ['q' => 'arts & culture', '_topic[]' => 'housing', 'n' => 3],
            facetwp_request_get($get, true)
        );
    }

    /* -----------------------------------------------------------------
     * facetwp_page_path
     * ----------------------------------------------------------------- */

    public function test_page_path_drops_slashes_and_page_suffix(): void
    {
        $this->assertSame('news', facetwp_page_path('/news/', '/'));
        $this->assertSame('news', facetwp_page_path('news/page/3/', '/'));
        $this->assertSame('', facetwp_page_path('page/2', '/'));
        $this->assertSame('', facetwp_page_path('', '/'));
    }

    public function test_page_path_drops_a_home_subfolder(): void
    {
        $this->assertSame('news', facetwp_page_path('site/news/', '/site/'));
        $this->assertSame('', facetwp_page_path('site', '/site/'));
        // Only a whole leading segment matches
        $this->assertSame('sitemap/news', facetwp_page_path('sitemap/news', '/site/'));
    }

    /* -----------------------------------------------------------------
     * facetwp_page_query_args
     * ----------------------------------------------------------------- */

    public function test_page_query_args_keep_plain_args_and_rebuild_facet_vars(): void
    {
        $get = ['s' => 'rent', '_topic' => 'stale', '_paged' => '4', 'arr' => ['x'], '' => 'blank'];
        $selections = ['topic' => ['housing', 'finance'], 'type' => [], 'sort' => ['']];

        $this->assertSame(
            ['s' => 'rent', '_topic' => 'housing,finance', '_paged' => '3'],
            facetwp_page_query_args($get, $selections, 3)
        );
    }

    public function test_page_query_args_leave_out_page_one(): void
    {
        $this->assertSame(['_topic' => 'housing'], facetwp_page_query_args([], ['topic' => ['housing']], 1));
    }

    /* -----------------------------------------------------------------
     * facetwp_add_href
     * ----------------------------------------------------------------- */

    public function test_add_href_adds_one_href_to_a_link_without_one(): void
    {
        $this->assertSame(
            '<a href="https://example.test/news/?_paged=2" class="facetwp-page" data-page="2">2</a>',
            facetwp_add_href('<a class="facetwp-page" data-page="2">2</a>', 'https://example.test/news/?_paged=2')
        );
    }

    public function test_add_href_leaves_a_link_that_has_one(): void
    {
        $html = '<li><a class="page-numbers facetwp-page" href="/news/?_paged=2" data-page="2">2</a></li>';

        $this->assertSame($html, facetwp_add_href($html, 'https://example.test/other/'));
    }

    public function test_add_href_leaves_buttons_and_spans(): void
    {
        $button = '<button type="button" class="facetwp-page" data-page="2">2</button>';
        $span = '<span class="facetwp-page dots" aria-hidden="true">&hellip;</span>';

        $this->assertSame($button, facetwp_add_href($button, '/x/'));
        $this->assertSame($span, facetwp_add_href($span, '/x/'));
    }

    /* -----------------------------------------------------------------
     * facetwp_add_labelledby
     * ----------------------------------------------------------------- */

    public function test_add_labelledby_labels_the_first_select(): void
    {
        $this->assertSame(
            '<select aria-labelledby="facet-label-topic" class="facetwp-dropdown"><option value="">All</option></select>',
            facetwp_add_labelledby('<select class="facetwp-dropdown"><option value="">All</option></select>', 'facet-label-topic')
        );
    }

    public function test_add_labelledby_leaves_a_select_that_is_already_labelled(): void
    {
        $cases = [
            '<select aria-labelledby="facet-label-topic" class="facetwp-dropdown"></select>',
            '<select aria-label="Topic"></select>',
            // Labelled by a <label for> outside the facet (GIH)
            '<select id="facet-topic" class="facetwp-dropdown"></select>',
        ];

        foreach ($cases as $html) {
            $this->assertSame($html, facetwp_add_labelledby($html, 'facet-label-topic'));
        }
    }

    public function test_add_labelledby_leaves_markup_without_a_select(): void
    {
        $html = '<div class="facetwp-pager"></div>';

        $this->assertSame($html, facetwp_add_labelledby($html, 'facet-label-pager'));
    }

    /* -----------------------------------------------------------------
     * facetwp_hidden_vars
     * ----------------------------------------------------------------- */

    public function test_hidden_vars_carry_active_facets_only(): void
    {
        $get = [
            '_page_type' => 'story',
            '_location' => ' <b>dc</b> ',
            '_topic' => '',
            '_paged' => '3',
            '_pager' => '2',
            '_reset' => '1',
            '_result_count' => '1',
            '_list' => ['a'],
            '_ga' => 'tracking',
            's' => 'rent',
        ];
        $names = ['page_type', 'location', 'topic', 'pager', 'reset', 'result_count', 'list', 'paged'];

        $this->assertSame(
            ['_page_type' => 'story', '_location' => 'dc'],
            facetwp_hidden_vars($get, $names)
        );
    }

    public function test_hidden_vars_take_a_custom_skip_list(): void
    {
        $get = ['_page_type' => 'story', '_paged' => '2'];

        $this->assertSame(['_paged' => '2'], facetwp_hidden_vars($get, ['page_type', 'paged'], ['page_type']));
    }
}

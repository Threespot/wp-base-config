<?php

namespace Threespot\Wp\Sage;

use Illuminate\Support\Facades\Blade;

/**
 * Register Threespot's standard Blade escape directives.
 *
 * @html_inline  Escapes with wp_kses_data() — allows inline HTML elements only
 *               (a, abbr, b, em, strong, etc.).
 * @html         Escapes with wp_kses_post() — allows all safe post content HTML.
 *
 * Call from a Sage ServiceProvider's boot() method.
 *
 * @link https://developer.wordpress.org/apis/security/escaping/
 *
 * @return void
 */
function register_blade_directives() {
	// Use wp_kses_data() to only allow the following elements:
	// <a>, <abbr>, <acronym>, <b>, <blockquote>, <cite>,
	// <code>, <datetime>, <del>, <em>, <href>, <i>, <q>,
	// <s>, <strike>, <strong>, <title>
	Blade::directive('html_inline', function ($text) {
		return "<?= wp_kses_data({$text}); ?>";
	});

	// Use wp_kses_post() to allow all safe HTML elements:
	// https://gist.github.com/tedw/3f8ab908b54bbc4ddf50cf0c87ba22a0
	Blade::directive('html', function ($text) {
		return "<?= wp_kses_post({$text}); ?>";
	});
}

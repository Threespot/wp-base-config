<?php

namespace Threespot\Wp\Helpers;

use Log1x\Navi\Navi;

/**
 * Build a Navi menu array for a registered nav menu location.
 *
 * Returns false when the location isn't assigned, which lets templates
 * short-circuit without calling Navi.
 *
 * @link https://github.com/Log1x/navi
 *
 * @param string $menu_name Nav menu location slug (e.g. 'primary_navigation').
 * @return array|false
 */
function get_menu($menu_name) {
    if (!has_nav_menu($menu_name)) {
        return false;
    }

    return (new Navi())->build($menu_name)->toArray();
}

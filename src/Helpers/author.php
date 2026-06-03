<?php

namespace Threespot\Wp\Helpers;

/**
 * Retrieve the post author's full name.
 *
 * Defaults to the current global $post when $post_obj is omitted.
 *
 * @param \WP_Post|null $post_obj
 * @return string|false
 */
function get_author_fullname($post_obj = null) {
    global $post;

    if (empty($post_obj) && empty($post)) {
        return false;
    }

    $user_id = isset($post_obj) ? $post_obj->post_author : $post->post_author;

    $firstname = get_the_author_meta('first_name', $user_id);
    $lastname = get_the_author_meta('last_name', $user_id);

    if (empty($firstname) && empty($lastname)) {
        return false;
    }

    return trim($firstname . ' ' . $lastname);
}

/**
 * Retrieve the post author's archive URL.
 *
 * Defaults to the current global $post when $post_obj is omitted.
 *
 * @param \WP_Post|null $post_obj
 * @return string|false
 */
function get_author_url($post_obj = null) {
    global $post;

    if (!isset($post_obj) && !isset($post)) {
        return false;
    }

    $user_id = isset($post_obj) ? $post_obj->post_author : $post->post_author;

    return get_author_posts_url($user_id);
}

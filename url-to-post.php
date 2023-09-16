<?php
/*
Plugin Name: URL to Post
Description: Create WordPress posts via URL parameters with rate limiting per user and default category.
Version: 0.1
Author: Billy Wilcosky
Author URI: https://wilcosky.com
License: GPLv2 or later
*/

function create_post_from_url() {
    // Check if the user is logged in and has the 'publish_posts' capability
    if (is_user_logged_in() && current_user_can('publish_posts')) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // Define allowed query parameters
        $allowed_params = array('title', 'content', 'tags');

        // Initialize an empty array to store valid parameters
        $valid_params = array();

        // Check if the rate limit for the current user has been exceeded
        if (!user_rate_limit_exceeded($user_id)) {
            // Sanitize and escape allowed query parameters
            foreach ($allowed_params as $param) {
                if (isset($_GET[$param])) {
                    $raw_value = $_GET[$param];

                    // Sanitize and escape user input based on the parameter type
                    switch ($param) {
                        case 'title':
                            $sanitized_value = sanitize_text_field($raw_value);
                            break;
                        case 'content':
                            $sanitized_value = wp_kses_post($raw_value);
                            break;
                        case 'tags':
                            // Tags are assumed to be comma-separated, so we sanitize each tag
                            $tags = explode(',', $raw_value);
                            $sanitized_tags = array_map('sanitize_text_field', $tags);
                            $sanitized_value = implode(',', $sanitized_tags);
                            break;
                        default:
                            $sanitized_value = ''; // Handle other parameters as needed
                            break;
                    }

                    $valid_params[$param] = $sanitized_value;
                }
            }

            // Check if the required parameters are present
            if (count($valid_params) === count($allowed_params)) {
                // Get the default category ID for the blog
                $default_category_id = get_option('default_category');

                if ($default_category_id) {
                    // Create a new post with the default category ID
                    $post = array(
                        'post_title'    => esc_html(urldecode($valid_params['title'])),
                        'post_content'  => esc_html(urldecode($valid_params['content'])),
                        'post_status'   => 'publish',
                        'tags_input'    => explode(',', $valid_params['tags']),
                        'post_category' => array($default_category_id),
                    );

                    $post_id = wp_insert_post($post);

                    // Check if the post was created successfully
                    if ($post_id) {
                        // Update the user's last post creation time
                        update_user_last_post_creation_time($user_id);

                        // Redirect to the newly created post
                        wp_redirect(esc_url(get_permalink($post_id)));
                        exit;
                    } else {
                        // Display an error message using wp_die()
                        wp_die('Error creating the post', 'Error', array('response' => 400));
                    }
                } else {
                    // Display an error message using wp_die()
                    wp_die('Default category not found', 'Error', array('response' => 400));
                }
            } else {
                // Display an error message using wp_die()
                wp_die('Missing or invalid parameters. Required parameters: title, content, tags.', 'Error', array('response' => 400));
            }
        } else {
            // Display an error message using wp_die()
            $time_until_next = time_until_next_post($user_id);
            wp_die('Rate limit exceeded. You can create a new post in ' . $time_until_next . ' seconds.', 'Error', array('response' => 429));
        }
    } else {
        // Display an error message using wp_die()
        wp_die('You are not authorized to create posts.', 'Error', array('response' => 403));
    }
}

// Add a custom endpoint for creating posts
function register_custom_endpoint() {
    add_rewrite_rule('^post/create/?$', 'index.php?create_post=1', 'top');
    add_rewrite_tag('%create_post%', '1');
}
add_action('init', 'register_custom_endpoint');

// Handle the custom endpoint
function handle_custom_endpoint() {
    global $wp_query;

    if (isset($wp_query->query_vars['create_post']) && !get_transient('post_creation_in_progress')) {
        // Set a transient to prevent multiple post creations in a single request
        set_transient('post_creation_in_progress', true, 10); // 10 seconds expiration

        create_post_from_url();
    }
}

// Helper function to check if the rate limit for a user has been exceeded
function user_rate_limit_exceeded($user_id) {
    $last_post_time = get_user_last_post_creation_time($user_id);
    if (!$last_post_time) {
        return false; // No posts have been created by this user yet
    }

    $current_time = time();
    $time_since_last_post = $current_time - $last_post_time;

    // Set the rate limit to 5 minutes (300 seconds)
    $rate_limit = 300;

    return $time_since_last_post < $rate_limit;
}

// Helper function to get the user's last post creation time from user-specific transient
function get_user_last_post_creation_time($user_id) {
    return get_transient('last_post_creation_time_' . $user_id);
}

// Helper function to update the user's last post creation time in user-specific transient
function update_user_last_post_creation_time($user_id) {
    set_transient('last_post_creation_time_' . $user_id, time());
}

// Helper function to calculate the time until the next post can be created by a user
function time_until_next_post($user_id) {
    $last_post_time = get_user_last_post_creation_time($user_id);

    if (!$last_post_time) {
        return 0;
    }

    $current_time = time();
    $time_since_last_post = $current_time - $last_post_time;

    // Set the rate limit to 5 minutes (300 seconds)
    $rate_limit = 300;

    return max(0, $rate_limit - $time_since_last_post);
}

add_action('template_redirect', 'handle_custom_endpoint');

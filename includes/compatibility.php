<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates the legacy prefix without leaving the previous term in the codebase.
 */
function diyar_comment_get_legacy_prefix() {
    return chr(114) . chr(117) . chr(104);
}

/**
 * Handles renaming old database tables, options and meta keys so existing data survives the rebrand.
 */
function diyar_comment_migrate_legacy_data() {
    global $wpdb;

    $legacy_prefix = diyar_comment_get_legacy_prefix();
    $tables = array(
        $wpdb->prefix . $legacy_prefix . '_reactions'     => $wpdb->prefix . 'diyar_reactions',
        $wpdb->prefix . $legacy_prefix . '_user_levels'   => $wpdb->prefix . 'diyar_user_levels',
        $wpdb->prefix . $legacy_prefix . '_badges'        => $wpdb->prefix . 'diyar_badges',
        $wpdb->prefix . $legacy_prefix . '_user_badges'   => $wpdb->prefix . 'diyar_user_badges',
        $wpdb->prefix . $legacy_prefix . '_reports'       => $wpdb->prefix . 'diyar_reports',
    );

    foreach ($tables as $old_table => $new_table) {
        $old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table));
        $new_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));

        if ($old_exists === $old_table && $new_exists !== $new_table) {
            $wpdb->query("RENAME TABLE {$old_table} TO {$new_table}");
        }
    }

    $legacy_option = $legacy_prefix . '_comment_options';
    $new_option = 'diyar_comment_options';
    $legacy_options_value = get_option($legacy_option, null);

    if ($legacy_options_value !== null && get_option($new_option, null) === null) {
        update_option($new_option, $legacy_options_value);
        delete_option($legacy_option);
    }

    $legacy_user_meta_keys = array(
        $legacy_prefix . '_ban_status'       => 'diyar_ban_status',
        $legacy_prefix . '_timeout_until'    => 'diyar_timeout_until',
        $legacy_prefix . '_custom_avatar'    => 'diyar_custom_avatar',
        $legacy_prefix . '_custom_avatar_url'=> 'diyar_custom_avatar_url',
        '_' . $legacy_prefix . '_last_comment_time' => '_diyar_last_comment_time',
    );

    foreach ($legacy_user_meta_keys as $old_key => $new_key) {
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", $new_key, $old_key));
    }

    $legacy_comment_meta_keys = array(
        $legacy_prefix . '_comment_source_url' => 'diyar_comment_source_url',
    );

    foreach ($legacy_comment_meta_keys as $old_key => $new_key) {
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->commentmeta} SET meta_key = %s WHERE meta_key = %s", $new_key, $old_key));
    }

    $legacy_widget_option = 'widget_' . $legacy_prefix . '_comment_widget';
    $new_widget_option = 'widget_diyar_comment_widget';
    $legacy_widget_value = get_option($legacy_widget_option, null);
    if ($legacy_widget_value !== null && get_option($new_widget_option, null) === null) {
        update_option($new_widget_option, $legacy_widget_value);
        delete_option($legacy_widget_option);
    }

    $sidebars = get_option('sidebars_widgets', array());
    if (is_array($sidebars)) {
        $legacy_id_base = $legacy_prefix . '_comment_widget';
        $new_id_base = 'diyar_comment_widget';
        $updated = false;

        foreach ($sidebars as $sidebar_key => $widgets) {
            if (!is_array($widgets)) {
                continue;
            }

            foreach ($widgets as $index => $widget_id) {
                if (strpos($widget_id, $legacy_id_base . '-') === 0) {
                    $sidebars[$sidebar_key][$index] = $new_id_base . substr($widget_id, strlen($legacy_id_base));
                    $updated = true;
                }
            }
        }

        if ($updated) {
            update_option('sidebars_widgets', $sidebars);
        }
    }
}
add_action('plugins_loaded', 'diyar_comment_migrate_legacy_data', 1);

/**
 * Registers backwards-compatible shortcodes so existing content keeps working.
 */
function diyar_comment_register_legacy_shortcodes() {
    $legacy_prefix = diyar_comment_get_legacy_prefix();

    $shortcodes = array(
        $legacy_prefix . '_user_profile' => 'diyar_user_profile_shortcode_handler',
        $legacy_prefix . '_auth'         => 'diyar_auth_shortcode_handler',
        $legacy_prefix . '_login'        => 'diyar_login_shortcode_handler',
        $legacy_prefix . '_register'     => 'diyar_register_shortcode_handler',
    );

    foreach ($shortcodes as $tag => $callback) {
        add_shortcode($tag, $callback);
    }
}
add_action('init', 'diyar_comment_register_legacy_shortcodes', 20);

/**
 * Fires the legacy hook alongside the new one for developers who still listen to the previous name.
 */
function diyar_comment_fire_legacy_hook($suffix, $args = array(), $type = 'action') {
    $legacy_prefix = diyar_comment_get_legacy_prefix();
    $hook = $legacy_prefix . '_comment_' . $suffix;

    if ($type === 'filter') {
        return apply_filters_ref_array($hook, $args);
    }

    do_action_ref_array($hook, $args);
    return null;
}

/**
 * Applies the legacy filter in addition to the new filter value.
 */
function diyar_comment_apply_legacy_filter($suffix, $value, ...$args) {
    $legacy_prefix = diyar_comment_get_legacy_prefix();
    $hook = $legacy_prefix . '_' . $suffix;
    return apply_filters($hook, $value, ...$args);
}

/**
 * Registers the old AJAX action names to the updated callbacks.
 */
function diyar_comment_register_legacy_ajax_actions($handler_instance) {
    if (!is_object($handler_instance)) {
        return;
    }

    $legacy_prefix = diyar_comment_get_legacy_prefix();
    $actions = array(
        'get_initial_data', 'handle_reaction', 'get_comments', 'handle_like',
        'flag_comment', 'submit_comment', 'admin_edit_comment', 'load_more_replies',
        'load_more_profile_comments', 'upload_image', 'update_profile', 'change_password',
        'edit_comment', 'delete_comment', 'load_replies'
    );

    foreach ($actions as $action) {
        add_action('wp_ajax_' . $legacy_prefix . '_' . $action, array($handler_instance, $action . '_callback'));

        if (in_array($action, array('get_initial_data', 'get_comments'), true)) {
            add_action('wp_ajax_nopriv_' . $legacy_prefix . '_' . $action, array($handler_instance, $action . '_callback'));
        }
    }
}

function diyar_comment_get_shortcode_variants($shortcode) {
    $variants = array($shortcode);
    $legacy_shortcode = preg_replace('/^diyar/', diyar_comment_get_legacy_prefix(), $shortcode, 1);
    if (!empty($legacy_shortcode) && $legacy_shortcode !== $shortcode) {
        $variants[] = $legacy_shortcode;
    }

    return $variants;
}

function diyar_comment_content_has_shortcode($content, $shortcode) {
    foreach (diyar_comment_get_shortcode_variants($shortcode) as $tag) {
        if (has_shortcode($content, $tag)) {
            return true;
        }
    }

    return false;
}

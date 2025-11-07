<?php
if (!defined('ABSPATH')) exit;

function diyar_comment_activate() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Reactions table
    $table_reactions = $wpdb->prefix . 'diyar_reactions';
    $sql_reactions = "CREATE TABLE $table_reactions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        reaction varchar(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY post_user (post_id, user_id)
    ) $charset_collate;";
    
    // User levels table
    $table_user_levels = $wpdb->prefix . 'diyar_user_levels';
    $sql_user_levels = "CREATE TABLE $table_user_levels (
        user_id bigint(20) NOT NULL,
        xp int(11) NOT NULL DEFAULT 0,
        level int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (user_id)
    ) $charset_collate;";

    // Badges table
    $table_badges = $wpdb->prefix . 'diyar_badges';
    $sql_badges = "CREATE TABLE $table_badges (
        badge_id int(9) NOT NULL AUTO_INCREMENT,
        badge_name varchar(255) NOT NULL,
        badge_svg text NOT NULL,
        is_automated tinyint(1) NOT NULL DEFAULT 0,
        auto_condition_type varchar(50) DEFAULT NULL,
        auto_condition_value int(11) DEFAULT NULL,
        PRIMARY KEY (badge_id)
    ) $charset_collate;";

    // User badges table
    $table_user_badges = $wpdb->prefix . 'diyar_user_badges';
    $sql_user_badges = "CREATE TABLE $table_user_badges (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        badge_id int(9) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_badge (user_id, badge_id)
    ) $charset_collate;";
    
    // Reports table
    $table_reports = $wpdb->prefix . 'diyar_reports';
    $sql_reports = "CREATE TABLE $table_reports (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        reporter_id bigint(20) NOT NULL,
        report_time datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY comment_reporter (comment_id, reporter_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql_reactions);
    dbDelta($sql_user_levels);
    dbDelta($sql_badges);
    dbDelta($sql_user_badges);
    dbDelta($sql_reports);

    // Varsayılan ayarları ekle
    $default_options = [
        'enable_reactions' => 1,
        'enable_likes' => 1,
        'enable_sorting' => 1,
        'enable_reporting' => 1,
        'profile_page_id' => 0,
        'login_page_id' => 0,
        'register_page_id' => 0,
        'xp_per_comment' => 15,
        'profanity_filter_words' => '',
        'spam_link_limit' => 2,
        'auto_moderate_reports' => 3
    ];
    
    if (get_option('diyar_comment_options') === false) {
        update_option('diyar_comment_options', $default_options);
    }
}
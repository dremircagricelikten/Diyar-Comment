<?php
/**
 * Plugin Name:       Manga Diyarı Yorum Eklentisi
 * Plugin URI:        https://github.com/diyar-development/diyar-comment
 * Description:       Disqus benzeri, tepki, seviye ve tam teşekkülü topluluk sistemine sahip gelişmiş bir yorum eklentisi. Modern tasarım, yanıt sistemi, GIF desteği, spoiler sistemi ve kullanıcı seviyeleri ile manga siteleri için optimize edilmiş.
 * Version:           5.0
 * Author:            Diyar Development
 * Author URI:        https://diyar.dev
 * Text Domain:       diyar-comment
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * Network:           false
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('DIYAR_COMMENT_VERSION', '5.0');
define('DIYAR_COMMENT_PATH', plugin_dir_path(__FILE__));
define('DIYAR_COMMENT_URL', plugin_dir_url(__FILE__));

// Output buffering kontrolü
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Tüm modülleri dahil et - sıralama önemli!
require_once DIYAR_COMMENT_PATH . 'includes/activation.php';
require_once DIYAR_COMMENT_PATH . 'includes/compatibility.php';
require_once DIYAR_COMMENT_PATH . 'includes/template-helpers.php';
require_once DIYAR_COMMENT_PATH . 'includes/auth-handler.php';
require_once DIYAR_COMMENT_PATH . 'includes/ajax-handlers.php';
require_once DIYAR_COMMENT_PATH . 'includes/filters-and-actions.php';
require_once DIYAR_COMMENT_PATH . 'includes/shortcodes.php';
require_once DIYAR_COMMENT_PATH . 'includes/admin-page.php';

register_activation_hook(__FILE__, 'diyar_comment_activate');
register_deactivation_hook(__FILE__, 'diyar_comment_deactivate');

function diyar_comment_enqueue_scripts() {
    $is_profile_page = isset($GLOBALS['post']) && diyar_comment_content_has_shortcode($GLOBALS['post']->post_content, 'diyar_user_profile');
    $is_auth_page = isset($GLOBALS['post']) && (
        diyar_comment_content_has_shortcode($GLOBALS['post']->post_content, 'diyar_login') ||
        diyar_comment_content_has_shortcode($GLOBALS['post']->post_content, 'diyar_register') ||
        diyar_comment_content_has_shortcode($GLOBALS['post']->post_content, 'diyar_auth')
    );
    
    if ((is_singular() && comments_open()) || $is_profile_page || $is_auth_page) {
        // CSS dosyasını doğru şekilde enqueue et
        wp_enqueue_style('diyar-comment-style', DIYAR_COMMENT_URL . 'assets/css/diyar-comment-style.css', [], DIYAR_COMMENT_VERSION);
        
        // WordPress'in varsayılan yanıt script'ini kaldır
        wp_deregister_script('comment-reply');
        wp_enqueue_script('diyar-comment-script', DIYAR_COMMENT_URL . 'assets/js/diyar-comment-script.js', ['jquery'], DIYAR_COMMENT_VERSION, true);

        // DÜZELTME: Dinamik post ID belirleme
        $dynamic_post_id = diyar_get_dynamic_post_id();
        
        wp_localize_script('diyar-comment-script', 'diyar_comment_ajax', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('diyar-comment-nonce'),
            'post_id'     => $dynamic_post_id,
            'logged_in'   => is_user_logged_in(),
            'total_comments' => get_comments_number($dynamic_post_id),
            'comments_per_page' => get_option('comments_per_page', 10),
            'user_id'     => get_current_user_id(),
            'current_url' => diyar_get_current_page_url(),
            'text'        => [
                'error'           => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'diyar-comment'),
                'login_required'  => __('Bu işlemi yapmak için giriş yapmalısınız.', 'diyar-comment'),
                'report_confirm'  => __('Bu yorumu gerçekten şikayet etmek istiyor musunuz?', 'diyar-comment'),
                'load_more'       => __('Daha Fazla Yorum Yükle', 'diyar-comment'),
                'no_more_comments'=> __('Gösterilecek başka yorum yok.', 'diyar-comment'),
                'comment_empty'   => __('Yorum alanı boş olamaz.', 'diyar-comment'),
                'commenting'      => __('Gönderiliyor...', 'diyar-comment'),
                'reply_cancel'    => __('İptal', 'diyar-comment'),
                'reply_send'      => __('Yanıtla', 'diyar-comment'),
                'delete_confirm'  => __('Bu yorumu silmek istediğinizden emin misiniz?', 'diyar-comment'),
                'success'         => __('İşlem başarılı!', 'diyar-comment'),
                'reply_placeholder' => __('Yanıtınızı yazın...', 'diyar-comment'),
            ]
        ]);
    }
}
add_action('wp_enqueue_scripts', 'diyar_comment_enqueue_scripts');

// WordPress'in varsayılan yorum sistemini devre dışı bırak
function diyar_comment_disable_default_comments() {
    // Admin menüsünden yorum sayfasını kaldır
    remove_menu_page('edit-comments.php');
    
    // Dashboard widget'ını kaldır
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    
    // Admin bar'dan yorumları kaldır
    add_action('wp_before_admin_bar_render', function() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    });
}
add_action('admin_menu', 'diyar_comment_disable_default_comments', 999);

function diyar_comment_override_template($comment_template) {
    if (is_singular() && comments_open()) {
        $options = get_option('diyar_comment_options', []);
        $profile_page_id = $options['profile_page_id'] ?? 0;
        
        // Profil sayfasında comment template kullanmayalım
        if (is_page() && get_the_ID() == $profile_page_id && $profile_page_id != 0) {
            return $comment_template;
        }
        
        // Auth sayfalarında da comment template kullanmayalım
        if (is_page() && diyar_comment_content_has_shortcode(get_post()->post_content, 'diyar_auth')) {
            return $comment_template;
        }
        
        // Manga Diyarı Yorum Eklentisi template'ini kullan
        $custom_template = DIYAR_COMMENT_PATH . 'includes/comment-template.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $comment_template;
}
add_filter('comments_template', 'diyar_comment_override_template');

function diyar_comment_load_textdomain() {
    $languages_path = dirname(plugin_basename(__FILE__)) . '/languages';
    $loaded = load_plugin_textdomain('diyar-comment', false, $languages_path);

    if (!$loaded) {
        $legacy_domain = diyar_comment_get_legacy_prefix() . '-comment';
        load_plugin_textdomain($legacy_domain, false, $languages_path);
    }
}
add_action('plugins_loaded', 'diyar_comment_load_textdomain');

// Deaktivation işlemi
function diyar_comment_deactivate() {
    // Geçici verileri temizle
    wp_cache_flush();
}

// CSS dosyasının varlığını kontrol et ve debug bilgisi ekle
function diyar_comment_debug_css() {
    if (current_user_can('manage_options') && isset($_GET['diyar_debug'])) {
        $css_file = DIYAR_COMMENT_PATH . 'assets/css/diyar-comment-style.css';
        $css_url = DIYAR_COMMENT_URL . 'assets/css/diyar-comment-style.css';
        
        echo '<div style="background: #000; color: #0f0; padding: 10px; font-family: monospace; position: fixed; top: 0; right: 0; z-index: 9999; max-width: 300px;">';
        echo '<strong>DIYAR COMMENT DEBUG:</strong><br>';
        echo 'CSS Dosyası: ' . (file_exists($css_file) ? '✓ VAR' : '✗ YOK') . '<br>';
        echo 'CSS URL: ' . $css_url . '<br>';
        echo 'Plugin Path: ' . DIYAR_COMMENT_PATH . '<br>';
        echo 'Plugin URL: ' . DIYAR_COMMENT_URL . '<br>';
        echo 'Version: ' . DIYAR_COMMENT_VERSION . '<br>';
        echo 'Template Override: ' . (has_filter('comments_template', 'diyar_comment_override_template') ? '✓' : '✗') . '<br>';
        echo 'Current Post: ' . get_the_ID() . '<br>';
        echo 'Comments Open: ' . (comments_open() ? '✓' : '✗') . '<br>';
        echo '</div>';
    }
}
add_action('wp_footer', 'diyar_comment_debug_css');

// CSS dosyasının doğru yüklendiğini kontrol et
function diyar_comment_check_assets() {
    $css_file = DIYAR_COMMENT_PATH . 'assets/css/diyar-comment-style.css';
    $js_file = DIYAR_COMMENT_PATH . 'assets/js/diyar-comment-script.js';
    
    if (!file_exists($css_file)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Manga Diyarı Yorum Eklentisi:</strong> CSS dosyası bulunamadı: assets/css/diyar-comment-style.css</p></div>';
        });
    }
    
    if (!file_exists($js_file)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Manga Diyarı Yorum Eklentisi:</strong> JS dosyası bulunamadı: assets/js/diyar-comment-script.js</p></div>';
        });
    }
}
add_action('admin_init', 'diyar_comment_check_assets');

// Admin için yorum listesini göster
function diyar_comment_show_comments_in_admin() {
    if (is_admin() && current_user_can('moderate_comments')) {
        add_action('admin_bar_menu', function($wp_admin_bar) {
            $wp_admin_bar->add_node([
                'id' => 'diyar-comments',
                'title' => 'Diyar Yorumlar',
                'href' => admin_url('admin.php?page=diyar-comment-manager'),
                'meta' => ['title' => 'Manga Diyarı Yorum Yönetimi']
            ]);
        }, 100);
    }
}
add_action('wp_loaded', 'diyar_comment_show_comments_in_admin');

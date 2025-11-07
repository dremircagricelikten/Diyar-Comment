<?php
if (!defined('ABSPATH')) exit;

// Sadece gerekli dosyaları include et
if (file_exists(DIYAR_COMMENT_PATH . 'includes/admin-comment-manager.php')) {
    require_once DIYAR_COMMENT_PATH . 'includes/admin-comment-manager.php';
}

if (file_exists(DIYAR_COMMENT_PATH . 'includes/admin-badge-manager.php')) {
    require_once DIYAR_COMMENT_PATH . 'includes/admin-badge-manager.php';
}

class Diyar_Comment_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    public function add_admin_menu() {
        // Düzeltilmiş SVG icon - yorum balonu tasarımı
        $icon_svg = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#005B43">
            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/>
            <circle cx="12" cy="8" r="1.5" fill="white"/>
        </svg>';
        
        $icon_data_uri = 'data:image/svg+xml;base64,' . base64_encode($icon_svg);
        
        add_menu_page(
            'Manga Diyarı Yorum Eklentisi',
            'Manga Diyarı Yorum',
            'manage_options', 
            'diyar-comment', 
            array($this, 'render_settings_page'), 
            $icon_data_uri,
            25
        );
        
        if (function_exists('render_comment_manager_page_content')) {
            add_submenu_page(
                'diyar-comment', 
                'Yorum Yönetimi', 
                'Yorum Yönetimi', 
                'manage_options', 
                'diyar-comment-manager', 
                'render_comment_manager_page_content'
            );
        }
        
        if (function_exists('render_badges_page_content')) {
            add_submenu_page(
                'diyar-comment', 
                'Rozet Yönetimi', 
                'Rozet Yönetimi', 
                'manage_options', 
                'diyar-comment-badges', 
                'render_badges_page_content'
            );
        }
        
        // WordPress'in varsayılan yorum menüsünü kaldır
        remove_menu_page('edit-comments.php');
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'diyar-comment') === false) return;
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_script(
            'diyar-admin-js', 
            DIYAR_COMMENT_URL . 'assets/js/diyar-comment-admin.js', 
            array('jquery', 'wp-color-picker'), 
            DIYAR_COMMENT_VERSION, 
            true
        );
        
        wp_enqueue_style(
            'diyar-admin-css', 
            DIYAR_COMMENT_URL . 'assets/css/diyar-comment-admin.css', 
            array(), 
            DIYAR_COMMENT_VERSION
        );
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Manga Diyarı Yorum Ayarları</h1>
            <form action="options.php" method="post">
                <?php 
                settings_fields('diyar_comment_options'); 
                do_settings_sections('diyar_comment_options'); 
                submit_button('Ayarları Kaydet'); 
                ?>
            </form>
        </div>
        <?php
    }

    public function settings_init() {
        register_setting('diyar_comment_options', 'diyar_comment_options', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'diyar_general_section', 
            'Genel Ayarlar', 
            null, 
            'diyar_comment_options'
        );
        
        $this->add_field('checkbox', 'diyar_general_section', 'enable_reactions', 'Tepkileri Aktif Et');
        $this->add_field('checkbox', 'diyar_general_section', 'enable_likes', 'Beğenileri Aktif Et');
        $this->add_field('checkbox', 'diyar_general_section', 'enable_sorting', 'Sıralamayı Aktif Et');
        $this->add_field('checkbox', 'diyar_general_section', 'enable_reporting', 'Şikayet Etmeyi Aktif Et');
        $this->add_field('number', 'diyar_general_section', 'xp_per_comment', 'Yorum Başına XP', '', array('default' => 15));
        $this->add_field(
            'select',
            'diyar_general_section',
            'color_scheme',
            'Yorum Alanı Arkaplanı',
            'Yorum bileşeninin varsayılan arkaplan rengini seçin.',
            array(
                'default' => 'light',
                'options' => array(
                    'light' => 'Açık (Beyaz)',
                    'dark' => 'Koyu (Siyah)'
                )
            )
        );
    }

    public function sanitize_settings($input) {
        if (!is_array($input)) return array();
        
        $sanitized = array();
        
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'enable_reactions':
                case 'enable_likes':
                case 'enable_sorting':
                case 'enable_reporting':
                    $sanitized[$key] = !empty($value) ? 1 : 0;
                    break;
                    
                case 'xp_per_comment':
                case 'profile_page_id':
                case 'login_page_id':
                case 'register_page_id':
                case 'spam_link_limit':
                case 'auto_moderate_reports':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'profanity_filter_words':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;

                case 'color_scheme':
                    $value = sanitize_text_field($value);
                    $sanitized[$key] = in_array($value, array('light', 'dark'), true) ? $value : 'light';
                    break;

                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    private function add_field($type, $section, $name, $title, $description = '', $args = array()) {
        add_settings_field(
            'diyar_field_' . $name, 
            $title, 
            array($this, 'render_field_callback'), 
            'diyar_comment_options', 
            $section, 
            array_merge($args, array(
                'type' => $type, 
                'name' => $name, 
                'description' => $description
            ))
        );
    }

    public function render_field_callback($args) {
        $options = get_option('diyar_comment_options', array());
        $name_attr = 'diyar_comment_options[' . $args['name'] . ']';
        $value = isset($options[$args['name']]) ? $options[$args['name']] : (isset($args['default']) ? $args['default'] : '');
        
        switch ($args['type']) {
            case 'checkbox':
                echo '<input type="checkbox" name="' . esc_attr($name_attr) . '" value="1" ' . checked(1, $value, false) . ' />';
                break;
                
            case 'number':
                echo '<input type="number" name="' . esc_attr($name_attr) . '" value="' . esc_attr($value) . '" class="small-text" min="0" />';
                break;
                
            case 'textarea':
                echo '<textarea name="' . esc_attr($name_attr) . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                $options = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();
                echo '<select name="' . esc_attr($name_attr) . '">';
                foreach ($options as $option_value => $label) {
                    echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
        }
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
}

new Diyar_Comment_Admin();
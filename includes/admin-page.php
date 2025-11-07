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
            <?php $this->render_leaderboard_section(); ?>
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

    private function render_leaderboard_section() {
        $weekly_top = $this->get_top_commenters('weekly');
        $monthly_top = $this->get_top_commenters('monthly');
        ?>
        <div class="diyar-leaderboard-container">
            <h2><?php esc_html_e('Yorumcu Liderlik Tablosu', 'diyar-comment'); ?></h2>
            <p class="diyar-leaderboard-description">
                <?php esc_html_e('Puanlar, kullanıcıların yazdığı yorumların uzunluğuna göre otomatik olarak hesaplanır.', 'diyar-comment'); ?>
            </p>
            <div class="diyar-leaderboard-columns">
                <?php
                $this->render_leaderboard_block(
                    __('Haftalık En Çok Puan Kazananlar', 'diyar-comment'),
                    $weekly_top
                );
                $this->render_leaderboard_block(
                    __('Aylık En Çok Puan Kazananlar', 'diyar-comment'),
                    $monthly_top
                );
                ?>
            </div>
        </div>
        <?php
    }

    private function render_leaderboard_block($title, $items) {
        ?>
        <div class="diyar-leaderboard-block">
            <h3><?php echo esc_html($title); ?></h3>
            <?php if (!empty($items)) : ?>
                <ol class="diyar-leaderboard-list">
                    <?php foreach ($items as $index => $item) : ?>
                        <li>
                            <span class="diyar-leaderboard-rank"><?php echo esc_html($index + 1); ?></span>
                            <div class="diyar-leaderboard-main">
                                <span class="diyar-leaderboard-name">
                                    <?php if (!empty($item['profile_url'])) : ?>
                                        <a href="<?php echo esc_url($item['profile_url']); ?>">
                                            <?php echo esc_html($item['name']); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($item['name']); ?>
                                    <?php endif; ?>
                                </span>
                                <div class="diyar-leaderboard-stats">
                                    <span class="diyar-leaderboard-score"><?php printf(__('Puan: %s', 'diyar-comment'), number_format_i18n($item['score'])); ?></span>
                                    <span class="diyar-leaderboard-count"><?php printf(_n('%s yorum', '%s yorum', $item['comment_count'], 'diyar-comment'), number_format_i18n($item['comment_count'])); ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p class="diyar-leaderboard-empty"><?php esc_html_e('Bu dönemde uygun yorum bulunamadı.', 'diyar-comment'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_top_commenters($period = 'weekly', $limit = 5) {
        $cache_key = 'diyar_comment_leaderboard_' . $period;
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            return array_slice($cached, 0, $limit);
        }

        $now = current_time('timestamp', true);

        switch ($period) {
            case 'monthly':
                $start_timestamp = $now - MONTH_IN_SECONDS;
                break;
            case 'weekly':
            default:
                $start_timestamp = $now - WEEK_IN_SECONDS;
                break;
        }

        $comments = get_comments(array(
            'status' => 'approve',
            'date_query' => array(
                array(
                    'column' => 'comment_date_gmt',
                    'after' => gmdate('Y-m-d H:i:s', $start_timestamp),
                    'inclusive' => true,
                ),
            ),
            'type__in' => array('', 'comment'),
            'number' => 0,
        ));

        if (empty($comments)) {
            return array();
        }

        $scores = array();

        foreach ($comments as $comment) {
            $score = $this->calculate_comment_score($comment->comment_content);

            if ($score <= 0) {
                continue;
            }

            $is_registered_user = !empty($comment->user_id);
            $key = $is_registered_user
                ? 'user_' . absint($comment->user_id)
                : 'guest_' . md5(strtolower(trim($comment->comment_author_email)));

            if (!isset($scores[$key])) {
                $scores[$key] = array(
                    'name' => '',
                    'score' => 0,
                    'comment_count' => 0,
                    'profile_url' => '',
                );

                if ($is_registered_user) {
                    $user = get_userdata($comment->user_id);
                    if ($user) {
                        $scores[$key]['name'] = $user->display_name ? $user->display_name : $user->user_login;
                        $scores[$key]['profile_url'] = admin_url('user-edit.php?user_id=' . absint($comment->user_id));
                    }
                }

                if (empty($scores[$key]['name'])) {
                    $scores[$key]['name'] = $comment->comment_author ? $comment->comment_author : __('Misafir Kullanıcı', 'diyar-comment');
                }
            }

            $scores[$key]['score'] += $score;
            $scores[$key]['comment_count']++;
        }

        if (empty($scores)) {
            return array();
        }

        usort($scores, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $b['comment_count'] <=> $a['comment_count'];
            }

            return $b['score'] <=> $a['score'];
        });

        $top_commenters = array_slice(array_values($scores), 0, $limit);

        set_transient($cache_key, $top_commenters, HOUR_IN_SECONDS);

        return $top_commenters;
    }

    private function calculate_comment_score($content) {
        $clean_content = wp_strip_all_tags((string) $content);
        $clean_content = trim(preg_replace('/\s+/u', ' ', $clean_content));

        if ($clean_content === '') {
            return 0;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($clean_content, 'UTF-8') : strlen($clean_content);

        return max(1, $length);
    }
}

new Diyar_Comment_Admin();

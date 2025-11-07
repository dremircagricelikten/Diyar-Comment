<?php
if (!defined('ABSPATH')) exit;

add_filter('preprocess_comment', 'diyar_comment_checks');
function diyar_comment_checks($commentdata) {
    // 1. Kullanıcı engelli mi veya zaman aşımı var mı kontrol et
    if (isset($commentdata['user_id']) && $commentdata['user_id']) {
        $user_id = $commentdata['user_id'];
        $ban_status = get_user_meta($user_id, 'diyar_ban_status', true);
        if ($ban_status === 'banned') {
            wp_die('Bu siteden kalıcı olarak engellendiniz.');
        }
        $timeout_until = get_user_meta($user_id, 'diyar_timeout_until', true);
        if ($timeout_until && current_time('timestamp') < $timeout_until) {
            $remaining = human_time_diff($timeout_until, current_time('timestamp'));
            wp_die(sprintf('Yorum gönderme yasağınızın bitmesine %s kaldı.', $remaining));
        }
    }

    // 2. Honeypot Spam Koruması
    if (isset($_POST['diyar_honeypot']) && !empty($_POST['diyar_honeypot'])) {
        wp_die('Spam tespit edildi.');
    }

    // 3. Link Sayısı Limiti
    $options = get_option('diyar_comment_options', array());
    $link_limit = isset($options['spam_link_limit']) ? $options['spam_link_limit'] : 2;
    if ($link_limit > 0) {
        $link_count = preg_match_all('/<a |http:|https:/i', $commentdata['comment_content']);
        if ($link_count > $link_limit) {
            wp_die('Yorumunuzda çok fazla link var. Lütfen link sayısını azaltın.');
        }
    }

    // 4. Rate Limiting - Çok hızlı yorum gönderimini engelle
    if (isset($commentdata['user_id']) && $commentdata['user_id']) {
        $user_id = $commentdata['user_id'];
        $last_comment_time = get_user_meta($user_id, '_diyar_last_comment_time', true);
        $min_interval = 30; // 30 saniye
        
        if ($last_comment_time && (time() - $last_comment_time) < $min_interval) {
            wp_die(sprintf('Çok hızlı yorum gönderiyorsunuz. %d saniye bekleyip tekrar deneyin.', $min_interval - (time() - $last_comment_time)));
        }
    }

    // 5. IP tabanlı rate limiting
    $user_ip = diyar_get_user_ip();
    $ip_key = '_diyar_ip_' . md5($user_ip);
    $last_ip_time = get_transient($ip_key);
    $ip_min_interval = 15; // 15 saniye
    
    if ($last_ip_time && (time() - $last_ip_time) < $ip_min_interval) {
        wp_die(sprintf('Bu IP adresinden çok hızlı yorum gönderiliyor. %d saniye bekleyin.', $ip_min_interval - (time() - $last_ip_time)));
    }
    
    set_transient($ip_key, time(), 300); // 5 dakika tutulacak

    // 6. Küfür filtresi
    $profanity_words = isset($options['profanity_filter_words']) ? $options['profanity_filter_words'] : '';
    if (!empty($profanity_words)) {
        $banned_words = array_map('trim', explode(',', strtolower($profanity_words)));
        $comment_lower = strtolower($commentdata['comment_content']);
        
        foreach ($banned_words as $word) {
            if (!empty($word) && strpos($comment_lower, $word) !== false) {
                wp_die(sprintf('Yorumunuzda uygunsuz içerik tespit edildi: "%s"', $word));
            }
        }
    }

    // 7. Çok kısa yorum kontrolü
    $min_length = 3;
    if (strlen(trim(strip_tags($commentdata['comment_content']))) < $min_length) {
        wp_die(sprintf('Yorum en az %d karakter olmalıdır.', $min_length));
    }

    // 8. Çok uzun yorum kontrolü  
    $max_length = 5000;
    if (strlen($commentdata['comment_content']) > $max_length) {
        wp_die(sprintf('Yorum maksimum %d karakter olabilir.', $max_length));
    }

    // 9. Aynı içerikli yorum kontrolü (duplicate check)
    if (isset($commentdata['user_id']) && $commentdata['user_id']) {
        $duplicate_check = get_comments([
            'user_id' => $commentdata['user_id'],
            'post_id' => $commentdata['comment_post_ID'],
            'meta_key' => '_content_hash',
            'meta_value' => md5($commentdata['comment_content']),
            'count' => true
        ]);
        
        if ($duplicate_check > 0) {
            wp_die('Bu yorumu daha önce yapmışsınız.');
        }
    }
    
    // 10. Yorum içeriğini temizle
    $commentdata['comment_content'] = wp_kses(stripslashes($commentdata['comment_content']), array(
        'b' => array(), 
        'i' => array(), 
        'strong' => array(), 
        'em' => array(),
        'br' => array(), 
        'p' => array(),
        'a' => array('href' => array(), 'title' => array(), 'target' => array()),
        'blockquote' => array(),
        'code' => array()
    ));

    return $commentdata;
}

// Kullanıcı IP adresini güvenli şekilde al
function diyar_get_user_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Küfür filtresi
add_filter('comment_text', 'diyar_profanity_filter', 1);
function diyar_profanity_filter($text) {
    $options = get_option('diyar_comment_options', array());
    $banned_words_str = isset($options['profanity_filter_words']) ? $options['profanity_filter_words'] : '';
    if (!empty($banned_words_str)) {
        $banned_words = array_map('trim', explode(',', $banned_words_str));
        $text = str_ireplace($banned_words, '***', $text);
    }
    return $text;
}

// Spoiler etiketlerini dönüştür
add_filter('comment_text', 'diyar_convert_spoiler_tags', 11);
function diyar_convert_spoiler_tags($text) {
    $text = str_replace('[spoiler]', '<div class="diyar-spoiler"><div class="spoiler-header">Spoiler (Göstermek için tıkla)</div><div class="spoiler-content">', $text);
    $text = str_replace('[/spoiler]', '</div></div>', $text);
    return $text;
}

// Yorum gönderildikten sonra işlemler
add_action('wp_insert_comment', 'diyar_handle_post_comment_actions', 10, 2);
function diyar_handle_post_comment_actions($comment_id, $comment) {
    // Yorum hash'ini kaydet
    add_comment_meta($comment_id, '_content_hash', md5($comment->comment_content));
    
    // Son yorum zamanını güncelle
    if ($comment->user_id) {
        update_user_meta($comment->user_id, '_diyar_last_comment_time', time());
    }
    
    if ($comment->comment_approved == 1) {
        $user_id = $comment->user_id;
        if ($user_id > 0) {
            if (function_exists('diyar_update_user_xp_and_level')) {
                diyar_update_user_xp_and_level($user_id);
            }
            if (function_exists('diyar_check_and_assign_auto_badges')) {
                diyar_check_and_assign_auto_badges($user_id);
            }
        }
    }

    do_action('diyar_comment_posted', $comment_id, $comment->user_id);
    diyar_comment_fire_legacy_hook('posted', array($comment_id, $comment->user_id));
}

// E-posta bildirimlerini devre dışı bırak
add_filter('wp_new_comment_notify_moderators', '__return_false');
add_filter('wp_new_comment_notify_postauthor', '__return_false');

// Kullanıcı profil sayfalarına rozet ekleme bölümleri
add_action('edit_user_profile', 'diyar_add_badges_to_user_profile');
add_action('show_user_profile', 'diyar_add_badges_to_user_profile');
function diyar_add_badges_to_user_profile($user) {
    if (!current_user_can('edit_user', $user->ID)) return;
    
    global $wpdb;
    $manual_badges = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}diyar_badges WHERE is_automated = 0");
    $user_badges = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT badge_id FROM {$wpdb->prefix}diyar_user_badges WHERE user_id = %d", $user->ID)));
    ?>
    <h3>Manga Diyarı Yorum Rozetleri</h3>
    <table class="form-table">
        <tr>
            <th><label for="diyar_user_badges">Manuel Rozetler</label></th>
            <td>
                <?php if (empty($manual_badges)) : ?>
                    <p>Henüz manuel olarak atanabilecek bir rozet oluşturulmamış.</p>
                <?php else: foreach ($manual_badges as $badge) : ?>
                    <label style="margin-right: 15px; display: inline-block; margin-bottom: 10px;">
                        <input type="checkbox" name="diyar_user_badges[]" value="<?php echo $badge->badge_id; ?>" <?php checked(in_array($badge->badge_id, $user_badges)); ?>>
                        <span style="display: inline-flex; align-items: center; gap: 5px;"><?php echo $badge->badge_svg; ?> <?php echo esc_html($badge->badge_name); ?></span>
                    </label><br>
                <?php endforeach; endif; ?>
            </td>
        </tr>
        <tr>
            <th><label>Kullanıcı Durumu</label></th>
            <td>
                <?php 
                $ban_status = get_user_meta($user->ID, 'diyar_ban_status', true);
                $timeout_until = get_user_meta($user->ID, 'diyar_timeout_until', true);
                ?>
                <fieldset>
                    <legend class="screen-reader-text">Kullanıcı Durumu</legend>
                    <label>
                        <input type="radio" name="diyar_user_status" value="active" <?php checked($ban_status !== 'banned' && (!$timeout_until || $timeout_until < time())); ?>>
                        Aktif
                    </label><br>
                    <label>
                        <input type="radio" name="diyar_user_status" value="timeout" <?php checked($timeout_until && $timeout_until > time()); ?>>
                        24 Saat Susturulmuş
                    </label><br>
                    <label>
                        <input type="radio" name="diyar_user_status" value="banned" <?php checked($ban_status === 'banned'); ?>>
                        Kalıcı Engellenmiş
                    </label>
                </fieldset>
            </td>
        </tr>
    </table>
    <?php
}

// Kullanıcı rozetlerini ve durumunu kaydet
add_action('edit_user_profile_update', 'diyar_save_user_badges_and_status');
add_action('personal_options_update', 'diyar_save_user_badges_and_status');
function diyar_save_user_badges_and_status($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'diyar_user_badges';
    
    // Önce kullanıcının tüm manuel rozetlerini sil
    $manual_badge_ids = $wpdb->get_col("SELECT badge_id FROM {$wpdb->prefix}diyar_badges WHERE is_automated = 0");
    if (!empty($manual_badge_ids)) {
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE user_id = %d AND badge_id IN (" . implode(',', array_map('intval', $manual_badge_ids)) . ")", $user_id));
    }

    // Sonra seçilenleri tekrar ekle
    if (!empty($_POST['diyar_user_badges'])) {
        foreach ($_POST['diyar_user_badges'] as $badge_id) {
            $wpdb->insert($table, array('user_id' => $user_id, 'badge_id' => intval($badge_id)));
        }
    }
    
    // Kullanıcı durumunu kaydet
    if (isset($_POST['diyar_user_status'])) {
        $status = sanitize_key($_POST['diyar_user_status']);
        
        // Önce eski durumları temizle
        delete_user_meta($user_id, 'diyar_ban_status');
        delete_user_meta($user_id, 'diyar_timeout_until');
        
        switch ($status) {
            case 'banned':
                update_user_meta($user_id, 'diyar_ban_status', 'banned');
                break;
            case 'timeout':
                update_user_meta($user_id, 'diyar_timeout_until', time() + 86400); // 24 saat
                break;
            // 'active' için herhangi bir meta eklemiyoruz
        }
    }
}

// Admin panelinde spam koruması ayarları
add_action('admin_init', 'diyar_register_spam_protection_settings');
function diyar_register_spam_protection_settings() {
    add_settings_section(
        'diyar_spam_section',
        'Spam Koruması',
        null,
        'diyar_comment_options'
    );
    
    add_settings_field(
        'profanity_filter_words',
        'Küfür Filtresi (virgülle ayırın)',
        'diyar_render_profanity_field',
        'diyar_comment_options',
        'diyar_spam_section'
    );
    
    add_settings_field(
        'spam_link_limit',
        'Maksimum Link Sayısı',
        'diyar_render_link_limit_field',
        'diyar_comment_options',
        'diyar_spam_section'
    );
    
    add_settings_field(
        'auto_moderate_reports',
        'Otomatik Moderasyon Şikayet Limiti',
        'diyar_render_auto_moderate_field',
        'diyar_comment_options',
        'diyar_spam_section'
    );
}

function diyar_render_profanity_field() {
    $options = get_option('diyar_comment_options', array());
    $value = isset($options['profanity_filter_words']) ? $options['profanity_filter_words'] : '';
    echo '<textarea name="diyar_comment_options[profanity_filter_words]" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Yasaklı kelimeleri virgülle ayırarak yazın. Örnek: kelime1, kelime2, kelime3</p>';
}

function diyar_render_link_limit_field() {
    $options = get_option('diyar_comment_options', array());
    $value = isset($options['spam_link_limit']) ? $options['spam_link_limit'] : 2;
    echo '<input type="number" name="diyar_comment_options[spam_link_limit]" value="' . esc_attr($value) . '" class="small-text" min="0" max="10" />';
    echo '<p class="description">Bir yorumda izin verilen maksimum link sayısı (0 = sınırsız)</p>';
}

function diyar_render_auto_moderate_field() {
    $options = get_option('diyar_comment_options', array());
    $value = isset($options['auto_moderate_reports']) ? $options['auto_moderate_reports'] : 3;
    echo '<input type="number" name="diyar_comment_options[auto_moderate_reports]" value="' . esc_attr($value) . '" class="small-text" min="1" max="20" />';
    echo '<p class="description">Kaç şikayet aldığında yorum otomatik olarak moderasyona alınır</p>';
}

// filters-and-actions.php dosyasına bu filtreyi ekleyin:

// Tüm yorumları otomatik onayla
add_filter('pre_comment_approved', 'diyar_auto_approve_comments', 10, 2);
function diyar_auto_approve_comments($approved, $commentdata) {
    // Sadece giriş yapmış kullanıcılar için otomatik onay
    if (isset($commentdata['user_id']) && $commentdata['user_id'] > 0) {
        return 1; // Onayla
    }
    
    // Diğer durumlarda WordPress'in kendi kararını ver
    return $approved;
}

// WordPress'in moderasyon ayarlarını geçersiz kıl
add_action('init', 'diyar_override_comment_moderation');
function diyar_override_comment_moderation() {
    // Yorum moderasyonunu kapat
    update_option('comment_moderation', '0');
    update_option('moderation_notify', '0');
    
    // Email bildirimlerini kapat
    update_option('comments_notify', '0');
}

// Yorum durum değişikliğini yakala
add_action('wp_set_comment_status', 'diyar_comment_status_changed', 10, 2);
function diyar_comment_status_changed($comment_id, $status) {
    if ($status === 'approve') {
        $comment = get_comment($comment_id);
        if ($comment && $comment->user_id) {
            // XP ver
            if (function_exists('diyar_update_user_xp_and_level')) {
                diyar_update_user_xp_and_level($comment->user_id);
            }
            
            // Rozetleri kontrol et
            if (function_exists('diyar_check_and_assign_auto_badges')) {
                diyar_check_and_assign_auto_badges($comment->user_id);
            }
        }
    }
}
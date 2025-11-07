<?php
if (!defined('ABSPATH')) exit;

/**
 * Yorum içeriğini render eder - GIF markdown'ını HTML'ye çevirir
 */
function diyar_render_comment_content($content) {
    if (empty($content)) return '';
    
    // GIF markdown'ını HTML'ye çevir: ![GIF](url) -> <div class="gif-container"><img src="url" alt="GIF" loading="lazy"></div>
    $content = preg_replace_callback(
        '/!\[GIF\]\((https?:\/\/[^\)]+)\)/',
        function($matches) {
            $gif_url = esc_url($matches[1]);
            return '<div class="gif-container"><img src="' . $gif_url . '" alt="GIF" loading="lazy" class="comment-gif"></div>';
        },
        $content
    );
    
    // Spoiler desteği: [spoiler]content[/spoiler] - Siyah kutu stili
    $content = preg_replace_callback(
        '/\[spoiler\](.*?)\[\/spoiler\]/s',
        function($matches) {
            $spoiler_content = trim($matches[1]);
            return '<span class="diyar-spoiler"><span class="spoiler-content" onclick="this.classList.toggle(\'revealed\')">' . $spoiler_content . '</span></span>';
        },
        $content
    );
    
    // WordPress'in kendi text formatlamasını uygula (br, p tagları vs.)
    $content = wpautop($content);
    
    return $content;
}

/**
 * Dinamik post ID belirleme - Manga sistemi uyumlu
 */
function diyar_get_dynamic_post_id() {
    $current_url = diyar_get_current_page_url();
    $normal_post_id = get_the_ID();

    if (!$normal_post_id && isset($GLOBALS['post']) && is_object($GLOBALS['post'])) {
        $normal_post_id = $GLOBALS['post']->ID;
    }

    if ($current_url) {
        $resolved = diyar_resolve_post_id_from_url($current_url);
        if (!empty($resolved['id'])) {
            return intval($resolved['id']);
        }
    }

    if ($normal_post_id && get_post($normal_post_id)) {
        return intval($normal_post_id);
    }

    return $normal_post_id ? intval($normal_post_id) : 0;
}

/**
 * Mevcut sayfa URL'sini al
 */
function diyar_get_current_page_url() {
    if (empty($_SERVER['HTTP_HOST']) || empty($_SERVER['REQUEST_URI'])) {
        return '';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Dinamik yorum ID'sini URL'den belirle
 */
function diyar_get_dynamic_post_id_from_url($url) {
    if (empty($url)) {
        return 0;
    }

    $resolved = diyar_resolve_post_id_from_url($url);
    return !empty($resolved['id']) ? intval($resolved['id']) : 0;
}

/**
 * URL'den özel post ID belirleyen yardımcı fonksiyon
 */
function diyar_resolve_post_id_from_url($url) {
    $result = array(
        'id' => 0,
        'is_virtual' => false
    );

    if (empty($url)) {
        return $result;
    }

    $parsed = wp_parse_url($url);
    if ($parsed === false) {
        return $result;
    }

    $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
    $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
    $path_for_match = strtolower($path);
    $query = isset($parsed['query']) ? $parsed['query'] : '';

    // Madara chapter patternleri
    if (preg_match('/^manga\/([^\/]+)\/chapter-([^\/]+)\/?$/i', $path, $matches)) {
        $slug = strtolower($matches[1]);
        $chapter = strtolower($matches[2]);
        $result['id'] = diyar_generate_virtual_post_id_from_string($slug . '|chapter|' . $chapter);
        $result['is_virtual'] = true;
        return $result;
    }

    // Madara farklı dil chapter patternleri (ör: bolum, episode)
    if (preg_match('/^manga\/([^\/]+)\/(?:bolum|episode)-([^\/]+)\/?$/i', $path, $matches)) {
        $slug = strtolower($matches[1]);
        $chapter = strtolower($matches[2]);
        $result['id'] = diyar_generate_virtual_post_id_from_string($slug . '|chapter|' . $chapter);
        $result['is_virtual'] = true;
        return $result;
    }

    // Madara ana sayfa patterni
    if (preg_match('/^manga\/([^\/]+)\/?$/i', $path, $matches)) {
        $slug = strtolower($matches[1]);
        $result['id'] = diyar_generate_virtual_post_id_from_string($slug . '|main');
        $result['is_virtual'] = true;
        return $result;
    }

    // Query string içinde chapter/episode parametrelerini kontrol et
    if (!empty($query)) {
        parse_str($query, $query_vars);
        foreach (array('chapter', 'bolum', 'episode', 'part') as $key) {
            if (!empty($query_vars[$key])) {
                $result['id'] = diyar_generate_virtual_post_id_from_string(($path_for_match ?: 'root') . '|' . $key . '|' . strtolower($query_vars[$key]));
                $result['is_virtual'] = true;
                return $result;
            }
        }
    }

    // Normal WordPress yazısı var mı kontrol et
    $post_id = url_to_postid($url);
    if ($post_id) {
        $result['id'] = intval($post_id);
        $result['is_virtual'] = false;
        return $result;
    }

    // Fallback: URL path + anlamlı query değerlerinden benzersiz ID üret
    $significant_query = '';
    if (!empty($query)) {
        parse_str($query, $query_vars);
        if (!empty($query_vars)) {
            $ignored = array('style', 'mode');
            foreach ($query_vars as $key => $value) {
                if (in_array(strtolower($key), $ignored, true)) {
                    unset($query_vars[$key]);
                }
            }
            if (!empty($query_vars)) {
                ksort($query_vars);
                $significant_query = http_build_query($query_vars);
            }
        }
    }

    $hash_source = $host . '/' . $path_for_match;
    if (!empty($significant_query)) {
        $hash_source .= '?' . strtolower($significant_query);
    }

    if (!empty($hash_source)) {
        $result['id'] = diyar_generate_virtual_post_id_from_string($hash_source);
        $result['is_virtual'] = true;
    }

    return $result;
}

/**
 * String'ten pozitif tamsayı ID üret
 */
function diyar_generate_virtual_post_id_from_string($string) {
    if ($string === '') {
        return 0;
    }

    $hash = sprintf('%u', crc32($string));
    $hash_int = intval($hash);

    if ($hash_int === 0) {
        $hash_int = intval(hexdec(substr(md5($string . '|diyar'), 0, 8)));
    }

    return $hash_int;
}

/**
 * Yorumun kaynak bağlantısı bilgilerini döndür
 */
function diyar_get_comment_source_data($comment) {
    if (is_numeric($comment)) {
        $comment = get_comment($comment);
    }

    if (!$comment) {
        return array(
            'raw_url'   => '',
            'permalink' => '',
            'label'     => __('Bilinmeyen Sayfa', 'diyar-comment'),
            'has_post'  => false,
        );
    }

    $raw_url = get_comment_meta($comment->comment_ID, 'diyar_comment_source_url', true);
    $raw_url = $raw_url ? esc_url_raw($raw_url) : '';
    $has_post = false;
    $label = '';
    $permalink = '';

    if ($comment->comment_post_ID) {
        $post = get_post($comment->comment_post_ID);
        if ($post) {
            $has_post = true;
            $label = get_the_title($post) ?: __('Bilinmeyen Yazı', 'diyar-comment');
            $permalink = get_comment_link($comment);

            if (!$raw_url) {
                $raw_url = get_permalink($post);
            }
        }
    }

    if (!$permalink) {
        if ($raw_url) {
            $permalink = $raw_url;
            if (strpos($permalink, '#') === false) {
                $permalink .= '#comment-' . $comment->comment_ID;
            }
        } else {
            $permalink = get_comment_link($comment);
        }
    }

    if (!$label) {
        if ($raw_url) {
            $parsed = wp_parse_url($raw_url);
            $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

            if ($path) {
                $label = urldecode($path);
            } elseif (!empty($parsed['host'])) {
                $label = $parsed['host'];
            }
        }
    }

    if (!$label) {
        $label = __('Bilinmeyen Sayfa', 'diyar-comment');
    }

    return array(
        'raw_url'   => $raw_url,
        'permalink' => $permalink,
        'label'     => $label,
        'has_post'  => $has_post,
    );
}

/**
 * Yorum için kullanılabilir bağlantıyı döndür
 */
function diyar_get_comment_permalink($comment) {
    $data = diyar_get_comment_source_data($comment);
    return !empty($data['permalink']) ? $data['permalink'] : '';
}

/**
 * Yorumun kaynak URL'sini döndür
 */
function diyar_get_comment_source_url($comment) {
    $data = diyar_get_comment_source_data($comment);
    return !empty($data['raw_url']) ? $data['raw_url'] : '';
}

/**
 * Yorumun konum etiketi
 */
function diyar_get_comment_location_label($comment) {
    $data = diyar_get_comment_source_data($comment);
    return $data['label'];
}

/**
 * Yorum HTML'ini oluşturan ana callback fonksiyonu - TÜM ÖZELLİKLER İLE
 */
function diyar_comment_format($comment, $args, $depth) {
    if (!$comment) return '';

    $GLOBALS['comment'] = $comment;
    $options = get_option('diyar_comment_options', array());
    $profile_url = diyar_get_user_profile_url($comment->user_id);
    $max_depth = isset($args['max_depth']) ? $args['max_depth'] : 5;
    $user_vote = '';
    $comment_link = diyar_get_comment_permalink($comment);

    if (is_user_logged_in()) {
        $user_vote = get_comment_meta($comment->comment_ID, '_user_vote_' . get_current_user_id(), true);
    }
    ?>
    <li <?php comment_class('diyar-comment-item') ?> id="comment-<?php comment_ID() ?>" data-comment-id="<?php comment_ID() ?>">
        <div class="comment-body" id="div-comment-<?php comment_ID() ?>">
            <div class="comment-author vcard">
                <a href="<?php echo esc_url($profile_url); ?>" class="avatar-link" target="_blank">
                    <?php echo diyar_get_avatar($comment->user_id ?: $comment->comment_author_email, 55); ?>
                </a>
            </div>
            <div class="comment-content">
                <div class="comment-author-meta">
                    <a class="fn author-name" href="<?php echo esc_url($profile_url); ?>" target="_blank">
                        <?php echo get_comment_author(); ?>
                    </a>
                    <?php 
                    // Kullanıcı seviye ve rozetlerini göster
                    if ($comment->user_id) {
                        echo diyar_get_user_level_badge($comment->user_id);
                        echo diyar_get_user_custom_badges($comment->user_id);
                        
                        // Kullanıcı durumu kontrolü
                        $ban_status = get_user_meta($comment->user_id, 'diyar_ban_status', true);
                        $timeout_until = get_user_meta($comment->user_id, 'diyar_timeout_until', true);
                        
                        if ($ban_status === 'banned') {
                            echo '<span class="user-status banned" title="Engellenmiş kullanıcı">🚫</span>';
                        } elseif ($timeout_until && current_time('timestamp') < $timeout_until) {
                            echo '<span class="user-status timeout" title="Susturulmuş kullanıcı">⏰</span>';
                        }
                    }
                    ?>
                    <span class="comment-metadata">
                        <a href="<?php echo esc_url($comment_link ?: '#'); ?>" class="comment-time">
                            <time datetime="<?php comment_time('c'); ?>" title="<?php comment_time(); ?>">
                                <?php printf('%s önce', human_time_diff(get_comment_time('U'), current_time('timestamp'))); ?>
                            </time>
                        </a>
                        <?php if ($comment->comment_approved == '0') : ?>
                            <span class="comment-awaiting-moderation">(Onay bekliyor)</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="comment-text" data-comment-id="<?php comment_ID(); ?>">
                    <?php echo diyar_render_comment_content(get_comment_text()); ?>
                </div>

                <div class="comment-actions-modern">
                    <div class="comment-interaction-buttons">
                        <!-- Modern Icon'lu Beğeni Butonu -->
                        <button class="heart-like-btn <?php echo ($user_vote === 'liked') ? 'active' : ''; ?>"
                                type="button"
                                data-comment-id="<?php comment_ID(); ?>"
                                title="Beğen">
                            <svg class="heart-icon" viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor" d="M12,21.35L10.55,20.03C5.4,15.36 2,12.27 2,8.5 2,5.41 4.42,3 7.5,3C9.24,3 10.91,3.81 12,5.08C13.09,3.81 14.76,3 16.5,3C19.58,3 22,5.41 22,8.5C22,12.27 18.6,15.36 13.45,20.03L12,21.35Z"/>
                            </svg>
                            <span class="like-count"><?php
                                $likes = get_comment_meta($comment->comment_ID, '_likes', true) ?: 0;
                                $dislikes = get_comment_meta($comment->comment_ID, '_dislikes', true) ?: 0;
                                $total = $likes - $dislikes;
                                echo $total > 0 ? $total : 0;
                            ?></span>
                        </button>
                        
                        <?php if ($depth < $max_depth) : ?>
                            <!-- Modern Icon'lu Yanıt Butonu -->
                            <button type="button" class="reply-btn-modern" data-comment-id="<?php comment_ID(); ?>" title="Yanıtla">
                                <svg class="reply-icon" viewBox="0 0 24 24" width="18" height="18">
                                    <path fill="currentColor" d="M10,9V5L3,12L10,19V14.9C15,14.9 18.5,16.5 21,20C20,15 17,10 10,9Z"/>
                                </svg>
                                <span class="reply-text">Yanıtla</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 3 nokta dropdown menü -->
                    <?php if (is_user_logged_in()) : ?>
                        <div class="comment-menu-dropdown">
                            <button type="button" class="menu-trigger" data-comment-id="<?php comment_ID(); ?>" title="Seçenekler">
                                <span class="dots">⋯</span>
                            </button>
                            <div class="dropdown-menu" id="menu-<?php comment_ID(); ?>">
                                <?php if ($comment->user_id == get_current_user_id()) : ?>
                                    <button type="button" class="dropdown-item edit-comment-btn" data-comment-id="<?php comment_ID(); ?>">
                                        <span class="icon">✏</span> Düzenle
                                    </button>
                                    <button type="button" class="dropdown-item delete-comment-btn" data-comment-id="<?php comment_ID(); ?>">
                                        <span class="icon">🗑</span> Sil
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="dropdown-item report-comment-btn" data-comment-id="<?php comment_ID(); ?>">
                                        <span class="icon">⚠</span> Şikayet Et
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (current_user_can('moderate_comments')) : ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="<?php echo admin_url('comment.php?action=editcomment&c=' . $comment->comment_ID); ?>"
                                       class="dropdown-item" target="_blank">
                                        <span class="icon">🔧</span> Admin Düzenle
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Yanıt formu konteyneri -->
        <div class="reply-form-container" id="reply-form-<?php comment_ID(); ?>" style="display: none;">
            <!-- AJAX ile yüklenecek -->
        </div>
        
        <?php
        // Alt yorumları göster - YENİ TOGGLE SİSTEMİ
        $children = get_comments(array(
            'parent' => $comment->comment_ID,
            'status' => 'approve',
            'count' => true
        ));
        
        if ($children > 0) : ?>
            <!-- Yanıtları Göster/Gizle Butonu -->
            <div class="replies-toggle-container">
                <button type="button" class="replies-toggle-btn" data-comment-id="<?php comment_ID(); ?>" data-replies-count="<?php echo $children; ?>">
                    <svg class="toggle-icon" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="currentColor" d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z"/>
                    </svg>
                    <span class="toggle-text"><?php echo $children; ?> yanıtı göster</span>
                </button>
            </div>
            
            <!-- Yanıtlar Konteyneri -->
            <ol class="children replies-container" style="display: none;" data-parent-id="<?php comment_ID(); ?>">
                <!-- AJAX ile yüklenecek -->
            </ol>
        <?php endif; ?>
    <?php
}

/**
 * Özel avatar fonksiyonu - custom avatar desteği ile
 */
function diyar_get_avatar($user_id_or_email, $size = 50, $default = '', $alt = '') {
    if (is_numeric($user_id_or_email)) {
        $custom_avatar = get_user_meta($user_id_or_email, 'diyar_custom_avatar', true);
        if ($custom_avatar && file_exists($custom_avatar)) {
            $avatar_url = wp_upload_dir()['baseurl'] . '/' . basename($custom_avatar);
            return '<img src="' . esc_url($avatar_url) . '" class="avatar avatar-' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr($alt) . '">';
        }
        $user = get_userdata($user_id_or_email);
        $email = $user ? $user->user_email : '';
    } else {
        $email = $user_id_or_email;
    }
    
    return get_avatar($email, $size, $default, $alt);
}

/**
 * Bir yorum yapıldığında kullanıcının XP'sini ve seviyesini günceller.
 */
function diyar_update_user_xp_and_level($user_id) {
    if (!$user_id) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'diyar_user_levels';
    $options = get_option('diyar_comment_options', array());
    $xp_per_comment = isset($options['xp_per_comment']) ? intval($options['xp_per_comment']) : 15;
    $xp_per_comment = apply_filters('diyar_xp_per_comment', $xp_per_comment, $user_id);
    $xp_per_comment = diyar_comment_apply_legacy_filter('xp_per_comment', $xp_per_comment, $user_id);
    
    $user_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));

    if ($user_data) {
        $new_xp = $user_data->xp + $xp_per_comment;
        
        // Seviye hesaplama - daha dengeli formül
        $current_level = $user_data->level;
        $xp_for_next_level = diyar_calculate_xp_for_level($current_level + 1);
        $new_level = $current_level;
        
        // Seviye atlama kontrolü
        while ($new_xp >= $xp_for_next_level) {
            $new_level++;
            $xp_for_next_level = diyar_calculate_xp_for_level($new_level + 1);
        }
        
        $result = $wpdb->update($table, 
            array('xp' => $new_xp, 'level' => $new_level), 
            array('user_id' => $user_id)
        );
        
        // Seviye atladıysa otomatik rozetleri kontrol et
        if ($new_level > $current_level) {
            diyar_check_and_assign_auto_badges($user_id);
        }
    } else {
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'xp' => $xp_per_comment,
            'level' => 1
        ));
    }
}

/**
 * Belirli bir seviye için gereken XP miktarını hesaplar
 */
function diyar_calculate_xp_for_level($level) {
    $required = (int)(pow($level, 1.8) * 100);
    $required = apply_filters('diyar_calculate_level_xp', $required, $level);
    return diyar_comment_apply_legacy_filter('calculate_level_xp', $required, $level);
}

/**
 * Kullanıcının seviye ve XP bilgisini veritabanından çeker.
 */
function diyar_get_user_level_info($user_id) {
    if (!$user_id) return (object)array('level' => 1, 'xp' => 0);
    
    global $wpdb;
    $table = $wpdb->prefix . 'diyar_user_levels';
    $info = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
    
    if (!$info) {
        return (object)array('level' => 1, 'xp' => 0);
    }
    
    return $info;
}

/**
 * Kullanıcının sahip olduğu tüm rozetleri veritabanından çeker.
 */
function diyar_get_user_badges($user_id) {
    if (!$user_id) return array();
    
    global $wpdb;
    $badges_table = $wpdb->prefix . 'diyar_badges';
    $user_badges_table = $wpdb->prefix . 'diyar_user_badges';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT b.* FROM $badges_table b 
         JOIN $user_badges_table ub ON b.badge_id = ub.badge_id 
         WHERE ub.user_id = %d 
         ORDER BY b.badge_id DESC", 
        $user_id
    ));
}

/**
 * Otomatik rozet koşullarını kontrol eder ve gerekirse kullanıcıya atar.
 */
function diyar_check_and_assign_auto_badges($user_id) {
    if (!$user_id) return;

    global $wpdb;
    $badges_table = $wpdb->prefix . 'diyar_badges';
    $user_badges_table = $wpdb->prefix . 'diyar_user_badges';
    
    // Otomatik rozetleri al
    $auto_badges = $wpdb->get_results("SELECT * FROM $badges_table WHERE is_automated = 1");
    if (empty($auto_badges)) return;

    // Kullanıcının yorum sayısını al
    $user_comment_count = get_comments(array(
        'user_id' => $user_id, 
        'count' => true, 
        'status' => 'approve'
    ));
    
    // Kullanıcının toplam beğeni sayısını al
    $user_total_likes = diyar_get_user_total_likes($user_id);
    
    // Kullanıcının seviyesini al
    $level_info = diyar_get_user_level_info($user_id);

    foreach ($auto_badges as $badge) {
        $should_assign = false;
        
        switch ($badge->auto_condition_type) {
            case 'comment_count':
                $should_assign = ($user_comment_count >= $badge->auto_condition_value);
                break;
            case 'like_count':
                $should_assign = ($user_total_likes >= $badge->auto_condition_value);
                break;
            case 'level':
                $should_assign = ($level_info->level >= $badge->auto_condition_value);
                break;
        }

        if ($should_assign) {
            // Rozet zaten var mı kontrol et
            $badge_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $user_badges_table WHERE user_id = %d AND badge_id = %d",
                $user_id,
                $badge->badge_id
            ));
            
            if ($badge_exists == 0) {
                $wpdb->insert($user_badges_table, array(
                    'user_id' => $user_id,
                    'badge_id' => $badge->badge_id
                ));
            }
        }
    }
}

/**
 * Bir kullanıcının tüm yorumlarından aldığı toplam beğeni sayısını hesaplar.
 */
function diyar_get_user_total_likes($user_id) {
    if (!$user_id) return 0;
    
    global $wpdb;
    
    $sql = "SELECT SUM(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->commentmeta} cm 
            JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID 
            WHERE c.user_id = %d 
            AND cm.meta_key = '_likes' 
            AND c.comment_approved = '1'";
            
    $total = $wpdb->get_var($wpdb->prepare($sql, $user_id));
    
    return (int)$total;
}

/**
 * Admin panelinde kullanılacak genişletilmiş SVG rozet şablonlarını döndürür.
 */
function diyar_get_badge_templates() {
    return array(
        'shield' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1Z"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/></svg>',
        'heart' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,21.35L10.55,20.03C5.4,15.36 2,12.27 2,8.5C2,5.41 4.42,3 7.5,3C9.24,3 10.91,3.81 12,5.08C13.09,3.81 14.76,3 16.5,3C19.58,3 22,5.41 22,8.5C22,12.27 18.6,15.36 13.45,20.03L12,21.35Z"/></svg>',
        'trophy' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M7,4V2A1,1 0 0,1 8,1H16A1,1 0 0,1 17,2V4H20A1,1 0 0,1 21,5V8A3,3 0 0,1 18,11L17.83,11C17.42,12.43 16.31,13.5 14.83,13.91V16.5A2.5,2.5 0 0,1 12.5,19H11.5A2.5,2.5 0 0,1 9,16.5V13.91C7.69,13.5 6.58,12.43 6.17,11L6,11A3,3 0 0,1 3,8V5A1,1 0 0,1 4,4H7M5,6V8A1,1 0 0,0 6,9H7.17C7.59,7.5 9.15,6.27 11,6.08V6H5M19,6H13V6.08C14.85,6.27 16.41,7.5 16.83,9H18A1,1 0 0,0 19,8V6Z"/></svg>',
        'diamond' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M6,2L2,8L12,22L22,8L18,2H6M6.5,4H8.5L7,7L6.5,4M9.5,4H14.5L15,7L12,10L9,7L9.5,4M16.5,4H17.5L17,7L15,4H16.5Z"/></svg>',
        'flame' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M17.66,11.2C17.43,10.9 17.15,10.64 16.89,10.38C16.22,9.78 15.46,9.35 14.82,8.72C13.33,7.26 13,4.85 13.95,3C13.74,3.09 13.54,3.22 13.34,3.36C11.44,4.72 10.5,7.15 10.5,9.26C9.74,8.21 9.77,6.79 10.75,5.81C10.75,5.81 10.75,5.81 10.75,5.81C10.1,6.27 9.66,6.99 9.66,7.83C9.66,9.47 10.83,10.86 12.41,11.14C12.03,11.72 12.4,12.54 13.09,12.54C13.92,12.54 14.59,11.87 14.59,11.04C15.73,11.04 16.66,10.08 16.66,8.91C16.66,8.91 16.66,8.91 16.66,8.91C17.66,9.74 18.25,10.88 18.25,12.11C18.25,14.57 16.28,16.54 13.82,16.54C11.36,16.54 9.39,14.57 9.39,12.11C9.39,11.64 9.5,11.2 9.66,10.78C8.61,11.75 8,13.13 8,14.61C8,18.08 10.92,21 14.39,21C17.86,21 20.78,18.08 20.78,14.61C20.78,13.38 20.31,12.11 17.66,11.2Z"/></svg>',
        'crown' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M5,16L3,5L5.5,7.5L8.5,4L12,7L15.5,4L18.5,7.5L21,5L19,16H5M12,2A1,1 0 0,1 13,3A1,1 0 0,1 12,4A1,1 0 0,1 11,3A1,1 0 0,1 12,2M19,19H5V21H19V19Z"/></svg>',
        'medal' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,4A6,6 0 0,0 6,10C6,13.31 8.69,16 12.03,16C15.31,16 18,13.31 18,10A6,6 0 0,0 12,4M12,14A4,4 0 0,1 8,10A4,4 0 0,1 12,6A4,4 0 0,1 16,10A4,4 0 0,1 12,14M7,18A1,1 0 0,0 6,19A1,1 0 0,0 7,20H17A1,1 0 0,0 18,19A1,1 0 0,0 17,18H7Z"/></svg>',
        'rocket' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M2.81,14.12L5.64,11.29L8.17,10.79C11.39,6.41 17.55,5.54 19.07,7.07C20.59,8.59 19.72,14.75 15.34,18L14.84,20.53L12,17.69L2.81,14.12M15.92,8.08C15.38,8.62 15.38,9.5 15.92,10.04C16.46,10.58 17.34,10.58 17.88,10.04C18.42,9.5 18.42,8.62 17.88,8.08C17.34,7.54 16.46,7.54 15.92,8.08M5.93,16.5L7.65,17.35L8.5,19.07L9.64,17.93L8.22,16.5L5.93,16.5Z"/></svg>',
        'lightning' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M11,4L6,10.5H9L8,18L13,11.5H10L11,4Z"/></svg>',
        'gem' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M16,9H19L14,16M10,9H14L12,16M5,9H8L10,16M15,4L14,9M10,4L12,9M9,4L10,9M2,9L7,4H17L22,9L12,20L2,9Z"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M21,19V20H3V19L5,17V11C5,7.9 7.03,5.17 10,4.29C10,4.19 10,4.1 10,4A2,2 0 0,1 12,2A2,2 0 0,1 14,4C14,4.19 14,4.29 14,4.29C16.97,5.17 19,7.9 19,11V17L21,19M14,21A2,2 0 0,1 12,23A2,2 0 0,1 10,21"/></svg>',
        'magic' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M7.5,5.6L10,7L8.6,4.5L10,2L7.5,3.4L5,2L6.4,4.5L5,7L7.5,5.6M19.5,15.4L22,14L20.6,16.5L22,19L19.5,17.6L17,19L18.4,16.5L17,14L19.5,15.4M22,2L20.6,4.5L22,7L19.5,5.6L17,7L18.4,4.5L17,2L19.5,3.4L22,2M13.34,12.78L15.78,10.34L13.66,8.22L11.22,10.66L13.34,12.78M14.37,7.29L16.71,9.63C17.1,10 17.1,10.65 16.71,11.04L5.04,22.71C4.65,23.1 4,23.1 3.63,22.71L1.29,20.37C0.9,20 0.9,19.35 1.29,18.96L12.96,7.29C13.35,6.9 14,6.9 14.37,7.29Z"/></svg>',
        'target' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10Z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M16.2,16.2L11,13V7H12.5V12.2L17,14.7L16.2,16.2Z"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M18,22A2,2 0 0,0 20,20V4A2,2 0 0,0 18,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18M6,4H13V12L9.5,10.5L6,12V4Z"/></svg>',
        'puzzle' => '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="{color}" d="M20.5,11H19V7C19,5.89 18.1,5 17,5H13V3.5A2.5,2.5 0 0,0 10.5,1A2.5,2.5 0 0,0 8,3.5V5H4A2,2 0 0,0 2,7V10.8H3.5C5,10.8 6.2,12 6.2,13.5C6.2,15 5,16.2 3.5,16.2H2V20A2,2 0 0,0 4,22H7.8V20.5C7.8,19 9,17.8 10.5,17.8C12,17.8 13.2,19 13.2,20.5V22H17A2,2 0 0,0 19,20V16H20.5A2.5,2.5 0 0,0 23,13.5A2.5,2.5 0 0,0 20.5,11Z"/></svg>'
    );
}

/**
 * Kullanıcının profil sayfasına giden URL'i oluşturur - DÜZELTİLMİŞ
 */
function diyar_get_user_profile_url($user_id) {
    if (!$user_id) return '#';
    
    $options = get_option('diyar_comment_options', array());
    $page_id = isset($options['profile_page_id']) ? $options['profile_page_id'] : 0;
    
    // Önce kayıtlı profile page ID'yi kontrol et
    if ($page_id && get_post($page_id) && get_post_status($page_id) === 'publish') {
        return add_query_arg('user_id', $user_id, get_permalink($page_id));
    }
    
    // Shortcode'a sahip sayfa ara
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => [
            [
                'key' => '_wp_page_template',
                'value' => 'page-profile.php',
                'compare' => 'LIKE'
            ]
        ]
    ]);
    
    // Template bulunamazsa content'te shortcode ara
    if (empty($pages)) {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            's' => '[diyar_user_profile]'
        ]);

        if (empty($pages)) {
            $legacy_shortcode = preg_replace('/^diyar/', diyar_comment_get_legacy_prefix(), 'diyar_user_profile', 1);
            if (!empty($legacy_shortcode)) {
                $pages = get_posts([
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    's' => '[' . $legacy_shortcode . ']'
                ]);
            }
        }

        // Manuel kontrol - s parametresi her zaman güvenilir değil
        if (empty($pages)) {
            $all_pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'numberposts' => -1
            ]);

            foreach ($all_pages as $page) {
                if (diyar_comment_content_has_shortcode($page->post_content, 'diyar_user_profile')) {
                    $pages = [$page];
                    break;
                }
            }
        }
    }
    
    // Sayfa bulunduysa
    if (!empty($pages)) {
        $profile_page = $pages[0];
        // Seçenekleri güncelle
        $options['profile_page_id'] = $profile_page->ID;
        update_option('diyar_comment_options', $options);
        
        return add_query_arg('user_id', $user_id, get_permalink($profile_page->ID));
    }
    
    // Fallback olarak author posts URL kullan
    return get_author_posts_url($user_id);
}

/**
 * Kullanıcının seviye rozetini HTML olarak döndürür - YENİ OVAL TASARIM
 */
function diyar_get_user_level_badge($user_id) {
    if (!$user_id) return '';
    
    $level_info = diyar_get_user_level_info($user_id);
    $level_color = diyar_get_level_color($level_info->level);
    $level_title = diyar_get_level_title($level_info->level);
    
    return sprintf(
        '<span class="user-level-oval" style="background: %s;" title="%s - %d XP" data-level="%d">Seviye %d</span>',
        $level_color,
        $level_title,
        $level_info->xp,
        $level_info->level,
        $level_info->level
    );
}

/**
 * Seviye rengini belirler - ana renk #005B43 korunarak
 */
function diyar_get_level_color($level) {
    if ($level >= 100) return 'linear-gradient(135deg, #e74c3c, #c0392b)';     // Kırmızı - Efsanevi
    if ($level >= 75) return 'linear-gradient(135deg, #9b59b6, #8e44ad)';      // Mor - Mitik  
    if ($level >= 50) return 'linear-gradient(135deg, #3498db, #2980b9)';      // Mavi - Epik
    if ($level >= 30) return 'linear-gradient(135deg, #1abc9c, #16a085)';      // Turkuaz - Nadir
    if ($level >= 20) return 'linear-gradient(135deg, #f39c12, #e67e22)';      // Turuncu - Sıradışı
    if ($level >= 10) return 'linear-gradient(135deg, #27ae60, #229954)';      // Yeşil - Deneyimli
    if ($level >= 5) return 'linear-gradient(135deg, #005B43, #003d2e)';       // Ana renk - Aktif
    return 'linear-gradient(135deg, #95a5a6, #7f8c8d)';                        // Gri - Yeni başlayan
}

/**
 * Seviye unvanını belirler
 */
function diyar_get_level_title($level) {
    if ($level >= 100) return 'Efsanevi Yorumcu';
    if ($level >= 75) return 'Mitik Katılımcı';
    if ($level >= 50) return 'Epik Topluluk Üyesi';
    if ($level >= 30) return 'Nadir Katkıda Bulunan';
    if ($level >= 20) return 'Sıradışı Yorum Yazarı';
    if ($level >= 10) return 'Deneyimli Üye';
    if ($level >= 5) return 'Aktif Katılımcı';
    return 'Yeni Başlayan';
}

/**
 * Kullanıcının özel rozetlerini HTML olarak döndürür - YAZILI VERSİYON
 */
function diyar_get_user_custom_badges($user_id) {
    if (!$user_id) return '';
    
    $badges = diyar_get_user_badges($user_id);
    if (empty($badges)) return '';

    $output = '<span class="user-badges">';
    foreach (array_slice($badges, 0, 3) as $badge) {
        $output .= sprintf(
            '<span class="badge-item-with-text" title="%s">%s <span class="badge-text">%s</span></span>',
            esc_attr($badge->badge_name),
            $badge->badge_svg,
            esc_html($badge->badge_name)
        );
    }
    
    if (count($badges) > 3) {
        $remaining = count($badges) - 3;
        $output .= sprintf(
            '<span class="badge-more" title="Ve %d rozet daha">+%d</span>',
            $remaining,
            $remaining
        );
    }
    
    $output .= '</span>';
    return $output;
}

/**
 * Kullanıcı istatistiklerini döndürür
 */
function diyar_get_user_stats($user_id) {
    if (!$user_id) return array();
    
    $comment_count = get_comments(array('user_id' => $user_id, 'count' => true, 'status' => 'approve'));
    $total_likes = diyar_get_user_total_likes($user_id);
    $level_info = diyar_get_user_level_info($user_id);
    $badges = diyar_get_user_badges($user_id);
    $user = get_userdata($user_id);
    
    $last_comment = get_comments(['user_id' => $user_id, 'number' => 1, 'status' => 'approve']);
    $last_activity = !empty($last_comment) ? strtotime($last_comment[0]->comment_date) : strtotime($user->user_registered);
    
    $avg_likes = $comment_count > 0 ? round($total_likes / $comment_count, 1) : 0;
    
    return array(
        'comment_count' => (int)$comment_count,
        'total_likes' => (int)$total_likes,
        'avg_likes' => $avg_likes,
        'level' => (int)$level_info->level,
        'xp' => (int)$level_info->xp,
        'badge_count' => count($badges),
        'join_date' => $user->user_registered,
        'last_activity' => $last_activity,
        'days_active' => floor((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS)
    );
}

/**
 * Yorum için meta bilgileri döndürür
 */
function diyar_get_comment_meta_info($comment_id) {
    $likes = get_comment_meta($comment_id, '_likes', true) ?: 0;
    $dislikes = get_comment_meta($comment_id, '_dislikes', true) ?: 0;
    $reports = diyar_get_comment_report_count($comment_id);
    
    return array(
        'likes' => (int)$likes,
        'dislikes' => (int)$dislikes,
        'reports' => (int)$reports,
        'score' => (int)$likes - (int)$dislikes
    );
}

/**
 * Yorumun şikayet sayısını döndürür
 */
function diyar_get_comment_report_count($comment_id) {
    global $wpdb;
    $reports_table = $wpdb->prefix . 'diyar_reports';
    
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $reports_table WHERE comment_id = %d",
        $comment_id
    ));
}

/**
 * Kullanıcı durumu kontrolü
 */
function diyar_is_user_banned($user_id) {
    if (!$user_id) return false;
    
    $ban_status = get_user_meta($user_id, 'diyar_ban_status', true);
    $timeout_until = get_user_meta($user_id, 'diyar_timeout_until', true);
    
    return $ban_status === 'banned' || ($timeout_until && current_time('timestamp') < $timeout_until);
}

/**
 * Widget sınıfı
 */
class Diyar_Comment_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'diyar_comment_widget',
            'Manga Diyarı Yorum İstatistikleri',
            array('description' => 'Topluluk istatistiklerini gösterir')
        );
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $stats = diyar_get_weekly_stats();
        $top_users = diyar_get_top_users(5);
        
        echo '<div class="diyar-widget-content">';
        echo '<div class="stats-section">';
        echo '<h4>Bu Hafta</h4>';
        echo '<ul>';
        echo '<li><strong>' . $stats['comments'] . '</strong> yeni yorum</li>';
        echo '<li><strong>' . $stats['new_users'] . '</strong> yeni üye</li>';
        echo '<li><strong>' . $stats['reactions'] . '</strong> tepki</li>';
        echo '</ul>';
        echo '</div>';
        
        if (!empty($top_users)) {
            echo '<div class="top-users-section">';
            echo '<h4>En Aktif Üyeler</h4>';
            echo '<ol>';
            foreach ($top_users as $user) {
                $profile_url = diyar_get_user_profile_url($user->ID);
                echo '<li><a href="' . esc_url($profile_url) . '">' . esc_html($user->display_name) . '</a> ';
                echo '<span class="level">(Lv.' . $user->level . ')</span></li>';
            }
            echo '</ol>';
            echo '</div>';
        }
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Topluluk İstatistikleri';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Başlık:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * En aktif kullanıcıları getir
 */
function diyar_get_top_users($limit = 10) {
    global $wpdb;
    
    $sql = "
        SELECT u.ID, u.display_name, 
               COALESCE(ul.level, 1) as level,
               COALESCE(ul.xp, 0) as xp,
               COUNT(c.comment_ID) as comment_count,
               COALESCE(SUM(CAST(cm.meta_value AS UNSIGNED)), 0) as total_likes
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}diyar_user_levels ul ON u.ID = ul.user_id
        LEFT JOIN {$wpdb->comments} c ON u.ID = c.user_id AND c.comment_approved = '1'
        LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = '_likes'
        GROUP BY u.ID
        ORDER BY (COALESCE(ul.level, 1) * 10 + COUNT(c.comment_ID) + COALESCE(SUM(CAST(cm.meta_value AS UNSIGNED)), 0)) DESC
        LIMIT %d
    ";
    
    return $wpdb->get_results($wpdb->prepare($sql, $limit));
}

/**
 * Haftalık istatistikler
 */
function diyar_get_weekly_stats() {
    global $wpdb;
    
    $week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
    
    $stats = array(
        'comments' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_date >= %s AND comment_approved = '1'",
            $week_ago
        )),
        'reactions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}diyar_reactions"),
        'new_users' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s",
            $week_ago
        )),
        'badges_earned' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}diyar_user_badges")
    );
    
    return $stats;
}

/**
 * Widget'ları kaydet
 */
function diyar_register_widgets() {
    register_widget('Diyar_Comment_Widget');
}
add_action('widgets_init', 'diyar_register_widgets');
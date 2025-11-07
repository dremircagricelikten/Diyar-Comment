<?php
if (!defined('ABSPATH')) exit;

class Diyar_Comment_Ajax_Handlers {
    
    public function __construct() {
        $actions = array(
            'get_initial_data', 'handle_reaction', 'get_comments', 
            'handle_like', 'flag_comment', 'submit_comment', 
            'admin_edit_comment', 'load_more_replies', 'load_more_profile_comments',
            'upload_image', 'update_profile', 'change_password',
            'edit_comment', 'delete_comment', 'load_replies'
        );
        
        foreach ($actions as $action) {
            // Logged in user actions
            add_action('wp_ajax_diyar_' . $action, array($this, $action . '_callback'));

            // Non-logged in actions (sadece gerekli olanlar)
            $public_actions = array('get_initial_data', 'get_comments');
            if (in_array($action, $public_actions)) {
                add_action('wp_ajax_nopriv_diyar_' . $action, array($this, $action . '_callback'));
            }
        }

        diyar_comment_register_legacy_ajax_actions($this);
    }

    /**
     * Güvenli nonce kontrolü
     */
    private function verify_nonce($action = 'diyar-comment-nonce') {
        if (check_ajax_referer($action, 'nonce', false)) {
            return;
        }

        $legacy_action = preg_replace('/^diyar/', diyar_comment_get_legacy_prefix(), $action, 1);
        if (!empty($legacy_action) && check_ajax_referer($legacy_action, 'nonce', false)) {
            return;
        }

        wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız.'));
    }

    /**
     * Kullanıcı oturum kontrolü
     */
    private function require_login() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Bu işlem için giriş yapmalısınız.'));
        }
    }

    /**
     * Görsel upload handler
     */
    public function upload_image_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Görsel yüklenemedi.'));
        }
        
        $file = $_FILES['image'];
        
        // Dosya tipi kontrolü
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Sadece JPEG, PNG, GIF ve WebP formatları desteklenir.'));
        }
        
        // Dosya boyutu kontrolü (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'Dosya boyutu 5MB\'dan küçük olmalıdır.'));
        }
        
        // WordPress upload fonksiyonunu kullan
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Upload işlemi
        $uploaded = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
        }
        
        // Attachment oluştur
        $attachment = array(
            'post_mime_type' => $uploaded['type'],
            'post_title' => sanitize_file_name(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Görsel veritabanına kaydedilemedi.'));
        }
        
        // Metadata oluştur
        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        wp_send_json_success(array(
            'url' => $uploaded['url'],
            'attachment_id' => $attachment_id,
            'message' => 'Görsel başarıyla yüklendi.'
        ));
    }

    /**
     * Profil güncelleme handler - DÜZELTİLMİŞ VERSİYON
     */
    public function update_profile_callback() {
        // DÜZELTME: Standart nonce kontrolü kullan
        $nonce_value = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce_value, 'diyar-comment-nonce')) {
            $legacy_nonce = preg_replace('/^diyar/', diyar_comment_get_legacy_prefix(), 'diyar-comment-nonce', 1);
            if (!$legacy_nonce || !wp_verify_nonce($nonce_value, $legacy_nonce)) {
                wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız. Sayfayı yenileyin ve tekrar deneyin.'));
            }
        }
        
        $this->require_login();
        
        $user_id = get_current_user_id();
        $action_type = sanitize_text_field($_POST['action_type']);
        
        // Debug için action type kontrolü
        if (empty($action_type)) {
            wp_send_json_error(array('message' => 'İşlem türü belirtilmedi.'));
        }
        
        switch($action_type) {
            case 'basic_info':
                $display_name = sanitize_text_field($_POST['display_name']);
                $description = sanitize_textarea_field($_POST['description']);
                
                if (empty($display_name)) {
                    wp_send_json_error(array('message' => 'Görünen ad boş olamaz.'));
                }
                
                $result = wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $display_name,
                    'description' => $description
                ));
                
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                
                wp_send_json_success(array('message' => 'Profil bilgileri başarıyla güncellendi.'));
                break;
                
            case 'account_info':
                $user_email = sanitize_email($_POST['user_email']);
                $user_url = esc_url_raw($_POST['user_url']);
                
                if (empty($user_email)) {
                    wp_send_json_error(array('message' => 'E-posta adresi boş olamaz.'));
                }
                
                if (!is_email($user_email)) {
                    wp_send_json_error(array('message' => 'Geçerli bir e-posta adresi girin.'));
                }
                
                // E-posta zaten kullanılıyor mu kontrol et
                $existing_user = get_user_by('email', $user_email);
                if ($existing_user && $existing_user->ID != $user_id) {
                    wp_send_json_error(array('message' => 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.'));
                }
                
                $update_data = array(
                    'ID' => $user_id,
                    'user_email' => $user_email
                );
                
                if (!empty($user_url)) {
                    $update_data['user_url'] = $user_url;
                }
                
                $result = wp_update_user($update_data);
                
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                
                wp_send_json_success(array('message' => 'Hesap bilgileri başarıyla güncellendi.'));
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    wp_send_json_error(array('message' => 'Tüm şifre alanlarını doldurunuz.'));
                }
                
                if ($new_password !== $confirm_password) {
                    wp_send_json_error(array('message' => 'Yeni şifreler eşleşmiyor.'));
                }
                
                if (strlen($new_password) < 6) {
                    wp_send_json_error(array('message' => 'Şifre en az 6 karakter olmalıdır.'));
                }
                
                $user = get_userdata($user_id);
                if (!$user) {
                    wp_send_json_error(array('message' => 'Kullanıcı bulunamadı.'));
                }
                
                if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
                    wp_send_json_error(array('message' => 'Mevcut şifre yanlış.'));
                }
                
                if (wp_check_password($new_password, $user->user_pass, $user_id)) {
                    wp_send_json_error(array('message' => 'Yeni şifre mevcut şifre ile aynı olamaz.'));
                }
                
                wp_set_password($new_password, $user_id);
                
                // Şifre değiştikten sonra kullanıcının oturumu devam etsin
                wp_clear_auth_cookie();
                wp_set_auth_cookie($user_id, true, is_ssl());
                
                wp_send_json_success(array('message' => 'Şifre başarıyla değiştirildi.'));
                break;

                case 'update_avatar':
    if (!isset($_POST['avatar_url'])) {
        wp_send_json_error(array('message' => 'Avatar URL gerekli.'));
    }
    
    $avatar_url = esc_url_raw($_POST['avatar_url']);
    
    // Avatar URL'ini user meta olarak kaydet
    update_user_meta($user_id, 'diyar_custom_avatar_url', $avatar_url);
    
    wp_send_json_success(array(
        'message' => 'Profil resmi başarıyla güncellendi.',
        'avatar_url' => $avatar_url
    ));
    break;
                
            default:
                wp_send_json_error(array('message' => 'Geçersiz işlem türü.'));
        }
    }

    /**
     * Kullanıcı yorumunu düzenle
     */
    public function edit_comment_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        $comment_id = intval($_POST['comment_id']);
        $content = trim($_POST['content']);
        $user_id = get_current_user_id();
        
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Yorum içeriği boş olamaz.'));
        }
        
        // Yorumu al ve yetki kontrolü yap
        $comment = get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => 'Yorum bulunamadı.'));
        }
        
        if ($comment->user_id != $user_id) {
            wp_send_json_error(array('message' => 'Bu yorumu düzenleme yetkiniz yok.'));
        }
        
        // Yorumu güncelle
        $result = wp_update_comment(array(
            'comment_ID' => $comment_id,
            'comment_content' => wp_kses_post($content)
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'content' => wp_kses_post($content),
            'message' => 'Yorum başarıyla güncellendi.'
        ));
    }
    
    /**
     * Kullanıcı yorumunu sil
     */
    public function delete_comment_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        $comment_id = intval($_POST['comment_id']);
        $user_id = get_current_user_id();
        
        // Yorumu al ve yetki kontrolü yap
        $comment = get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => 'Yorum bulunamadı.'));
        }
        
        if ($comment->user_id != $user_id && !current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => 'Bu yorumu silme yetkiniz yok.'));
        }
        
        // Yorumu sil (çöpe at)
        $result = wp_trash_comment($comment_id);
        
        if (!$result) {
            wp_send_json_error(array('message' => 'Yorum silinemedi.'));
        }
        
        wp_send_json_success(array(
            'message' => 'Yorum başarıyla silindi.'
        ));
    }

    /**
     * Yorum HTML'ini oluşturan yardımcı fonksiyon
     */
    private function generate_comment_html($comment) {
        if (is_numeric($comment)) {
            $comment = get_comment($comment);
        }
        
        if (!$comment) {
            return '';
        }
        
        // Global comment değişkenini ayarla
        $GLOBALS['comment'] = $comment;
        
        // HTML çıktısını yakala
        ob_start();
        if (function_exists('diyar_comment_format')) {
            diyar_comment_format($comment, array(
                'max_depth' => get_option('thread_comments_depth', 5)
            ), 1);
        } else {
            echo '<li class="diyar-comment-item">Yorum: ' . esc_html($comment->comment_content) . '</li>';
        }
        $html = ob_get_clean();
        
        return $html;
    }

    /**
     * Yorumları getir - geliştirilmiş sıralama ve sayfalama ile
     */
    public function get_comments_callback() {
        // Public endpoint olduğu için nonce kontrolü sadece giriş yapmış kullanıcılar için
        if (is_user_logged_in()) {
            $this->verify_nonce();
        }
        
        $post_id = intval($_POST['post_id']);
        $page = intval($_POST['page']);
        $sort = sanitize_key($_POST['sort']);
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $comments_per_page = get_option('comments_per_page', 10);

        // Temel sorgu parametreleri
        $args = array(
            'post_id' => $post_id,
            'status' => 'approve',
            'number' => $comments_per_page,
            'offset' => ($page - 1) * $comments_per_page,
            'parent' => $parent_id,
            'hierarchical' => false
        );
        
        // Sıralama seçenekleri
        switch($sort) {
            case 'oldest':
                $args['orderby'] = 'comment_date_gmt';
                $args['order'] = 'ASC';
                break;
                
            case 'best':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_likes';
                $args['order'] = 'DESC';
                break;
                
            case 'most_replied':
                // En çok yanıtlanan yorumlar
                $args['orderby'] = 'comment_date_gmt';
                $args['order'] = 'DESC';
                break;
                
            case 'newest':
            default:
                $args['orderby'] = 'comment_date_gmt';
                $args['order'] = 'DESC';
                break;
        }

        $comments = get_comments($args);
        
        // Toplam yorum sayısını al
        $total_comment_count = get_comments(array(
            'post_id' => $post_id,
            'status' => 'approve',
            'count' => true,
            'parent' => 0 // Sadana ana yorumlar
        ));
        
        if (empty($comments)) {
            wp_send_json_success(array(
                'html' => '',
                'has_more' => false,
                'total' => 0,
                'comment_count' => $total_comment_count
            ));
        }

        $html = '';
        foreach($comments as $comment) {
            $html .= $this->generate_comment_html($comment);
        }
        
        // Daha fazla yorum var mı kontrolü
        $next_page_args = $args;
        $next_page_args['offset'] = $page * $comments_per_page;
        $next_page_args['number'] = 1;
        $has_more = !empty(get_comments($next_page_args));
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $has_more,
            'total' => count($comments),
            'comment_count' => $total_comment_count
        ));
    }
    
    /**
     * Alt yorumları yükle
     */
    public function load_more_replies_callback() {
        $this->verify_nonce();
        
        $parent_id = intval($_POST['parent_id']);
        $page = intval($_POST['page']);
        $replies_per_page = 5; // Alt yorumlar için daha az

        $args = array(
            'parent' => $parent_id,
            'status' => 'approve',
            'number' => $replies_per_page,
            'offset' => ($page - 1) * $replies_per_page,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC'
        );

        $replies = get_comments($args);
        
        $html = '';
        foreach($replies as $reply) {
            $html .= $this->generate_comment_html($reply);
        }
        
        // Daha fazla alt yorum var mı?
        $next_args = $args;
        $next_args['offset'] = $page * $replies_per_page;
        $next_args['number'] = 1;
        $has_more = !empty(get_comments($next_args));
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $has_more
        ));
    }
    
    /**
     * İlk veri yükleme - tepkiler ve istatistikler
     */
    public function get_initial_data_callback() {
        // Public endpoint olduğu için nonce kontrolü sadece giriş yapmış kullanıcılar için
        if (is_user_logged_in()) {
            $this->verify_nonce();
        }
        
        global $wpdb;
        $post_id = intval($_POST['post_id']);
        $reactions_table = $wpdb->prefix . 'diyar_reactions';
        
        // Tepki sayılarını al
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT reaction, COUNT(id) as count FROM $reactions_table WHERE post_id = %d GROUP BY reaction",
            $post_id
        ), OBJECT_K);
        
        // Kullanıcının tepkisini al
        $user_reaction = null;
        if (is_user_logged_in()) {
            $user_reaction = $wpdb->get_var($wpdb->prepare(
                "SELECT reaction FROM $reactions_table WHERE post_id = %d AND user_id = %d",
                $post_id,
                get_current_user_id()
            ));
        }
        
        // Yorum istatistikleri
        $total_comments = wp_count_comments($post_id);
        
        wp_send_json_success(array(
            'counts' => $counts,
            'user_reaction' => $user_reaction,
            'comment_stats' => array(
                'approved' => $total_comments->approved,
                'moderated' => $total_comments->moderated,
                'total' => $total_comments->total_comments
            )
        ));
    }

    /**
     * Tepki işleme - geliştirilmiş
     */
    public function handle_reaction_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        global $wpdb;
        $post_id = intval($_POST['post_id']);
        $reaction = sanitize_key($_POST['reaction']);
        $user_id = get_current_user_id();
        $reactions_table = $wpdb->prefix . 'diyar_reactions';
        
        // Geçerli tepki tipleri
        $valid_reactions = array('guzel', 'sevdim', 'asik_oldum', 'sasirtici', 'gaza_geldim', 'uzucu');
        if (!in_array($reaction, $valid_reactions)) {
            wp_send_json_error(array('message' => 'Geçersiz tepki türü.'));
        }
        
        // Mevcut tepkiyi kontrol et
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, reaction FROM $reactions_table WHERE post_id = %d AND user_id = %d",
            $post_id,
            $user_id
        ));

        if ($existing) {
            if ($existing->reaction == $reaction) {
                // Aynı tepki - kaldır
                $result = $wpdb->delete($reactions_table, array('id' => $existing->id));
            } else {
                // Farklı tepki - güncelle
                $result = $wpdb->update(
                    $reactions_table,
                    array('reaction' => $reaction),
                    array('id' => $existing->id)
                );
            }
        } else {
            // Yeni tepki ekle
            $result = $wpdb->insert($reactions_table, array(
                'post_id' => $post_id,
                'user_id' => $user_id,
                'reaction' => $reaction
            ));
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Tepki kaydedilemedi.'));
        }

        // Güncel sayıları al ve döndür
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT reaction, COUNT(id) as count FROM $reactions_table WHERE post_id = %d GROUP BY reaction",
            $post_id
        ), OBJECT_K);
        
        wp_send_json_success(array('counts' => $counts));
    }

    /**
     * Beğeni/beğenmeme işleme - geliştirilmiş
     */
    public function handle_like_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        $comment_id = intval($_POST['comment_id']);
        $type = sanitize_key($_POST['type']);
        $user_id = get_current_user_id();
        
        if (!in_array($type, array('like', 'dislike'))) {
            wp_send_json_error(array('message' => 'Geçersiz işlem türü.'));
        }
        
        // Yorumun var olduğunu kontrol et
        $comment = get_comment($comment_id);
        if (!$comment || $comment->comment_approved !== '1') {
            wp_send_json_error(array('message' => 'Yorum bulunamadı.'));
        }
        
        // Kullanıcı kendi yorumunu beğenmeye çalışıyor mu?
        if ($comment->user_id == $user_id) {
            wp_send_json_error(array('message' => 'Kendi yorumunuzu beğenemezsiniz.'));
        }
        
        // Mevcut oyları al
        $likes = intval(get_comment_meta($comment_id, '_likes', true));
        $dislikes = intval(get_comment_meta($comment_id, '_dislikes', true));
        $user_vote = get_comment_meta($comment_id, '_user_vote_' . $user_id, true);

        if ($type == 'like') {
            if ($user_vote == 'liked') {
                // Beğeniyi kaldır
                update_comment_meta($comment_id, '_likes', max(0, $likes - 1));
                delete_comment_meta($comment_id, '_user_vote_' . $user_id);
                $new_user_vote = '';
            } else {
                // Beğeni ekle
                update_comment_meta($comment_id, '_likes', $likes + 1);
                if ($user_vote == 'disliked') {
                    update_comment_meta($comment_id, '_dislikes', max(0, $dislikes - 1));
                }
                update_comment_meta($comment_id, '_user_vote_' . $user_id, 'liked');
                $new_user_vote = 'liked';
            }
        } elseif ($type == 'dislike') {
            if ($user_vote == 'disliked') {
                // Beğenmemeyi kaldır
                update_comment_meta($comment_id, '_dislikes', max(0, $dislikes - 1));
                delete_comment_meta($comment_id, '_user_vote_' . $user_id);
                $new_user_vote = '';
            } else {
                // Beğenmeme ekle
                update_comment_meta($comment_id, '_dislikes', $dislikes + 1);
                if ($user_vote == 'liked') {
                    update_comment_meta($comment_id, '_likes', max(0, $likes - 1));
                }
                update_comment_meta($comment_id, '_user_vote_' . $user_id, 'disliked');
                $new_user_vote = 'disliked';
            }
        }
        
        // Güncel değerleri döndür
        $new_likes = intval(get_comment_meta($comment_id, '_likes', true));
        $new_dislikes = intval(get_comment_meta($comment_id, '_dislikes', true));
        
        wp_send_json_success(array(
            'likes' => $new_likes,
            'dislikes' => $new_dislikes,
            'user_vote' => $new_user_vote
        ));
    }

    /**
     * Şikayet etme - geliştirilmiş
     */
    public function flag_comment_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        global $wpdb;
        $comment_id = intval($_POST['comment_id']);
        $reporter_id = get_current_user_id();
        $reports_table = $wpdb->prefix . 'diyar_reports';
        
        // Yorumun var olduğunu kontrol et
        $comment = get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(array('message' => 'Yorum bulunamadı.'));
        }
        
        // Kendi yorumunu şikayet etmeyi engelle
        if ($comment->user_id == $reporter_id) {
            wp_send_json_error(array('message' => 'Kendi yorumunuzu şikayet edemezsiniz.'));
        }
        
        // Daha önce şikayet edilmiş mi kontrol et
        $existing_report = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reports_table WHERE comment_id = %d AND reporter_id = %d",
            $comment_id,
            $reporter_id
        ));
        
        if ($existing_report > 0) {
            wp_send_json_error(array('message' => 'Bu yorumu zaten şikayet ettiniz.'));
        }
        
        // Şikayeti kaydet
        $result = $wpdb->insert($reports_table, array(
            'comment_id' => $comment_id,
            'reporter_id' => $reporter_id,
            'report_time' => current_time('mysql', 1)
        ));
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Şikayet kaydedilemedi.'));
        }
        
        // Toplam şikayet sayısı
        $total_reports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reports_table WHERE comment_id = %d",
            $comment_id
        ));
        
        // Eğer şikayet sayısı belirli bir eşiği geçerse otomatik moderasyona al
        $options = get_option('diyar_comment_options', array());
        $report_threshold = isset($options['auto_moderate_reports']) ? $options['auto_moderate_reports'] : 3;
        
        if ($total_reports >= $report_threshold) {
            wp_set_comment_status($comment_id, 'hold');
        }
        
        wp_send_json_success(array(
            'message' => 'Şikayetiniz alındı. Teşekkür ederiz.',
            'total_reports' => $total_reports
        ));
    }
    
    /**
     * Yorum gönderme - geliştirilmiş güvenlik ve doğrulama
     */
    public function submit_comment_callback() {
        $this->verify_nonce();
        $this->require_login();
        
        // Kullanıcı engelli mi kontrol et
        $user_id = get_current_user_id();
        if (function_exists('diyar_is_user_banned') && diyar_is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Yorum gönderme yetkiniz bulunmuyor.'));
        }
        
        // DÜZELTME: Dinamik post ID sistemi
        $raw_post_id = isset($_POST['comment_post_ID']) ? intval($_POST['comment_post_ID']) : 0;
        $current_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : $raw_post_id;
        $current_url = isset($_POST['current_url']) ? esc_url_raw($_POST['current_url']) : '';

        $resolved = $current_url ? diyar_resolve_post_id_from_url($current_url) : array('id' => 0, 'is_virtual' => false);
        $final_post_id = !empty($resolved['id']) ? intval($resolved['id']) : 0;

        // Eğer dinamik sistem başarısız olursa, form verilerini kullan
        if (!$final_post_id) {
            $final_post_id = $current_post_id ?: $raw_post_id;
        }
        
        if (!$final_post_id) {
            wp_send_json_error(array('message' => 'Geçersiz sayfa ID\'si. Debug: ' .
                json_encode([
                    'raw_post_id' => $raw_post_id,
                    'current_post_id' => $current_post_id,
                    'current_url' => $current_url,
                    'final_post_id' => $final_post_id
                ])
            ));
        }
        
        // Form verilerini temizle ve doğrula
        $comment_data = array(
            'comment_post_ID' => $final_post_id,
            'comment_content' => trim($_POST['comment']),
            'comment_parent' => intval($_POST['comment_parent']),
            'user_id' => $user_id,
            'comment_approved' => 1  // OTOMATIK ONAY
        );
        
        // Post ID kontrolü - daha esnek yaklaşım
        $post = get_post($comment_data['comment_post_ID']);

        if ($post) {
            // Normal WordPress post - yorum durumunu kontrol et
            if (!comments_open($post->ID)) {
                wp_send_json_error(array('message' => 'Bu yazı için yorumlar kapalı.'));
            }
        } elseif (empty($resolved['is_virtual'])) {
            // Post bulunamadı ve URL sanal değil - güvenlik için reddet
            wp_send_json_error(array('message' => "Sayfa doğrulanamadı. Lütfen sayfayı yenileyin."));
        }
        
        // İçerik kontrolü
        if (empty($comment_data['comment_content'])) {
            wp_send_json_error(array('message' => 'Yorum içeriği boş olamaz.'));
        }
        
        if (strlen($comment_data['comment_content']) > 5000) {
            wp_send_json_error(array('message' => 'Yorum çok uzun. Maksimum 5000 karakter.'));
        }
        
        // Kullanıcı bilgilerini ekle
        $user = wp_get_current_user();
        $comment_data = array_merge($comment_data, array(
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_author_url' => $user->user_url,
            'comment_type' => '',
            'comment_meta' => array()
        ));
        
        // Parent comment kontrolü
        if ($comment_data['comment_parent'] > 0) {
            $parent_comment = get_comment($comment_data['comment_parent']);
            if (!$parent_comment || $parent_comment->comment_post_ID != $comment_data['comment_post_ID']) {
                wp_send_json_error(array('message' => 'Geçersiz üst yorum.'));
            }
        }
        
        // WordPress'in kendi filtresini devre dışı bırak
        add_filter('pre_comment_approved', function($approved, $commentdata) {
            return 1; // Her zaman onayla
        }, 10, 2);
        
        // Yorumu ekle
        $comment_id = wp_insert_comment($comment_data);
        
        if (is_wp_error($comment_id)) {
            wp_send_json_error(array('message' => $comment_id->get_error_message()));
        }

        if (!empty($current_url)) {
            $hash_position = strpos($current_url, '#');
            if ($hash_position !== false) {
                $current_url = substr($current_url, 0, $hash_position);
            }

            update_comment_meta($comment_id, 'diyar_comment_source_url', esc_url_raw($current_url));
        }

        // Yorumu al
        $comment = get_comment($comment_id);

        // HTML çıktısını oluştur
        $html = $this->generate_comment_html($comment);
        
        wp_send_json_success(array(
            'html' => $html,
            'comment_id' => $comment_id,
            'parent_id' => $comment->comment_parent,
            'approved' => true,
            'message' => 'Yorumunuz başarıyla gönderildi.'
        ));
    }
    
    /**
     * Admin yorum düzenleme
     */
    public function admin_edit_comment_callback() {
        // Admin yetkisi kontrolü
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => 'Yetkiniz bulunmuyor.'));
        }
        
        // Özel nonce kontrolü
        if (!check_ajax_referer('diyar_admin_edit_comment', '_ajax_nonce', false)) {
            $legacy_action = diyar_comment_get_legacy_prefix() . '_admin_edit_comment';
            if (!check_ajax_referer($legacy_action, '_ajax_nonce', false)) {
                wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız.'));
            }
        }
        
        $comment_id = intval($_POST['comment_id']);
        $content = trim($_POST['content']);
        
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Yorum içeriği boş olamaz.'));
        }
        
        // Yorumu güncelle
        $result = wp_update_comment(array(
            'comment_ID' => $comment_id,
            'comment_content' => wp_kses_post($content)
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Başarı
        wp_send_json_success(array(
            'content' => wp_trim_words(esc_html($content), 50),
            'message' => 'Yorum başarıyla güncellendi.'
        ));
    }

    /**
     * Profil sayfasında daha fazla yorum yükleme
     */
    public function load_more_profile_comments_callback() {
        $this->verify_nonce();
        
        $user_id = intval($_POST['user_id']);
        $page = intval($_POST['page']);
        $comments_per_page = 10;
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Geçersiz kullanıcı ID.'));
        }
        
        $args = array(
            'user_id' => $user_id,
            'number' => $comments_per_page,
            'offset' => ($page - 1) * $comments_per_page,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC'
        );
        
        $comments = get_comments($args);
        
        if (empty($comments)) {
            wp_send_json_success(array(
                'html' => '',
                'has_more' => false
            ));
        }
        
        ob_start();
        foreach ($comments as $comment) {
            $comment_link = diyar_get_comment_permalink($comment);
            $post_link = diyar_get_comment_source_url($comment);
            $location_label = diyar_get_comment_location_label($comment);

            if (empty($post_link) && $comment->comment_post_ID) {
                $post_link = get_permalink($comment->comment_post_ID);
            }

            if (empty($post_link)) {
                $post_link = $comment_link;
            }

            $post_title = $location_label;
            $likes = get_comment_meta($comment->comment_ID, '_likes', true) ?: 0;
            $comment_time = get_comment_time('U', true, $comment);
            ?>
            <div class="profile-comment-item">
                <div class="comment-header">
                    <div class="comment-post-info">
                        <a href="<?php echo esc_url($post_link ?: '#'); ?>" class="post-title" target="_blank">
                            <?php echo esc_html($post_title ?: __('Bilinmeyen Yazı', 'diyar-comment')); ?>
                        </a>
                    </div>
                    <div class="comment-meta">
                        <span class="comment-date">
                            <a href="<?php echo esc_url($comment_link ?: '#'); ?>" target="_blank">
                                <?php echo human_time_diff($comment_time, current_time('timestamp')); ?> önce
                            </a>
                        </span>
                        <?php if ($likes > 0) : ?>
                        <span class="comment-likes">
                            👍 <?php echo $likes; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="comment-excerpt">
                    <?php 
                    $excerpt = wp_trim_words(strip_tags($comment->comment_content), 25, '...');
                    echo esc_html($excerpt); 
                    ?>
                </div>
                <div class="comment-actions">
                    <a href="<?php echo esc_url($comment_link ?: '#'); ?>" target="_blank" class="view-comment">
                        <?php echo esc_html__('Yorumu Görüntüle', 'diyar-comment'); ?>
                    </a>
                    <a href="<?php echo esc_url($post_link ?: '#'); ?>" target="_blank" class="view-post">
                        <?php echo esc_html__('Yazıya Git', 'diyar-comment'); ?>
                    </a>
                </div>
            </div>
            <?php
        }
        $html = ob_get_clean();
        
        // Daha fazla yorum var mı kontrol et
        $next_args = $args;
        $next_args['offset'] = $page * $comments_per_page;
        $next_args['number'] = 1;
        $has_more = !empty(get_comments($next_args));
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $has_more
        ));
    }

    /**
     * Yanıtları yükle - Toggle sistemi için
     */
    public function load_replies_callback() {
        $this->verify_nonce();
        
        $parent_id = intval($_POST['parent_id']);
        
        if (!$parent_id) {
            wp_send_json_error(array('message' => 'Geçersiz parent ID.'));
        }
        
        $args = array(
            'parent' => $parent_id,
            'status' => 'approve',
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC'
        );
        
        $replies = get_comments($args);
        
        if (empty($replies)) {
            wp_send_json_success(array(
                'html' => '',
                'count' => 0
            ));
        }
        
        $html = '';
        foreach($replies as $reply) {
            $html .= $this->generate_comment_html($reply);
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => count($replies)
        ));
    }
}

// Sınıfı başlat
new Diyar_Comment_Ajax_Handlers();
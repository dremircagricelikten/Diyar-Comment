<?php
if (!defined('ABSPATH')) exit;

$xp_for_next_level = diyar_calculate_xp_for_level($user_data['level_info']->level + 1);
$xp_progress_percent = ($xp_for_next_level > 0) ? ($user_data['level_info']->xp / $xp_for_next_level) * 100 : 0;
if ($xp_progress_percent > 100) $xp_progress_percent = 100;

// Kullanıcı cezaları
$ban_status = get_user_meta($user_data['info']->ID, 'diyar_ban_status', true);
$timeout_until = get_user_meta($user_data['info']->ID, 'diyar_timeout_until', true);

// Son aktivite
$last_comment = get_comments(['user_id' => $user_data['info']->ID, 'number' => 1, 'status' => 'approve']);
if (!empty($last_comment)) {
    $last_activity = strtotime($last_comment[0]->comment_date);
} else {
    $last_activity = strtotime($user_data['info']->user_registered);
}

// Ensure we have valid timestamps
if (!$last_activity) {
    $last_activity = current_time('timestamp');
}
?>

<div class="diyar-user-profile">
    <div class="profile-header">
        <div class="profile-avatar">
            <?php echo diyar_get_avatar($user_data['info']->ID, 120); ?>
            <?php if ($user_data['is_own_profile']) : ?>
                <button class="change-avatar-btn" type="button" onclick="document.getElementById('avatar-upload').click();">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                </button>
                <input type="file" id="avatar-upload" accept="image/*" style="display: none;">
            <?php endif; ?>
        </div>
        
        <div class="profile-info">
            <div class="profile-name-section">
                <h2><?php echo esc_html($user_data['info']->display_name); ?></h2>
                <?php if ($user_data['is_own_profile']) : ?>
                    <button class="edit-profile-btn" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        <?php _e('Düzenle', 'diyar-comment'); ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ($ban_status === 'banned') : ?>
                <div class="user-status banned">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m4.9 4.9 14.2 14.2"></path>
                    </svg>
                    <?php _e('Bu kullanıcı kalıcı olarak engellenmiştir.', 'diyar-comment'); ?>
                </div>
            <?php elseif ($timeout_until && current_time('timestamp') < $timeout_until) : ?>
                <div class="user-status timeout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    <?php printf(__('Bu kullanıcı %s tarihine kadar askıya alınmıştır.', 'diyar-comment'),
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timeout_until)); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <strong><?php echo $user_data['total_comments']; ?></strong>
                    <span><?php _e('Yorum', 'diyar-comment'); ?></span>
                </div>
                <div class="stat-item">
                    <strong><?php echo $user_data['total_likes']; ?></strong>
                    <span><?php _e('Beğeni Aldı', 'diyar-comment'); ?></span>
                </div>
                <div class="stat-item">
                    <strong><?php echo count($user_data['badges']); ?></strong>
                    <span><?php _e('Rozet', 'diyar-comment'); ?></span>
                </div>
            </div>
            
            <div class="profile-level-info">
                <div class="level-badge-oval" style="background: <?php echo diyar_get_level_color($user_data['level_info']->level); ?>">
                    <?php printf(__('Seviye %d', 'diyar-comment'), $user_data['level_info']->level); ?>
                </div>
                <div class="xp-bar-container">
                    <div class="xp-bar">
                        <div class="xp-bar-progress" style="width: <?php echo $xp_progress_percent; ?>%;"></div>
                    </div>
                    <span class="xp-text"><?php echo $user_data['level_info']->xp; ?> / <?php echo $xp_for_next_level; ?> XP</span>
                </div>
            </div>
            
            <div class="profile-meta">
                <p><strong><?php _e('Katılım:', 'diyar-comment'); ?></strong> 
                    <?php echo date_i18n(get_option('date_format'), strtotime($user_data['info']->user_registered)); ?>
                </p>
                <p><strong><?php _e('Son Aktivite:', 'diyar-comment'); ?></strong> 
                    <?php 
                    if ($last_activity && is_numeric($last_activity)) {
                        echo human_time_diff($last_activity, current_time('timestamp')) . ' ' . __('önce', 'diyar-comment');
                    } else {
                        _e('Bilinmiyor', 'diyar-comment');
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <?php if (!empty($user_data['badges'])) : ?>
    <div class="profile-section">
        <h3><?php _e('Kazanılan Rozetler', 'diyar-comment'); ?></h3>
        <div class="profile-badges">
            <?php foreach ($user_data['badges'] as $badge) : ?>
            <div class="profile-badge-item" title="<?php echo esc_attr($badge->badge_name); ?>">
                <div class="badge-icon"><?php echo $badge->badge_svg; ?></div>
                <span class="badge-name"><?php echo esc_html($badge->badge_name); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-section">
        <h3><?php _e('Son Yorumları', 'diyar-comment'); ?></h3>
        <div class="profile-comments-list">
            <?php if (!empty($user_data['comments'])) : ?>
                <?php foreach ($user_data['comments'] as $comment) :
                    $comment_link = diyar_get_comment_permalink($comment);
                    $post_link = diyar_get_comment_source_url($comment);
                    $post_title = diyar_get_comment_location_label($comment);

                    if (empty($post_link) && $comment->comment_post_ID) {
                        $post_link = get_permalink($comment->comment_post_ID);
                    }

                    if (empty($post_link)) {
                        $post_link = $comment_link;
                    }
                    $likes = get_comment_meta($comment->comment_ID, '_likes', true) ?: 0;
                    
                    // FIX: Convert to integer timestamp properly
                    $comment_time = intval(get_comment_time('U', true, $comment));
                    if (!$comment_time) {
                        // Fallback if get_comment_time fails
                        $comment_time = strtotime($comment->comment_date);
                    }
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
                                    <?php echo human_time_diff($comment_time, current_time('timestamp')); ?>
                                    <?php _e('önce', 'diyar-comment'); ?>
                                </a>
                            </span>
                            <?php if ($likes > 0) : ?>
                            <span class="comment-likes">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                                <?php echo $likes; ?>
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
                            <?php _e('Yorumu Görüntüle', 'diyar-comment'); ?>
                        </a>
                        <a href="<?php echo esc_url($post_link ?: '#'); ?>" target="_blank" class="view-post">
                            <?php _e('Yazıya Git', 'diyar-comment'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($user_data['comments']) >= 10) : ?>
                    <div class="load-more-comments-wrapper">
                        <button type="button" id="load-more-profile-comments" data-user-id="<?php echo $user_data['info']->ID; ?>" data-page="2">
                            Daha Fazla Yorum Göster
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-content">
                    <div class="no-content-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <p><?php _e('Henüz hiç yorum yapmamış.', 'diyar-comment'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($user_data['is_own_profile']) : ?>
    <!-- Profil Düzenleme Modalı -->
    <div id="profile-edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Profili Düzenle', 'diyar-comment'); ?></h3>
                <button class="modal-close" type="button">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="basic"><?php _e('Temel Bilgiler', 'diyar-comment'); ?></button>
                    <button class="tab-btn" data-tab="account"><?php _e('Hesap', 'diyar-comment'); ?></button>
                    <button class="tab-btn" data-tab="password"><?php _e('Şifre', 'diyar-comment'); ?></button>
                </div>
                
                <div class="tab-content">
                    <!-- Temel Bilgiler -->
                    <div class="tab-pane active" id="basic-tab">
                        <form id="profile-basic-form">
                            <?php wp_nonce_field('diyar_profile_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="diyar_update_profile">
                            <input type="hidden" name="action_type" value="basic_info">
                            
                            <div class="form-group">
                                <label for="display_name"><?php _e('Görünen Ad', 'diyar-comment'); ?></label>
                                <input type="text" id="display_name" name="display_name" 
                                       value="<?php echo esc_attr($user_data['info']->display_name); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description"><?php _e('Hakkımda', 'diyar-comment'); ?></label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="Kendiniz hakkında birkaç kelime..."><?php echo esc_textarea($user_data['info']->description); ?></textarea>
                            </div>
                            
                            <button type="submit" class="diyar-submit"><?php _e('Bilgileri Güncelle', 'diyar-comment'); ?></button>
                        </form>
                    </div>
                    
                    <!-- Hesap Bilgileri -->
                    <div class="tab-pane" id="account-tab">
                        <form id="profile-account-form">
                            <?php wp_nonce_field('diyar_profile_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="diyar_update_profile">
                            <input type="hidden" name="action_type" value="account_info">
                            
                            <div class="form-group">
                                <label for="user_email"><?php _e('E-posta Adresi', 'diyar-comment'); ?></label>
                                <input type="email" id="user_email" name="user_email" 
                                       value="<?php echo esc_attr($user_data['info']->user_email); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="user_url"><?php _e('Web Sitesi', 'diyar-comment'); ?></label>
                                <input type="url" id="user_url" name="user_url" 
                                       value="<?php echo esc_attr($user_data['info']->user_url); ?>" 
                                       placeholder="https://example.com">
                            </div>
                            
                            <button type="submit" class="diyar-submit"><?php _e('Hesap Bilgilerini Güncelle', 'diyar-comment'); ?></button>
                        </form>
                    </div>
                    
                    <!-- Şifre Değiştirme -->
                    <div class="tab-pane" id="password-tab">
                        <form id="profile-password-form">
                            <?php wp_nonce_field('diyar_profile_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="diyar_update_profile">
                            <input type="hidden" name="action_type" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password"><?php _e('Mevcut Şifre', 'diyar-comment'); ?></label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password"><?php _e('Yeni Şifre', 'diyar-comment'); ?></label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password"><?php _e('Yeni Şifre Tekrar', 'diyar-comment'); ?></label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="diyar-submit"><?php _e('Şifreyi Güncelle', 'diyar-comment'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Çıkış Butonu -->
    <div class="profile-actions">
        <a href="<?php echo diyar_logout_url(); ?>" class="logout-btn">
            <?php _e('Çıkış Yap', 'diyar-comment'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.diyar-user-profile {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
    background: #1C1C1C;
    color: #ffffff;
}

.profile-header {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
    margin-bottom: 3rem;
    background: #2a2a2a;
    padding: 2rem;
    border-radius: 12px;
    border: 1px solid #404040;
}

.profile-avatar {
    position: relative;
}

.profile-avatar img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid #005B43;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.change-avatar-btn {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #005B43;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-info {
    flex: 1;
}

.profile-name-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.profile-name-section h2 {
    margin: 0;
    font-size: 2rem;
    color: #ffffff;
}

.edit-profile-btn {
    background: #005B43;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
}

.user-status {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.user-status.banned {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid #ef4444;
}

.user-status.timeout {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid #f59e0b;
}

.profile-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.stat-item {
    text-align: center;
}

.stat-item strong {
    display: block;
    font-size: 1.5rem;
    color: #005B43;
    font-weight: 700;
}

.stat-item span {
    color: #e2e8f0;
    font-size: 0.875rem;
}

.profile-level-info {
    margin-bottom: 1rem;
}

.level-badge-oval, .user-level-oval {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    color: white;
    font-weight: 700;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    border: 2px solid rgba(255,255,255,0.2);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.xp-bar-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.xp-bar {
    flex: 1;
    height: 10px;
    background: #404040;
    border-radius: 5px;
    overflow: hidden;
}

.xp-bar-progress {
    height: 100%;
    background: linear-gradient(90deg, #005B43, #00b894);
    transition: width 0.3s ease;
}

.xp-text {
    font-size: 0.875rem;
    color: #94a3b8;
    white-space: nowrap;
}

.profile-meta p {
    margin: 0.5rem 0;
    color: #e2e8f0;
    font-size: 0.875rem;
}

.profile-section {
    margin-bottom: 3rem;
}

.profile-section h3 {
    margin: 0 0 1.5rem;
    font-size: 1.5rem;
    color: #ffffff;
    border-bottom: 2px solid #005B43;
    padding-bottom: 0.5rem;
}

.profile-badges {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.profile-badge-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #2a2a2a;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #404040;
    transition: all 0.2s ease;
}

.profile-badge-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-color: #005B43;
}

.profile-badge-item .badge-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-badge-item .badge-icon svg {
    width: 28px;
    height: 28px;
}

.profile-badge-item .badge-name {
    font-weight: 600;
    color: #ffffff;
    font-size: 0.9rem;
}

.profile-comments-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.profile-comment-item {
    padding: 1.5rem;
    background: #2a2a2a;
    border-radius: 12px;
    border-left: 4px solid #005B43;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.profile-comment-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.comment-post-info {
    flex: 1;
}

.post-title {
    color: #005B43;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    line-height: 1.4;
    display: block;
}

.post-title:hover {
    text-decoration: underline;
    color: #007a5a;
}

.comment-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
    color: #94a3b8;
}

.comment-date a {
    color: #94a3b8;
    text-decoration: none;
}

.comment-date a:hover {
    color: #e2e8f0;
}

.comment-likes {
    color: #10b981;
    font-weight: 600;
}

.comment-excerpt {
    color: #e2e8f0;
    line-height: 1.6;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.comment-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.comment-actions a {
    color: #005B43;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.comment-actions a:hover {
    background: rgba(0, 91, 67, 0.1);
    border-color: #005B43;
    text-decoration: none;
}

.load-more-comments-wrapper {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #404040;
}

#load-more-profile-comments {
    background: #2a2a2a;
    color: #ffffff;
    border: 2px solid #404040;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
}

#load-more-profile-comments:hover {
    border-color: #005B43;
    background: rgba(0, 91, 67, 0.1);
}

.no-content {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
    background: #2a2a2a;
    border-radius: 12px;
    border: 2px dashed #404040;
}

.no-content-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal.show {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
}

.modal-content {
    background: #2a2a2a;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #404040;
}

.modal-header h3 {
    margin: 0;
    color: #ffffff;
}

.modal-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.5rem;
    cursor: pointer;
    transition: color 0.2s ease;
}

.modal-close:hover {
    color: #ffffff;
}

.modal-body {
    padding: 1.5rem;
}

.tab-navigation {
    display: flex;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #404040;
}

.tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    font-weight: 600;
}

.tab-btn.active {
    color: #005B43;
    border-bottom-color: #005B43;
}

.tab-btn:hover:not(.active) {
    color: #e2e8f0;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #e2e8f0;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #404040;
    border-radius: 6px;
    background: #404040;
    color: #ffffff;
    transition: border-color 0.2s ease;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #005B43;
    box-shadow: 0 0 0 3px rgba(0, 91, 67, 0.1);
}

.diyar-submit {
    background: linear-gradient(135deg, #005B43, #007a5a);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.diyar-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 91, 67, 0.3);
}

.diyar-submit:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.profile-actions {
    text-align: center;
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #404040;
}

.logout-btn {
    background: #ef4444;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
    text-decoration: none;
    color: white;
}

.avatar-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-loading .spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #ffffff;
    border-top: 2px solid #005B43;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
    }
    
    .xp-bar-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .profile-badges {
        grid-template-columns: 1fr;
    }
    
    .comment-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal işlemleri
    const editBtn = document.querySelector('.edit-profile-btn');
    const modal = document.getElementById('profile-edit-modal');
    const closeBtn = document.querySelector('.modal-close');
    
    if (editBtn && modal) {
        editBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Edit profile button clicked'); // Debug
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.visibility = 'visible';
            
            // İlk tab'ı aktif yap
            const firstTab = modal.querySelector('.tab-btn');
            const firstPane = modal.querySelector('.tab-pane');
            if (firstTab && firstPane) {
                firstTab.classList.add('active');
                firstPane.classList.add('active');
            }
        });
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                console.log('Modal close button clicked'); // Debug
                modal.classList.remove('show');
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.visibility = 'hidden';
                }, 300);
            });
        }
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                console.log('Modal overlay clicked'); // Debug
                modal.classList.remove('show');
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.visibility = 'hidden';
                }, 300);
            }
        });
    } else {
        console.log('Modal elements not found:', {editBtn, modal, closeBtn}); // Debug
    }
    
    // Tab navigation
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
    
    // Form submissions
    const basicForm = document.getElementById('profile-basic-form');
    const accountForm = document.getElementById('profile-account-form');
    const passwordForm = document.getElementById('profile-password-form');
    
    if (basicForm) {
        basicForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitProfileForm(this, 'Bilgiler güncelleniyor...');
        });
    }

    if (accountForm) {
        accountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitProfileForm(this, 'Hesap bilgileri güncelleniyor...');
        });
    }
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = this.querySelector('[name="new_password"]').value;
            const confirmPassword = this.querySelector('[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('Şifreler eşleşmiyor.', 'error');
                return;
            }
            
            submitProfileForm(this, 'Şifre güncelleniyor...');
        });
    }
    
    function submitProfileForm(form, loadingText) {
        const submitBtn = form.querySelector('.diyar-submit');
        const originalText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = loadingText;
        
        const formData = new FormData(form);
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.data.message, 'success');
                
                // Eğer şifre değiştirildiyse formu temizle
                if (form.id === 'profile-password-form') {
                    form.reset();
                }
                
                // Modal'ı kapat
                setTimeout(() => {
                    modal.classList.remove('show');
                    setTimeout(() => modal.style.display = 'none', 300);
                    location.reload(); // Sayfayı yenile
                }, 1500);
            } else {
                showNotification(data.data.message || 'Bir hata oluştu.', 'error');
            }
        })
        .catch(error => {
            showNotification('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    }
    
    function showNotification(message, type) {
        // Mevcut bildirimleri kaldır
        document.querySelectorAll('.diyar-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `diyar-notification ${type}`;
        notification.innerHTML = `
            <span class="notification-text">${message}</span>
            <button class="notification-close">&times;</button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#005B43'};
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            font-size: 14px;
            max-width: 300px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
        
        setTimeout(() => notification.remove(), 5000);
    }
    
    // Daha fazla yorum yükleme
    const loadMoreBtn = document.getElementById('load-more-profile-comments');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const page = parseInt(this.dataset.page);
            
            this.textContent = 'Yükleniyor...';
            this.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'diyar_load_more_profile_comments');
            formData.append('nonce', '<?php echo wp_create_nonce('diyar-comment-nonce'); ?>');
            formData.append('user_id', userId);
            formData.append('page', page);
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.html) {
                    const commentsContainer = document.querySelector('.profile-comments-list');
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.data.html;
                    
                    while (tempDiv.firstChild) {
                        commentsContainer.insertBefore(tempDiv.firstChild, this.parentElement);
                    }
                    
                    if (data.data.has_more) {
                        this.dataset.page = (page + 1).toString();
                        this.textContent = 'Daha Fazla Yorum Göster';
                        this.disabled = false;
                    } else {
                        this.parentElement.remove();
                    }
                } else {
                    this.parentElement.remove();
                }
            })
            .catch(() => {
                this.textContent = 'Hata! Tekrar deneyin';
                this.disabled = false;
            });
        });
    }
// Avatar upload
const avatarUpload = document.getElementById('avatar-upload');
if (avatarUpload) {
    avatarUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validasyonlar
        if (!file.type.startsWith('image/')) {
            showNotification('Sadece görsel dosyaları yükleyebilirsiniz.', 'error');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            showNotification('Görsel dosyası 5MB\'dan küçük olmalıdır.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'diyar_upload_image');
        formData.append('nonce', '<?php echo wp_create_nonce('diyar-comment-nonce'); ?>');
        formData.append('image', file);
        formData.append('upload_type', 'avatar');
        
        // Loading göster
        const avatarContainer = document.querySelector('.profile-avatar');
        const loadingSpinner = document.createElement('div');
        loadingSpinner.className = 'avatar-loading';
        loadingSpinner.innerHTML = '<div class="spinner"></div>';
        avatarContainer.appendChild(loadingSpinner);
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Avatar URL'ini direkt user meta olarak kaydet
                const updateFormData = new FormData();
                updateFormData.append('action', 'diyar_update_profile');
                updateFormData.append('nonce', '<?php echo wp_create_nonce('diyar-comment-nonce'); ?>');
                updateFormData.append('action_type', 'update_avatar');
                updateFormData.append('avatar_url', data.data.url);
                
                return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: updateFormData
                });
            } else {
                throw new Error(data.data?.message || 'Avatar yüklenemedi.');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Avatar resmini güncelle
                const avatarImg = document.querySelector('.profile-avatar img');
                if (avatarImg) {
                    avatarImg.src = data.data.avatar_url + '?t=' + Date.now();
                }
                showNotification('Profil resmi başarıyla güncellendi!', 'success');
            } else {
                showNotification(data.data?.message || 'Profil resmi kaydedilemedi.', 'error');
            }
        })
        .catch(error => {
            console.error('Avatar upload error:', error);
            showNotification(error.message || 'Profil resmi yüklenirken hata oluştu.', 'error');
        })
        .finally(() => {
            if (loadingSpinner && loadingSpinner.parentNode) {
                loadingSpinner.remove();
            }
        });
    });
}

});
</script>
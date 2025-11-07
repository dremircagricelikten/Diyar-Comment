<?php
if (post_password_required()) return;

$options = get_option('diyar_comment_options', array());
// DÜZELTME: Dinamik post ID kullan
$post_id = diyar_get_dynamic_post_id();
$color_scheme = isset($options['color_scheme']) && $options['color_scheme'] === 'dark' ? 'dark' : 'light';
$color_class = 'diyar-color-' . $color_scheme;
$comment_count = get_comments_number($post_id);
?>
<div id="diyar-comments" class="comments-area <?php echo esc_attr($color_class); ?>">
    <?php if (isset($options['enable_reactions']) && $options['enable_reactions']) : ?>
    <div class="diyar-reactions-section">
        <div class="reactions-header">
            <h3 class="section-title">Bu içeriğe tepki ver</h3>
            <div class="total-reactions">
                <span id="total-reaction-count">0</span> tepki
            </div>
        </div>
        <div class="reactions">
            <button class="reaction" data-reaction="guzel" title="Mükemmel">
                <span class="emoji">😍</span>
                <span>Mükemmel</span>
                <span class="count">0</span>
            </button>
            <button class="reaction" data-reaction="sevdim" title="Sevdim">
                <span class="emoji">❤️</span>
                <span>Sevdim</span>
                <span class="count">0</span>
            </button>
            <button class="reaction" data-reaction="asik_oldum" title="Harika">
                <span class="emoji">🔥</span>
                <span>Harika</span>
                <span class="count">0</span>
            </button>
            <button class="reaction" data-reaction="sasirtici" title="Şaşırtıcı">
                <span class="emoji">😱</span>
                <span>Şaşırtıcı</span>
                <span class="count">0</span>
            </button>
            <button class="reaction" data-reaction="gaza_geldim" title="Heyecanlı">
                <span class="emoji">🎉</span>
                <span>Heyecanlı</span>
                <span class="count">0</span>
            </button>
            <button class="reaction" data-reaction="uzucu" title="Üzücü">
                <span class="emoji">😢</span>
                <span>Üzücü</span>
                <span class="count">0</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="diyar-comments-main">
        <div class="comments-header">
            <h3 class="comments-title">
                <span class="comment-count"><?php echo $comment_count; ?></span> 
                <?php printf(_n('Yorum', 'Yorum', $comment_count), $comment_count); ?>
            </h3>
            <?php if (isset($options['enable_sorting']) && $options['enable_sorting']) : ?>
            <div class="comment-sorting">
                <label>Sırala:</label>
                <div class="sort-buttons">
                    <button class="sort-button active" data-sort="newest" type="button">
                        En Yeniler
                    </button>
                    <button class="sort-button" data-sort="best" type="button">
                        En Beğenilenler
                    </button>
                    <button class="sort-button" data-sort="oldest" type="button">
                        En Eskiler
                    </button>
                    <button class="sort-button" data-sort="most_replied" type="button">
                        En Çok Yanıtlanan
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (comments_open()) : ?>
            <?php if (is_user_logged_in()) : ?>
                <div id="diyar-comment-form-wrapper">
                    <div class="comment-user-info">
                        <?php echo get_avatar(get_current_user_id(), 50); ?>
                    </div>
                    <form id="commentform" class="comment-form" novalidate>
                        <div id="diyar-editor-container">
                            <div id="diyar-editor-toolbar">
                                <button type="button" class="diyar-toolbar-button" data-tag="b" title="Kalın">
                                    <strong>B</strong>
                                </button>
                                <button type="button" class="diyar-toolbar-button" data-tag="i" title="İtalik">
                                    <em>I</em>
                                </button>
                                <button type="button" class="diyar-toolbar-button" data-tag="spoiler" title="Spoiler">
                                    [S]
                                </button>
                                <button type="button" class="diyar-toolbar-button gif-search-btn" title="GIF Ekle">
                                    🎬
                                </button>
                            </div>
                            <textarea 
                                id="comment" 
                                name="comment" 
                                placeholder="Yorumunuzu yazın..." 
                                required
                                maxlength="5000"
                            ></textarea>
                        </div>
                        <div class="form-submit">
                            <div class="diyar-form-hidden-fields" style="position:absolute;left:-9999px;">
                                <input type="text" name="diyar_honeypot" value="" tabindex="-1" autocomplete="off">
                            </div>
                            <input type="hidden" name="comment_post_ID" value="<?php echo $post_id; ?>">
                            <input type="hidden" name="comment_parent" value="0">
                            
                            <div class="form-submit-info">
                                <small class="char-counter">
                                    <span id="char-count">0</span>/5000 karakter
                                </small>
                            </div>
                            
                            <button type="submit" id="submit" class="submit diyar-submit">
                                Yorum Gönder
                            </button>
                        </div>
                    </form>
                </div>
            <?php else : ?>
                <div class="auth-required-message">
                    <h3>Toplulukla Etkileşime Geç!</h3>
                    <p>Yorumlar yapmak, tepki vermek ve diğer kullanıcılarla etkileşime geçmek için hesabınıza giriş yapın.</p>
                    <div class="auth-buttons">
                        <?php 
                        $auth_page_found = false;
                        
                        // Auth sayfasını bul
                        $auth_pages = get_posts([
                            'post_type' => 'page',
                            'meta_query' => [
                                [
                                    'key' => '_wp_page_template',
                                    'value' => 'page-auth.php',
                                    'compare' => 'LIKE'
                                ]
                            ],
                            'numberposts' => 1
                        ]);
                        
                        if (empty($auth_pages)) {
                            // Shortcode'a sahip sayfaları ara
                            $all_pages = get_posts([
                                'post_type' => 'page',
                                'numberposts' => -1
                            ]);
                            
                            foreach($all_pages as $page) {
                                if (diyar_comment_content_has_shortcode($page->post_content, 'diyar_auth') ||
                                    diyar_comment_content_has_shortcode($page->post_content, 'diyar_login')) {
                                    $auth_pages = [$page];
                                    break;
                                }
                            }
                        }
                        
                        if (!empty($auth_pages)) {
                            $auth_page_found = true;
                            $auth_url = get_permalink($auth_pages[0]->ID);
                        }
                        
                        if (!$auth_page_found) {
                            $login_page = isset($options['login_page_id']) ? $options['login_page_id'] : 0;
                            if ($login_page && get_post($login_page)) {
                                $auth_url = get_permalink($login_page);
                                $auth_page_found = true;
                            }
                        }
                        
                        if ($auth_page_found) : ?>
                            <a href="<?php echo esc_url($auth_url); ?>" class="auth-button primary">
                                Giriş Yap / Kayıt Ol
                            </a>
                        <?php else : ?>
                            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="auth-button primary">
                                Giriş Yap
                            </a>
                            <?php if (get_option('users_can_register')) : ?>
                            <a href="<?php echo wp_registration_url(); ?>" class="auth-button">
                                Kayıt Ol
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="comments-closed">
                <p>Bu yazı için yorumlar kapalı.</p>
            </div>
        <?php endif; ?>

        <div id="comment-list-wrapper">
            <ol class="comment-list" role="list">
                <!-- Yorumlar AJAX ile yüklenecek -->
            </ol>
            
            <div id="comment-loader" style="display: none;">
                <p>Yorumlar yükleniyor...</p>
            </div>
            
            <button id="load-more-comments" style="display: none;" type="button">
                Daha Fazla Yorum Yükle
            </button>
            
            <?php if ($comment_count == 0) : ?>
                <div class="no-comments">
                    <p>İlk yorumu sen yap!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bildirim alanı -->
    <div id="diyar-notification" class="diyar-notification" style="display: none;">
        <span class="notification-text"></span>
        <button class="notification-close" type="button">&times;</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bildirim sistemi
    window.showNotification = function(message, type) {
        const notification = document.getElementById('diyar-notification');
        if (!notification) return;
        
        const textElement = notification.querySelector('.notification-text');
        notification.className = 'diyar-notification ' + (type || 'info');
        textElement.textContent = message;
        notification.style.display = 'flex';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    };
    
    const closeBtn = document.querySelector('.notification-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            document.getElementById('diyar-notification').style.display = 'none';
        });
    }
    
    // Karakter sayacı
    const textarea = document.getElementById('comment');
    const charCount = document.getElementById('char-count');
    
    if (textarea && charCount) {
        textarea.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            const counterContainer = charCount.parentElement;
            counterContainer.classList.remove('warning', 'danger');
            
            if (count > 4500) {
                counterContainer.classList.add('danger');
            } else if (count > 4000) {
                counterContainer.classList.add('warning');
            }
        });
        
        // Otomatik yeniden boyutlandırma
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    }
});
</script>
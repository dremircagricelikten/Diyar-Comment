jQuery(function ($) {
    if (!$('#diyar-comments').length) return;

    const DiyarComments = {
        state: { 
            currentPage: 1, 
            currentSort: 'newest',
            isLoading: false, 
            allCommentsLoaded: false,
            replyForms: new Map(),
            uploadedImages: []
        },
        
        elements: {
            container: $('#diyar-comments'),
            commentList: $('.comment-list'),
            loadMoreBtn: $('#load-more-comments'),
            loader: $('#comment-loader'),
            reactions: $('.reactions'),
            commentForm: $('#commentform'),
            commentTextarea: $('#comment'),
            submitBtn: $('#submit'),
            toolbar: $('#diyar-editor-toolbar'),
            commentCountSpan: $('.comment-count'),
            totalReactionCount: $('#total-reaction-count'),
            charCounter: $('#char-count')
        },

        init: function () {
            this.loadInitialData();
            this.setupEventListeners();
            this.initDropdownFilter();
            this.setupGifSearch();
            this.setupCharCounter();
            
            // Sayfa yüklendiğinde mevcut yorumlardaki GIF'leri render et
            setTimeout(() => {
                this.renderGifsInComments();
            }, 500);
        },

        showNotification: function(message, type = 'info') {
            if (window.showNotification) {
                window.showNotification(message, type);
            } else {
                // Fallback notification
                const notification = $(`
                    <div class="diyar-notification ${type}">
                        <span class="notification-text">${message}</span>
                        <button class="notification-close">&times;</button>
                    </div>
                `);
                
                $('body').append(notification);
                
                notification.find('.notification-close').on('click', function() {
                    notification.remove();
                });
                
                setTimeout(() => notification.remove(), 5000);
            }
        },

        setupCharCounter: function() {
            if (this.elements.charCounter.length) {
                this.elements.commentTextarea.on('input', () => {
                    const count = this.elements.commentTextarea.val().length;
                    this.elements.charCounter.text(count);
                    
                    // Renk değişimi
                    const counterContainer = this.elements.charCounter.parent();
                    counterContainer.removeClass('warning danger');
                    
                    if (count > 4500) {
                        counterContainer.addClass('danger');
                    } else if (count > 4000) {
                        counterContainer.addClass('warning');
                    }
                });
            }
        },

        setupGifSearch: function() {
            // GIF arama modalı HTML'ini oluştur
            const gifModalHtml = `
                <div id="gif-search-modal" class="gif-modal" style="display: none;">
                    <div class="gif-modal-content">
                        <div class="gif-modal-header">
                            <h3>GIF Ara</h3>
                            <button class="gif-modal-close" type="button">&times;</button>
                        </div>
                        <div class="gif-modal-body">
                            <input type="text" id="gif-search-input" placeholder="GIF ara..." />
                            <div class="gif-search-results" id="gif-search-results">
                                <div class="gif-placeholder">🎬 Arama yapın ve GIF'leri keşfedin</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Modal'ı body'ye ekle
            if (!$('#gif-search-modal').length) {
                $('body').append(gifModalHtml);
            }
        },

        searchGifs: function(query) {
            if (!query.trim()) return;
            
            const apiKey = 'GlVGYHkr3WSBnllca54iNt0yFbjz7L65'; // Giphy public API key
            const url = `https://api.giphy.com/v1/gifs/search?api_key=${apiKey}&q=${encodeURIComponent(query)}&limit=20&rating=g`;
            
            $('#gif-search-results').html('<div class="gif-loading">🔍 GIF\'ler aranıyor...</div>');
            
            $.ajax({
                url: url,
                method: 'GET',
                success: (response) => {
                    this.displayGifs(response.data);
                },
                error: () => {
                    $('#gif-search-results').html('<div class="gif-error">❌ GIF\'ler yüklenirken hata oluştu</div>');
                }
            });
        },

        displayGifs: function(gifs) {
            const resultsContainer = $('#gif-search-results');
            
            if (gifs.length === 0) {
                resultsContainer.html('<div class="gif-no-results">😔 Hiç GIF bulunamadı</div>');
                return;
            }
            
            let html = '<div class="gif-grid">';
            gifs.forEach(gif => {
                const previewUrl = gif.images.fixed_height_small.webp;
                const fullUrl = gif.images.original.webp;
                html += `
                    <div class="gif-item" data-url="${fullUrl}" data-preview="${previewUrl}">
                        <img src="${previewUrl}" alt="${gif.title}" loading="lazy">
                    </div>
                `;
            });
            html += '</div>';
            
            resultsContainer.html(html);
        },

        insertGif: function(gifUrl) {
            const currentText = this.elements.commentTextarea.val();
            const gifMarkdown = `\n![GIF](${gifUrl})\n`;
            this.elements.commentTextarea.val(currentText + gifMarkdown);
            this.elements.commentTextarea.trigger('input');
            
            // Modal'ı kapat
            $('#gif-search-modal').hide();
            this.showNotification('GIF başarıyla eklendi!', 'success');
        },

        // GIF'ler artık server-side render ediliyor, sadece yeni eklenen yorumlar için gerekli
        renderGifsInComments: function() {
            $('.comment-text').each(function() {
                let content = $(this).html();
                
                // Sadece henüz render edilmemiş GIF markdown'larını çevir
                if (content.includes('![GIF](')) {
                    content = content.replace(/!\[GIF\]\((https?:\/\/[^\)]+)\)/g,
                        '<div class="gif-container"><img src="$1" alt="GIF" loading="lazy" class="comment-gif"></div>');
                    
                    $(this).html(content);
                }
            });
        },

        loadInitialData: function () {
            this.getComments(true);
            this.getReactions();
        },

        initDropdownFilter: function() {
            const $sortButtons = $('.sort-buttons');
            if ($sortButtons.length) {
                const currentSort = $('.sort-button.active').data('sort') || 'newest';
                const currentText = $('.sort-button.active').text() || 'En Yeniler';
                
                const dropdownHTML = `
                    <div class="sort-dropdown">
                        <button type="button" class="sort-dropdown-btn" data-current="${currentSort}">
                            ${currentText}
                        </button>
                        <div class="sort-dropdown-menu">
                            <div class="sort-option" data-sort="newest">En Yeniler</div>
                            <div class="sort-option" data-sort="best">En Beğenilenler</div>
                            <div class="sort-option" data-sort="oldest">En Eskiler</div>
                            <div class="sort-option" data-sort="most_replied">En Çok Yanıtlanan</div>
                        </div>
                    </div>
                `;
                
                $sortButtons.replaceWith(dropdownHTML);
                $(`.sort-option[data-sort="${currentSort}"]`).addClass('active');
            }
        },
        
        getComments: function (replace = false) {
            if (this.state.isLoading || (this.state.allCommentsLoaded && !replace)) return;
            
            this.state.isLoading = true;
            this.elements.loader.show();
            this.elements.loadMoreBtn.prop('disabled', true);
            
            if (replace) {
                this.elements.commentList.empty();
                this.state.allCommentsLoaded = false;
                this.state.currentPage = 1;
                $('.no-comments').hide();
            }

            $.post(diyar_comment_ajax.ajax_url, {
                action: 'diyar_get_comments',
                nonce: diyar_comment_ajax.nonce,
                post_id: diyar_comment_ajax.post_id,
                current_url: window.location.href,
                page: this.state.currentPage,
                sort: this.state.currentSort
            })
            .done(response => {
                if (response.success && response.data.html.trim() !== '') {
                    const $newComments = $(response.data.html);
                    this.elements.commentList.append($newComments);
                    this.state.currentPage++;
                    
                    $newComments.hide().fadeIn(400);
                    
                    // GIF'leri render et
                    setTimeout(() => {
                        this.renderGifsInComments();
                    }, 100);
                    
                    // Yorum sayısını güncelle
                    if (response.data.comment_count) {
                        this.elements.commentCountSpan.text(response.data.comment_count);
                    }
                    
                    if (!response.data.has_more) {
                        this.state.allCommentsLoaded = true;
                        this.elements.loadMoreBtn.hide();
                    } else {
                        this.elements.loadMoreBtn.show();
                    }
                } else {
                    this.state.allCommentsLoaded = true;
                    this.elements.loadMoreBtn.hide();
                    if (this.state.currentPage === 1) {
                        $('.no-comments').show();
                        // Yorum sayısını 0 yap
                        this.elements.commentCountSpan.text(0);
                    }
                }
            })
            .fail(() => {
                this.showNotification(diyar_comment_ajax.text.error, 'error');
            })
            .always(() => {
                this.state.isLoading = false;
                this.elements.loader.hide();
                this.elements.loadMoreBtn.prop('disabled', false);
            });
        },

        getReactions: function () {
            $.post(diyar_comment_ajax.ajax_url, {
                action: 'diyar_get_initial_data',
                nonce: diyar_comment_ajax.nonce,
                post_id: diyar_comment_ajax.post_id,
                current_url: window.location.href
            })
            .done(response => {
                if (response.success) {
                    this.updateReactionUI(response.data);
                    // Yorum sayısını güncelle
                    if (response.data.comment_count) {
                        this.elements.commentCountSpan.text(response.data.comment_count);
                    }
                }
            });
        },

        updateReactionUI: function(data) {
            let total = 0;
            
            this.elements.reactions.find('.reaction').each(function() {
                const $this = $(this);
                const reaction = $this.data('reaction');
                const count = data.counts && data.counts[reaction] ? parseInt(data.counts[reaction].count, 10) : 0;
                $this.find('.count').text(count);
                total += count;
            });

            this.elements.totalReactionCount.text(total);
            
            this.elements.reactions.find('.reaction').removeClass('selected');
            if (data.user_reaction) {
                this.elements.reactions.find(`.reaction[data-reaction="${data.user_reaction}"]`).addClass('selected');
            }
        },

        createInlineReplyForm: function(commentId) {
            if (this.state.replyForms.has(commentId)) {
                return this.state.replyForms.get(commentId);
            }

            const userAvatar = this.getUserAvatar();
            const $form = $(`
                <div class="inline-reply-container" data-comment-id="${commentId}">
                    <div class="comment-user-info">
                        ${userAvatar ? `<img src="${userAvatar}" alt="" class="avatar">` : ''}
                    </div>
                    <form class="inline-reply-form">
                        <div class="inline-reply-editor">
                            <div class="inline-reply-toolbar">
                                <button type="button" class="diyar-toolbar-button" data-tag="b"><b>B</b></button>
                                <button type="button" class="diyar-toolbar-button" data-tag="i"><i>I</i></button>
                                <button type="button" class="diyar-toolbar-button" data-tag="spoiler">[S]</button>
                                <button type="button" class="diyar-toolbar-button image-upload" title="Görsel Yükle">
                                    🖼️
                                    <input type="file" accept="image/*" multiple style="position: absolute; left: -9999px; opacity: 0;">
                                </button>
                            </div>
                            <textarea name="comment" placeholder="${diyar_comment_ajax.text.reply_placeholder || 'Yanıtınızı yazın...'}" required></textarea>
                        </div>
                        <div class="inline-reply-actions">
                            <input type="hidden" name="comment_post_ID" value="${diyar_comment_ajax.post_id}">
                            <input type="hidden" name="post_id" value="${diyar_comment_ajax.post_id}">
                            <input type="hidden" name="comment_parent" value="${commentId}">
                            <div></div>
                            <div>
                                <button type="button" class="inline-reply-cancel">${diyar_comment_ajax.text.reply_cancel || 'İptal'}</button>
                                <button type="submit" class="inline-reply-submit">${diyar_comment_ajax.text.reply_send || 'Yanıtla'}</button>
                            </div>
                        </div>
                    </form>
                </div>
            `);

            this.state.replyForms.set(commentId, $form);
            return $form;
        },

        getUserAvatar: function() {
            const $existingAvatar = this.elements.commentForm.find('.comment-user-info img');
            return $existingAvatar.length ? $existingAvatar.attr('src') : '';
        },

        wrapText: function(textarea, tag) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selectedText = text.substring(start, end);
            let replacement;

            if (tag === 'spoiler') {
                replacement = `[spoiler]${selectedText}[/spoiler]`;
            } else {
                replacement = `<${tag}>${selectedText}</${tag}>`;
            }

            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            textarea.focus();
            textarea.selectionStart = start + replacement.length - selectedText.length;
            textarea.selectionEnd = textarea.selectionStart;
        },

        setupEventListeners: function () {
            // GIF arama butonu
            this.elements.toolbar.on('click', '.gif-search-btn', (e) => {
                e.preventDefault();
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }
                $('#gif-search-modal').show();
                $('#gif-search-input').focus();
            });

            // GIF arama modal event'leri
            $(document).on('click', '.gif-modal-close', () => {
                $('#gif-search-modal').hide();
            });

            $(document).on('click', '#gif-search-modal', (e) => {
                if (e.target === document.getElementById('gif-search-modal')) {
                    $('#gif-search-modal').hide();
                }
            });

            $(document).on('input', '#gif-search-input', (e) => {
                const query = e.target.value.trim();
                if (query.length > 2) {
                    clearTimeout(this.gifSearchTimeout);
                    this.gifSearchTimeout = setTimeout(() => {
                        this.searchGifs(query);
                    }, 500);
                }
            });

            $(document).on('click', '.gif-item', (e) => {
                const gifUrl = $(e.currentTarget).data('url');
                this.insertGif(gifUrl);
            });

            // Modern Spoiler - Click to Reveal (Siyah Kutu)
            $('body').on('click', '.spoiler-content:not(.revealed)', function() {
                $(this).addClass('revealed');
            });

            // Yanıtları Göster/Gizle Toggle - DÜZELTİLMİŞ
            $(document).on('click', '.replies-toggle-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const $container = $btn.closest('.diyar-comment-item');
                const parentId = $btn.data('comment-id');
                const repliesCount = $btn.data('replies-count');
                const $text = $btn.find('.toggle-text');
                
                let $repliesContainer = $container.find('> .replies-container');
                
                const isExpanded = $btn.hasClass('expanded');
                
                if (isExpanded) {
                    // Yanıtları gizle
                    $repliesContainer.slideUp(300);
                    $btn.removeClass('expanded');
                    $text.text(repliesCount + ' yanıtı göster');
                } else {
                    // Yanıtları göster
                    if ($repliesContainer.children().length === 0) {
                        // İlk kez yükleniyor - AJAX ile yanıtları getir
                        $.post(diyar_comment_ajax.ajax_url, {
                            action: 'diyar_load_replies',
                            nonce: diyar_comment_ajax.nonce,
                            parent_id: parentId
                        })
                        .done(response => {
                            if (response.success && response.data.html) {
                                $repliesContainer.html(response.data.html);
                                $repliesContainer.slideDown(300);
                                $btn.addClass('expanded');
                                $text.text('Yanıtları gizle');
                                
                                // Yeni yüklenen yanıtlardaki GIF'leri render et
                                setTimeout(() => {
                                    DiyarComments.renderGifsInComments();
                                }, 100);
                            }
                        })
                        .fail(() => {
                            DiyarComments.showNotification('Yanıtlar yüklenirken hata oluştu.', 'error');
                        });
                    } else {
                        // Zaten yüklenmiş - sadece göster
                        $repliesContainer.slideDown(300);
                        $btn.addClass('expanded');
                        $text.text('Yanıtları gizle');
                    }
                }
            });

            // Dropdown toggle
            $(document).on('click', '.sort-dropdown-btn', e => {
                e.stopPropagation();
                $('.sort-dropdown').toggleClass('open');
            });

            // Option seçimi
            $(document).on('click', '.sort-option', e => {
                e.stopPropagation();
                
                const $option = $(e.currentTarget);
                const sortValue = $option.data('sort');
                const sortText = $option.text();
                
                $('.sort-option').removeClass('active');
                $option.addClass('active');
                
                $('.sort-dropdown-btn').text(sortText).attr('data-current', sortValue);
                $('.sort-dropdown').removeClass('open');
                
                this.state.currentSort = sortValue;
                this.getComments(true);
            });

            // Dışarı tıklamada dropdown'ı kapat
            $(document).on('click', e => {
                if (!$(e.target).closest('.sort-dropdown').length) {
                    $('.sort-dropdown').removeClass('open');
                }
            });

            // ESC tuşu ile kapat
            $(document).on('keydown', e => {
                if (e.keyCode === 27) {
                    $('.sort-dropdown').removeClass('open');
                    $('#gif-search-modal').hide();
                }
            });

            // Daha fazla yorum yükle
            this.elements.loadMoreBtn.on('click', () => {
                this.getComments();
            });

            // Tepkiler
            this.elements.reactions.on('click', '.reaction', e => {
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }

                const $btn = $(e.currentTarget);
                const reaction = $btn.data('reaction');
                const wasSelected = $btn.hasClass('selected');

                $btn.addClass('loading');

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_handle_reaction',
                    nonce: diyar_comment_ajax.nonce,
                    post_id: diyar_comment_ajax.post_id,
                    current_url: window.location.href,
                    reaction: reaction
                })
                .done(response => {
                    if (response.success) {
                        this.updateReactionUI({
                            counts: response.data.counts,
                            user_reaction: wasSelected ? null : reaction
                        });
                    }
                })
                .fail(() => {
                    this.showNotification(diyar_comment_ajax.text.error, 'error');
                })
                .always(() => {
                    $btn.removeClass('loading');
                });
            });

            // MODERN TEK KALP BEĞENİ SİSTEMİ - DÜZELTİLMİŞ
            $(document).on('click', '.heart-like-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Heart button clicked!');
                
                if (!diyar_comment_ajax.logged_in) {
                    DiyarComments.showNotification('Beğeni yapmak için giriş yapmalısınız.', 'warning');
                    return;
                }

                const $btn = $(e.currentTarget);
                const commentId = $btn.data('comment-id');
                const $heartIcon = $btn.find('.heart-icon path');
                const $likeCount = $btn.find('.like-count');
                const wasActive = $btn.hasClass('active');

                console.log('Processing like for comment:', commentId, 'Active:', wasActive);

                if ($btn.prop('disabled')) return;
                
                $btn.prop('disabled', true).addClass('loading');

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_handle_like',
                    nonce: diyar_comment_ajax.nonce,
                    comment_id: commentId,
                    type: 'like'
                })
                .done(response => {
                    console.log('Like response:', response);
                    if (response.success) {
                        // Net skoru hesapla (likes - dislikes)
                        const netScore = Math.max(0, response.data.likes - response.data.dislikes);
                        $likeCount.text(netScore);
                        
                        if (response.data.user_vote === 'liked') {
                            $btn.addClass('active');
                            console.log('Heart filled');
                        } else {
                            $btn.removeClass('active');
                            console.log('Heart emptied');
                        }
                        
                        DiyarComments.showNotification('İşlem tamamlandı.', 'success');
                    } else {
                        DiyarComments.showNotification(response.data?.message || 'Bir hata oluştu.', 'error');
                    }
                })
                .fail((xhr) => {
                    console.error('Heart like failed:', xhr);
                    DiyarComments.showNotification('Beğeni işlemi başarısız oldu.', 'error');
                })
                .always(() => {
                    $btn.prop('disabled', false).removeClass('loading');
                });
            });

            // ESKİ SİSTEM İLE GERİYE UYUMLULUK
            this.elements.commentList.on('click', '.like-btn, .dislike-btn', e => {
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }

                const $btn = $(e.currentTarget);
                const $container = $btn.closest('.comment-like-buttons');
                const commentId = $container.data('comment-id');
                const type = $btn.hasClass('like-btn') ? 'like' : 'dislike';

                $btn.prop('disabled', true);

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_handle_like',
                    nonce: diyar_comment_ajax.nonce,
                    comment_id: commentId,
                    type: type
                })
                .done(response => {
                    if (response.success) {
                        $container.find('.like-btn .count').text(response.data.likes);
                        $container.find('.dislike-btn .count').text(response.data.dislikes);
                        
                        $container.find('button').removeClass('active');
                        if (response.data.user_vote === 'liked') {
                            $container.find('.like-btn').addClass('active');
                        } else if (response.data.user_vote === 'disliked') {
                            $container.find('.dislike-btn').addClass('active');
                        }
                    }
                })
                .always(() => {
                    $btn.prop('disabled', false);
                });
            });

            // MODERN YANITLAMA SİSTEMİ
            this.elements.commentList.on('click', '.reply-btn-modern', e => {
                e.preventDefault();
                e.stopPropagation();
                
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }

                const $btn = $(e.currentTarget);
                const $commentItem = $btn.closest('.diyar-comment-item');
                const commentId = $commentItem.data('comment-id');
                const $replyContainer = $commentItem.find('.reply-form-container');
                
                console.log('Reply button clicked, comment ID:', commentId); // Debug
                
                // Eğer zaten yanıt formu açıksa, kapat
                if ($replyContainer.is(':visible')) {
                    $replyContainer.slideUp(300);
                    return;
                }
                
                // Yanıt formu HTML'i
                const replyFormHtml = `
                    <div class="reply-form">
                        <textarea placeholder="Yanıtınızı yazın..." required></textarea>
                        <div class="reply-form-actions">
                            <button type="button" class="reply-cancel">İptal</button>
                            <button type="button" class="reply-submit" data-comment-id="${commentId}">Yanıtla</button>
                        </div>
                    </div>
                `;
                
                $replyContainer.html(replyFormHtml).slideDown(300);
                $replyContainer.find('textarea').focus();
            });

            // Yanıt formu - İptal
            this.elements.commentList.on('click', '.reply-cancel', e => {
                const $container = $(e.currentTarget).closest('.reply-form-container');
                $container.slideUp(300);
            });

            // Yanıt formu - Gönder
            this.elements.commentList.on('click', '.reply-submit', e => {
                const $btn = $(e.currentTarget);
                const $form = $btn.closest('.reply-form');
                const $textarea = $form.find('textarea');
                const commentId = $btn.data('comment-id');
                const replyText = $textarea.val().trim();
                
                console.log('Reply submit clicked, comment ID:', commentId, 'Text:', replyText); // Debug
                
                if (!replyText) {
                    this.showNotification('Yanıt içeriği boş olamaz.', 'warning');
                    return;
                }
                
                const originalText = $btn.text();
                $btn.text('Gönderiliyor...').prop('disabled', true);
                
                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_submit_comment',
                    nonce: diyar_comment_ajax.nonce,
                    comment: replyText,
                    comment_post_ID: diyar_comment_ajax.post_id,
                    post_id: diyar_comment_ajax.post_id,
                    comment_parent: commentId,
                    current_url: window.location.href
                })
                .done(response => {
                    console.log('Reply response:', response); // Debug
                    if (response.success) {
                        // Sayfayı yenile veya dinamik olarak yanıtı ekle
                        location.reload();
                    } else {
                        this.showNotification(response.data?.message || 'Yanıt gönderilemedi.', 'error');
                    }
                })
                .fail(xhr => {
                    console.error('Reply failed:', xhr); // Debug
                    this.showNotification('Ağ hatası. Lütfen tekrar deneyin.', 'error');
                })
                .always(() => {
                    $btn.text(originalText).prop('disabled', false);
                });
            });

            // 3 NOKTA DROPDOWN MENÜ - DEBUG İLE DÜZELTİLMİŞ
            $(document).on('click', '.menu-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Dropdown trigger clicked!'); // Debug
                
                const $trigger = $(this);
                const $dropdown = $trigger.next('.dropdown-menu');
                
                console.log('Dropdown found:', $dropdown.length); // Debug
                
                // Diğer açık dropdown'ları kapat
                $('.dropdown-menu').not($dropdown).removeClass('show');
                
                // Bu dropdown'ı aç/kapat
                $dropdown.toggleClass('show');
                
                console.log('Dropdown show class:', $dropdown.hasClass('show')); // Debug
            });

            // Dropdown dışına tıklama ile kapat
            $(document).on('click', e => {
                if (!$(e.target).closest('.comment-menu-dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });

            // Dropdown menü item'leri - DÜZELTİLMİŞ
            this.elements.commentList.on('click', '.edit-comment-btn', e => {
                e.preventDefault();
                e.stopPropagation();
                const commentId = $(e.currentTarget).data('comment-id');
                console.log('Edit button clicked for comment:', commentId);
                
                // Dropdown'ı kapat
                $('.dropdown-menu').removeClass('show');
                
                // Yorum düzenleme fonksiyonunu direkt çağır
                this.editComment(commentId);
            });

            this.elements.commentList.on('click', '.delete-comment-btn', e => {
                e.preventDefault();
                e.stopPropagation();
                const commentId = $(e.currentTarget).data('comment-id');
                console.log('Delete button clicked for comment:', commentId);
                
                // Dropdown'ı kapat
                $('.dropdown-menu').removeClass('show');
                
                // Silme onayı
                if (!confirm('Bu yorumu silmek istediğinizden emin misiniz?')) {
                    return;
                }
                
                this.deleteComment(commentId);
            });

            this.elements.commentList.on('click', '.report-comment-btn', e => {
                e.preventDefault();
                e.stopPropagation();
                const commentId = $(e.currentTarget).data('comment-id');
                console.log('Report button clicked for comment:', commentId);
                
                // Dropdown'ı kapat
                $('.dropdown-menu').removeClass('show');
                
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }
                
                if (!confirm('Bu yorumu gerçekten şikayet etmek istiyor musunuz?')) {
                    return;
                }
                
                this.reportComment(commentId);
            });

            // Şikayet et
            this.elements.commentList.on('click', '.report-btn', e => {
                e.preventDefault();
                
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }

                if (!confirm(diyar_comment_ajax.text.report_confirm)) {
                    return;
                }

                const $btn = $(e.currentTarget);
                const commentId = $btn.data('comment-id');

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_flag_comment',
                    nonce: diyar_comment_ajax.nonce,
                    comment_id: commentId
                })
                .done(response => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                        $btn.text('Şikayet Edildi').prop('disabled', true);
                    } else {
                        this.showNotification(response.data.message || diyar_comment_ajax.text.error, 'error');
                    }
                });
            });

            // Yorum düzenle
            this.elements.commentList.on('click', '.comment-edit-btn', e => {
                e.preventDefault();
                
                const $btn = $(e.currentTarget);
                const commentId = $btn.data('comment-id');
                const $commentItem = $btn.closest('.diyar-comment-item');
                const $commentText = $commentItem.find('.comment-text');
                
                // Zaten düzenleme modunda mı kontrol et
                if ($commentItem.find('.comment-edit-form').length > 0) {
                    return;
                }
                
                const currentText = $commentText.text().trim();
                
                const $editForm = $(`
                    <div class="comment-edit-form">
                        <div class="comment-edit-toolbar">
                            <button type="button" class="diyar-toolbar-button" data-tag="b"><b>B</b></button>
                            <button type="button" class="diyar-toolbar-button" data-tag="i"><i>I</i></button>
                            <button type="button" class="diyar-toolbar-button" data-tag="spoiler">[S]</button>
                        </div>
                        <textarea name="comment_content" required>${currentText}</textarea>
                        <div class="comment-edit-actions">
                            <div></div>
                            <div>
                                <button type="button" class="comment-edit-cancel">İptal</button>
                                <button type="button" class="comment-edit-save" data-comment-id="${commentId}">Kaydet</button>
                            </div>
                        </div>
                    </div>
                `);
                
                $commentText.hide();
                $commentText.after($editForm);
                $editForm.find('textarea').focus();
            });

            // Yorum düzenleme iptal
            this.elements.commentList.on('click', '.comment-edit-cancel', e => {
                const $form = $(e.currentTarget).closest('.comment-edit-form');
                const $commentText = $form.prev('.comment-text');
                
                $form.remove();
                $commentText.show();
            });

            // Yorum düzenleme kaydet
            this.elements.commentList.on('click', '.comment-edit-save', e => {
                const $btn = $(e.currentTarget);
                const $form = $btn.closest('.comment-edit-form');
                const $textarea = $form.find('textarea');
                const commentId = $btn.data('comment-id');
                const newContent = $textarea.val().trim();
                
                if (!newContent) {
                    this.showNotification('Yorum içeriği boş olamaz.', 'warning');
                    return;
                }
                
                const originalText = $btn.text();
                $btn.text('Kaydediliyor...').prop('disabled', true);
                
                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_edit_comment',
                    nonce: diyar_comment_ajax.nonce,
                    comment_id: commentId,
                    content: newContent
                })
                .done(response => {
                    if (response.success) {
                        const $commentText = $form.prev('.comment-text');
                        $commentText.html(response.data.content).show();
                        $form.remove();
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data.message || 'Yorum güncellenemedi.', 'error');
                    }
                })
                .always(() => {
                    $btn.text(originalText).prop('disabled', false);
                });
            });

            // Yorum sil
            this.elements.commentList.on('click', '.comment-delete-btn', e => {
                e.preventDefault();
                
                if (!confirm('Bu yorumu silmek istediğinizden emin misiniz?')) {
                    return;
                }
                
                const $btn = $(e.currentTarget);
                const commentId = $btn.data('comment-id');
                const $commentItem = $btn.closest('.diyar-comment-item');
                
                $btn.text('Siliniyor...').prop('disabled', true);
                
                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_delete_comment',
                    nonce: diyar_comment_ajax.nonce,
                    comment_id: commentId
                })
                .done(response => {
                    if (response.success) {
                        $commentItem.fadeOut(400, () => {
                            $commentItem.remove();
                            // Yorum sayısını güncelle
                            const currentCount = parseInt(this.elements.commentCountSpan.text(), 10);
                            this.elements.commentCountSpan.text(Math.max(0, currentCount - 1));
                        });
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data.message || 'Yorum silinemedi.', 'error');
                        $btn.text('Sil').prop('disabled', false);
                    }
                })
                .fail(() => {
                    this.showNotification('Bir hata oluştu.', 'error');
                    $btn.text('Sil').prop('disabled', false);
                });
            });

            // Yorum düzenleme toolbar
            this.elements.commentList.on('click', '.comment-edit-toolbar .diyar-toolbar-button', e => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const $textarea = $btn.closest('.comment-edit-form').find('textarea')[0];
                const tag = $btn.data('tag');
                
                if (tag && $textarea) {
                    this.wrapText($textarea, tag);
                }
            });

            // Ana yorum formu
            this.elements.commentForm.on('submit', e => {
                e.preventDefault();
                
                if (this.state.isLoading) return;
                
                const commentText = this.elements.commentTextarea.val().trim();
                if (commentText === '') {
                    this.showNotification(diyar_comment_ajax.text.comment_empty, 'warning');
                    return;
                }

                this.state.isLoading = true;
                const originalBtnText = this.elements.submitBtn.text();
                this.elements.submitBtn.text(diyar_comment_ajax.text.commenting).prop('disabled', true);

                // DÜZELTME: Post ID'yi dinamik olarak kontrol et
                const formData = this.elements.commentForm.serializeObject();
                
                // Eğer form data'sında comment_post_ID yoksa veya yanlışsa, mevcut post ID'yi kullan
                if (!formData.comment_post_ID || formData.comment_post_ID == '0') {
                    formData.comment_post_ID = diyar_comment_ajax.post_id;
                }
                
                // Ekstra güvenlik için post_id'yi de gönder
                formData.post_id = diyar_comment_ajax.post_id;

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_submit_comment',
                    nonce: diyar_comment_ajax.nonce,
                    current_url: window.location.href,
                    ...formData
                })
                .done(response => {
                    if (response.success) {
                        if (response.data.html) {
                            const $newComment = $(response.data.html).hide();
                            this.elements.commentList.prepend($newComment);
                            
                            $newComment.fadeIn(400, () => {
                                // Animasyon tamamlandıktan sonra GIF'leri render et
                                setTimeout(() => {
                                    this.renderGifsInComments();
                                }, 50);
                            });
                        }
                        
                        this.elements.commentTextarea.val('');
                        $('.image-previews').remove(); // Clear image previews
                        this.state.uploadedImages = []; // Clear uploaded images array
                        
                        const currentCount = parseInt(this.elements.commentCountSpan.text(), 10);
                        this.elements.commentCountSpan.text(currentCount + 1);
                        $('.no-comments').hide();
                        
                        this.showNotification(response.data.message || diyar_comment_ajax.text.success, 'success');
                    } else {
                        this.showNotification(response.data.message || diyar_comment_ajax.text.error, 'error');
                    }
                })
                .always(() => {
                    this.state.isLoading = false;
                    this.elements.submitBtn.text(originalBtnText).prop('disabled', false);
                });
            });

            // Toolbar butonları - görsel butonunu hariç tut
            this.elements.toolbar.on('click', '.diyar-toolbar-button:not(.image-upload)', e => {
                e.preventDefault();
                const tag = $(e.currentTarget).data('tag');
                if (tag) {
                    this.wrapText(this.elements.commentTextarea[0], tag);
                }
            });

            // Kullanıcı profil linklerine tıklama - profil sayfasına git
            this.elements.commentList.on('click', '.avatar-link, .author-name', e => {
                e.preventDefault();
                const href = $(e.currentTarget).attr('href');
                if (href && href !== '#') {
                    window.open(href, '_blank');
                }
            });

            // Yanıtla linki
            this.elements.commentList.on('click', '.comment-reply-link', e => {
                e.preventDefault();
                
                if (!diyar_comment_ajax.logged_in) {
                    this.showNotification(diyar_comment_ajax.text.login_required, 'warning');
                    return;
                }

                const $link = $(e.currentTarget);
                const commentId = $link.data('comment-id');
                const $commentItem = $link.closest('.diyar-comment-item');
                
                $commentItem.find('.inline-reply-container').remove();
                
                const $replyForm = this.createInlineReplyForm(commentId);
                $commentItem.append($replyForm);
                $replyForm.hide().slideDown(300);
                $replyForm.find('textarea').focus();
            });

            // Inline yanıt formu - iptal
            this.elements.commentList.on('click', '.inline-reply-cancel', e => {
                const $container = $(e.currentTarget).closest('.inline-reply-container');
                const commentId = $container.data('comment-id');
                
                $container.slideUp(300, () => {
                    $container.remove();
                    this.state.replyForms.delete(commentId);
                });
            });

            // Inline yanıt formu - gönder
            this.elements.commentList.on('submit', '.inline-reply-form', e => {
                e.preventDefault();
                
                const $form = $(e.currentTarget);
                const $container = $form.closest('.inline-reply-container');
                const $submitBtn = $form.find('.inline-reply-submit');
                const $textarea = $form.find('textarea');
                
                if ($textarea.val().trim() === '') {
                    this.showNotification(diyar_comment_ajax.text.comment_empty, 'warning');
                    return;
                }

                const originalBtnText = $submitBtn.text();
                $submitBtn.text(diyar_comment_ajax.text.commenting).prop('disabled', true);

                // DÜZELTME: Inline reply için de post ID kontrolü
                const replyFormData = $form.serializeObject();
                
                // Post ID kontrolü ve düzeltmesi
                if (!replyFormData.comment_post_ID || replyFormData.comment_post_ID == '0') {
                    replyFormData.comment_post_ID = diyar_comment_ajax.post_id;
                }
                
                // Ekstra güvenlik için post_id'yi de gönder
                replyFormData.post_id = diyar_comment_ajax.post_id;

                $.post(diyar_comment_ajax.ajax_url, {
                    action: 'diyar_submit_comment',
                    nonce: diyar_comment_ajax.nonce,
                    current_url: window.location.href,
                    ...replyFormData
                })
                .done(response => {
                    if (response.success) {
                        if (response.data.html) {
                            const $newReply = $(response.data.html).hide();
                            
                            if (response.data.parent_id && response.data.parent_id != '0') {
                                const $parentComment = $(`#comment-${response.data.parent_id}`);
                                let $children = $parentComment.children('.children');
                                
                                if (!$children.length) {
                                    $children = $('<ol class="children"></ol>');
                                    $parentComment.append($children);
                                }
                                
                                $children.append($newReply);
                            } else {
                                this.elements.commentList.prepend($newReply);
                            }
                            
                            $newReply.fadeIn(400, () => {
                                // Yanıt animasyonu tamamlandıktan sonra GIF'leri render et
                                setTimeout(() => {
                                    this.renderGifsInComments();
                                }, 50);
                            });
                        }
                        
                        const commentId = $container.data('comment-id');
                        $container.slideUp(300, () => {
                            $container.remove();
                            this.state.replyForms.delete(commentId);
                        });
                        
                        const currentCount = parseInt(this.elements.commentCountSpan.text(), 10);
                        this.elements.commentCountSpan.text(currentCount + 1);
                        
                        this.showNotification(response.data.message || diyar_comment_ajax.text.success, 'success');
                    } else {
                        this.showNotification(response.data.message || diyar_comment_ajax.text.error, 'error');
                    }
                })
                .always(() => {
                    $submitBtn.text(originalBtnText).prop('disabled', false);
                });
            });

            // Inline yanıt toolbar
            this.elements.commentList.on('click', '.inline-reply-toolbar .diyar-toolbar-button:not(.image-upload)', e => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const $textarea = $btn.closest('.inline-reply-editor').find('textarea')[0];
                const tag = $btn.data('tag');
                
                if (tag && $textarea) {
                    this.wrapText($textarea, tag);
                }
            });

            // Inline reply görsel upload
            this.elements.commentList.on('change', '.inline-reply-toolbar .image-upload input[type="file"]', e => {
                this.handleImageUpload(e.target.files);
            });
            
            // Inline reply görsel upload button click
            this.elements.commentList.on('click', '.inline-reply-toolbar .image-upload', e => {
                e.preventDefault();
                $(e.currentTarget).find('input[type="file"]').click();
            });
        },

        // Yorum düzenleme fonksiyonu
        editComment: function(commentId) {
            const $commentItem = $(`#comment-${commentId}`);
            const $commentText = $commentItem.find('.comment-text');
            
            if ($commentItem.find('.comment-edit-form').length > 0) {
                return;
            }
            
            const currentText = $commentText.text().trim();
            
            const $editForm = $(`
                <div class="comment-edit-form">
                    <div class="comment-edit-toolbar">
                        <button type="button" class="diyar-toolbar-button" data-tag="b"><b>B</b></button>
                        <button type="button" class="diyar-toolbar-button" data-tag="i"><i>I</i></button>
                        <button type="button" class="diyar-toolbar-button" data-tag="spoiler">[S]</button>
                    </div>
                    <textarea name="comment_content" required>${currentText}</textarea>
                    <div class="comment-edit-actions">
                        <div></div>
                        <div>
                            <button type="button" class="comment-edit-cancel">İptal</button>
                            <button type="button" class="comment-edit-save" data-comment-id="${commentId}">Kaydet</button>
                        </div>
                    </div>
                </div>
            `);
            
            $commentText.hide();
            $commentText.after($editForm);
            $editForm.find('textarea').focus();
        },

        // Yorum silme fonksiyonu
        deleteComment: function(commentId) {
            const $commentItem = $(`#comment-${commentId}`);
            
            $.post(diyar_comment_ajax.ajax_url, {
                action: 'diyar_delete_comment',
                nonce: diyar_comment_ajax.nonce,
                comment_id: commentId
            })
            .done(response => {
                if (response.success) {
                    $commentItem.fadeOut(400, () => {
                        $commentItem.remove();
                        const currentCount = parseInt(this.elements.commentCountSpan.text(), 10);
                        this.elements.commentCountSpan.text(Math.max(0, currentCount - 1));
                    });
                    this.showNotification(response.data.message || 'Yorum başarıyla silindi.', 'success');
                } else {
                    this.showNotification(response.data.message || 'Yorum silinemedi.', 'error');
                }
            })
            .fail(() => {
                this.showNotification('Bir hata oluştu.', 'error');
            });
        },

        // Yorum şikayet fonksiyonu
        reportComment: function(commentId) {
            $.post(diyar_comment_ajax.ajax_url, {
                action: 'diyar_flag_comment',
                nonce: diyar_comment_ajax.nonce,
                comment_id: commentId
            })
            .done(response => {
                if (response.success) {
                    this.showNotification('Şikayetiniz alındı. Teşekkür ederiz.', 'success');
                } else {
                    this.showNotification(response.data?.message || 'Bir hata oluştu.', 'error');
                }
            })
            .fail(() => {
                this.showNotification('Şikayet gönderilemedi.', 'error');
            });
        }
    };

    // jQuery serializeObject plugin
    $.fn.serializeObject = function() {
        var o = {};
        var a = this.serializeArray();
        $.each(a, function() {
            if (o[this.name]) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

    // Başlat
    DiyarComments.init();
    
    // Global olarak erişilebilir yap
    window.DiyarComments = DiyarComments;
});
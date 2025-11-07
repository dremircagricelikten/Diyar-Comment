jQuery(document).ready(function($) {
    // Renk seçiciyi başlat
    $('.diyar-color-picker').wpColorPicker();

    // Rozet şablonu seçimi
    function setupTemplateSelector(containerClass, hiddenInputId) {
        var container = $('.' + containerClass);
        container.on('click', '.template-item, .template-item-auto', function() {
            container.find('.template-item, .template-item-auto').removeClass('selected');
            $(this).addClass('selected');
            $('#' + hiddenInputId).val($(this).data('template'));
        });
        container.find('.template-item, .template-item-auto').first().addClass('selected');
    }
    setupTemplateSelector('badge-templates', 'selected_badge_template');
    setupTemplateSelector('badge-templates-auto', 'selected_auto_badge_template');

    // Yorum Yönetimi - Hızlı Düzenle
    $('#comments-filter').on('click', '.quick-edit-comment', function(e) {
        e.preventDefault();
        var commentId = $(this).data('comment-id');
        $('#comment-text-' + commentId).hide();
        $('#edit-comment-' + commentId).show();
    });

    $('#comments-filter').on('click', '.cancel-edit-comment', function() {
        var wrapper = $(this).closest('div');
        wrapper.hide();
        wrapper.prev().show();
    });

    $('#comments-filter').on('click', '.save-edit-comment', function() {
        var button = $(this);
        var commentId = button.data('comment-id');
        var newContent = button.siblings('textarea').val();
        
        button.prop('disabled', true).text('Kaydediliyor...');

        $.post(ajaxurl, {
            action: 'diyar_admin_edit_comment',
            _ajax_nonce: '<?php echo wp_create_nonce("diyar_admin_edit_comment"); ?>',
            comment_id: commentId,
            content: newContent
        }).done(function(response) {
            if (response.success) {
                $('#comment-text-' + commentId).text(response.data.content).show();
                $('#edit-comment-' + commentId).hide();
            } else {
                alert('Hata: ' + response.data.message);
            }
        }).always(function() {
            button.prop('disabled', false).text('Kaydet');
        });
    });
});
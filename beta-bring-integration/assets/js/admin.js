(function($){
    $(function(){
        $('#bbi_book_btn').on('click', function(e){
            e.preventDefault();
            var btn = $(this);
            btn.prop('disabled', true).text(bbi_ajax.i18n.booking);

            var preset = $('#bbi_preset').val();
            var order_id = window.bbi_order_id || $('input#post_ID').val();

            $.post(bbi_ajax.ajax_url, {
                action: 'bbi_book_order',
                nonce: bbi_ajax.nonce,
                order_id: order_id,
                preset: preset
            }, function(resp){
                btn.prop('disabled', false).text(bbi_ajax.i18n.book);
                if ( resp.success ) {
                    var d = resp.data;
                    var html = '<p>' + (d.consignment_no ? 'Consignment: ' + d.consignment_no : '') + '</p>';
                    if ( d.label_url ) {
                        html += '<p><a href="'+d.label_url+'" target="_blank">' + bbi_ajax.i18n.download_label + '</a></p>';
                    }
                    if ( d.tracking_url ) {
                        html += '<p><button class="button bbi-copy-tracking" data-url="'+d.tracking_url+'">'+bbi_ajax.i18n.copy_tracking+'</button></p>';
                    }
                    $('#bbi_status').html(html);
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Booking failed');
                }
            }).fail(function(){
                btn.prop('disabled', false).text(bbi_ajax.i18n.book);
                alert('Booking failed (network)');
            });
        });

        $(document).on('click', '.bbi-copy-tracking', function(e){
            var url = $(this).data('url');
            if (!url) return;
            navigator.clipboard.writeText(url).then(function(){
                alert('Tracking link copied');
            });
        });
    });
})(jQuery);

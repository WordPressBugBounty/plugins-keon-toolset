jQuery( document ).ready( function ( $ ) {
//Bosa Pro Admin Page Notice 
    $(document).on( 'click', '.keon-remind-me-later', function() {
        $(document).find('.bosa-go-pro-notice').slideUp();
         $.ajax({
            url: KEON_BOSA_PRO_UPGRADE.ajaxurl,
            type: 'POST',
            data: {
                nonce: KEON_BOSA_PRO_UPGRADE.nonce,
                action: 'remind_me_later_bosa_pro',
            },
        });
    });

    $(document).on( 'click', '#keon-bosa-pro-dismiss', function() {
        $(document).find('.bosa-go-pro-notice').slideUp();
         $.ajax({
            url: KEON_BOSA_PRO_UPGRADE.ajaxurl,
            type: 'POST',
            data: {
                nonce: KEON_BOSA_PRO_UPGRADE.dismiss_nonce,
                action: 'upgrade_bosa_pro_notice_dismiss',
            },
        });
    });
} );
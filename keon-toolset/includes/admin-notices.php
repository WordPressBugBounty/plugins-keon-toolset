<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Enqueues style for admin notices.
 * 
 * @since    1.3.7
 */
add_action( 'admin_enqueue_scripts', 'keon_toolset_bosa_store' );
function keon_toolset_bosa_store() {
    wp_enqueue_style( 'keon-toolset-bosa-store', KEON_TEMPLATE_URL . 'assets/bosa-store.css', false );
}

/**
 * Adds gutener upsell admin notice.
 * 
 * @since    1.3.7
 */
function gutener_upsell_admin_notice(){
    if( !get_user_meta( get_current_user_id(), 'dismiss_gutener_upsell_notice' ) ){
        $pro_img_url = KEON_TEMPLATE_URL . 'assets/img/gutener-pro.png';
        ?>
        <div class="keon-notice">
            <div class="getting-img">
                <img id="" src="<?php echo esc_url( $pro_img_url ); ?>" />
            </div>
            <div class="getting-content">
                <h2><?php esc_html_e('Go PRO for More Features', 'keon-toolset');?></h2>
                <p class="text">Get <a href="https://keonthemes.com/downloads/gutener-pro" target="_blank"><?php esc_html_e('Gutener Pro', 'keon-toolset');?></a> <?php esc_html_e('for more stunning elements, demos and customization options.', 'keon-toolset');?></p>
                <a href="<?php echo esc_url( 'https://keonthemes.com/downloads/gutener-pro' ); ?>" class="button button-primary" target="_blank"><?php esc_html_e('Theme Details', 'keon-toolset');?></a>
                <a href="<?php echo esc_url( 'https://keonthemes.com/downloads/gutener-pro' ); ?>" class="button button-primary" target="_blank"><?php esc_html_e('Buy Gutener Pro', 'keon-toolset');?></a>
             </div>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'gutener-upsell-notice-dismissed', 'dismiss_gutener_upsell_notice' ), 'gutener_upsell_state', 'gutener_upsell_nonce' ) ); ?>" class="admin-notice-dismiss"><?php esc_html_e('Dismiss', 'keon-toolset');?><button type="button" class="notice-dismiss"></button></a>
        </div>
        <?php 
    }
}

/**
 * Adds store admin notice.
 * 
 * @since    1.3.7
 */
function keon_store_admin_notice(){
    if( !get_user_meta( get_current_user_id(), 'store_notice_dismissed' ) ){
        $store_img_url = KEON_TEMPLATE_URL . 'assets/img/bosa-store.png';
        ?>        
        <div class="keon-notice">
            <div class="getting-img">
            <img id="" src="<?php echo esc_url( $store_img_url ); ?>" />
            </div>
            <div class="getting-content">
                <h2><?php esc_html_e('New Awesome FREE WooCommerce Theme - Bosa Store', 'keon-toolset');?></h2>
                <p class="text"><a href="<?php echo esc_url( 'https://bosathemes.com/bosa-store' ); ?>" target="_blank"><?php esc_html_e('Bosa Store', 'keon-toolset');?></a><?php esc_html_e(' - new free WooCommerce theme from BosaThemes. Check out theme ', 'keon-toolset');?><a href="<?php echo esc_url( 'https://bosathemes.com/bosa-store' ); ?>" target="_blank"><?php esc_html_e('Demo', 'keon-toolset');?></a><?php esc_html_e(' that can be imported for FREE with simple click.', 'keon-toolset');?></p>
                <a href="<?php echo esc_url( 'https://demo.bosathemes.com/bosa/store' ); ?>" class="button button-primary"><?php esc_html_e('View Demo', 'keon-toolset');?></a>
                <a href="<?php echo esc_url( 'https://bosathemes.com/bosa-store' ); ?>" class="button button-primary"><?php esc_html_e('Theme Details', 'keon-toolset');?></a>
            </div>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'store-notice-dismissed', 'dismiss_store_notice' ), 'store_notice_state', 'store_notice_nonce' ) ); ?>" class="admin-notice-dismiss"><?php esc_html_e('Dismiss', 'keon-toolset');?><button type="button" class="notice-dismiss"></button></a>
        </div>
        <?php 
    }
}

/**
 * Registers admin notice for current user.
 * 
 * @since    1.3.7
 */
add_action( 'admin_init', 'keon_toolset_notice_dismissed' );
function keon_toolset_notice_dismissed() {
    if ( isset( $_GET['store-notice-dismissed'] ) && wp_verify_nonce($_GET['store_notice_nonce'], 'store_notice_state') ){
        add_user_meta( get_current_user_id(), 'store_notice_dismissed', true, true );
    }
}

/**
 * Registers admin notice for current user.
 * 
 */
add_action( 'admin_init', 'keon_toolset_gutener_notice_dismissed' );
function keon_toolset_gutener_notice_dismissed() {
    if ( isset( $_GET['gutener-upsell-notice-dismissed'] ) && wp_verify_nonce($_GET['gutener_upsell_nonce'], 'gutener_upsell_state') ){
        add_user_meta( get_current_user_id(), 'dismiss_gutener_upsell_notice', true, true );
    }
}


/**
 * Removes admin notice dismiss state for current user.
 * 
 * @since    1.3.7
 */
add_action( 'switch_theme', 'flush_admin_notices_dismiss_status' );
function flush_admin_notices_dismiss_status(){
    delete_user_meta( get_current_user_id(), 'store_notice_dismissed', true, true );
    delete_user_meta( get_current_user_id(), 'dismiss_gutener_upsell_notice', true, true );
}
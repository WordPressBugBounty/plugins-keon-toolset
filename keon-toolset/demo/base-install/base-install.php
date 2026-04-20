<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * The base theme install functionality of the plugin.
 *
 */
class Kt_Base_Install_Hooks {

    /**
     * Initialize the class and set its properties.
     *
     */
    public function __construct() {
        add_action( 'wp_ajax_install_base_theme', array( $this, 'install_base_theme' ));
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ), 10, 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );
    }

    /**
     * Enqueue styles.
     *
     */
    public function enqueue_styles() {
    
        wp_enqueue_style( 'kt-base-install', plugin_dir_url( __FILE__ ) . 'assets/base-install.css',array( 'wp-admin' ), '1.0.0', 'all' );
    }

    /**
     * Enqueue scripts.
     *
     */
    public function enqueue_scripts() {

        wp_enqueue_script( 'kt-base-install', plugin_dir_url( __FILE__ ) . 'assets/base-install.js', array( 'jquery' ), '1.0.0', true );

        $base_theme = kt_get_base_theme();

        $site_link = '';
        $site_price_link = '';
        $base_theme_title = '';
        $base_theme_description = '';

        if ( $base_theme['name'] == 'Bosa' ) {
            $site_link = 'https://bosathemes.com/pricing';
            $site_price_link = 'https://bosathemes.com/bosa-pro/#pricing';
            $base_theme_title = 'Bosa Pro';
            $base_theme_description = 'Unlock pro features, enhanced functionalities, customizable options & Full Starter sites Library.';
        }
        else {
            $site_link = 'https://bosathemes.com/shoppable-pro/';
            $site_price_link = 'https://bosathemes.com/shoppable-pro/#pricing';
            $base_theme_title = 'Shoppable Pro Extension';
            $base_theme_description = 'Unlock Pro and get instant access to Full Starter Sites Library.';
        }

        $action = __( 'Install and Activate', 'keon-toolset' );
        if( kt_base_theme_installed() ){
            $action = __( 'Activate', 'keon-toolset' );
        }
        wp_localize_script(
            'kt-base-install',
            'direct_install',
            array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'direct_theme_install' ),
                'base_html' => sprintf(
                    '<div class="base-install-notice-outer">
                        <div class="base-install-notice-inner">
                            <div class="base-install-prompt" >
                                <div class="base-install-content">
                                    <h2 class="base-install-title">%1$s</h2>
                                    <p>'.esc_html__('We recommend to','keon-toolset').' %2$s %1$s '.esc_html__('theme as all our demo works perfectly with this theme. You can still try our demo on any bosa theme but it might not look as you see on our demo.','keon-toolset').'</p>
                                </div>
                                <div class="base-install-btn">
                                    <a class= "install-base-theme button button-primary">%2$s %1$s</a>
                                    <br>
                                    <a class="close-base-notice close-base-button">'.esc_html__( 'Skip', 'keon-toolset' ).'</a>
                                </div>
                            </div>
                            <div class="base-install-success">
                                <div class="base-install-content">
                                    <h3>'.esc_html__('Thank you for installing','keon-toolset').' %1$s'.esc_html__('.  Click on Next to proceed to demo importer.','keon-toolset').'</h3>
                                </div>
                                <div class="base-install-btn">
                                    <a class= "close-base-notice button button-primary">'.esc_html__('Next','keon-toolset').'</a>
                                </div>
                            </div>
                            <div class="base-go-pro-bosa-prompt">
                                <div class="go-pro-description">
                                    <h2 class="bosa-notice-title">'.esc_html__('Upgrade to','keon-toolset').' 
                                        <a href="%3$s" target="_blank" class="bosa-title">%5$s</a>
                                    </h2>
                                    <p>%6$s</p>
                                </div>
                                <a href="%4$s" class="btn-primary" target="_blank">'.esc_html__('Buy Now','keon-toolset').'</a>
                            </div>
                        </div>
                    </div>',
                    esc_html__( $base_theme['name'], 'keon-toolset' ),
                    esc_html($action),
                    esc_url($site_link),
                    esc_url($site_price_link),                    
                    esc_html__($base_theme_title, 'keon-toolset'),
                    esc_html__($base_theme_description, 'keon-toolset'),
                ),
            )
        );
    }

    /**
     *  Install base theme.
     */
    public function install_base_theme(){
        check_ajax_referer( 'direct_theme_install', 'security' );

        if( !current_user_can('manage_options') ) {
            $error = __( 'Sorry, you are not allowed to install themes on this site.', 'keon-toolset' );
            wp_send_json_error( $error );
        }

        $base_theme = kt_get_base_theme();
        if ( kt_base_theme_installed() ) {
            switch_theme( $base_theme['slug'] );
            $current_theme = keon_toolset_get_theme_slug();
            wp_send_json_success($current_theme);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/theme.php';

        $api = themes_api(
            'theme_information',
            array(
                'slug'   => $base_theme['slug'],
                'fields' => array( 'sections' => false ),
            )
        );
     
        if ( is_wp_error( $api ) ) {
            $status['errorMessage'] = $api->get_error_message();
            wp_send_json_error( $status['errorMessage'] );
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $skin );
        $result   = $upgrader->install( $api->download_link );

        if (is_wp_error($result)) {
           wp_send_json_error( $result->errors );
        }

        switch_theme( $base_theme['slug'] );
        $current_theme = keon_toolset_get_theme_slug();
        wp_send_json_success($current_theme);
        die();
    }
}

/**
 * Checks if base theme installed.
 */
function kt_base_theme_installed(){
    $base_theme = kt_get_base_theme();
    $all_themes = wp_get_themes();
    $installed_themes = array();
    foreach( $all_themes as $theme ){
        $theme_text_domain = esc_attr ( $theme->get('TextDomain') );
        $installed_themes[] = $theme_text_domain;
    }
    if( in_array( $base_theme['slug'], $installed_themes, true ) ){
        return true;
    }
    return false;
    
}

/**
 * Returns base theme.
 */
function kt_get_base_theme(){
    $theme = keon_toolset_get_theme_slug();
    $base_theme = array(
        'name' => '',
        'slug' => '',
    );
    if( strpos( $theme, 'bosa' ) !== false ){
        $base_theme['name'] = 'Bosa';
        $base_theme['slug'] = 'bosa';
    }elseif( strpos( $theme, 'shoppable' ) !== false ){
        $base_theme['name'] = 'Hello Shoppable';
        $base_theme['slug'] = 'hello-shoppable';
    }
    return $base_theme;
}

return new Kt_Base_Install_Hooks();
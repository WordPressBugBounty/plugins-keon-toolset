<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: Keon Toolset
Plugin URI:  
Description: A demo importer plugin that makes importing starter sites effortless for building your website!
Version:     2.4.8
Author:      Keon Themes
Author URI:  https://keonthemes.com
License:     GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Domain Path: /languages
Text Domain: keon-toolset
*/
define( 'KEON_TOOLSET_URL', plugin_dir_url( __FILE__ ).'demo/' );
define( 'KEON_TEMPLATE_URL', plugin_dir_url( __FILE__ ) );
define( 'KEON_TOOLSET_PATH', plugin_dir_path( __FILE__ ) );
define( 'KEON_TOOLSET_VERSION', '2.4.8');

/**
 * Returns the currently active theme's name.
 *
 * @since    1.0.1
 */
function keon_toolset_get_theme_slug(){
    $demo_theme = wp_get_theme();
   	return $demo_theme->get( 'TextDomain' );
}

/**
 * Returns the currently active theme's screenshot.
 *
 * @since    1.0.0
 */
function keon_toolset_get_theme_screenshot(){
	$demo_theme = wp_get_theme();
    return $demo_theme->get_screenshot();
}
/**
 * The core plugin class that is used to define internationalization,admin-specific hooks, 
 * and public-facing site hooks..
 *
 * @since    1.0.0
 */   
require KEON_TOOLSET_PATH . 'demo/functions.php';
require KEON_TOOLSET_PATH . 'includes/class-template-library-base.php';
require KEON_TOOLSET_PATH . 'includes/theme-check-functions.php';
require KEON_TOOLSET_PATH . 'includes/admin-notices.php';
require_once KEON_TOOLSET_PATH . 'includes/class-elementor-image-import-fixer.php';
if ( class_exists( 'Keon_Toolset_Elementor_Image_Import_Fixer' ) ) {
    Keon_Toolset_Elementor_Image_Import_Fixer::init();
}

/**
 * Prints Kirki version compatibility notice markup.
 *
 * @since    2.4.8
 */
function keon_toolset_kirki_notice_is_dismissed() {
    return (bool) get_option( 'keon_toolset_kirki_notice_dismissed', false );
}

/**
 * Builds dismiss URL for the Kirki notice.
 *
 * @since    2.4.8
 */
function keon_toolset_kirki_notice_dismiss_url() {
    return wp_nonce_url(
        admin_url( 'admin-post.php?action=keon_toolset_dismiss_kirki_notice' ),
        'keon_toolset_dismiss_kirki_notice'
    );
}

/**
 * Handles dismiss request for the Kirki compatibility notice.
 *
 * @since    2.4.8
 */
function keon_toolset_handle_kirki_notice_dismiss() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to perform this action.', 'keon-toolset' ) );
    }

    check_admin_referer( 'keon_toolset_dismiss_kirki_notice' );
    update_option( 'keon_toolset_kirki_notice_dismissed', 1 );

    $redirect_url = wp_get_referer();
    if ( empty( $redirect_url ) ) {
        $redirect_url = admin_url();
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_keon_toolset_dismiss_kirki_notice', 'keon_toolset_handle_kirki_notice_dismiss' );

/**
 * Resets dismissed state for the Kirki compatibility notice.
 *
 * @since    2.4.8
 */
function keon_toolset_handle_kirki_notice_reset() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to perform this action.', 'keon-toolset' ) );
    }

    check_admin_referer( 'keon_toolset_reset_kirki_notice' );
    delete_option( 'keon_toolset_kirki_notice_dismissed' );

    $redirect_url = wp_get_referer();
    if ( empty( $redirect_url ) ) {
        $redirect_url = admin_url();
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_keon_toolset_reset_kirki_notice', 'keon_toolset_handle_kirki_notice_reset' );

/**
 * Prints Kirki version compatibility notice markup.
 *
 * @since    2.4.8
 */
function keon_toolset_kirki_version_notice_markup() {
    ?>
    
        <h2><?php esc_html_e( 'Theme Compatibility Notice:', 'keon-toolset' ); ?></h2>
        <p>
        <?php esc_html_e( 'We strongly recommend using Kirki 5.1.1 as it is stable and fully compatible with our theme. Please temporarily avoid updating beyond 5.1.1 (including latest version 5.2.3) for now due to known issues, and we will update compatibility once resolved.', 'keon-toolset' ); ?><br/>
        <?php esc_html_e( 'Download Kirki version 5.1.1 here: ', 'keon-toolset' ); ?>
        <a href="<?php echo esc_url( 'https://downloads.wordpress.org/plugin/kirki.5.1.1.zip' ); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'https://downloads.wordpress.org/plugin/kirki.5.1.1.zip', 'keon-toolset' ); ?>
        </a>
    </p>
    <p>
        <a class="button button-secondary" href="<?php echo esc_url( keon_toolset_kirki_notice_dismiss_url() ); ?>">
            <?php esc_html_e( 'Dismiss', 'keon-toolset' ); ?>
        </a>
    </p>
    <?php
}

/**
 * Shows Kirki compatibility notice on wp-admin dashboard pages.
 *
 * @since    2.4.8
 */
function keon_toolset_kirki_admin_notice() {
    if ( keon_toolset_kirki_notice_is_dismissed() ) {
        return;
    }

    ?>
    <div class="notice notice-warning is-dismissible">
        <?php keon_toolset_kirki_version_notice_markup(); ?>
    </div>
    <?php
}
add_action( 'admin_notices', 'keon_toolset_kirki_admin_notice' );

/**
 * Injects Kirki compatibility notice in Customizer controls panel.
 *
 * @since    2.4.8
 */
function keon_toolset_kirki_customizer_notice_script() {
    if ( keon_toolset_kirki_notice_is_dismissed() ) {
        return;
    }

    $dismiss_url  = esc_url( keon_toolset_kirki_notice_dismiss_url() );
    $download_url = esc_url( 'https://downloads.wordpress.org/plugin/kirki.5.1.1.zip' );
    $title_text   = esc_html__( 'Theme Compatibility Notice:', 'keon-toolset' );
    $message_text = esc_html__( 'We strongly recommend using Kirki 5.1.1 as it is stable and fully compatible with our theme. Please temporarily avoid updating beyond 5.1.1 (including latest version 5.2.3) for now due to known issues, and we will update compatibility once resolved.', 'keon-toolset' );
    $link_label   = esc_html__( 'Download Kirki version 5.1.1 here:', 'keon-toolset' );
    $dismiss_text = esc_html__( 'Dismiss', 'keon-toolset' );
    $close_text   = esc_html__( 'Close this notice.', 'keon-toolset' );

    ?>
    <script>
        ( function() {
            function insertKirkiNotice() {
                if ( document.getElementById( 'keon-toolset-customizer-kirki-notice' ) ) {
                    return;
                }

                var container = document.querySelector( '#customize-theme-controls .wp-full-overlay-sidebar-content' );
                if ( ! container ) {
                    container = document.querySelector( '#customize-theme-controls' );
                }
                if ( ! container ) {
                    return;
                }

                var notice = document.createElement( 'div' );
                notice.id = 'keon-toolset-customizer-kirki-notice';
                notice.className = 'notice notice-warning is-dismissible';
                notice.style.margin = '12px';

                var dismissButton = document.createElement( 'button' );
                dismissButton.type = 'button';
                dismissButton.className = 'notice-dismiss';
                dismissButton.innerHTML = '<span class="screen-reader-text"><?php echo esc_js( $close_text ); ?></span>';
                dismissButton.addEventListener( 'click', function( event ) {
                    event.preventDefault();
                    notice.style.display = 'none';
                } );

                notice.innerHTML = '<p><strong><?php echo esc_js( $title_text ); ?></strong> <?php echo esc_js( $message_text ); ?><br><?php echo esc_js( $link_label ); ?> <a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_js( $download_url ); ?></a></p><p><a class="button button-secondary" href="<?php echo esc_url( $dismiss_url ); ?>"><?php echo esc_js( $dismiss_text ); ?></a></p>';
                notice.appendChild( dismissButton );

                var activeThemeSection = document.getElementById( 'customize-section-themes' );
                if ( activeThemeSection && activeThemeSection.parentNode ) {
                    if ( activeThemeSection.nextSibling ) {
                        activeThemeSection.parentNode.insertBefore( notice, activeThemeSection.nextSibling );
                    } else {
                        activeThemeSection.parentNode.appendChild( notice );
                    }
                } else {
                    var upsellSection = document.getElementById( 'accordion-section-theme_upsell' );
                    if ( upsellSection && upsellSection.parentNode ) {
                        upsellSection.parentNode.insertBefore( notice, upsellSection );
                    } else {
                        container.insertBefore( notice, container.firstChild );
                    }
                }
            }

            if ( document.readyState === 'loading' ) {
                document.addEventListener( 'DOMContentLoaded', insertKirkiNotice );
            } else {
                insertKirkiNotice();
            }

            // Customizer panes can render asynchronously.
            setTimeout( insertKirkiNotice, 400 );
            setTimeout( insertKirkiNotice, 1200 );
        }() );
    </script>
    <?php
}
add_action( 'customize_controls_print_footer_scripts', 'keon_toolset_kirki_customizer_notice_script', 1 );

if ( keon_toolset_theme_check( 'bosa' ) && !keon_toolset_theme_check( 'bosa-pro' ) ){
    require KEON_TOOLSET_PATH . 'includes/class-bosa-pro-upgrade-notice.php';
}

/**
 * Register all of the hooks related to the admin area functionality
 * of the plugin.
 *
 * @since    1.0.0
 */
$plugin_admin = keon_toolset_hooks();
add_filter( 'advanced_import_demo_lists', array( $plugin_admin,'keon_toolset_demo_import_lists'), 10, 1 );
add_filter( 'admin_menu', array( $plugin_admin, 'import_menu' ), 10, 1 );
add_filter( 'wp_ajax_keon_toolset_getting_started', array( $plugin_admin, 'install_advanced_import' ), 10, 1 );
add_filter( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ), 10, 1 );
add_filter( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ), 10, 1 );
add_action( 'advanced_import_replace_term_ids', array( $plugin_admin, 'replace_term_ids' ), 20 );
add_action( 'advanced_import_replace_post_ids', array( $plugin_admin, 'replace_attachment_ids' ), 30 );

if( ( keon_toolset_theme_check( 'shoppable' ) && !keon_toolset_theme_check( 'hello-shoppable' ) ) || ( keon_toolset_theme_check( 'bosa-media-marketing' ) || keon_toolset_theme_check( 'bosa-business-firm' ) || keon_toolset_theme_check( 'bosa-photograph' ) || keon_toolset_theme_check( 'bosa-interior-design' ) || keon_toolset_theme_check( 'bosa-cleaning-service' ) || keon_toolset_theme_check( 'bosa-veterinary' ) || keon_toolset_theme_check( 'bosa-yoga' ) || keon_toolset_theme_check( 'bosa-logistics' ) || keon_toolset_theme_check( 'bosa-crypto' ) || keon_toolset_theme_check( 'bosa-clinic' ) || keon_toolset_theme_check( 'bosa-it-services' ) || keon_toolset_theme_check( 'bosa-university' ) || keon_toolset_theme_check( 'bosa-creative-agency' ) || keon_toolset_theme_check( 'bosa-garden-care' ) || keon_toolset_theme_check( 'bosa-construction-company' ) || keon_toolset_theme_check( 'bosa-travel-agency' ) || keon_toolset_theme_check( 'bosa-business-agency' ) || keon_toolset_theme_check( 'bosa-online-marketing' ) || keon_toolset_theme_check( 'bosa-law-firm' ) || keon_toolset_theme_check( 'bosa-veterinary-care' ) || keon_toolset_theme_check( 'bosa-ai-robotics-sector' ) || keon_toolset_theme_check( 'bosa-charity-firm' ) || keon_toolset_theme_check( 'bosa-restaurant-inn' ) || keon_toolset_theme_check( 'bosa-business-solutions' ) || keon_toolset_theme_check( 'bosa-portfolio-bio' ) || keon_toolset_theme_check( 'bosa-event-organizer' ) || keon_toolset_theme_check( 'bosa-ev-rental-car' ) || keon_toolset_theme_check( 'bosa-finance-consult' ) || keon_toolset_theme_check( 'bosa-beauty-care' ) || keon_toolset_theme_check( 'bosa-app-hub' ) || keon_toolset_theme_check( 'bosa-real-estate-group' ) || keon_toolset_theme_check( 'bosa-gym-fitness' ) || keon_toolset_theme_check( 'bosa-influencer-marketing' ) || keon_toolset_theme_check( 'bosa-handyman-services' ) || keon_toolset_theme_check( 'bosa-education-zone' ) || keon_toolset_theme_check( 'bosa-insurance-agency' ) || keon_toolset_theme_check( 'bosa-resort' ) || keon_toolset_theme_check( 'bosa-business-coach' ) || keon_toolset_theme_check( 'bosa-medical-care' ) || keon_toolset_theme_check( 'bosa-preschool' ) || keon_toolset_theme_check( 'bosa-tech-company' ) || keon_toolset_theme_check( 'bosa-driving-school' ) || keon_toolset_theme_check( 'bosa-advertising-agency' ) || keon_toolset_theme_check( 'bosa-cyber-security' ) || keon_toolset_theme_check( 'bosa-startup-business' ) || keon_toolset_theme_check( 'bosa-job-portal' ) || keon_toolset_theme_check( 'bosa-finance-company' ) || keon_toolset_theme_check( 'bosa-courier-service' ) || keon_toolset_theme_check( 'bosa-bakery' ) || keon_toolset_theme_check( 'bosa-health-coach' ) || keon_toolset_theme_check( 'bosa-freelancer' )) ){
    require KEON_TOOLSET_PATH . 'demo/base-install/base-install.php';
    add_action('advanced_import_after_complete_screen', array( $plugin_admin, 'kt_advance_import' ));
    add_action('advanced_import_after_content_screen', array( $plugin_admin, 'kt_advance_import_transient' )); 
}

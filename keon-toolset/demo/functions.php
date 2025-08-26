<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * The Keon Toolset hooks callback functionality of the plugin.
 *
 */
class Keon_Toolset_Hooks {

    private $hook_suffix;

    public static function instance() {

        static $instance = null;

        if ( null === $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        add_action( 'switch_theme', array( $this, 'flush_transient' ) );
        add_filter( 'advanced_export_include_options', array( $this, 'export_include_options' ) );
        add_action( 'advanced_import_before_complete_screen', array( $this, 'update_elementskit_mega_menu_post' ) );
        add_filter( 'advanced_import_update_value_elementskit_options', array( $this, 'update_elementskit_options' ) );
    }

    /**
     * Check to see if advanced import plugin is not installed or activated.
     * Adds the Demo Import menu under Apperance.
     *
     * @since    1.0.0
     */
    public function import_menu() {
        if( !class_exists( 'Advanced_Import' ) ){
            $this->hook_suffix[] = add_theme_page( esc_html__( 'Demo Import ','keon-toolset' ), esc_html__( 'Demo Import','keon-toolset'  ), 'manage_options', 'advanced-import', array( $this, 'demo_import_screen' ) );
        } 
    }

    /**
     * Enqueue styles.
     *
     * @since    1.0.0
     */
    public function enqueue_styles( $hook_suffix ) {
        if ( !is_array( $this->hook_suffix ) || !in_array( $hook_suffix, $this->hook_suffix ) ){
            return;
        }
        wp_enqueue_style( 'keon-toolset', KEON_TEMPLATE_URL . 'assets/keon-toolset.css',array( 'wp-admin', 'dashicons' ), '1.0.0', 'all' );
    }

    /**
     * Enqueue scripts.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts( $hook_suffix ) {
        if ( !is_array($this->hook_suffix) || !in_array( $hook_suffix, $this->hook_suffix )){
            return;
        }

        wp_enqueue_script( 'keon-toolset', KEON_TEMPLATE_URL . 'assets/keon-toolset.js', array( 'jquery' ), '1.0.0', true );
        wp_localize_script( 'keon-toolset', 'keon_toolset', array(
            'btn_text' => esc_html__( 'Processing...', 'keon-toolset' ),
            'nonce'    => wp_create_nonce( 'keon_toolset_nonce' )
        ) );
    }

    /**
     * The demo import menu page comtent.
     *
     * @since    1.0.0
     */
    public function demo_import_screen() {
        ?>
        <div id="ads-notice">
            <div class="ads-container">
                <img class="ads-screenshot" src="<?php echo esc_url( keon_toolset_get_theme_screenshot() ) ?>" >
                <div class="ads-notice">
                    <h2>
                        <?php
                        printf(
                            esc_html__( 'Thank you for choosing %1$s! It is detected that an essential plugin, Advanced Import, is not activated. Importing demos for %1$s can begin after pressing the button below.', 'keon-toolset' ), '<strong>'. esc_html( wp_get_theme()->get('Name') ). '</strong>');
                        ?>
                    </h2>

                    <p class="plugin-install-notice"><?php esc_html_e( 'Clicking the button below will install and activate the Advanced Import plugin.', 'keon-toolset' ); ?></p>

                    <a class="ads-gsm-btn button" href="#" data-name="" data-slug="" aria-label="<?php esc_html_e( 'Get started with the Theme', 'keon-toolset' ); ?>">
                        <?php esc_html_e( 'Install Now', 'keon-toolset' );?>
                    </a>
                </div>
            </div>
        </div>
        <?php

    }

    /**
     * Installs or activates advanced import plugin if not detected as such.
     *
     * @since    1.0.0
     */
    public function install_advanced_import() {

        check_ajax_referer( 'keon_toolset_nonce', 'security' );

        $slug   = 'advanced-import';
        $plugin = 'advanced-import/advanced-import.php';
        $status = array(
            'install' => 'plugin',
            'slug'    => sanitize_key( wp_unslash( $slug ) ),
        );
        $status['redirect'] = admin_url( '/themes.php?page=advanced-import&browse=all&at-gsm-hide-notice=welcome' );

        if ( is_plugin_active_for_network( $plugin ) || is_plugin_active( $plugin ) ) {
            // Plugin is activated
            wp_send_json_success( $status );
        }

        if ( ! current_user_can( 'install_plugins' ) ) {
            $status['errorMessage'] = __( 'Sorry, you are not allowed to install plugins on this site.', 'keon-toolset' );
            wp_send_json_error( $status );
        }

        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Looks like a plugin is installed, but not active.
        if ( file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
            $plugin_data          = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            $status['plugin']     = $plugin;
            $status['pluginName'] = $plugin_data['Name'];

            if ( current_user_can( 'activate_plugin', $plugin ) && is_plugin_inactive( $plugin ) ) {
                $result = activate_plugin( $plugin );

                if ( is_wp_error( $result ) ) {
                    $status['errorCode']    = $result->get_error_code();
                    $status['errorMessage'] = $result->get_error_message();
                    wp_send_json_error( $status );
                }

                wp_send_json_success( $status );
            }
        }

        $api = plugins_api(
            'plugin_information',
            array(
                'slug'   => sanitize_key( wp_unslash( $slug ) ),
                'fields' => array(
                    'sections' => false,
                ),
            )
        );

        if ( is_wp_error( $api ) ) {
            $status['errorMessage'] = $api->get_error_message();
            wp_send_json_error( $status );
        }

        $status['pluginName'] = $api->name;

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $api->download_link );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $status['debug'] = $skin->get_upgrade_messages();
        }

        if ( is_wp_error( $result ) ) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error( $status );
        } elseif ( is_wp_error( $skin->result ) ) {
            $status['errorCode']    = $skin->result->get_error_code();
            $status['errorMessage'] = $skin->result->get_error_message();
            wp_send_json_error( $status );
        } elseif ( $skin->get_errors()->get_error_code() ) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error( $status );
        } elseif ( is_null( $result ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            WP_Filesystem();
            global $wp_filesystem;

            $status['errorCode']    = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'keon-toolset' );

            // Pass through the error from WP_Filesystem if one was raised.
            if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
                $status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
            }

            wp_send_json_error( $status );
        }

        $install_status = install_plugin_install_status( $api );

        if ( current_user_can( 'activate_plugin', $install_status['file'] ) && is_plugin_inactive( $install_status['file'] ) ) {
            $result = activate_plugin( $install_status['file'] );

            if ( is_wp_error( $result ) ) {
                $status['errorCode']    = $result->get_error_code();
                $status['errorMessage'] = $result->get_error_message();
                wp_send_json_error( $status );
            }
        }

        wp_send_json_success( $status );

    }
    /**
     * Demo list of the Keon Themes with their recommended plugins.
     *
     * @since    1.0.0
     */
    public function keon_toolset_demo_import_lists( $demos ){
        if( get_transient( 'keon_toolset_demo_lists' ) ){
            return array_merge( get_transient( 'keon_toolset_demo_lists' ), $demos );
        }
        $theme_slug = keon_toolset_get_theme_slug();
        $demo_lists = array();
        if( keon_toolset_theme_check( 'gutener' ) ){
            // Get the demos list
            while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/gutener%2Fdemolist%2Ejson?ref=main' ) );
                if( is_wp_error( $request_demo_list_body ) ) {
                    return $demos; // Bail early
                }
                $demo_list_std     = json_decode( $request_demo_list_body, true );
                $demo_list_array   = (array) $demo_list_std;
                $demo_list_content = $demo_list_array['content'];
                $demo_lists_json   = base64_decode( $demo_list_content );
                $demo_lists        = json_decode( $demo_lists_json, true );
                set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
            }
            while( empty( get_transient( 'keon_toolset_theme_state_list' ) ) ){
                $request_state_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/gutener%2Fstate%2Ejson?ref=main' ) );
                if( is_wp_error( $request_state_list_body ) ) {
                    return $demos; // Bail early
                }
                $state_list_std     = json_decode( $request_state_list_body,true );
                $state_list_array   = (array) $state_list_std;
                $state_list_content = $state_list_array['content'];
                $state_lists_json   = base64_decode( $state_list_content );
                $state_lists        = json_decode( $state_lists_json, true );
                $theme_state_list   = $state_lists[$theme_slug];
                set_transient( 'keon_toolset_theme_state_list', $theme_state_list, DAY_IN_SECONDS );
            }
            
            $demo_lists = get_transient( 'keon_toolset_demo_lists' );
            $theme_state_list = get_transient( 'keon_toolset_theme_state_list' );
            $i = 0;
            
            foreach($theme_state_list as $list){
                if( !is_array( $list ) ){
                    $pos = array_search( $list, array_column( $demo_lists,'title' ) );
                    if( !$pos === FALSE || $pos == 0 ){
                        $demo_lists[$pos]['is_pro'] = false;
                        $this->array_move( $demo_lists, $pos, $i );   
                    }
                }else{
                    $pro_item = $list['pro'];
                    $pos = array_search( $pro_item,array_column( $demo_lists,'title' ) );
                    if( !$pos === FALSE ){
                        $this->array_move( $demo_lists, $pos, $i );
                    }
                }
                $i++;
            }
            foreach ( $demo_lists as &$val ){
                $hit = $this->in_multiarray( $val['title'], $theme_state_list );
                if( !$hit ){
                    $pos_demo = array_search( $val['title'], array_column( $demo_lists,'title' ) );
                    array_splice( $demo_lists, $pos_demo, 1 );
                }
            }
            set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
            return array_merge( $demo_lists, $demos );
        }elseif( keon_toolset_theme_check( 'bosa' ) ){
            if( $theme_slug == 'bosa' ){
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $bosa_demo_list = 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fv2%2Fbosa-demo-list%2Ejson?ref=main';
                    
                    $bosa_demo_list_body = wp_remote_retrieve_body( wp_remote_get( $bosa_demo_list ) );
                    if( is_wp_error( $bosa_demo_list_body ) ) {
                        return $demos; // Bail early
                    }
                    $demo_list_std     = json_decode( $bosa_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $bosa_demos_decoded = json_decode( $demo_lists_json, true );
                    $demo_lists = is_array( $bosa_demos_decoded ) ? $bosa_demos_decoded : array();

                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                return array_merge( $demo_lists, $demos );
            }
            while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                $full_args_list = 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fv2%2Fbosa-full-args-demo-list%2Ejson?ref=main';
                
                $full_args_list_body = wp_remote_retrieve_body( wp_remote_get( $full_args_list ) );
                if( is_wp_error( $full_args_list_body ) ) {
                    return $demos; // Bail early
                }
                $demo_list_std     = json_decode( $full_args_list_body, true );
                $demo_list_array   = (array) $demo_list_std;
                $demo_list_content = $demo_list_array['content'];
                $demo_lists_json   = base64_decode( $demo_list_content );
                $full_args_decoded     = json_decode( $demo_lists_json, true );
                $demo_lists = is_array( $full_args_decoded ) ? $full_args_decoded : array();          
                set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
            }

            if( $theme_slug == 'bosa-pro' ){
                return array_merge( $demo_lists, $demos );
            }

            // common demo list
            while( empty( get_transient( 'keon_toolset_bosa_common_demo_lists' ) ) ){
                $common_list = 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fv2%2Fbosa-common-demo-list%2Ejson?ref=main';
                $common_list_body = wp_remote_retrieve_body( wp_remote_get( $common_list ) );
                if( is_wp_error( $common_list_body ) ) {
                    return $demos; // Bail early
                }
                $demo_list_std     = json_decode( $common_list_body, true );
                $demo_list_array   = (array) $demo_list_std;
                $demo_list_content = $demo_list_array['content'];
                $demo_lists_json   = base64_decode( $demo_list_content );
                $common_list_decoded     = json_decode( $demo_lists_json, true );
                $common_demo_list = is_array( $common_list_decoded ) ? $common_list_decoded : array();
                
                set_transient( 'keon_toolset_bosa_common_demo_lists', $common_demo_list, DAY_IN_SECONDS );
            }

            if( $theme_slug && !empty( $demo_lists ) && !empty( $common_demo_list ) ){
                $theme_list[$theme_slug] = isset( $demo_lists[$theme_slug] ) ? $demo_lists[$theme_slug] : array();
                $theme_list[$theme_slug.'-pro'] = isset( $common_demo_list[$theme_slug.'-pro'] ) ? $common_demo_list[$theme_slug.'-pro'] : array();
                $demo_lists =  array_merge( $theme_list, $common_demo_list, $theme_list );
            }
            set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
            return array_merge( $demo_lists, $demos );
        }elseif( keon_toolset_theme_check( 'shoppable' ) ){
            while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                $shoppable_demo_list = 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fv2%2Fshoppable-demo-list%2Ejson?ref=main';
                $shoppable_demo_list_body = wp_remote_retrieve_body( wp_remote_get( $shoppable_demo_list ) );
                if( is_wp_error( $shoppable_demo_list_body ) ) {
                    return $demos; // Bail early
                }
                $demo_list_std     = json_decode( $shoppable_demo_list_body, true );
                $demo_list_array   = (array) $demo_list_std;
                $demo_list_content = $demo_list_array['content'];
                $demo_lists_json   = base64_decode( $demo_list_content );
                $shoppable_demos_decoded     = json_decode( $demo_lists_json, true );
                $shoppable_demos = is_array( $shoppable_demos_decoded ) ? $shoppable_demos_decoded : array();

                if( $theme_slug && !empty( $shoppable_demos ) ){
                    $theme_list[$theme_slug] = isset( $shoppable_demos[$theme_slug] ) ? $shoppable_demos[$theme_slug] : array();
                    $theme_list[$theme_slug.'-pro'] = isset( $shoppable_demos[$theme_slug.'-pro'] ) ? $shoppable_demos[$theme_slug.'-pro'] : array();
                    $demo_lists = array_merge( $theme_list, $shoppable_demos );
                }
                set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
            }
            return array_merge( $demo_lists, $demos );
        }
        return $demos;
    }

    /**
     * Reposition of the demos in the demolist.
     *
     * @since    1.1.4
     */
    public function array_move( &$a, $oldpos, $newpos ) {
        if ( $oldpos == $newpos ) {return;}
        array_splice( $a, max( $newpos, 0 ), 0, array_splice( $a, max( $oldpos, 0 ), 1 ) );
    }

    /**
     * Check to if element is in the demolist.
     *
     * @since    1.1.4
     */
    public function in_multiarray( $elem, $array )
    {
        $top = sizeof( $array ) - 1;
        $bottom = 0;
        while( $bottom <= $top )
        {
            if( $array[$bottom] == $elem )
                return true;
            else
                if( is_array( $array[$bottom] ) )
                    if( $array[$bottom]['pro'] == $elem )
                        return true;
                   
            $bottom++;
        }       
        return false;
    }
    /**
     * Deletes the demo and template lists upon theme switch.
     *
     * @since    1.1.4
     */
    public function flush_transient(){
        delete_transient( 'keon_toolset_demo_lists' );
        delete_transient( 'keon_toolset_theme_state_list' );
        delete_transient( 'keon_toolset_template_lists' );
        delete_transient( 'keon_toolset_template_state_list' );
        delete_transient( 'keon_toolset_bosa_common_demo_lists' );
    }

    /**
     * Replaces categories id during demo import.
     *
     * @since    1.1.9
     */
    public function replace_term_ids( $replace_term_ids ){

        /*terms IDS*/
        $term_ids = array(
            'slider_category',
            'highlight_posts_category',
            'feature_posts_category',
            'latest_posts_category',
            'feature_posts_two_category',
        );

        return array_merge( $replace_term_ids, $term_ids );
    }

    /**
     * Replaces attachment id during demo import.
     *
     * @since    1.1.9
     */
    public function replace_attachment_ids( $replace_attachment_ids ){
        $theme_slug = keon_toolset_get_theme_slug();
        switch( $theme_slug ):
            case 'bosa-pro':
            case 'bosa':
            case 'bosa-business':
            case 'bosa-corporate-dark':
            case 'bosa-consulting':
            case 'bosa-blog-dark':
            case 'bosa-charity':
            case 'bosa-music':
            case 'bosa-travelers-blog':
            case 'bosa-insurance':
            case 'bosa-blog':
            case 'bosa-marketing':
            case 'bosa-lawyer':
            case 'bosa-wedding':
            case 'bosa-corporate-business':
            case 'bosa-fitness':
            case 'bosa-finance':
            case 'bosa-news-blog':
            case 'bosa-store':
            case 'bosa-ecommerce':
            case 'bosa-shop':
            case 'bosa-shopper':
            case 'bosa-online-shop':
            case 'bosa-storefront':
            case 'bosa-ecommerce-shop':
            case 'bosa-shop-store':
            case 'bosa-construction-shop':
            case 'bosa-travel-shop':
            case 'bosa-beauty-shop':
            case 'bosa-shop-dark':
            case 'bosa-charity-fundraiser':
            case 'bosa-shopfront':
            case 'bosa-medical-health':
            case 'bosa-ev-charging-station':
            case 'bosa-marketplace':
            case 'bosa-travel-tour':
            case 'bosa-education-hub':
            case 'bosa-digital-agency':
            case 'bosa-decor-shop':
            case 'bosa-biz':
            case 'bosa-construction-industrial':
            case 'bosa-agency-dark':
            case 'bosa-online-education':
            case 'hello-shoppable':
            case 'bosa-business-services':
            case 'bosa-event-conference':
            case 'bosa-rental-car':
            case 'bosa-real-estate':
            case 'bosa-restaurant-cafe':
            case 'bosa-digital-marketing':
            case 'shoppable-fashion':
            case 'bosa-finance-business':
            case 'shoppable-wardrobe':
            case 'bosa-kindergarten':
            case 'bosa-portfolio-resume':
            case 'shoppable-marketplace':
            case 'bosa-corpo':
            case 'bosa-accounting':
            case 'shoppable-grocery-store':
            case 'bosa-dental-care':
            case 'shoppable-furnish':
            case 'bosa-mobile-app':
            case 'bosa-educare':
            case 'bosa-plumber':
            case 'shoppable-jewelry':
            case 'bosa-ai-robotics':
            case 'shoppable-camera':
            case 'bosa-hotel':
            case 'bosa-media-marketing':
            case 'bosa-business-firm':
            case 'bosa-photograph':
            case 'bosa-interior-design':
            case 'bosa-cleaning-service':
            case 'bosa-veterinary':
            case 'bosa-yoga':
            case 'bosa-logistics':
            case 'bosa-crypto':
            case 'bosa-clinic':
            case 'bosa-it-services':
            case 'bosa-university':
            case 'bosa-creative-agency':
            case 'shoppable-beauty':
            case 'bosa-garden-care':
            case 'bosa-construction-company':
            case 'bosa-travel-agency':
            case 'bosa-business-agency':
            case 'bosa-online-marketing':
            case 'bosa-law-firm':
            case 'shoppable-style':
            case 'bosa-veterinary-care':
            case 'bosa-ai-robotics-sector':
            case 'bosa-charity-firm':
            case 'bosa-restaurant-inn':
            case 'shoppable-electronics':
            case 'bosa-business-solutions':
            case 'bosa-portfolio-bio':
            case 'bosa-event-organizer':
            case 'bosa-ev-rental-car':
            case 'bosa-finance-consult':
            case 'bosa-beauty-care':
                /*attachments IDS*/
                $attachment_ids = array(
                    'banner_image',
                    'error404_image',
                    'footer_image',
                    'bottom_footer_image',
                    'box_frame_background_image',
                    'fixed_header_separate_logo',
                    'header_separate_logo',
                    'header_advertisement_banner',
                    'preloader_custom_image',
                    'notification_bar_image',
                    'slider_item',
                    'blog_advertisement_banner',
                    'featured_pages_one',
                    'featured_pages_two',
                    'featured_pages_three',
                    'featured_pages_four',
                    'blog_services_page_one',
                    'blog_services_page_two',
                    'blog_services_page_three',
                    'teams_page_one',
                    'teams_page_two',
                    'teams_page_three'
                );
                break;
            default:
                $attachment_ids = array();
                break;
        endswitch;
        return array_merge( $replace_attachment_ids, $attachment_ids );
    }

    public function kt_advance_import(){
        $active_theme = wp_get_theme();
        $text_domain = $active_theme->get( 'TextDomain' );
        $transient = get_transient( 'imported_option' );
        $option = $transient['options'];
        $demo_theme = '';
        foreach( $option as $key => $value ){
            if( strpos( $key, 'theme_mods_' ) !== false && strpos( $key, '-child' ) === false ){
                if( $key == 'theme_mods_'.$text_domain ){
                   delete_transient( 'imported_option' );
                   return;
                }
                 $demo_theme = $key;
            }
        }

        $demo_options = get_option( $demo_theme );
        update_option( 'theme_mods_'.$text_domain , $demo_options );
        delete_transient( 'imported_option' );

    }
    
    public function kt_advance_import_transient(){
        $import_option = get_transient( 'options.json' );
        set_transient('imported_option',$import_option);
    }

    /**
     * Update Mega Menu active nav menu ids in demo import. 
     *
     * @since    2.1.8
     */
    function update_elementskit_options( $value = "", $option = "elementskit_options" ){
        $megamenu_settings = isset( $value['megamenu_settings'] ) ? $value['megamenu_settings'] : '';
        $replaced_ids = array();
        if( is_array( $megamenu_settings ) ){
            foreach( $megamenu_settings as $location => $enabled ){
                if( $enabled ){
                    $term_id = '';
                    if( strpos( $location, 'menu_location' ) !== false  ){
                        $term_id = substr(  $location, 14 );
                    }
                    $advanced_import_obj = advanced_import_admin();
                    $new_id = $advanced_import_obj->imported_term_id( $term_id );

                    $value['megamenu_settings']['menu_location_'.$new_id]= array( 
                        'is_enabled' => 1
                    );
                    
                    $replaced_ids[] = $new_id;
                    if( $term_id != $new_id && !in_array($term_id, $replaced_ids) ){
                        unset( $value['megamenu_settings']['menu_location_'.$term_id] );
                    }
                }
            }
        }
        $post_ids = get_transient( 'imported_post_ids' );
        set_transient('kt_adim_imported_post_ids', $post_ids, 60 * 60 * 24);
        return $value;
    }

    /**
     * Updates post_title and post_name of elementskit_content post_type in demo import. 
     *
     * @since    2.1.8
     */
    function update_elementskit_mega_menu_post(){
        
        $post_ids = get_transient( 'kt_adim_imported_post_ids' );
        if( $post_ids !== false ){
            set_transient('imported_post_ids', $post_ids, 60 * 60 * 24);

            $query = new WP_Query( array( 'post_type' => 'elementskit_content' ) );
            $posts = $query->get_posts();
            if( is_array( $posts ) && !empty( $posts ) ){
                foreach( $posts as $key => $value ){
                    $old_id = '';
                    if( strpos( $value->post_title, 'dynamic-content-megamenu-menuitem' ) !== false  ){
                        $old_id = substr(  $value->post_title, 33 );
                        $advanced_import_obj = advanced_import_admin();
                        $new_id = $advanced_import_obj->imported_post_id( $old_id );
                        $elementskit_post = array(
                            'ID'           => $value->ID,
                            'post_title'   => 'dynamic-content-megamenu-menuitem'.$new_id,
                            'post_name'   => 'dynamic-content-megamenu-menuitem'.$new_id,
                              
                        );

                        // Update the specified post into the database
                        wp_update_post( $elementskit_post );
                    }
                }
            }
            delete_transient( 'kt_adim_imported_post_ids' );
            delete_transient( 'imported_post_ids' );
        }
    }

    /**
     * Includes options in advanced export plugin demo zip.
     *
     * @since    2.1.8
     */
    public function export_include_options( $included_options ){
        $my_options = array(
            'elementskit_options',
        );
        return array_unique (array_merge( $included_options, $my_options));
    }
}

/**
 * Begins execution of the hooks.
 *
 * @since    1.0.0
 */
function keon_toolset_hooks( ) {
    return Keon_Toolset_Hooks::instance();
}
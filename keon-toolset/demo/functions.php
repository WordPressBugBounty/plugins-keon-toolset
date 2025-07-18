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

        $theme_slug = keon_toolset_get_theme_slug();
        switch( $theme_slug ):
            case 'gutener-pro':
            case 'gutener':
            case 'gutener-charity-ngo':
            case 'gutener-pro-child':
            case 'gutener-medical':
            case 'blog-gutener':
            case 'gutener-consultancy':
            case 'gutener-business':
            case 'gutener-corporate':
            case 'gutener-education':
            case 'gutener-corporate-business':
                // Get the demos list
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/gutener%2Fdemolist%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
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
                        return false; // Bail early
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

                break;
            case 'bosa':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-pro':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-pro-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-business':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-business-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-corporate-dark':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-corporate-dark-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-consulting':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-consulting-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-blog-dark':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-blog-dark-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-charity':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-charity-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
             case 'bosa-music':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-music-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-travelers-blog':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-travelers-blog-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-insurance':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-insurance-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-blog':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-blog-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-marketing':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-marketing-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-lawyer':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-lawyer-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-wedding':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-wedding-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-corporate-business':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-corporate-business-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-fitness':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-fitness-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-finance':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-finance-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-news-blog':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-news-blog-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-store':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-store-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-ecommerce':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-ecommerce-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-shopper':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-shopper-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-online-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-online-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-storefront':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-storefront-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-ecommerce-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-ecommerce-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-shop-store':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-shop-store-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-construction-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-construction-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-travel-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-travel-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-beauty-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-beauty-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-shop-dark':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-shop-dark-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-charity-fundraiser':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-charity-fundraiser-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-shopfront':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-shopfront-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-medical-health':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-medical-health-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-ev-charging-station':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-ev-charging-station-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-marketplace':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-marketplace-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-travel-tour':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-travel-tour-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-education-hub':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-education-hub-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-digital-agency':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-digital-agency-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-decor-shop':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-decor-shop-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-biz':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-biz-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-construction-industrial':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-construction-industrial-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-agency-dark':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-agency-dark-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
             case 'bosa-online-education':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-online-education-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'hello-shoppable':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fhello-shoppable-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-business-services':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-business-services-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-event-conference':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-event-conference-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-rental-car':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-rental-car-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-real-estate':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-real-estate-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-restaurant-cafe':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-restaurant-cafe-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-digital-marketing':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-digital-marketing-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-fashion':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-fashion-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-finance-business':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-finance-business-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-wardrobe':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-wardrobe-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-kindergarten':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-kindergarten-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-portfolio-resume':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-portfolio-resume-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-marketplace':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-marketplace-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-corpo':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-corpo-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-accounting':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-accounting-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-grocery-store':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-grocery-store-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-dental-care':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-dental-care-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-furnish':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-furnish-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-mobile-app':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-mobile-app-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-educare':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-educare-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-plumber':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-plumber-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-jewelry':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-jewelry-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-ai-robotics':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-ai-robotics-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-camera':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-camera-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-hotel':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-hotel-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-media-marketing':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-media-marketing-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;  
            case 'bosa-business-firm':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-business-firm-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;   
            case 'bosa-photograph':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-photograph-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-interior-design':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-interior-design-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-cleaning-service':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-cleaning-service-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;       
            case 'bosa-veterinary':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-veterinary-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;     
            case 'bosa-yoga':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-yoga-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-logistics':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-logistics-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-crypto':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-crypto-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-clinic':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-clinic-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-it-services':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-it-services-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;      
            case 'bosa-university':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-university-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-creative-agency':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-creative-agency-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;   
            case 'shoppable-beauty':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-beauty-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;  
            case 'bosa-garden-care':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-garden-care-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break; 
            case 'bosa-construction-company':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-construction-company-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;   
            case 'bosa-travel-agency':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-travel-agency-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-business-agency':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-business-agency-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-online-marketing':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-online-marketing-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-law-firm':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-law-firm-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-style':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-style-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-veterinary-care':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-veterinary-care-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-ai-robotics-sector':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-ai-robotics-sector-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-charity-firm':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-charity-firm-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-restaurant-inn':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-restaurant-inn-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'shoppable-electronics':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/hello-shoppable%2Fshoppable-electronics-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-business-solutions':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-business-solutions-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-portfolio-bio':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-portfolio-bio-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            case 'bosa-event-organizer':
                while( empty( get_transient( 'keon_toolset_demo_lists' ) ) ){
                    $request_demo_list_body = wp_remote_retrieve_body( wp_remote_get( 'https://gitlab.com/api/v4/projects/53725287/repository/files/bosa%2Fbosa-event-organizer-demo-list%2Ejson?ref=main' ) );
                    if( is_wp_error( $request_demo_list_body ) ) {
                        return false; // Bail early
                    }
                    $demo_list_std     = json_decode( $request_demo_list_body, true );
                    $demo_list_array   = (array) $demo_list_std;
                    $demo_list_content = $demo_list_array['content'];
                    $demo_lists_json   = base64_decode( $demo_list_content );
                    $demo_lists        = json_decode( $demo_lists_json, true );
                    set_transient( 'keon_toolset_demo_lists', $demo_lists, DAY_IN_SECONDS );
                }
                $demo_lists = get_transient( 'keon_toolset_demo_lists' );
                break;
            default:
                $demo_lists = array();
                break;
        endswitch;
        return array_merge( $demo_lists, $demos );
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
        $megamenu_settings = $value['megamenu_settings'];
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
        set_transient('imported_post_ids', $post_ids, 60 * 60 * 24);

        $query = new WP_Query( array( 'post_type' => 'elementskit_content', ) );
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
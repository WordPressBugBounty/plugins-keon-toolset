<?php

if (!defined('ABSPATH')) exit;

if (!class_exists('Keon_Toolset_Admin_Notice')) {
    class Keon_Toolset_Admin_Notice {
        private $current_date;

        public function __construct() {
            $this->current_date = strtotime( 'now' );
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
            add_action( 'admin_init', [$this, 'check_pro_install'] );
            add_action( 'wp_ajax_remind_me_later_bosa_pro', [$this, 'remind_me_later_bosa_pro'] );
            add_action( 'wp_ajax_upgrade_bosa_pro_notice_dismiss', [$this, 'upgrade_dismiss'] );
        }

        public function admin_scripts() {
            wp_enqueue_script( 'keon-toolset-admin-notice', KEON_TEMPLATE_URL . 'assets/keon-toolset-admin-notice.js', array( 'jquery' ), '1.0.0', true );
            wp_localize_script( 'keon-toolset-admin-notice', 'KEON_BOSA_PRO_UPGRADE', 
                array( 
                    'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( 'kt_bosa_pro_upgrade_nonce' ),
                    'dismiss_nonce'     => wp_create_nonce( 'kt_bosa_pro_upgrade_dismiss_nonce' ),
                ) 
            );
        }

        public function check_pro_install() { 

            if ( $this->current_date >= (int)get_option('remind_me_later_bosa_pro_time') ) {
                if ( !get_option('upgrade_bosa_pro_notice_dismiss_' . KEON_TOOLSET_VERSION) ) {
                    add_action( 'admin_notices', [$this, 'admin_notice_bosa_pro' ]);
                }
            }
        }

        public function remind_me_later_bosa_pro() {
            $nonce = $_POST['nonce'];

            if ( !wp_verify_nonce( $nonce, 'kt_bosa_pro_upgrade_nonce')  || !current_user_can( 'manage_options' ) ) {
              exit; // Get out of here, the nonce is rotten!
            }

            update_option( 'remind_me_later_bosa_pro_time', strtotime('7 days') );
        }

        public function upgrade_dismiss() {
            $nonce = $_POST['nonce'];

            if ( !wp_verify_nonce( $nonce, 'kt_bosa_pro_upgrade_dismiss_nonce')  || !current_user_can( 'manage_options' ) ) {
              exit; // Get out of here, the nonce is rotten!
            }

            add_option( 'upgrade_bosa_pro_notice_dismiss_' . KEON_TOOLSET_VERSION, true );
        }

        public function admin_notice_bosa_pro() {
            $pro_img_url = KEON_TEMPLATE_URL . 'assets/img/bosa-pro-banner.png';
            echo '<div class="bosa-go-pro-notice notice is-dismissible">';
                echo '<div class="getting-img">';
                    echo '<img id="" src="'.esc_url( $pro_img_url ).'" />';
                echo '</div>';
                echo '<div class="getting-content">';
                    echo '<h2 class="bosa-notice-title"> Upgrade to <a href="https://bosathemes.com/bosa-pro/#pricing" target="_blank" class="bosa-title">Bosa Pro</a> for 100+ Starter sites & Advanced Features</h2>';
                    echo '<ul class="bosa-demo-info-list">';
                    echo '<li><div><strong>Pre-built 100+ Starter sites</strong> – Access a collection of libraries that come ready to use for different kinds of websites.</div></li>';
                    echo '<li> <div><strong>Access Premium Features</strong> – Unlocking a richer, more personalized, and higher-value experience than what’s available to free-tier. </div></li>';
                    echo '<li> <div><strong>Seamless Import System</strong> – Easily import demo for quick and hassle-free customization.</div></li>';   
                    echo '<li> <div><strong>Priority Support</strong> – Ensuring faster response times and quicker resolutions to your requests.</div></li>';                        
                    echo '</ul>';
                    echo '<div class="button-wrapper">';
                    echo '<a href="https://bosathemes.com/bosa-pro/#pricing" class="btn-primary" target="_blank">Buy Now</a>';
                    echo '<a href="https://bosathemes.com/bosa-pro" class="btn-primary btn-theme-detail" target="_blank">Theme Details</a>';
                    echo'<button class=" btn-primary keon-remind-me-later">Remind Me Later</button>';
                    echo '</div>';
                 echo '</div>';
                 echo '<a href="javascript:void(0)" id="keon-bosa-pro-dismiss" class="admin-notice-dismiss">
                        <span class="keon-toolset-top-dissmiss-btn">Dismiss</span>
                    </a>';
            echo '</div>';
        }
    }
}
return new Keon_Toolset_Admin_Notice();
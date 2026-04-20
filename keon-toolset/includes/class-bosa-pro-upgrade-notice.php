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
            ?>
            <div class="bosa-go-pro-notice notice is-dismissible">
                <div class="getting-img">
                    <img id="" src="<?php echo esc_url( $pro_img_url ); ?>" />
                </div>
                <div class="getting-content">
                    <h2 class="bosa-notice-title"><?php esc_html_e('Upgrade to', 'keon-toolset');?> <a href="<?php echo esc_url( 'https://bosathemes.com/bosa-pro/#pricing' ); ?>" target="_blank" class="bosa-title"><?php esc_html_e('Bosa Pro', 'keon-toolset'); ?></a><?php esc_html_e(' for Full Starter sites Library & Advanced Features', 'keon-toolset');?> </h2>
                    <ul class="bosa-demo-info-list">
                        <li>
                            <div>
                                <strong><?php esc_html_e('Pre-built Starter sites', 'keon-toolset');?></strong>
                                <?php esc_html_e(' – Access a collection of libraries that come ready to use for different kinds of websites.', 'keon-toolset');?>
                            </div>
                        </li>
                        <li>
                            <div>
                                <strong><?php esc_html_e('Access Premium Features', 'keon-toolset');?></strong>
                                <?php esc_html_e(' – Unlocking a richer, more personalized, and higher-value experience than what’s available to free-tier.', 'keon-toolset');?>
                            </div>
                        </li>
                        <li>
                            <div>
                                <strong><?php esc_html_e('Seamless Import System', 'keon-toolset');?></strong>
                                <?php esc_html_e(' – Easily import demo for quick and hassle-free customization.', 'keon-toolset');?>
                            </div>
                        </li>
                        <li>
                            <div>
                                <strong><?php esc_html_e('Priority Support', 'keon-toolset');?></strong>
                                <?php esc_html_e(' – Ensuring faster response times and quicker resolutions to your requests.', 'keon-toolset');?>
                            </div>
                        </li>
                    </ul>
                    <div class="button-wrapper">
                        <a href="<?php echo esc_url( 'https://bosathemes.com/bosa-pro/#pricing' ); ?>" class="btn-primary" target="_blank"><?php esc_html_e('Buy Now', 'keon-toolset');?></a>
                        <a href="<?php echo esc_url( 'https://bosathemes.com/bosa-pro' ); ?>" class="btn-primary btn-theme-detail" target="_blank"><?php esc_html_e('Theme Details', 'keon-toolset');?></a>
                        <button class=" btn-primary keon-remind-me-later"><?php esc_html_e('Remind Me Later', 'keon-toolset');?></button>
                    </div>
                </div>
                 <a href="javascript:void(0)" id="keon-bosa-pro-dismiss" class="admin-notice-dismiss">
                    <span class="keon-toolset-top-dissmiss-btn"><?php esc_html_e('Dismiss', 'keon-toolset');?></span>
                </a>
            </div>
            <?php 
        }
    }
}
return new Keon_Toolset_Admin_Notice();
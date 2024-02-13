<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_wciowgcssp {

    /*
     * Bootstraps the class and hooks required actions & filters.
     *
     */
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_wciowgcssp', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_wciowgcssp', __CLASS__ . '::update_settings' );
    }


    /*
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['wciowgcssp'] = __( 'WooPos', 'woocommerce-settings-tab-demo' );
        return $settings_tabs;
    }


    /*
     * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    /*
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    /*
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debugMessage = "<p style=\"color:red;\">WARNING: WP_DEBUG activated. Log files will contain sensitive data. Please set WP_DEBUG to false when done with debugging.</p>";
        } else {
            $debugMessage = "";
        }

        $settings = array(
            'section_title' => array(
                'name'     => __( 'WooPOS settings', 'woocommerce-settings-tab-demo' ),
                'type'     => 'title',
                'desc'     => ''.$debugMessage.'',
                'id'       => 'woopos_section_title'
            ),
            'woopos_pos' => array(
                'name' => __( 'Select POS provider', 'woocommerce-settings-tab-demo' ),
                'type' => 'select',
                'options' => array( 
                  'ipos' => __('Ipos'),
                  ),      
                'desc' => __( '', 'woocommerce-settings-tab-demo' ),
                'id'   => 'woopos_pos'
            ),
            'woopos_token' => array(
                'name' => __( 'Api key from POS', 'woocommerce-settings-tab-demo' ),
                'type' => 'text',
                'desc' => __( 'APi keys kan be found in your Dashboard at the POS system you have selected.', 'woocommerce-settings-tab-demo' ),
                'id'   => 'woopos_token'
            ),
            'woopos_extrafield1' => array(
                'name' => __( 'Extra field 1', 'woocommerce-settings-tab-demo' ),
                'type' => 'text',
                'desc' => __( 'If your choosen POS requires extra fields, you will need to fill this out here. Example: POS_kunde_demo', 'woocommerce-settings-tab-demo' ),
                'id'   => 'woopos_extrafield1'
            ),
            'woopos_api' => array(
                'name' => __( 'Api key from WooPOS', 'woocommerce-settings-tab-demo' ),
                'type' => 'text',
                'value' => base64_encode($_SERVER['HTTP_HOST']),
                'desc' => __( 'You will get this from WooPOS.dk', 'woocommerce-settings-tab-demo' ),
                'id'   => 'woopos_api'
            ),
            'woopos_woopos_pagelength' => array(
                'name' => __( 'Page length', 'woocommerce-settings-tab-demo' ),
                'type' => 'number',
                'value' => 100,
                'desc' => __( 'Must be a number above 0. Default: 100', 'woocommerce-settings-tab-demo' ),
                'id'   => 'woopos_woopos_pagelength'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'woopos_section_end'
            )
        );

        return apply_filters( 'woopos_settings', $settings );
    }

}

WC_wciowgcssp::init();

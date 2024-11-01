<?php
/*
Plugin Name: WooCommerce Dynamic Sorting
Description: Order products based on Google Analytics data
Plugin URI: http://pbweb.co.uk/
Author: Phill Brown
Author URI: http://pbweb.co.uk/
Version: 1.0

Copyright: © 2013 Phill Brown
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

require_once dirname( __FILE__ ) . '/includes/base.php';
class Woocommerce_Dynamic_Sorting extends Phill_Brown_WP_Base {

    function __construct() {
        parent::__construct();

        // Activation hooks
        register_activation_hook( __FILE__, array( &$this, 'activation' ) );
        register_deactivation_hook( __FILE__, array( &$this, 'deactivation' ) );

        // Dependency notice
        if (
            ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
            || ! function_exists( 'curl_init' )
            || ! function_exists( 'json_decode' )
        ) {
            add_action( 'admin_init', array( &$this, 'notice_dependencies_hide' ) );
            add_action( 'admin_notices', array( &$this, 'notice_dependencies' ) );
            return;
        }

        // Set sort types, with each key as a query var.
        $this->sort_types = array(
            'views' => array(
                'label' => __( 'Most viewed' ),
                'ga_metric' => 'pageviews',
            ),
            'visits' => array(
                'label' => __( 'Most visited' ),
                'ga_metric' => 'visits',
            ),
        );

        // Google Analytics helper
        require_once dirname( __FILE__ ) . '/classes/ga.php';
        WooCommerce_Dynamic_Sorting_GA::i();

        // URL indexer
        require_once dirname( __FILE__ ) . '/classes/indexer_urls.php';
        WooCommerce_Dynamic_Sorting_Indexer_URLs::i();

        // Data indexer
        require_once dirname( __FILE__ ) . '/classes/indexer_data.php';
        WooCommerce_Dynamic_Sorting_Indexer_Data::i();

        // Admin
        require_once dirname( __FILE__ ) . '/admin/settings.php';
        WooCommerce_Dynamic_Sorting_Admin::i();

        // Extra links on plugin page
        add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'plugin_actions' ) );
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_list_feedback_link' ), 10, 2 );

        // Hook into product query
        add_filter( 'woocommerce_get_catalog_ordering_args', array( &$this, 'orderby_query' ) );

        // User-facing order options
        add_filter( 'woocommerce_catalog_orderby', array( &$this, 'orderby_options' ) );
    }

    function activation() {
        do_action( $this->hook_name( 'activation' ) );
    }

    function deactivation() {
        do_action( $this->hook_name( 'deactivation' ) );
    }

    function notice_dependencies_hide() {
        $notice_name = 'woocommerce_dynamic_sorting_hide_dep_notice';
        if ( ! empty( $_GET[ $notice_name ]) ) {
            add_user_meta( $GLOBALS['current_user']->ID, $notice_name, true, true);
            wp_safe_redirect( remove_query_arg( $notice_name ) );
            exit;
        }
    }

    function notice_dependencies() {
        $notice_name = 'woocommerce_dynamic_sorting_hide_dep_notice';

        // User has hidden the error message
        if ( get_user_meta($GLOBALS['current_user']->ID, $notice_name ) ) return;

        echo '<div class="error">';

        // WooCommerce plugin
        if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            echo '<p>' . __( 'WooCommerce Dynamic Sorting requires WooCommerce installed.' ) . '</p>';
        }

        if ( ! function_exists( 'curl_init' ) ) {
            echo '<p>' . __( 'WooCommerce Dynamic Sorting requires the PHP cURL extension enabled.' ) . '</p>';
        }

        if ( ! function_exists( 'json_decode' ) ) {
            echo '<p>' . __( 'WooCommerce Dynamic Sorting requires the PHP JSON extension enabled.' ) . '</p>';
        }

        echo '<p><a href="' . add_query_arg( $notice_name, true ) . '">' . __( 'Hide this message' ) . '</a></p></div>';
    }

    function orderby_query( $args ) {

        // Ignore if indexing isn't complete
        if ( ! WooCommerce_Dynamic_Sorting_Indexer_Data::i()->is_data_indexed() )
            return $args;

        // The query var isn't passed into hook, so we need copy some of the WC code
        // From: woocommerce/classes/class-wc-query.php
        $orderby_value = isset( $_GET['orderby'] ) ? woocommerce_clean( $_GET['orderby'] ) : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );

        // Get order + orderby args from string
        $orderby_value = explode( '-', $orderby_value );
        $orderby = esc_attr( $orderby_value[0] );
        $order = ! empty( $orderby_value[1] ) ? $orderby_value[1] : '';


        if ( in_array( $orderby, array_keys( $this->sort_types ) ) && get_option( 'woocommerce_dynamic_sorting_hide_' . $orderby ) !== 'yes' ) {
            $args = array(
                'orderby' => 'meta_value_num',
                'order' => $order == 'ASC' ? 'ASC' : 'DESC',
                'meta_key' => '_dynamic_sorting_' . $orderby,
            );
        }

        return $args;
    }

    function orderby_options( $options, $show_hidden = false ) {

        // Hide if indexing isn't complete
        if ( ! WooCommerce_Dynamic_Sorting_Indexer_Data::i()->is_data_indexed() )
            return $options;

        foreach ( $this->sort_types as $name => $data ) {
            if ( ! $show_hidden && get_option( 'woocommerce_dynamic_sorting_hide_' . $name ) == 'yes' ) continue;
            $options[ $name ] = $data['label'];
        }
        return $options;
    }
    
    function plugin_actions( $links ) {
        $new_links[] = '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=catalog#woocommerce_dynamic_sorting_field_auth' ) . '">' . __('Settings' ) . '</a>';

        return array_merge($new_links, $links);
    }

    /**
     * Adds feedback link to plugin meta in plugin list table
     *
     * @access public
     * @return void
     */
    function plugin_list_feedback_link( $plugin_meta, $plugin_file ) {
        if ( $plugin_file == basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) ) {
           $plugin_meta[] = '<a href="mailto:wp@pbweb.co.uk"><strong>Leave feedback</strong></a>';
        }
        return $plugin_meta;
    }

    static function i() {
        static $inst = null;
        if ($inst === null) {
            $class_name = __CLASS__;
            $inst = new $class_name;
        }
        return $inst;
    }
}

Woocommerce_Dynamic_Sorting::i();
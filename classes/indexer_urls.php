<?php

class WooCommerce_Dynamic_Sorting_Indexer_URLs extends Phill_Brown_WP_Base {

    function __construct() {
        parent::__construct();

        // Default filtered vars
        $this->batch_size = 200;

        // Set the background indexer away
        // This is for when the plugin is first activated and as belt and braces for any other permalink changes
        add_action( 'woocommerce_dynamic_sorting_activation', array( &$this, 'schedule' ) );
        add_action( 'woocommerce_dynamic_sorting_deactivation', array( &$this, 'unschedule' ) );

        // Scheduler hook
        add_action( 'woocommerce_dynamic_sorting_index_urls', array( &$this, 'index_urls' ), 10, 2 );

        // On permalink update
        add_action( 'permalink_structure_changed', array( &$this, 'index_urls' ) );

        // On post slug update
        add_action( 'save_post', array( &$this, 'save_post_trigger' ) );
    }

    function save_post_trigger( $post_id ) {
        if ( ! wp_is_post_revision( $post_id ) ) {
            $this->index_urls( $post_id );
        }
    }

    function index_urls( $post_id = 0, $offset = 0, $schedule_next = true ) {

        // All products
        if ( empty( $post_id ) ) {
            $products_q = new WP_Query( array(
                'post_type' => 'product',
                'posts_per_page' => $this->batch_size,
                'offset' => $offset,
            ) );
            $products = $products_q->get_posts();

            // Products still to index
            // Fire up another cron job until we've got them all done
            $new_offset = $offset + $products_q->post_count;
            if ( $products_q->found_posts > $new_offset ) {
                if ( $schedule_next ) {
                    wp_schedule_single_event( time(), 'woocommerce_dynamic_sorting_index_urls', array( 0, $new_offset ) );
                } else {
                    return $new_offset;
                }
            }
        } 
        // Single product
        else {
            $products = array( get_post( $post_id ) );
        }

        // No products found
        if ( empty( $products ) ) return;

        // Update products current permalinks
        $meta_key = '_dynamic_sorting_past_permalinks';
        foreach ( $products as $product ) {
            if (
                ! get_post_custom_values( $meta_key, $product->ID ) 
                || ! in_array( get_permalink( $product->ID ), get_post_custom_values( $meta_key, $product->ID ) )
            ) {
                add_post_meta( $product->ID, $meta_key, get_permalink( $product->ID ) );
            }
        }

        return true;
    }

    function schedule() {
        wp_schedule_event( time(), 'daily', 'woocommerce_dynamic_sorting_index_urls' );
    }

    function unschedule() {
        wp_clear_scheduled_hook( 'woocommerce_dynamic_sorting_index_urls' );
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
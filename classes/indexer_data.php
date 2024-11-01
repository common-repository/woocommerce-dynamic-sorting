<?php

class WooCommerce_Dynamic_Sorting_Indexer_Data extends Phill_Brown_WP_Base {

    function __construct() {
        parent::__construct();

        // Default filtered vars
        $this->batch_size = 200;
        $this->frequency = 'twicedaily';

        // Set the background indexer away
        // Run after the URL indexer which has a priority of 10
        add_action( 'woocommerce_dynamic_sorting_activation', array( &$this, 'schedule' ), 11 );
        add_action( 'woocommerce_dynamic_sorting_deactivation', array( &$this, 'unschedule' ) );
        
        // Scheduler hook
        add_action( 'woocommerce_dynamic_sorting_index_data', array( &$this, 'get_data' ) );

        // How strictly Google matches paths
        add_filter( 'woocommerce_dynamic_sorting_post_path_to_ga_exp', array( &$this, 'post_path_to_ga_exp' ) );
        add_filter( 'woocommerce_dynamic_sorting_ga_path_to_post_id', array( &$this, 'ga_path_to_post_id' ), 10, 2 );
    }

    function get_data( $offset = 0, $schedule_next_batch = true ) {

        // Fetch a list of product IDs to index data for
        $products_q = new WP_Query( array(
            'post_type' => 'product',
            'posts_per_page' => $this->batch_size,
            'offset' => $offset,
        ) );
        $products = $products_q->get_posts();

        // Fetch a list of URLs to fetch stats for
        global $wpdb;
        $meta_rows = $wpdb->get_results( 
            $wpdb->prepare("
                SELECT post_id, meta_value
                FROM $wpdb->postmeta
                WHERE meta_key = %s
                AND post_id IN (" . implode( ',', array_map( create_function( '$v', 'return $v->ID;' ), $products ) ) . ")
            ", '_dynamic_sorting_past_permalinks' )
        );

        // No URLs indexed yet
        if ( empty( $meta_rows ) ) return;

        // Due to limitations with Google's filter API, we need to separate URLs by host
        foreach ( $meta_rows as $key => $meta_row ) {
            $url_parts = parse_url( $meta_row->meta_value );
            $grouped_urls[ $url_parts['host'] ][ $url_parts['path'] . ( ! empty( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' ) ]  = $meta_row->post_id;
        }


        // Start the Google service
        try {
            $service = WooCommerce_Dynamic_Sorting_GA::i()->get_service();
        }
        catch( Exception $e ) {
            wp_die( $e->getMessage() );
        }

        // Extra data we need from Google to match metrics against posts
        $ga_dimensions = array( 'ga:pagePath' );

        // Start date
        // Old school strtotime until PHP 5.3 DateTime is adopted by WP
        $date_parts = explode( '_', get_option( 'woocommerce_dynamic_sorting_date_range', 'all' ) );
        $start_date = $date_parts[0] == 'all'
            ? '2005-01-01'
            : date( 'Y-m-d', strtotime( "- $date_parts[0] $date_parts[1]" ) );

        // Fetch GA data. 1 request per host.
        foreach ( $grouped_urls as $host => $paths ) {

            // Max URL length of Google is assumed to be 2048 characters
            // Split the API calls into blocks of 1000 characters
            $ga_url_parts = array_map(
                create_function( '$path', 'return "ga:pagePath" . apply_filters( "woocommerce_dynamic_sorting_post_path_to_ga_exp", $path );' ),
                array_keys( $paths ) 
            );
            $filter_strings_index = 0;
            foreach ( $ga_url_parts as $ga_url_part ) {
                $filter_string_sets[ $filter_strings_index ][] = $ga_url_part;
                if ( strlen( implode( ',', $filter_string_sets[ $filter_strings_index ] ) ) > 1000 ) $filter_strings_index++;
            }
							
			// From this point onwards, any stats not found need to be set to 0
			// The sorting query requires the meta field set. Any products without it will not return
			// Default each post to 0 of each metric
			$post_ga_data = array_fill_keys(
				array_values( $paths ),
				array_fill_keys( array_keys( WooCommerce_Dynamic_Sorting::i()->sort_types ), 0 )
			);

			foreach ( $filter_string_sets as $filter_string_set ) {
				try {
					// https://developers.google.com/analytics/devguides/reporting/core/v3/reference
					$ga_data = $service->data_ga->get(
					   'ga:' . get_option( 'woocommerce_dynamic_sorting_profile' ), // Profile ID
					   $start_date, // Start date
					   date( 'Y-m-d' ), // End date
					   implode( ',', array_map( create_function( '$v', 'return "ga:" . $v["ga_metric"];' ), WooCommerce_Dynamic_Sorting::i()->sort_types ) ), // Metrics
						array(
							'dimensions' => implode( ',', $ga_dimensions ),
							'filters' => 'ga:hostname==' . $host . ';' . implode( ',', $filter_string_set ),
						)
					);
				}
				catch ( Exception $e ) {
					wp_die( $e->getMessage() );
				}

				// Check the result found data
				if ( $ga_data->getTotalResults() > 0 ) {

					// Add all the data together so there's a single figure for each metric against a post
					foreach ( $ga_data->getRows() as $ga_row ) {

						// Match the path returned by Google to a post URL
						if ( isset( $paths[ $ga_row[0] ] ) ) {
							$post_id = $paths[ $ga_row[0] ];
						} else {
							$post_id = apply_filters( 'woocommerce_dynamic_sorting_ga_path_to_post_id', $ga_row[0], $paths );
						}

						// Can't match a post against Google's returned path...
						if ( ! $post_id ) continue;

						// Loop over each metric
						// Google API returns an numerically indexed array ordered by dimensions then metrics
						foreach ( array_keys( WooCommerce_Dynamic_Sorting::i()->sort_types ) as $key => $metric ) {
							$post_ga_data[ $post_id ][ $metric ] += (int) $ga_row[ $key + count( $ga_dimensions ) ];
						}
					}
				}

				// Update the post itself
				foreach ( $post_ga_data as $post_id => $post_meta ) {
					foreach ( $post_meta as $meta_key => $meta_value ) {
						update_post_meta( $post_id, '_dynamic_sorting_' . $meta_key, $meta_value );
					}
				}
			}
        }

        // Products still to index
        // Fire up another cron job until we've got them all done
        if ( $products_q->found_posts > ( $offset + $products_q->post_count ) ) {
            $new_offset = $offset + $products_q->post_count;
            
            if ( $schedule_next_batch )
                wp_schedule_single_event( time(), 'woocommerce_dynamic_sorting_index_data', array( $new_offset ) );

            return $new_offset;
        }
        // All done!
        else {
            // Set a flag to indicate we can start using the plugin
            // Without this, there may be products missing in the product query
            update_option( 'woocommerce_dynamic_sorting_data_indexed', true );
            return true;
        }
    }

    function schedule() {
        wp_schedule_event( time(), $this->frequency, 'woocommerce_dynamic_sorting_index_data' );
    }

    function unschedule() {
        wp_clear_scheduled_hook( 'woocommerce_dynamic_sorting_index_data' );
    }

    function post_path_to_ga_exp( $path ) {
        return "==" . $path;

        // Example of including query strings using a regular expression
        //return "=~^" . $path . "(\?.*)?$";
    }

    function ga_path_to_post_id( $ga_path, $paths ) {
        // Example of matching a GA path to a post. Useful if including query strings.
        // With exact matching, we should never get this far..
        foreach ( $paths as $post_path => $post_id ) {
            if ( preg_match( '/^'. preg_quote( $post_path, '/' ) . '(\?.*)?$/', $ga_path ) ) {
                return $post_id;
            }
        }
        return false;
    }

    function is_data_indexed() {
        return get_option( 'woocommerce_dynamic_sorting_data_indexed', false );
    }

    function start( $delay = 0 ) {
        wp_schedule_single_event( time() + $delay, 'woocommerce_dynamic_sorting_index_data' );
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
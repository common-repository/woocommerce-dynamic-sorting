<?php

class WooCommerce_Dynamic_Sorting_Admin extends Phill_Brown_WP_Base {

    function __construct() {
        parent::__construct();

        // Default filtered vars
        $this->date_ranges = array(
            '1_day' => __( '1 day' ),
            '7_day' => __( '1 week' ),
            '14_day' => __( '2 weeks' ),
            '1_month' => __( '1 month' ),
            '3_month' => __( '3 months' ),
            '6_month' => __( '6 months' ),
            '1_year' => __( '1 year' ),
            'all' => __( 'All time' ),
        );

        // Admin notices on first activation
        if ( ! WooCommerce_Dynamic_Sorting_Indexer_Data::i()->is_data_indexed() ) {
            add_action( 'admin_init', array( &$this, 'notice_setup_hide' ) );
            add_action( 'admin_notices', array( &$this, 'notice_setup' ) );
        }

        // Handle custom actions
        add_action( 'admin_init', array( &$this, 'init' ) );

        // Add connection settings to catalog settings tab
        // woocommerce/admin/settings/settings-init.php
        add_filter( 'woocommerce_catalog_settings', array( &$this, 'register_settings' ) );

        // Display of custom field types
        // woocommerce/admin/woocommerce-admin-settings.php
        add_filter( 'woocommerce_admin_field_woocommerce_dynamic_sorting_auth', array( &$this, 'display_settings_auth' ) );
        add_filter( 'woocommerce_admin_field_woocommerce_dynamic_sorting_indexer', array( &$this, 'display_settings_indexer' ) );

        // Saving of custom field types
        // woocommerce/admin/settings/settings-save.php
        add_filter( 'woocommerce_update_option_woocommerce_dynamic_sorting_auth', array( &$this, 'save_settings_auth' ) );
        add_filter( 'woocommerce_update_option_woocommerce_dynamic_sorting_indexer', array( &$this, 'save_settings_indexer' ) );

        // Trigger an index on changing the profile
        add_action( 'woocommerce_update_option', array( &$this, 'save_settings_profile' ) );

        // Add to default product sorting
        add_filter( 'woocommerce_default_catalog_orderby_options', array( &$this, 'default_orderby_options' ) );
    }

    function notice_setup_hide() {
        $notice_name = 'woocommerce_dynamic_sorting_hide_setup_notice';
        if ( ! empty( $_GET[ $notice_name ]) ) {
            add_user_meta( $GLOBALS['current_user']->ID, $notice_name, true, true);
            wp_safe_redirect( remove_query_arg( $notice_name ) );
            exit;
        }
    }

    function notice_setup() {
        $notice_name = 'woocommerce_dynamic_sorting_hide_setup_notice';

        // User has hidden the notice
        if ( get_user_meta($GLOBALS['current_user']->ID, $notice_name ) ) return;

        echo '
        <div class="updated">
            <p>
                <strong>' . sprintf( __( 'You need to %sconfigure WooCommerce Dynamic Sorting%s before it can be used.' ), '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=catalog#woocommerce_dynamic_sorting_field_auth' ) . '">', '</a>' ) . '</strong>
                <a href="' . add_query_arg( $notice_name, true ) . '">' . __( 'Hide this message.' ) . '</a>
            </p>
        </div>
        ';
    }

    function init() {
        if ( ! isset( $GLOBALS['plugin_page'] ) || $GLOBALS['plugin_page'] !== 'woocommerce_settings' ) return;

        // Logout from Google
        if ( ! empty( $_GET['woocommerce_dynamic_sorting_logout'] ) ) {
            delete_option( 'woocommerce_dynamic_sorting_token' );
            wp_safe_redirect( remove_query_arg( 'woocommerce_dynamic_sorting_logout' ) );
            exit;
        }

        // Reindex data - triggered by reindex button or changing the GA profile
        if ( isset( $_GET['woocommerce_dynamic_sorting_reindex'] )  ) {
            $this->reindex( (int) $_GET['woocommerce_dynamic_sorting_reindex'] );
            wp_safe_redirect( remove_query_arg( 'woocommerce_dynamic_sorting_reindex' ) );
            exit;
        }
    }

    function register_settings( $settings ) {
        $settings[] = array(
            'title' => __( 'Dynamic Sorting' ),
            'type' => 'title',
            'desc' => sprintf( __( 'To use dynamic sorting, you need to sign in to Google and grant access to your Google Analytics account. %sTo sort any post type by any Google Analytics metric - buy Sort by Google Analytics plugin.%s' ), '<br/><a href="http://pbweb.co.uk/sort-by-ga-shop"><strong>', '</strong></a>' ),
            'id' => 'woocommerce_dynamic_sorting_options',
        );

        $settings[] = array(
            'type' => 'woocommerce_dynamic_sorting_auth',
            'id' => 'woocommerce_dynamic_sorting_auth',
        );

        // Only display when there's an active token
        if ( get_option( 'woocommerce_dynamic_sorting_token' ) ) {
            $profiles = WooCommerce_Dynamic_Sorting_GA::i()->get_ga_profiles();
            $default = false;
            foreach ( $profiles as $profile_id => $profile ) {
                if ( strpos( $profile, site_url() ) !== false ) $default = $profile_id;
            }
            $settings[] = array(
                'type' => 'select',
                'id' => 'woocommerce_dynamic_sorting_profile',
                'name' => __( 'Select your Analytics profile' ),
                'class' => 'chosen_select',
                'options' => $profiles,
                'default' => $default,
            );

            // Set up profile before indexing
            if ( get_option( 'woocommerce_dynamic_sorting_profile', false ) ) {
                $settings[] = array(
                    'type' => 'select',
                    'id' => 'woocommerce_dynamic_sorting_date_range',
                    'name' => __( 'Data range' ),
                    'desc' => __( 'How far back should Google Analytics data should be taken' ),
                    'desc_tip' => true,
                    'options' => $this->date_ranges,
                    'default' => 'all',
                );
                $settings[] = array(
                    'type' => 'woocommerce_dynamic_sorting_indexer',
                    'id' => 'woocommerce_dynamic_sorting_indexer',
                );

                $i = 0;
                foreach ( WooCommerce_Dynamic_Sorting::i()->sort_types as $name => $sort_opts ) {
                    $id = 'woocommerce_dynamic_sorting_hide_' . $name;

                    $settings[ $id ] = array(
                        'type' => 'checkbox',
                        'id' => $id,
                        'name' => __( 'Hide these options from dropdown' ),
                        'desc' => $sort_opts['label'],
                    );

                    if ( $i == 0 ) $settings[ $id ]['checkboxgroup'] = 'start';
                    if ( $i + 1 == count( WooCommerce_Dynamic_Sorting::i()->sort_types ) ) $settings[ $id ]['checkboxgroup'] = 'end';

                    $i++;
                }
            }
        }

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'woocommerce_dynamic_sorting_options',
        );

        return $settings;
    }

    function default_orderby_options( $options ) {
        return Woocommerce_Dynamic_Sorting::i()->orderby_options( $options, true );
    }

    function display_settings_auth( $value ) {

        // Get Google client and service
        $client = WooCommerce_Dynamic_Sorting_GA::i()->get_client();
        
        echo '<tr valign="top" id="woocommerce_dynamic_sorting_field_auth">';

        if ( WooCommerce_Dynamic_Sorting_GA::i()->is_authenticated() ): ?>

            <th scope="row" class="titledesc">
                <?php _e( 'Google Authentication' ); ?>
            </th>
            <td class="forminp">
                <a href="<?php echo add_query_arg( 'woocommerce_dynamic_sorting_logout', 1 ); ?>#woocommerce_dynamic_sorting_field_auth" class="button">Logout</a>
            </td>

        <?php else: ?>

            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php _e( 'Google Authentication Code' ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
                <input
                    name="<?php echo esc_attr( $value['id'] ); ?>"
                    id="<?php echo esc_attr( $value['id'] ); ?>"
                    type="text"
                    value=""
                    style="min-width:300px;"
                    /><br/>
                <strong><a onclick="window.open('<?php echo $client->createAuthUrl(); ?>', 'activate','width=700, height=600, menubar=0, status=0, location=0, toolbar=0')"
                href="javascript:void(0);"><?php _e( 'Click here to get your authentication code' ); ?></a></strong>
                - <small><?php printf( __( 'or %shere if you have popups blocked%s' ), '<a href="' . $client->createAuthUrl() . '" target="_blank">', '</a>'  ); ?></small>
            </td>

        <?php
        endif;
        
        echo '</tr>';
    }

    function save_settings_auth( $value ) {
        if ( empty( $_POST[ $value['id'] ] ) ) return;

        $client = WooCommerce_Dynamic_Sorting_GA::i()->get_client();

        try {
            $client->authenticate( $_POST[ $value['id'] ] );
        }
        catch ( Exception $e ) {
            wp_die( __( 'Error authenticating code' ) );
        }
        
        update_option( 'woocommerce_dynamic_sorting_token', $client->getAccessToken() );
    }

    function save_settings_profile( $value ) {

        // Trigger a reindex when the profile is changed
        if (
            $value['id'] !== 'woocommerce_dynamic_sorting_profile'
            || empty( $_POST[ $value['id'] ] )
            || $_POST['woocommerce_dynamic_sorting_profile'] == get_option( 'woocommerce_dynamic_sorting_profile' )
        ) return;

        // Set new profile before running the indexer
        update_option( $value['id'], $_POST[ $value['id'] ] );
        $this->reindex();
    }

    function display_settings_indexer( $value ) {
        echo '
        <tr valign="top" id="woocommerce_dynamic_sorting_field_indexer_status">
            <th scope="row" class="titledesc">';
                _e( 'Indexer status' );
            echo '
            </th>
            <td class="forminp">';
                if ( ! WooCommerce_Dynamic_Sorting_Indexer_Data::i()->is_data_indexed() ) {
                    echo '<a href="' . add_query_arg( 'woocommerce_dynamic_sorting_reindex', 0 ) . '#woocommerce_dynamic_sorting_field_indexer_status" class="button">' . __( 'Index now' ) . '</a>';

                    echo '<mark class="notice">' . __( 'Data has not been indexed yet' ) . '</mark>';
                }
                elseif ( $offset = get_option( 'woocommerce_dynamic_sorting_data_reindex_offset' ) ) {
                    echo '<a href="' . add_query_arg( 'woocommerce_dynamic_sorting_reindex', $offset ) . '#woocommerce_dynamic_sorting_field_indexer_status" class="button">' . __( 'Continue indexing' ) . '</a> ';

                    echo '<span class="description">' . sprintf( _n( '%d product indexed', '%d products indexed', $offset ), $offset ) . '</span>';
                }
                else {
                    echo '<a href="' . add_query_arg( 'woocommerce_dynamic_sorting_reindex', 0 ) . '#woocommerce_dynamic_sorting_field_indexer_status" class="button">' . __( 'Reindex now' ) . '</a> ';

                    echo '<span class="description">' . __( 'All products indexed' ) . '</span>';
                }
            echo '
            </td>
        </tr>
        ';
    }

    protected function reindex( $offset = 0 ) {

        // Index URLs first
        $url_offset = 0;
        while ( is_int( $url_offset ) ) {
            $url_offset = WooCommerce_Dynamic_Sorting_Indexer_URLs::i()->index_urls( 0, 0, false );
        }

        if ( WooCommerce_Dynamic_Sorting_Indexer_Data::i()->get_data( $offset, false ) === true ) {
            delete_option( 'woocommerce_dynamic_sorting_data_reindex_offset' );
        } else {
            update_option(
                'woocommerce_dynamic_sorting_data_reindex_offset',
                WooCommerce_Dynamic_Sorting_Indexer_Data::i()->get_data( $_GET['woocommerce_dynamic_sorting_reindex'], false )
            );
        }
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
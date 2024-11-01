<?php

class WooCommerce_Dynamic_Sorting_GA extends Phill_Brown_WP_Base {

    protected $authenticated = false;

    function get_client() {

        // Check cache first
        if ( isset( $this->client ) ) return $this->client;

        // Add Google libraries
        // WordPress autoloader please...
        if ( ! class_exists( 'Google_Client' ) ) require_once dirname( __FILE__ ) . '/../includes/vendor/google-api-php-client/src/Google_Client.php';
        if ( ! class_exists( 'Google_ManagementServiceResource' ) ) require_once dirname( __FILE__ ) . '/../includes/vendor/google-api-php-client/src/contrib/Google_AnalyticsService.php';

        $client = new Google_Client();
        $client->setApplicationName( 'WooCommerce Dynamic Sorting' );

        // Generated at https://code.google.com/apis/console?api=analytics
        $client->setClientId( '1086870519021-88nsgk549ceivigr8caeopv9c22j97f2.apps.googleusercontent.com' );
        $client->setClientSecret( 'h3vUUfAKSlTBvnukA4FkLnuE' );
        $client->setRedirectUri( 'urn:ietf:wg:oauth:2.0:oob' );
        $client->setUseObjects( true );

        // Try authenticating
        if ( get_option( 'woocommerce_dynamic_sorting_token' ) ) {
            try {
                $client->setAccessToken( get_option( 'woocommerce_dynamic_sorting_token' ) );
                $this->authenticated = true;
            }
            catch( Exception $e ) {
                // Do nothing
            }
        }

        // Add Analytics service
        $this->service = new Google_AnalyticsService( $client );

        // Cache client
        $this->client = $client;

        return $client;
    }

    function get_service() {
        if ( ! isset( $this->service ) ) $this->get_client();
        return $this->service;
    }

    function is_authenticated() {
        return $this->authenticated;
    }

    function get_ga_profiles() {
        if ( ! get_option( 'woocommerce_dynamic_sorting_token' ) ) {
            return array( __( 'Please connect to Google' ) );
        }

        $client = $this->get_client();
        $service = new Google_AnalyticsService( $client );

        try {
            $client->setAccessToken( get_option( 'woocommerce_dynamic_sorting_token' ) );
            $profiles = $service->management_profiles->listManagementProfiles( '~all', '~all' );
        }
        catch ( Exception $e ) {
            return array( $e->getMessage() );
        }

        // No properties in this account
        if ( $profiles->getItems() ) {
            foreach ( $profiles->getItems() as $profile ) {
                $options[ $profile->id ] = $profile->websiteUrl . ' (' . $profile->name . ')';
            }
        }

        // Sort alphabetically
        asort( $options );

        return $options;
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
<?php

if ( ! class_exists( 'Phill_Brown_WP_Base' ) ):

abstract class Phill_Brown_WP_Base {
    protected $vars;

    function __construct() {}

    /**
     * Helper for generating namespaced hook names
     *
     * @access protected
     * @param string $name Hook name to namespace
     * @return string
     */
    protected function hook_name( $name ) {
        return strtolower( get_class( $this ) ) . '_' . $name;
    }

    function __get( $name ) {
        return apply_filters(
            $this->hook_name( $name ),
            isset( $this->vars[ $name ] ) ? $this->vars[ $name ] : null
        );
    }

    function __set( $name, $value ) {
        $this->vars[ $name ] = $value;
    }

    function __unset( $name ) {
        unset( $this->vars[ $name ] );
    }

    function __isset( $name ) {
        return isset( $this->vars[ $name ] );
    }
}

endif;
<?php
/**
 * Plugin Name: REST API Menus
 * Description: Exposes WordPress menus via a custom REST API endpoint.
 * Version: 1.1.0
 * Author: Gunjan Jaswal
 * Author URI: https://gunjanjaswal.me
 * Plugin URI: https://github.com/gunjanjaswal/Wp-Rest-Menus-Plugin
 * License: GPL2
 *
 * This plugin registers custom REST API routes to expose WordPress menus
 * so they can be consumed by external applications.
 *
 * Support the developer: https://buymeacoffee.com/gunjanjaswal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class REST_API_Menus {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
    }

    /**
     * Add 'Buy me a coffee' link to plugin row meta in plugins list.
     *
     * @param array  $links Plugin row meta links.
     * @param string $file  Plugin file name.
     * @return array Modified links.
     */
    public function add_plugin_row_meta( $links, $file ) {
        if ( strpos( $file, 'rest-api-menus.php' ) !== false ) {
            $new_links = array(
                'donate' => '<a href="https://buymeacoffee.com/gunjanjaswal" target="_blank">Buy me a coffee</a>',
            );
            $links = array_merge( $links, $new_links );
        }
        return $links;
    }

    /**
     * Register custom REST routes for menus.
     */
    public function register_routes() {
        register_rest_route(
            'wp-rest-menu/v1',
            '/menus',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_menus' ),
                'permission_callback' => '__return_true',
                'args' => array(
                    'nested' => array(
                        'default' => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ),
                ),
            )
        );

        register_rest_route(
            'wp-rest-menu/v1',
            '/menus/(?P<id>[0-9]+)',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_menu' ),
                'permission_callback' => '__return_true',
                'args' => array(
                    'nested' => array(
                        'default' => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ),
                ),
            )
        );

        register_rest_route(
            'wp-rest-menu/v1',
            '/locations',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_locations' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wp-rest-menu/v1',
            '/locations/(?P<location>[a-zA-Z0-9_-]+)',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_menu_by_location' ),
                'permission_callback' => '__return_true',
                'args' => array(
                    'nested' => array(
                        'default' => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ),
                ),
            )
        );
    }

    /**
     * Get all registered menus with their locations.
     */
    public function get_menus( $request ) {
        $menus = wp_get_nav_menus();
        $locations = get_nav_menu_locations();
        $nested = $request->get_param( 'nested' );

        $data = array();

        foreach ( $menus as $menu ) {
            $menu_items = wp_get_nav_menu_items( $menu->term_id );

            $menu_locations = array();
            foreach ( $locations as $location_key => $menu_id ) {
                if ( (int) $menu_id === (int) $menu->term_id ) {
                    $menu_locations[] = $location_key;
                }
            }

            $items = $nested ? $this->build_menu_tree( $menu_items ) : $this->format_menu_items( $menu_items );

            $data[] = array(
                'id'        => (int) $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'locations' => $menu_locations,
                'items'     => $items,
            );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get a single menu by ID.
     */
    public function get_menu( $request ) {
        $menu_id = (int) $request['id'];
        $menu    = wp_get_nav_menu_object( $menu_id );
        $nested  = $request->get_param( 'nested' );

        if ( ! $menu ) {
            return new WP_Error( 'wp_rest_menu_not_found', __( 'Menu not found.', 'rest-api-menus' ), array( 'status' => 404 ) );
        }

        $menu_items = wp_get_nav_menu_items( $menu_id );
        $items = $nested ? $this->build_menu_tree( $menu_items ) : $this->format_menu_items( $menu_items );

        return rest_ensure_response( array(
            'id'    => (int) $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'items' => $items,
        ) );
    }

    /**
     * Get all registered menu locations.
     */
    public function get_locations( $request ) {
        $locations = get_registered_nav_menus();
        $menu_locations = get_nav_menu_locations();

        $data = array();

        foreach ( $locations as $location_key => $description ) {
            $menu_id = isset( $menu_locations[ $location_key ] ) ? (int) $menu_locations[ $location_key ] : null;
            $menu = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;

            $data[] = array(
                'location'    => $location_key,
                'description' => $description,
                'menu_id'     => $menu_id,
                'menu_name'   => $menu ? $menu->name : null,
            );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get a menu by location (e.g., 'primary', 'footer').
     */
    public function get_menu_by_location( $request ) {
        $location = $request['location'];
        $nested   = $request->get_param( 'nested' );
        $locations = get_nav_menu_locations();

        if ( ! isset( $locations[ $location ] ) ) {
            return new WP_Error( 'wp_rest_menu_location_not_found', __( 'Menu location not found.', 'rest-api-menus' ), array( 'status' => 404 ) );
        }

        $menu_id = (int) $locations[ $location ];
        $menu    = wp_get_nav_menu_object( $menu_id );

        if ( ! $menu ) {
            return new WP_Error( 'wp_rest_menu_not_assigned', __( 'No menu assigned to this location.', 'rest-api-menus' ), array( 'status' => 404 ) );
        }

        $menu_items = wp_get_nav_menu_items( $menu_id );
        $items = $nested ? $this->build_menu_tree( $menu_items ) : $this->format_menu_items( $menu_items );

        return rest_ensure_response( array(
            'location' => $location,
            'id'       => (int) $menu->term_id,
            'name'     => $menu->name,
            'slug'     => $menu->slug,
            'items'    => $items,
        ) );
    }

    /**
     * Format menu items into a clean array structure.
     */
    private function format_menu_items( $items ) {
        if ( empty( $items ) ) {
            return array();
        }

        $formatted = array();

        foreach ( $items as $item ) {
            $formatted[] = array(
                'id'           => (int) $item->ID,
                'title'        => $item->title,
                'url'          => $item->url,
                'parent'       => (int) $item->menu_item_parent,
                'order'        => (int) $item->menu_order,
                'type'         => $item->type,
                'object'       => $item->object,
                'object_id'    => (int) $item->object_id,
                'target'       => $item->target,
                'classes'      => $item->classes,
                'xfn'          => $item->xfn,
            );
        }

        return $formatted;
    }

    /**
     * Build a nested tree structure from flat menu items.
     */
    private function build_menu_tree( $items, $parent_id = 0 ) {
        if ( empty( $items ) ) {
            return array();
        }

        $tree = array();

        foreach ( $items as $item ) {
            if ( (int) $item->menu_item_parent === $parent_id ) {
                $menu_item = array(
                    'id'           => (int) $item->ID,
                    'title'        => $item->title,
                    'url'          => $item->url,
                    'parent'       => (int) $item->menu_item_parent,
                    'order'        => (int) $item->menu_order,
                    'type'         => $item->type,
                    'object'       => $item->object,
                    'object_id'    => (int) $item->object_id,
                    'target'       => $item->target,
                    'classes'      => $item->classes,
                    'xfn'          => $item->xfn,
                    'children'     => $this->build_menu_tree( $items, (int) $item->ID ),
                );

                $tree[] = $menu_item;
            }
        }

        return $tree;
    }
}

new REST_API_Menus();

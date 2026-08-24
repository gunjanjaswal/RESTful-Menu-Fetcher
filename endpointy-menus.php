<?php
/**
 * Plugin Name: EndPointy Menus
 * Description: Exposes WordPress menus via a custom REST API endpoint.
 * Version: 1.2.0
 * Author: Gunjan Jaswal
 * Author URI: https://gunjanjaswal.me
 * Plugin URI: https://github.com/gunjanjaswal/Endpointy-Menus
 * Text Domain: endpointy-menus
 * License: GPL2
 * Requires at least: 5.0
 * Tested up to: 7.1
 *
 * This plugin registers custom REST API routes to expose WordPress menus
 * so they can be consumed by external applications.
 *
 * Support the developer: https://ko-fi.com/gunjanjaswal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Endpointy_Menus {

    /**
     * REST namespace.
     */
    const REST_NS = 'endpointy-menus/v1';

    /**
     * Options key for plugin settings.
     */
    const OPTION_KEY = 'endpointy_menus_settings';

    /**
     * Transient prefix used for cached responses.
     */
    const CACHE_PREFIX = 'endpointy_menus_cache_';

    /**
     * Cached settings array.
     *
     * @var array|null
     */
    private $settings = null;

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );

        // Admin settings page.
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Cache invalidation whenever a menu changes.
        add_action( 'wp_update_nav_menu', array( $this, 'flush_cache' ) );
        add_action( 'wp_delete_nav_menu', array( $this, 'flush_cache' ) );
        add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), array( $this, 'flush_cache' ) );

        // Seed a baseline modification time for ETag / Last-Modified on first run.
        register_activation_hook( __FILE__, array( __CLASS__, 'on_activate' ) );
    }

    /**
     * Activation: seed the last-modified timestamp.
     */
    public static function on_activate() {
        if ( ! get_option( 'endpointy_menus_last_modified' ) ) {
            update_option( 'endpointy_menus_last_modified', time(), false );
        }
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------- */

    /**
     * Default settings.
     *
     * @return array
     */
    private function default_settings() {
        return array(
            'cache_enabled' => 1,
            'cache_ttl'     => 3600,
            'require_key'   => 0,
            'api_key'       => '',
            'cors_origins'  => '',
            'rate_limit'    => 0,
            'etag_enabled'  => 1,
        );
    }

    /**
     * Get a setting value (lazily loaded and cached for the request).
     *
     * @param string $key Setting key.
     * @return mixed
     */
    private function get_setting( $key ) {
        if ( null === $this->settings ) {
            $this->settings = wp_parse_args(
                get_option( self::OPTION_KEY, array() ),
                $this->default_settings()
            );
        }

        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
    }

    /**
     * Register settings, sections and fields.
     */
    public function register_settings() {
        register_setting(
            self::OPTION_KEY,
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'endpointy_menus_main',
            __( 'EndPointy Menus Settings', 'endpointy-menus' ),
            '__return_false',
            self::OPTION_KEY
        );

        $fields = array(
            'cache_enabled' => __( 'Enable response caching', 'endpointy-menus' ),
            'cache_ttl'     => __( 'Cache lifetime (seconds)', 'endpointy-menus' ),
            'require_key'   => __( 'Require API key', 'endpointy-menus' ),
            'api_key'       => __( 'API key', 'endpointy-menus' ),
            'cors_origins'  => __( 'Allowed CORS origins', 'endpointy-menus' ),
            'rate_limit'    => __( 'Rate limit (requests/min per IP)', 'endpointy-menus' ),
            'etag_enabled'  => __( 'Send ETag / Last-Modified', 'endpointy-menus' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                $key,
                $label,
                array( $this, 'render_field' ),
                self::OPTION_KEY,
                'endpointy_menus_main',
                array( 'key' => $key )
            );
        }
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $out                  = array();
        $out['cache_enabled'] = empty( $input['cache_enabled'] ) ? 0 : 1;
        $out['cache_ttl']     = isset( $input['cache_ttl'] ) ? max( 0, absint( $input['cache_ttl'] ) ) : 3600;
        $out['require_key']   = empty( $input['require_key'] ) ? 0 : 1;
        $out['api_key']       = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $out['cors_origins']  = isset( $input['cors_origins'] ) ? sanitize_textarea_field( $input['cors_origins'] ) : '';
        $out['rate_limit']    = isset( $input['rate_limit'] ) ? max( 0, absint( $input['rate_limit'] ) ) : 0;
        $out['etag_enabled']  = empty( $input['etag_enabled'] ) ? 0 : 1;

        // Bust cached responses whenever settings change.
        $this->flush_cache();

        return $out;
    }

    /**
     * Render a single settings field.
     *
     * @param array $args Field args.
     */
    public function render_field( $args ) {
        $key   = $args['key'];
        $value = $this->get_setting( $key );
        $name  = self::OPTION_KEY . '[' . $key . ']';

        switch ( $key ) {
            case 'cache_enabled':
            case 'require_key':
            case 'etag_enabled':
                printf(
                    '<input type="checkbox" name="%1$s" value="1" %2$s />',
                    esc_attr( $name ),
                    checked( 1, $value, false )
                );
                break;

            case 'rate_limit':
                printf(
                    '<input type="number" min="0" step="1" name="%1$s" value="%2$s" class="small-text" /> <span class="description">%3$s</span>',
                    esc_attr( $name ),
                    esc_attr( $value ),
                    esc_html__( 'Max requests per minute per IP. Set 0 to disable.', 'endpointy-menus' )
                );
                break;

            case 'cache_ttl':
                printf(
                    '<input type="number" min="0" step="60" name="%1$s" value="%2$s" class="small-text" /> <span class="description">%3$s</span>',
                    esc_attr( $name ),
                    esc_attr( $value ),
                    esc_html__( 'Default 3600 (1 hour). Set 0 to disable.', 'endpointy-menus' )
                );
                break;

            case 'api_key':
                printf(
                    '<input type="text" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" /> ',
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                printf(
                    '<button type="button" class="button" onclick="var k=this.previousElementSibling;k.value=(Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2));">%s</button>',
                    esc_html__( 'Generate', 'endpointy-menus' )
                );
                printf(
                    '<p class="description">%s</p>',
                    esc_html__( 'Send as X-API-Key header or ?api_key= query param when "Require API key" is on.', 'endpointy-menus' )
                );
                break;

            case 'cors_origins':
                printf(
                    '<textarea name="%1$s" rows="3" class="large-text" placeholder="%3$s">%2$s</textarea>',
                    esc_attr( $name ),
                    esc_textarea( $value ),
                    'https://app.example.com'
                );
                printf(
                    '<p class="description">%s</p>',
                    esc_html__( 'One origin per line. Use * to allow all. Leave blank to send no CORS header.', 'endpointy-menus' )
                );
                break;
        }
    }

    /**
     * Register the admin settings page under Settings.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'EndPointy Menus', 'endpointy-menus' ),
            __( 'EndPointy Menus', 'endpointy-menus' ),
            'manage_options',
            'endpointy-menus',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'EndPointy Menus', 'endpointy-menus' ); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: REST base URL */
                    esc_html__( 'Base endpoint: %s', 'endpointy-menus' ),
                    '<code>' . esc_html( rest_url( self::REST_NS ) ) . '</code>'
                );
                ?>
            </p>
            <form action="options.php" method="post">
                <?php
                settings_fields( self::OPTION_KEY );
                do_settings_sections( self::OPTION_KEY );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Plugin list links
     * ------------------------------------------------------------------- */

    /**
     * Add Ko-fi support + Settings links to plugin action links.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified action links.
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=endpointy-menus' ) ) . '">' . __( 'Settings', 'endpointy-menus' ) . '</a>';
        array_unshift( $links, $settings_link );

        $kofi_link = '<a href="https://ko-fi.com/gunjanjaswal" target="_blank" style="color: #0073aa; font-weight: bold;">Support on Ko-fi</a>';
        array_unshift( $links, $kofi_link );

        return $links;
    }

    /**
     * Add support / contact links to plugin row meta.
     *
     * @param array  $links Existing plugin row meta links.
     * @param string $file  Plugin file name.
     * @return array Modified row meta links.
     */
    public function add_plugin_row_meta( $links, $file ) {
        if ( plugin_basename( __FILE__ ) === $file ) {
            $links[] = '<a href="https://wordpress.org/support/plugin/endpointy-menus/" target="_blank">' . __( 'Plugin Support', 'endpointy-menus' ) . '</a>';
            $links[] = '<a href="mailto:hello@gunjanjaswal.me">' . __( 'Contact Developer', 'endpointy-menus' ) . '</a>';
        }
        return $links;
    }

    /* ---------------------------------------------------------------------
     * Routes
     * ------------------------------------------------------------------- */

    /**
     * Shared args used by menu-returning endpoints.
     *
     * @return array
     */
    private function menu_query_args() {
        return array(
            'nested' => array(
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
            'meta' => array(
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
            'fields' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Register custom REST routes for menus.
     */
    public function register_routes() {
        $permission = array( $this, 'check_permission' );

        register_rest_route( self::REST_NS, '/menus', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_menus' ),
            'permission_callback' => $permission,
            'args'                => $this->menu_query_args(),
        ) );

        register_rest_route( self::REST_NS, '/menus/(?P<id>[0-9]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_menu' ),
            'permission_callback' => $permission,
            'args'                => $this->menu_query_args(),
        ) );

        register_rest_route( self::REST_NS, '/menus/slug/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_menu_by_slug' ),
            'permission_callback' => $permission,
            'args'                => $this->menu_query_args(),
        ) );

        register_rest_route( self::REST_NS, '/locations', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_locations' ),
            'permission_callback' => $permission,
        ) );

        register_rest_route( self::REST_NS, '/locations/(?P<location>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_menu_by_location' ),
            'permission_callback' => $permission,
            'args'                => $this->menu_query_args(),
        ) );
    }

    /* ---------------------------------------------------------------------
     * Permission / CORS
     * ------------------------------------------------------------------- */

    /**
     * Permission callback: optional API-key gate plus CORS headers.
     *
     * @param WP_REST_Request $request Request.
     * @return bool|WP_Error
     */
    public function check_permission( $request ) {
        $this->maybe_send_cors_headers();

        $limited = $this->check_rate_limit();
        if ( is_wp_error( $limited ) ) {
            return $limited;
        }

        if ( ! $this->get_setting( 'require_key' ) ) {
            return true;
        }

        $expected = (string) $this->get_setting( 'api_key' );
        if ( '' === $expected ) {
            // Misconfigured: required but no key set. Fail closed.
            return new WP_Error(
                'endpointy_no_key_configured',
                __( 'API key is required but not configured on the server.', 'endpointy-menus' ),
                array( 'status' => 503 )
            );
        }

        $provided = $request->get_header( 'x_api_key' );
        if ( null === $provided ) {
            $provided = $request->get_param( 'api_key' );
        }

        if ( is_string( $provided ) && hash_equals( $expected, $provided ) ) {
            return true;
        }

        return new WP_Error(
            'endpointy_invalid_key',
            __( 'Invalid or missing API key.', 'endpointy-menus' ),
            array( 'status' => 401 )
        );
    }

    /**
     * Emit CORS headers based on the configured allow-list.
     */
    private function maybe_send_cors_headers() {
        $raw = trim( (string) $this->get_setting( 'cors_origins' ) );
        if ( '' === $raw ) {
            return;
        }

        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

        if ( '*' === $raw ) {
            header( 'Access-Control-Allow-Origin: *' );
            header( 'Vary: Origin' );
            return;
        }

        $allowed = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ) );
        if ( $origin && in_array( $origin, $allowed, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Vary: Origin' );
        }
    }

    /**
     * Simple per-IP, per-minute rate limiter backed by transients.
     *
     * @return true|WP_Error True when within budget, WP_Error (429) otherwise.
     */
    private function check_rate_limit() {
        $limit = (int) $this->get_setting( 'rate_limit' );
        if ( $limit <= 0 ) {
            return true;
        }

        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $key = self::CACHE_PREFIX . 'rl_' . md5( $ip );

        $count = (int) get_transient( $key );
        $count++;

        // 60-second fixed window. First hit (re)starts the window.
        set_transient( $key, $count, MINUTE_IN_SECONDS );

        $remaining = max( 0, $limit - $count );
        header( 'X-RateLimit-Limit: ' . $limit );
        header( 'X-RateLimit-Remaining: ' . $remaining );

        if ( $count > $limit ) {
            header( 'Retry-After: ' . MINUTE_IN_SECONDS );
            return new WP_Error(
                'endpointy_rate_limited',
                __( 'Rate limit exceeded. Try again later.', 'endpointy-menus' ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * Build a WP_REST_Response with ETag / Last-Modified support and 304 handling.
     *
     * @param mixed           $data    Response data.
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    private function respond( $data, $request ) {
        $response = rest_ensure_response( $data );

        if ( ! $this->get_setting( 'etag_enabled' ) ) {
            return $response;
        }

        $etag      = '"' . md5( wp_json_encode( $data ) ) . '"';
        $modified  = (int) get_option( 'endpointy_menus_last_modified', 0 );
        $last_mod  = $modified ? gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' : '';

        $response->header( 'ETag', $etag );
        $response->header( 'Cache-Control', 'public, max-age=0, must-revalidate' );
        if ( $last_mod ) {
            $response->header( 'Last-Modified', $last_mod );
        }

        $inm = $request->get_header( 'if_none_match' );
        $ims = $request->get_header( 'if_modified_since' );

        $etag_match = ( null !== $inm && trim( $inm ) === $etag );
        $time_match = ( $last_mod && null !== $ims && strtotime( $ims ) >= $modified );

        if ( $etag_match || $time_match ) {
            $response->set_status( 304 );
            $response->set_data( null );
        }

        return $response;
    }

    /* ---------------------------------------------------------------------
     * Caching
     * ------------------------------------------------------------------- */

    /**
     * Build a cache key from the request route + relevant params.
     *
     * @param string          $base    Logical name for the endpoint.
     * @param WP_REST_Request $request Request.
     * @return string
     */
    private function cache_key( $base, $request ) {
        $parts = array(
            $base,
            $request->get_param( 'nested' ) ? 1 : 0,
            $request->get_param( 'meta' ) ? 1 : 0,
            (string) $request->get_param( 'fields' ),
        );
        return self::CACHE_PREFIX . md5( implode( '|', $parts ) );
    }

    /**
     * Read a cached response if caching is enabled.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    private function cache_get( $key ) {
        if ( ! $this->get_setting( 'cache_enabled' ) || ! $this->get_setting( 'cache_ttl' ) ) {
            return false;
        }
        return get_transient( $key );
    }

    /**
     * Store a response in cache.
     *
     * @param string $key  Cache key.
     * @param mixed  $data Data.
     */
    private function cache_set( $key, $data ) {
        if ( ! $this->get_setting( 'cache_enabled' ) ) {
            return;
        }
        $ttl = (int) $this->get_setting( 'cache_ttl' );
        if ( $ttl > 0 ) {
            set_transient( $key, $data, $ttl );
        }
    }

    /**
     * Delete all cached responses. Tracked keys live in an index option.
     */
    public function flush_cache() {
        global $wpdb;

        // Transients are stored in the options table; clear ours by prefix.
        // A direct, prepared query is required to match transients by name prefix;
        // results are not cached because this only runs on menu/settings changes.
        $like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $keys = $wpdb->get_col(
            $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
        );

        foreach ( $keys as $option_name ) {
            $transient = str_replace( '_transient_', '', $option_name );
            delete_transient( $transient );
        }

        // Stamp the modification time so ETag / Last-Modified stay accurate.
        update_option( 'endpointy_menus_last_modified', time(), false );
    }

    /* ---------------------------------------------------------------------
     * Endpoint callbacks
     * ------------------------------------------------------------------- */

    /**
     * Get all registered menus with their locations.
     */
    public function get_menus( $request ) {
        $key    = $this->cache_key( 'menus', $request );
        $cached = $this->cache_get( $key );
        if ( false !== $cached ) {
            return $this->respond( $cached, $request );
        }

        $menus     = wp_get_nav_menus();
        $locations = get_nav_menu_locations();
        $data      = array();

        foreach ( $menus as $menu ) {
            $menu_items = wp_get_nav_menu_items( $menu->term_id );

            $menu_locations = array();
            foreach ( $locations as $location_key => $menu_id ) {
                if ( (int) $menu_id === (int) $menu->term_id ) {
                    $menu_locations[] = $location_key;
                }
            }

            $data[] = array(
                'id'        => (int) $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'count'     => (int) $menu->count,
                'locations' => $menu_locations,
                'items'     => $this->prepare_items( $menu_items, $request ),
            );
        }

        $this->cache_set( $key, $data );
        return $this->respond( $data, $request );
    }

    /**
     * Get a single menu by ID.
     */
    public function get_menu( $request ) {
        $menu_id = (int) $request['id'];
        $menu    = wp_get_nav_menu_object( $menu_id );

        if ( ! $menu ) {
            return new WP_Error( 'endpointy_menu_not_found', __( 'Menu not found.', 'endpointy-menus' ), array( 'status' => 404 ) );
        }

        return $this->respond( $this->prepare_single_menu( $menu, $request ), $request );
    }

    /**
     * Get a single menu by slug.
     */
    public function get_menu_by_slug( $request ) {
        $slug = sanitize_title( $request['slug'] );
        $menu = wp_get_nav_menu_object( $slug );

        if ( ! $menu ) {
            return new WP_Error( 'endpointy_menu_not_found', __( 'Menu not found.', 'endpointy-menus' ), array( 'status' => 404 ) );
        }

        return $this->respond( $this->prepare_single_menu( $menu, $request ), $request );
    }

    /**
     * Get all registered menu locations.
     */
    public function get_locations( $request ) {
        $locations      = get_registered_nav_menus();
        $menu_locations = get_nav_menu_locations();
        $data           = array();

        foreach ( $locations as $location_key => $description ) {
            $menu_id = isset( $menu_locations[ $location_key ] ) ? (int) $menu_locations[ $location_key ] : null;
            $menu    = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;

            $data[] = array(
                'location'    => $location_key,
                'description' => $description,
                'menu_id'     => $menu_id,
                'menu_name'   => $menu ? $menu->name : null,
                'menu_slug'   => $menu ? $menu->slug : null,
            );
        }

        return $this->respond( $data, $request );
    }

    /**
     * Get a menu by location (e.g., 'primary', 'footer').
     */
    public function get_menu_by_location( $request ) {
        $location  = $request['location'];
        $locations = get_nav_menu_locations();

        if ( ! isset( $locations[ $location ] ) ) {
            return new WP_Error( 'endpointy_menu_location_not_found', __( 'Menu location not found.', 'endpointy-menus' ), array( 'status' => 404 ) );
        }

        $menu = wp_get_nav_menu_object( (int) $locations[ $location ] );

        if ( ! $menu ) {
            return new WP_Error( 'endpointy_menu_not_assigned', __( 'No menu assigned to this location.', 'endpointy-menus' ), array( 'status' => 404 ) );
        }

        $payload             = $this->prepare_single_menu( $menu, $request );
        $payload             = array( 'location' => $location ) + $payload;

        return $this->respond( $payload, $request );
    }

    /* ---------------------------------------------------------------------
     * Item formatting
     * ------------------------------------------------------------------- */

    /**
     * Prepare a single menu payload, honouring cache + query params.
     *
     * @param WP_Term         $menu    Menu term.
     * @param WP_REST_Request $request Request.
     * @return array
     */
    private function prepare_single_menu( $menu, $request ) {
        $key    = $this->cache_key( 'menu_' . $menu->term_id, $request );
        $cached = $this->cache_get( $key );
        if ( false !== $cached ) {
            return $cached;
        }

        $menu_items = wp_get_nav_menu_items( $menu->term_id );

        $payload = array(
            'id'    => (int) $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'count' => (int) $menu->count,
            'items' => $this->prepare_items( $menu_items, $request ),
        );

        $this->cache_set( $key, $payload );
        return $payload;
    }

    /**
     * Turn raw menu items into the requested shape (flat/nested, fields, meta).
     *
     * @param array           $items   Raw menu items.
     * @param WP_REST_Request $request Request.
     * @return array
     */
    private function prepare_items( $items, $request ) {
        $nested = (bool) $request->get_param( 'nested' );
        $meta   = (bool) $request->get_param( 'meta' );
        $fields = $this->parse_fields( $request->get_param( 'fields' ) );

        if ( $nested ) {
            return $this->build_menu_tree( $items, 0, $meta, $fields );
        }

        if ( empty( $items ) ) {
            return array();
        }

        $formatted = array();
        foreach ( $items as $item ) {
            $formatted[] = $this->format_menu_item( $item, $meta, $fields );
        }
        return $formatted;
    }

    /**
     * Parse the comma-separated `fields` param into a lookup map.
     *
     * @param string $fields Raw param.
     * @return array Empty array = all fields.
     */
    private function parse_fields( $fields ) {
        $fields = trim( (string) $fields );
        if ( '' === $fields ) {
            return array();
        }
        $list = array_filter( array_map( 'trim', explode( ',', $fields ) ) );
        return array_fill_keys( $list, true );
    }

    /**
     * Format a single menu item into a clean array.
     *
     * @param object $item   Menu item.
     * @param bool   $meta   Whether to include extra meta.
     * @param array  $fields Field filter map.
     * @return array
     */
    private function format_menu_item( $item, $meta = false, $fields = array() ) {
        $data = array(
            'id'        => (int) $item->ID,
            'title'     => $item->title,
            'url'       => $item->url,
            'parent'    => (int) $item->menu_item_parent,
            'order'     => (int) $item->menu_order,
            'type'      => $item->type,
            'object'    => $item->object,
            'object_id' => (int) $item->object_id,
            'target'    => $item->target,
            'classes'   => $item->classes,
            'xfn'       => $item->xfn,
        );

        if ( $meta ) {
            $data['description'] = $item->description;
            $data['attr_title']  = $item->attr_title;
            $data['current']     = ! empty( $item->current );

            $thumb_id = ( 'post_type' === $item->type ) ? get_post_thumbnail_id( $item->object_id ) : 0;
            $data['featured_image'] = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : null;

            if ( 'post_type' === $item->type && $item->object_id ) {
                $post = get_post( $item->object_id );
                $data['excerpt'] = $post ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';
            }
        }

        /**
         * Filter a formatted menu item before it is returned.
         *
         * @param array  $data The formatted item.
         * @param object $item The raw menu item object.
         * @param bool   $meta Whether meta was requested.
         */
        $data = apply_filters( 'endpointy_menus_item', $data, $item, $meta );

        return $this->filter_fields( $data, $fields );
    }

    /**
     * Restrict an item to the requested fields (children always kept).
     *
     * @param array $data   Item data.
     * @param array $fields Field map.
     * @return array
     */
    private function filter_fields( $data, $fields ) {
        if ( empty( $fields ) ) {
            return $data;
        }
        $kept = array_intersect_key( $data, $fields );
        if ( isset( $data['children'] ) ) {
            $kept['children'] = $data['children'];
        }
        return $kept;
    }

    /**
     * Build a nested tree structure from flat menu items.
     *
     * @param array $items     Menu items.
     * @param int   $parent_id Parent item ID.
     * @param bool  $meta      Whether to include meta.
     * @param array $fields    Field filter map.
     * @return array
     */
    private function build_menu_tree( $items, $parent_id = 0, $meta = false, $fields = array() ) {
        if ( empty( $items ) ) {
            return array();
        }

        $tree = array();
        foreach ( $items as $item ) {
            if ( (int) $item->menu_item_parent === $parent_id ) {
                $node             = $this->format_menu_item( $item, $meta, $fields );
                $node['children'] = $this->build_menu_tree( $items, (int) $item->ID, $meta, $fields );
                $tree[]           = $node;
            }
        }

        return $tree;
    }
}

new Endpointy_Menus();

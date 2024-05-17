<?php
/**
 * Plugin Name: Disciple.Tools - Survey Collection
 * Plugin URI: https://github.com/DiscipleTools/disciple-tools-survey-collection
 * Description: Disciple.Tools - Survey Collection is intended to help with the collection and presentation of regular activity statistics.
 * Text Domain: disciple-tools-survey-collection
 * Domain Path: /languages
 * Version:  1.2.3
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-survey-collection
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.6
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Gets the instance of the `Disciple_Tools_Survey_Collection` class.
 *
 * @since  0.1
 * @access public
 * @return object|bool
 */
function disciple_tools_survey_collection() {
    $disciple_tools_survey_collection_required_dt_theme_version = '1.19';
    $wp_theme = wp_get_theme();
    $version = $wp_theme->version;

    /*
     * Check if the Disciple.Tools theme is loaded and is the latest required version
     */
    $is_theme_dt = class_exists( 'Disciple_Tools' );
    if ( $is_theme_dt && version_compare( $version, $disciple_tools_survey_collection_required_dt_theme_version, '<' ) ) {
        add_action( 'admin_notices', 'disciple_tools_survey_collection_hook_admin_notice' );
        add_action( 'wp_ajax_dismissed_notice_handler', 'dt_hook_ajax_notice_handler' );
        return false;
    }
    if ( !$is_theme_dt ){
        return false;
    }
    /**
     * Load useful function from the theme
     */
    if ( !defined( 'DT_FUNCTIONS_READY' ) ){
        require_once get_template_directory() . '/dt-core/global-functions.php';
    }

    return Disciple_Tools_Survey_Collection::instance();

}

add_action( 'disciple_tools_load_plugins', 'disciple_tools_survey_collection', 20 );

add_action( 'disciple_tools_loaded', function () {
    require_once( 'magic-link/magic-link-user-app.php' );
} );


//register the D.T Plugin
add_filter( 'dt_plugins', function ( $plugins ){
    $plugin_data = get_file_data( __FILE__, [ 'Version' => 'Version', 'Plugin Name' => 'Plugin Name' ], false );
    $plugins['disciple-tools-survey-collection'] = [
        'plugin_url' => trailingslashit( plugin_dir_url( __FILE__ ) ),
        'version' => $plugin_data['Version'] ?? null,
        'name' => $plugin_data['Plugin Name'] ?? null,
    ];
    return $plugins;
});

/**
 * Singleton class for setting up the plugin.
 *
 * @since  0.1
 * @access public
 */
class Disciple_Tools_Survey_Collection {

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $is_rest = dt_is_rest();
        /**
         * Create a new post type
         */
        require_once( 'post-type/loader.php' ); // add starter post type extension to Disciple.Tools system

        /**
         * Create a custom site-to-site link
         */
        require_once( 'site-link/custom-site-to-site-links.php' ); // add site to site link class and capabilities

        /**
         * Add new charts to the metrics section
         */
        if ( strpos( dt_get_url_path(), 'metrics' ) !== false || ( $is_rest && strpos( dt_get_url_path(), 'disciple-tools-survey-collection-metrics' ) !== false ) ){
            require_once( 'charts/charts-loader.php' );  // add custom charts to the metrics area
        }

        /**
         * Add a custom tile or settings page tile
         */
        require_once( 'tile/custom-tile.php' ); // add custom tile
        require_once( 'tile/dashboard-tile.php' ); // add dashboard tile
//        if ( 'settings' === dt_get_url_path() && ! $is_rest ) {
//            require_once( 'tile/settings-tile.php' ); // add custom settings page tile
//        }

        /**
         * Add a custom admin page in the admin area
         */
//        if ( is_admin() ) {
//            require_once( 'admin/admin-menu-and-tabs.php' ); // adds starter admin page and section for plugin
//        }

        /**
         * Support localization of your plugin
         */
        $this->i18n();

        /**
         * Customize links for your plugin in the plugin admin area
         */
        if ( is_admin() ) { // adds links to the plugin description area in the plugin admin list.
            add_filter( 'plugin_row_meta', [ $this, 'plugin_description_links' ], 10, 4 );
        }

        /**
         * Versioning System
         */
        require_once( 'admin/config-required-plugins.php' );
    }

    /**
     * Filters the array of row meta for each/specific plugin in the Plugins list table.
     * Appends additional links below each/specific plugin on the plugins page.
     */
    public function plugin_description_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
        if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
            // You can still use `array_unshift()` to add links at the beginning.

            $links_array[] = '<a href="https://disciple.tools">Disciple.Tools Community</a>';
        }

        return $links_array;
    }

    /**
     * Method that runs only when the plugin is activated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function activation() {
        // add elements here that need to fire on activation
    }

    /**
     * Method that runs only when the plugin is deactivated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function deactivation() {
        // add functions here that need to happen on deactivation
        delete_option( 'dismissed-disciple-tools-survey-collection' );
    }

    /**
     * Loads the translation files.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function i18n() {
        $domain = 'disciple-tools-survey-collection';
        load_plugin_textdomain( $domain, false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ). 'languages' );
    }

    /**
     * Magic method to output a string if trying to use the object as a string.
     *
     * @since  0.1
     * @access public
     * @return string
     */
    public function __toString() {
        return 'disciple-tools-survey-collection';
    }

    /**
     * Magic method to keep the object from being cloned.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
    }

    /**
     * Magic method to keep the object from being unserialized.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
    }

    /**
     * Magic method to prevent a fatal error when calling a method that doesn't exist.
     *
     * @param string $method
     * @param array $args
     * @return null
     * @since  0.1
     * @access public
     */
    public function __call( $method = '', $args = array() ) {
        _doing_it_wrong( 'disciple_tools_survey_collection::' . esc_html( $method ), 'Method does not exist.', '0.1' );
        unset( $method, $args );
        return null;
    }

    /**
     * Get the plugin directory.
     *
     * @return string
     */
    public static function dir() {
        return __DIR__ . '/';
    }

    /**
     * Get the plugin directory.
     *
     * @return string
     */
    public static function includes_dir() {
        return self::dir() . 'includes/';
    }

    /**
     * Get the plugin directory.
     *
     * @return string
     */
    public static function path() {
        return plugin_dir_url( __FILE__ );
    }
}


// Register activation hook.
register_activation_hook( __FILE__, [ 'Disciple_Tools_Survey_Collection', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'Disciple_Tools_Survey_Collection', 'deactivation' ] );


if ( ! function_exists( 'disciple_tools_survey_collection_hook_admin_notice' ) ) {
    function disciple_tools_survey_collection_hook_admin_notice() {
        global $disciple_tools_survey_collection_required_dt_theme_version;
        $wp_theme = wp_get_theme();
        $current_version = $wp_theme->version;
        $message = "'Disciple.Tools - Survey Collection' plugin requires 'Disciple.Tools' theme to work. Please activate 'Disciple.Tools' theme or make sure it is latest version.";
        if ( $wp_theme->get_template() === 'disciple-tools-theme' ){
            $message .= ' ' . sprintf( esc_html( 'Current Disciple.Tools version: %1$s, required version: %2$s' ), esc_html( $current_version ), esc_html( $disciple_tools_survey_collection_required_dt_theme_version ) );
        }
        // Check if it's been dismissed...
        if ( ! get_option( 'dismissed-disciple-tools-survey-collection', false ) ) { ?>
            <div class="notice notice-error notice-disciple-tools-survey-collection is-dismissible" data-notice="disciple-tools-survey-collection">
                <p><?php echo esc_html( $message );?></p>
            </div>
            <script>
                jQuery(function($) {
                    $( document ).on( 'click', '.notice-disciple-tools-survey-collection .notice-dismiss', function () {
                        $.ajax( ajaxurl, {
                            type: 'POST',
                            data: {
                                action: 'dismissed_notice_handler',
                                type: 'disciple-tools-survey-collection',
                                security: '<?php echo esc_html( wp_create_nonce( 'wp_rest_dismiss' ) ) ?>'
                            }
                        })
                    });
                });
            </script>
        <?php }
    }
}

/**
 * AJAX handler to store the state of dismissible notices.
 */
if ( !function_exists( 'dt_hook_ajax_notice_handler' ) ){
    function dt_hook_ajax_notice_handler(){
        check_ajax_referer( 'wp_rest_dismiss', 'security' );
        if ( isset( $_POST['type'] ) ){
            $type = sanitize_text_field( wp_unslash( $_POST['type'] ) );
            update_option( 'dismissed-' . $type, true );
        }
    }
}

/**
 * Plugin Releases and updates
 *
 * To remove: delete the section of code below and delete the file called version-control.json in the plugin root
 *
 * This section runs the remote plugin updating service, so you can issue distributed updates to your plugin
 *
 * @note See the instructions for version updating to understand the steps involved.
 * @link https://github.com/DiscipleTools/disciple-tools-survey-collection/wiki/Configuring-Remote-Updating-System
 *
 */
/**
 * Check for plugin updates even when the active theme is not Disciple.Tools
 *
 * Below is the publicly hosted .json file that carries the version information. This file can be hosted
 * anywhere as long as it is publicly accessible. You can download the version file listed below and use it as
 * a template.
 * Also, see the instructions for version updating to understand the steps involved.
 * @see https://github.com/DiscipleTools/disciple-tools-version-control/wiki/How-to-Update-the-Starter-Plugin
 */
add_action( 'plugins_loaded', function (){
    if ( is_admin() && ! ( is_multisite() && class_exists( 'DT_Multisite' ) ) || wp_doing_cron() ) {
        // Check for plugin updates
        if ( ! class_exists( 'Puc_v4_Factory' ) ) {
            if ( file_exists( get_template_directory() . '/dt-core/libraries/plugin-update-checker/plugin-update-checker.php' ) ) {
                require( get_template_directory() . '/dt-core/libraries/plugin-update-checker/plugin-update-checker.php' );
            }
        }
        if ( class_exists( 'Puc_v4_Factory' ) ) {
            Puc_v4_Factory::buildUpdateChecker(
                'https://raw.githubusercontent.com/DiscipleTools/disciple-tools-survey-collection/master/version-control.json',
                __FILE__,
                'disciple-tools-survey-collection'
            );

        }
    }
} );

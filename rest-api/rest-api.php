<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Survey_Collection_Endpoints
{
    /**
     * @todo Set the permissions your endpoint needs
     * @link https://github.com/DiscipleTools/Documentation/blob/master/theme-core/capabilities.md
     * @var string[]
     */
    public $permissions = [ 'access_contacts', 'dt_all_access_contacts', 'view_project_metrics' ];


    /**
     * @todo define the name of the $namespace
     * @todo define the name of the rest route
     * @todo defne method (CREATABLE, READABLE)
     * @todo apply permission strategy. '__return_true' essentially skips the permission check.
     */
    //See https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
    public function add_api_routes() {
        $namespace = 'disciple-tools-survey-collection/v1';

        register_rest_route(
            $namespace, '/stats', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'stats' ],
                'permission_callback' => function ( WP_REST_Request $request ) {
                    return $this->has_permission();
                },
            ]
        );
    }


    public function stats( WP_REST_Request $request ) {

        // Calculate statistics for reports post type.
        $stats = apply_filters( 'dt_after_get_post_fields_filter', [], 'reports' );

        // Package any generated statistics.
        $response = [];
        if ( isset( $stats['new_baptisms_all_time'], $stats['new_groups_all_time'], $stats['active_groups_all_time'], $stats['shares_all_time'], $stats['prayers_all_time'], $stats['invites_all_time'] ) ) {
            $field_settings = DT_Posts::get_post_field_settings( 'reports', false );
            $fields         = [
                'new_baptisms_all_time',
                'new_groups_all_time',
                'active_groups_all_time',
                'shares_all_time',
                'prayers_all_time',
                'invites_all_time'
            ];

            foreach ( $fields as $field ) {
                $response[] = [
                    'label' => $field_settings[ $field ]['name'] ?? $field,
                    'value' => $stats[ $field ]
                ];
            }
        }

        return $response;
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }
    public function has_permission(){
        $pass = false;
        foreach ( $this->permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }
}
Disciple_Tools_Survey_Collection_Endpoints::instance();

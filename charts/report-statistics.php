<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * @todo replace all occurrences of the string "template" with a string of your choice
 * @todo also rename in charts-loader.php
 */
class Disciple_Tools_Survey_Collection_Report_Statistics extends DT_Metrics_Chart_Base {
    public $base_slug = 'disciple-tools-survey-collection-metrics'; // lowercase
    public $base_title = 'Survey Collection Metrics';

    public $title = 'Report Statistics';
    public $slug = 'report_stats'; // lowercase
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = 'report-statistics.js'; // should be full file name plus extension
    public $permissions = [ 'dt_all_access_contacts', 'view_project_metrics' ];

    public function __construct() {
        parent::__construct();

        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        if ( ! $this->has_permission() ) {
            return;
        }
        $url_path = dt_get_url_path();

        // only load scripts if exact url
        if ( "metrics/$this->base_slug/$this->slug" === $url_path ) {

            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }
    }


    /**
     * Load scripts for the plugin
     */
    public function scripts() {

        wp_register_script( 'amcharts-core', 'https://www.amcharts.com/lib/4/core.js', false, '4' );
        wp_register_script( 'amcharts-charts', 'https://www.amcharts.com/lib/4/charts.js', false, '4' );

        wp_enqueue_script( 'dt_' . $this->slug . '_script', trailingslashit( plugin_dir_url( __FILE__ ) ) . $this->js_file_name, [
            'jquery',
            'amcharts-core',
            'amcharts-charts'
        ], filemtime( plugin_dir_path( __FILE__ ) . $this->js_file_name ), true );

        // Localize script with array data
        wp_localize_script(
            'dt_' . $this->slug . '_script', $this->js_object_name, [
                'rest_endpoints_base' => esc_url_raw( rest_url() ) . "$this->base_slug/$this->slug",
                'base_slug'           => $this->base_slug,
                'slug'                => $this->slug,
                'root'                => esc_url_raw( rest_url() ),
                'plugin_uri'          => plugin_dir_url( __DIR__ ),
                'nonce'               => wp_create_nonce( 'wp_rest' ),
                'current_user_login'  => wp_get_current_user()->user_login,
                'current_user_id'     => get_current_user_id(),
                'stats'               => $this->stats( 0, time() ),
                'translations'        => [
                    'title'     => $this->title,
                    'sub_title' => __( 'Year-To-Date (YTD) Report Statistics', 'disciple-tools-survey-collection' ),
                    'refresh'   => __( 'Refresh', 'disciple-tools-survey-collection' )
                ]
            ]
        );
    }

    public function add_api_routes() {
        $namespace = "$this->base_slug/$this->slug";
        register_rest_route(
            $namespace, '/refresh', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'refresh' ],
                'permission_callback' => function ( WP_REST_Request $request ) {
                    return $this->has_permission();
                },
            ]
        );
    }

    public function refresh( WP_REST_Request $request ) {
        return $this->stats( 0, time() );
    }

    private function stats( $start_ts, $end_ts ): array {

        // Fetch raw global stats.
        $raw_stats = apply_filters( 'survey_collection_metrics_global_stats', [], 'reports', $start_ts, $end_ts );

        // Re-package data into something usable!
        $packaged_stats = [];
        $stats          = [
            [
                'key'   => 'stats_new_baptisms',
                'label' => __( 'Total Global New Baptisms', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_new_groups',
                'label' => __( 'Total Global New Groups', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_shares',
                'label' => __( 'Total Global Shares', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_prayers',
                'label' => __( 'Total Global Prayers', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_invites',
                'label' => __( 'Total Global Invites', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_active_groups',
                'label' => __( 'Current Global Active Groups', 'disciple-tools-survey-collection' )
            ]
        ];
        if ( ! empty( $raw_stats ) ) {
            foreach ( $stats as $stat ) {
                if ( isset( $raw_stats[ $stat['key'] ] ) ) {
                    $packaged_stats[] = [
                        'key'   => $stat['key'],
                        'label' => $stat['label'],
                        'value' => $raw_stats[ $stat['key'] ]
                    ];
                }
            }
        }

        return $packaged_stats;
    }
}

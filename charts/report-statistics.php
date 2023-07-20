<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class Disciple_Tools_Survey_Collection_Report_Statistics extends DT_Metrics_Chart_Base {
    public $base_slug = 'disciple-tools-survey-collection-metrics'; // lowercase
    public $base_title = 'Survey Collection Metrics';

    public $title = 'Report Statistics';
    public $slug = 'report_stats'; // lowercase
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = 'report-statistics.js'; // should be full file name plus extension
    public $permissions = [ 'dt_all_access_contacts', 'view_project_metrics', 'access_reports' ];

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
                'stats'               => $this->stats( $this->ytd_start(), time() ),
                'translations'        => [
                    'title'     => $this->title,
                    'sub_title' => __( 'Year-To-Date (YTD) Report Statistics', 'disciple-tools-survey-collection' ),
                    'refresh'   => __( 'Refresh', 'disciple-tools-survey-collection' ),
                    'sections'  => [
                        'leading' => __( 'Leading Indicators', 'disciple-tools-survey-collection' ),
                        'lagging' => __( 'Lagging Indicators', 'disciple-tools-survey-collection' ),
                        'custom' => __( 'Custom Indicators', 'disciple-tools-survey-collection' ),
                        'accountability' => [
                            'account' => __( 'Accounted', 'disciple-tools-survey-collection' ),
                            'not_account' => __( 'Not Accounted', 'disciple-tools-survey-collection' ),
                            'user' => __( 'Users', 'disciple-tools-survey-collection' )
                        ]
                    ]
                ]
            ]
        );
    }

    public function ytd_start(){
        return strtotime( gmdate( 'Y' ) . '/01/01' );
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
        return $this->stats( $this->ytd_start(), time() );
    }

    private function stats( $start_ts, $end_ts ): array {

        // Fetch raw global stats.
        $raw_stats = apply_filters( 'survey_collection_metrics_global_stats', [], 'reports', $start_ts, $end_ts );

        // Re-package data into something usable!
        $packaged_stats = [];
        $stats          = [
            [
                'key'   => 'stats_prayers',
                'label' => __( 'Total Global Prayers', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_shares',
                'label' => __( 'Total Global Shares', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_invites',
                'label' => __( 'Total Global Invites', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_new_baptisms',
                'label' => __( 'Total Global New Baptisms', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key'   => 'stats_new_groups',
                'label' => __( 'Total Global New Groups', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key'   => 'stats_active_groups',
                'label' => __( 'Current Global Active Groups', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key'   => 'stats_participants',
                'label' => __( 'Active Global Participants', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ]
        ];

        // Start packaging response stats.
        $response = [];
        if ( ! empty( $raw_stats ) ) {
            foreach ( $stats as $stat ) {
                if ( isset( $raw_stats[ $stat['key'] ] ) ) {
                    $packaged_stats[] = [
                        'key'   => $stat['key'],
                        'label' => $stat['label'],
                        'section' => $stat['section'],
                        'value' => $raw_stats[ $stat['key'] ]
                    ];
                }
            }

            // Package any identified custom fields.
            if ( !empty( $raw_stats['stats_custom'] ) ){
                $custom_field_settings = DT_Posts::get_post_field_settings( 'reports', false );
                foreach ( $raw_stats['stats_custom'] as $field_key => $stat ){
                    if ( isset( $custom_field_settings[$field_key], $custom_field_settings[$field_key]['name'] ) ){
                        $packaged_stats[] = [
                            'key' => $field_key,
                            'label' => $custom_field_settings[$field_key]['name'],
                            'section' => 'custom',
                            'value' => $stat
                        ];
                    }
                }
            }

            $response['general'] = $packaged_stats;

            // Package any detected accountability stats.
            if ( isset( $raw_stats['stats_accountability'] ) ){
                $response['accountability'] = [
                    'key' => 'stats_accountability',
                    'label' => __( 'Accountability Meetings Within Past 30 Days Of Submitted Reports', 'disciple-tools-survey-collection' ),
                    'section' => 'accountability',
                    'stats' => $raw_stats['stats_accountability']
                ];
            }
        }

        return $response;
    }
}

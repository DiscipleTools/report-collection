<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_Survey_Collection_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_Survey_Collection_Base extends DT_Module_Base {

    /**
     * Define post type variables
     * @var string
     */
    public $post_type = 'reports';
    public $module = 'reports_base';
    public $single_name = 'Report';
    public $plural_name = 'Reports';
    public static function post_type(){
        return 'reports';
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }

        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 ); //after contacts

        //setup tiles and fields
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_filter( 'dt_get_post_type_settings', [ $this, 'dt_get_post_type_settings' ], 20, 2 );

        // hooks
        add_filter( 'dt_post_create_fields', [ $this, 'dt_post_create_fields' ], 10, 2 );
        add_filter( 'dt_after_get_post_fields_filter', [ $this, 'dt_after_get_post_fields_filter' ], 10, 2 );
        add_filter( 'survey_collection_identify_other_metric_fields', [ $this, 'identify_other_metric_fields' ], 10, 5 );
        add_filter( 'survey_collection_metrics_user_stats', [ $this, 'calculate_user_statistics' ], 10, 2 );
        add_filter( 'survey_collection_metrics_global_stats', [ $this, 'calculate_global_statistics' ], 10, 4 );
        add_action( 'survey_collection_metrics_dashboard_stats_html', [ $this, 'render_metrics_dashboard_stats_html' ], 10, 1 );
        add_action( 'dt_post_created', [ $this, 'dt_post_created' ], 100, 3 );

        //list
        add_filter( 'dt_user_list_filters', [ $this, 'dt_user_list_filters' ], 10, 2 );
        add_filter( 'dt_filter_access_permissions', [ $this, 'dt_filter_access_permissions' ], 20, 2 );

    }

    public function after_setup_theme() {
        $this->single_name = __( 'Report', 'disciple-tools-survey-collection' );
        $this->plural_name = __( 'Reports', 'disciple-tools-survey-collection' );

        if ( class_exists( 'Disciple_Tools_Post_Type_Template' ) ) {
            new Disciple_Tools_Post_Type_Template( $this->post_type, $this->single_name, $this->plural_name );
        }

        if ( class_exists( 'Disciple_Tools_Survey_Collection_Dashboard_Tile' ) ) {
            DT_Dashboard_Plugin_Tiles::instance()->register(
                new Disciple_Tools_Survey_Collection_Dashboard_Tile(
                    'dt_survey_collection_dashboard_tile',
                    __( 'My Report Statistics', 'disciple-tools-survey-collection' ),
                    [
                        'priority' => 1,
                        'span'     => 2
                    ]
                )
            );
        }
    }

    /**
     * Set the singular and plural translations for this post types settings
     * The add_filter is set onto a higher priority than the one in Disciple_tools_Post_Type_Template
     * so as to enable localisation changes. Otherwise the system translation passed in to the custom post type
     * will prevail.
     */
    public function dt_get_post_type_settings( $settings, $post_type ) {
        if ( $post_type === $this->post_type ) {
            $settings['label_singular'] = __( 'Report', 'disciple-tools-survey-collection' );
            $settings['label_plural']   = __( 'Reports', 'disciple-tools-survey-collection' );
            $settings['status_field']   = [
                'status_key'   => 'status',
                'archived_key' => 'archive',
            ];
        }

        return $settings;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/roles-permissions.md#rolesd
     */
    public function dt_set_roles_and_permissions( $expected_roles ){

        if ( !isset( $expected_roles['multiplier'] ) ){
            $expected_roles['multiplier'] = [

                'label' => __( 'Multiplier', 'disciple-tools-survey-collection' ),
                'description' => 'Interacts with Contacts and Groups',
                'permissions' => []
            ];
        }

        // if the user can access contact they also can access this post type
        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]['permissions']['access_contacts'] ) && $expected_roles[$role]['permissions']['access_contacts'] ){
                $expected_roles[$role]['permissions']['access_' . $this->post_type ] = true;
                $expected_roles[$role]['permissions']['create_' . $this->post_type] = true;
                $expected_roles[$role]['permissions']['update_' . $this->post_type] = true;
            }
        }

        if ( isset( $expected_roles['administrator'] ) ){
            $expected_roles['administrator']['permissions']['view_any_'.$this->post_type ] = true;
            $expected_roles['administrator']['permissions']['update_any_'.$this->post_type ] = true;
        }
        if ( isset( $expected_roles['dt_admin'] ) ){
            $expected_roles['dt_admin']['permissions']['view_any_'.$this->post_type ] = true;
            $expected_roles['dt_admin']['permissions']['update_any_'.$this->post_type ] = true;
        }

        return $expected_roles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ) {
        if ( $post_type === $this->post_type ) {

            // Status fields
            $fields['status']      = [
                'name'          => __( 'Status', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Set the current status.', 'disciple-tools-survey-collection' ),
                'type'          => 'key_select',
                'default'       => [
                    'archive' => [
                        'label'       => __( 'Archived', 'disciple-tools-survey-collection' ),
                        'description' => __( 'No longer active.', 'disciple-tools-survey-collection' ),
                        'color'       => '#F43636'
                    ],
                    'active'   => [
                        'label'       => __( 'Active', 'disciple-tools-survey-collection' ),
                        'description' => __( 'Is active.', 'disciple-tools-survey-collection' ),
                        'color'       => '#4CAF50'
                    ],
                ],
                'tile'          => 'status',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/status.svg',
                'default_color' => '#366184',
                'show_in_table' => 10,
            ];
            $fields['assigned_to'] = [
                'name'          => __( 'Assigned To', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Select the main person who is responsible for reporting on this record.', 'disciple-tools-survey-collection' ),
                'type'          => 'user_select',
                'default'       => '',
                'tile'          => 'status',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg',
                'show_in_table' => 16,
            ];

            // Tracking fields
            $fields['rpt_start_date'] = [
                'name'        => __( 'Report Start Date', 'disciple-tools-survey-collection' ),
                'description' => __( 'Report start date.', 'disciple-tools-survey-collection' ),
                'type'        => 'date',
                'default'     => '',
                'tile'        => 'tracking',
                'icon'        => get_template_directory_uri() . '/dt-assets/images/date.svg',
            ];
            $fields['submit_date']    = [
                'name'        => __( 'Submission Date', 'disciple-tools-survey-collection' ),
                'description' => __( 'Report submission date; which forms the basis of all statistical calculations.', 'disciple-tools-survey-collection' ),
                'type'        => 'date',
                'default'     => '',
                'tile'        => 'tracking',
                'icon'        => get_template_directory_uri() . '/dt-assets/images/date.svg',
            ];
            $fields['shares']         = [
                'name'          => __( 'Shares', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total Shares.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/share.svg',
                'show_in_table' => 10
            ];
            $fields['prayers']        = [
                'name'          => __( 'Prayers', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total Prayers.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/bible.svg',
                'show_in_table' => 11
            ];
            $fields['invites']        = [
                'name'          => __( 'Invites', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total Invites.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/chat.svg',
                'show_in_table' => 12
            ];
            $fields['new_baptisms']   = [
                'name'          => __( 'New Baptisms', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total New Baptisms.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/baptism.svg',
                'show_in_table' => 13
            ];
            $fields['new_groups']     = [
                'name'          => __( 'New Groups', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total New Groups.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
                'show_in_table' => 14
            ];
            $fields['active_groups']  = [
                'name'          => __( 'Active Groups', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total Active Groups.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/groups.svg',
                'show_in_table' => 15
            ];
            $fields['participants']   = [
                'name'          => __( 'Participants', 'disciple-tools-survey-collection' ),
                'description'   => __( 'Count of total group participants.', 'disciple-tools-survey-collection' ),
                'type'          => 'number',
                'default'       => '',
                'tile'          => 'tracking',
                'icon'          => get_template_directory_uri() . '/dt-assets/images/participants.svg',
                'show_in_table' => 16
            ];
            $fields['accountability']    = [
                'name'        => __( 'Accountability', 'disciple-tools-survey-collection' ),
                'description' => __( 'Last accountability date.', 'disciple-tools-survey-collection' ),
                'type'        => 'date',
                'default'     => '',
                'tile'        => 'tracking',
                'icon'        => get_template_directory_uri() . '/dt-assets/images/date.svg',
            ];
        }

        return $fields;
    }

    /**
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md
     */
    public function dt_details_additional_tiles( $tiles, $post_type = '' ) {
        if ( $post_type === $this->post_type ) {
            $tiles['tracking'] = [ 'label' => __( 'Tracking', 'disciple-tools-survey-collection' ) ];
        }

        return $tiles;
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md#add-custom-content
     */
    public function dt_details_additional_section( $section, $post_type ){

        if ( $post_type === $this->post_type && $section === 'other' ) {
            $fields = DT_Posts::get_post_field_settings( $post_type );
            $post = DT_Posts::get_post( $this->post_type, get_the_ID() );
            ?>
            <div class="section-subheader">
                <?php esc_html_e( 'Custom Section Contact', 'disciple-tools-survey-collection' ) ?>
            </div>
            <div>
                <p>Add information or custom fields here</p>
            </div>

        <?php }
    }

    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === $this->post_type ){
            $post_fields = DT_Posts::get_post_field_settings( $post_type );
            if ( isset( $post_fields['status'] ) && !isset( $fields['status'] ) ){
                $fields['status'] = 'active';
            }
        }
        return $fields;
    }

    public function render_metrics_dashboard_stats_html( $stats ) {
        $leading_section = [];
        $lagging_section = [];

        // Place stats into their respective sections.
        foreach ( $stats ?? [] as $stat ){
            if ( isset( $stat['value'], $stat['label'], $stat['section'] ) ){
                if ( $stat['section'] == 'leading' ){
                    $leading_section[] = $stat;

                } else {
                    $lagging_section[] = $stat;
                }
            }
        }

        // Display leading section.
        if ( count( $leading_section ) > 0 ){
            ?>
            <h5><b><?php echo esc_attr( __( 'Leading Indicators', 'disciple-tools-survey-collection' ) ) ?></b></h5>
            <div style="display: flex; flex-flow: row wrap; justify-content: center; overflow: auto;">
            <?php
            foreach ( $leading_section as $stat ){
                ?>
                <div style="margin-right: 30px; flex: 1 1 0;">
                    <div><span
                            style="font-size: 30px; font-weight: bold; color: blue;"><?php echo esc_attr( is_numeric( $stat['value'] ) ? number_format( $stat['value'] ?: 0 ) : $stat['value'] ) ?></span>
                    </div>
                    <div><?php echo esc_attr( $stat['label'] ) ?></div>
                </div>
                <?php
            }
            ?>
            </div><br>
            <?php
        }

        // Display lagging section.
        if ( count( $lagging_section ) > 0 ){
            ?>
            <h5><b><?php echo esc_attr( __( 'Lagging Indicators', 'disciple-tools-survey-collection' ) ) ?></b></h5>
            <div style="display: flex; flex-flow: row wrap; justify-content: center; overflow: auto;">
            <?php
            foreach ( $lagging_section as $stat ){
                ?>
                <div style="margin-right: 30px; flex: 1 1 0;">
                    <div><span
                            style="font-size: 30px; font-weight: bold; color: blue;"><?php echo esc_attr( is_numeric( $stat['value'] ) ? number_format( $stat['value'] ?: 0 ) : $stat['value'] ) ?></span>
                    </div>
                    <div><?php echo esc_attr( $stat['label'] ) ?></div>
                </div>
                <?php
            }
            ?>
            </div>
            <?php
        }
    }

    public function calculate_global_statistics( $stats, $post_type, $start_ts, $end_ts ) {
        global $wpdb;

        // Calculate all-time global statistics.
        // phpcs:disable
        $all_time_global_results = $wpdb->get_results( self::calculate_global_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ), ARRAY_A );
        // phpcs:enable

        // Capture all-time global result statistics.
        if ( ! empty( $all_time_global_results ) ) {
            $stats['stats_new_baptisms'] = $all_time_global_results[0]['new_baptisms'];
            $stats['stats_new_groups']   = $all_time_global_results[0]['new_groups'];
            $stats['stats_shares']       = $all_time_global_results[0]['shares'];
            $stats['stats_prayers']      = $all_time_global_results[0]['prayers'];
            $stats['stats_invites']      = $all_time_global_results[0]['invites'];
        }

        // Capture active groups global statistics.
        // phpcs:disable
        $active_groups_global_results = $wpdb->get_results( self::calculate_global_active_groups_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ), ARRAY_A );
        // phpcs:enable
        if ( !empty( $active_groups_global_results ) ){
            $stats_active_groups = 0;
            $processed_users = [];
            foreach ( $active_groups_global_results as $active_stats ){
                if ( isset( $active_stats['assigned_to'], $active_stats['active_groups'] ) && !in_array( $active_stats['assigned_to'], $processed_users ) ){
                    $processed_users[] = $active_stats['assigned_to'];
                    $stats_active_groups += intval( $active_stats['active_groups'] );
                }
            }
            $stats['stats_active_groups'] = $stats_active_groups;
        }

        // Capture participants global statistics.
        // phpcs:disable
        $participants_global_results = $wpdb->get_results( self::calculate_global_participants_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ), ARRAY_A );
        // phpcs:enable
        if ( !empty( $participants_global_results ) ){
            $stats_participants = 0;
            $processed_users = [];
            foreach ( $participants_global_results as $participants ){
                if ( isset( $participants['assigned_to'], $participants['participants'] ) && !in_array( $participants['assigned_to'], $processed_users ) ){
                    $processed_users[] = $participants['assigned_to'];
                    $stats_participants += intval( $participants['participants'] );
                }
            }
            $stats['stats_participants'] = $stats_participants;
        }

        // Capture accountability global statistics, from last x days.
        $accountability_start_ts = strtotime( '-30 days', $end_ts );

        // phpcs:disable
        $accountability_global_results = $wpdb->get_results( self::calculate_global_accountability_statistics_prepare_sql( $wpdb, $post_type, $accountability_start_ts, $end_ts ), ARRAY_A );
        // phpcs:enable

        if ( !empty( $accountability_global_results ) ){
            $accountability_in_range = 0;
            $processed_users = [];
            foreach ( $accountability_global_results as $accountability_stats ){
                if ( isset( $accountability_stats['assigned_to'], $accountability_stats['accountability_ts'] ) && !in_array( $accountability_stats['assigned_to'], $processed_users ) ){
                    $processed_users[] = $accountability_stats['assigned_to'];

                    // Determine if accountability timestamp is within specified range.
                    $accountability_ts = $accountability_stats['accountability_ts'];
                    if ( !empty( $accountability_ts ) && is_numeric( $accountability_ts ) ){
                        if ( ( $accountability_ts >= $accountability_start_ts ) && ( $accountability_ts <= $end_ts ) ){
                            $accountability_in_range++;
                        }
                    }
                }
            }
            $stats['stats_accountability'] = [
                'user_count' => count( $processed_users ),
                'in_range_count' => $accountability_in_range
            ];
        }

        return $stats;
    }

    public function calculate_user_statistics( $stats, $current_user_id ) {

        // Calculate collective report record statistics.
        $raw_statistics = self::calculate_statistics( [], $this->post_type, $current_user_id );

        // Package and return calculated statistics.
        return self::package_calculated_statistics( $stats, $raw_statistics );
    }

    //filter following get post requests
    public function dt_after_get_post_fields_filter( $fields, $post_type ) {
        if ( $post_type === $this->post_type ) {

            // Determine correct user id to be used.
            $user_id = get_current_user_id();
            if ( isset( $fields['assigned_to'] ) && $fields['assigned_to']['type'] == 'user' ) {
                $user_id = $fields['assigned_to']['id'];
            }

            // Calculate collective report record statistics.
            $statistics = self::calculate_statistics( $fields, $post_type, $user_id );

            // Package and return calculated statistics.
            return self::package_calculated_statistics( $fields, $statistics );
        }

        return $fields;
    }

    private function package_calculated_statistics( $packaged_stats, $raw_stats ) {
        if ( isset( $raw_stats['ytd'] ) ){
            foreach ( $raw_stats['ytd'] as $key => $stat ){
                $packaged_stats['stats_' . $key . '_ytd'] = $raw_stats['ytd'][$key];
            }
        }
        if ( isset( $raw_stats['all_time'] ) ){
            foreach ( $raw_stats['all_time'] as $key => $stat ){
                $packaged_stats['stats_' . $key . '_all_time'] = $raw_stats['all_time'][$key];
            }
        }
        if ( $raw_stats['misc'] ) {
            $packaged_stats['stats_active_groups'] = $raw_stats['misc']['active_groups'];
            $packaged_stats['stats_accountability_days_since'] = $raw_stats['misc']['accountability_days_since'];
        }

        return $packaged_stats;
    }

    public function identify_other_metric_fields( $other_metric_fields, $post_type, $supported_field_types, $supported_field_tiles, $ignored_fields ): array{
        $field_settings = DT_Posts::get_post_field_settings( $post_type, false );
        foreach ( $field_settings ?? [] as $field_key => $field ){
            if ( isset( $field['type'], $field['tile'] ) ){
                if ( in_array( $field['type'], $supported_field_types ) && in_array( $field['tile'], $supported_field_tiles ) ){
                    if ( !in_array( $field_key, $ignored_fields ) ){
                        $other_metric_fields[$field_key] = $field;
                    }
                }
            }
        }

        return $other_metric_fields;
    }

    private function calculate_statistics( $fields, $post_type, $current_user_id ) {
        global $wpdb;
        $statistics = [];

        // In addition to defaults, identify other metric fields to be captured.
        $other_metric_fields = self::identify_other_metric_fields( [], $post_type, [ 'number' ], [ 'tracking' ], [
            'status',
            'assigned_to',
            'submit_date',
            'rpt_start_date',
            'shares',
            'prayers',
            'invites',
            'new_baptisms',
            'new_groups',
            'active_groups'
        ] );

        // Calculate year-to-date (ytd) statistics.
        if ( ! empty( $fields['submit_date'] ) ) {
            // phpcs:disable
            $ytd_results = $wpdb->get_results( self::calculate_statistics_prepare_sql( $wpdb, $current_user_id, $fields['submit_date']['timestamp'], time(), $post_type ), ARRAY_A );
            // phpcs:enable

            // Capture ytd result stats.
            if ( ! empty( $ytd_results ) ) {
                $statistics['ytd'] = [
                    'new_baptisms'  => $ytd_results[0]['new_baptisms'],
                    'new_groups'    => $ytd_results[0]['new_groups'],
                    'shares'        => $ytd_results[0]['shares'],
                    'prayers'       => $ytd_results[0]['prayers'],
                    'invites'       => $ytd_results[0]['invites']
                ];

                // Process any other identified field metrics.
                if ( !empty( $other_metric_fields ) ){
                    foreach ( $other_metric_fields as $field_key => $field ){

                        // phpcs:disable
                        $ytd_results_other_metric_fields = $wpdb->get_results( self::calculate_other_metric_fields_statistics_prepare_sql( $wpdb, $current_user_id, $fields['submit_date']['timestamp'], time(), $field_key, $post_type ), ARRAY_A );
                        // phpcs:enable

                        // Append results to existing stats.
                        if ( !empty( $ytd_results_other_metric_fields ) ){
                            $statistics['ytd'][$field_key] = $ytd_results_other_metric_fields[0]['field_metric'];
                        }
                    }
                }
            }
        }

        // Calculate all-time statistics.
        // phpcs:disable
        $all_time_results = $wpdb->get_results( self::calculate_statistics_prepare_sql( $wpdb, $current_user_id, 0, time(), $post_type ), ARRAY_A );
        // phpcs:enable

        // Capture all time result stats.
        if ( ! empty( $all_time_results ) ) {
            $statistics['all_time'] = [
                'new_baptisms'  => $all_time_results[0]['new_baptisms'],
                'new_groups'    => $all_time_results[0]['new_groups'],
                'shares'        => $all_time_results[0]['shares'],
                'prayers'       => $all_time_results[0]['prayers'],
                'invites'       => $all_time_results[0]['invites']
            ];

            // Process any other identified field metrics.
            if ( !empty( $other_metric_fields ) ){
                foreach ( $other_metric_fields as $field_key => $field ){

                    // phpcs:disable
                    $all_time_results_other_metric_fields = $wpdb->get_results( self::calculate_other_metric_fields_statistics_prepare_sql( $wpdb, $current_user_id, 0, time(), $field_key, $post_type ), ARRAY_A );
                    // phpcs:enable

                    // Append results to existing stats.
                    if ( !empty( $all_time_results_other_metric_fields ) ){
                        $statistics['all_time'][$field_key] = $all_time_results_other_metric_fields[0]['field_metric'];
                    }
                }

                // Enforce specific participants personal metric calculations.
                if ( isset( $statistics['all_time']['participants'] ) ){

                    // phpcs:disable
                    $all_time_results_participants = $wpdb->get_results( self::calculate_participants_statistics_prepare_sql( $wpdb, $current_user_id, 0, time(), $post_type ), ARRAY_A );
                    // phpcs:enable

                    $statistics['all_time']['participants'] = $all_time_results_participants[0]['participants'] ?? 0;
                }
            }
        }

        // Update logged-in user state as required.
        $original_user = wp_get_current_user();
        wp_set_current_user( $current_user_id );

        // Obtain handle to recently submitted report.
        $recent_report_hit = DT_Posts::list_posts( 'reports', [
            'limit' => 1,
            'sort' => '-submit_date',
            'fields' => [
                [
                    'assigned_to' => [ $current_user_id ]
                ],
                'status' => [
                    'new',
                    'unassigned',
                    'assigned',
                    'active'
                ]
            ]
        ] );

        // Fetch active groups total from the latest report.
        $active_groups_latest_total = $recent_report_hit['posts'][0]['active_groups'] ?? 0;

        // Determine days since last reported accountability.
        $accountability_days_since = -1;
        $accountability_ts = DT_Posts::list_posts( 'reports', [
            'limit' => 1,
            'sort' => '-accountability',
            'fields' => [
                [
                    'assigned_to' => [ $current_user_id ]
                ],
                'status' => [
                    'new',
                    'unassigned',
                    'assigned',
                    'active'
                ]
            ]
        ] )['posts'][0]['accountability']['timestamp'] ?? 0;
        if ( $accountability_ts > 0 ){
            $accountability_days_since = round( ( time() - $accountability_ts ) / 86400 /* Days in secs! */ );
        }

        // Revert to original user.
        if ( ! empty( $original_user ) && isset( $original_user->ID ) ) {
            wp_set_current_user( $original_user->ID );
        }

        // Capture miscellaneous stats.
        $statistics['misc'] = [
            'active_groups' => $active_groups_latest_total,
            'accountability_days_since' => ( $accountability_days_since >= 0 ) ? $accountability_days_since : '-'
        ];

        return $statistics;
    }

    private function calculate_other_metric_fields_statistics_prepare_sql( $wpdb, $user_id, $start_ts, $end_ts, $field_key, $post_type ){
        return $wpdb->prepare( "
        SELECT SUM(field_metric) field_metric
            FROM (SELECT DISTINCT p.ID, (pm_field_metric.meta_value) field_metric
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to' AND pm.meta_value = CONCAT( 'user-', %s ))
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_field_metric ON (p.ID = pm_field_metric.post_id) AND (pm_field_metric.meta_key = %s)
            WHERE p.post_type = %s) AS user_stats
            ", $user_id, $start_ts, $end_ts, $field_key, $post_type );
    }

    private function calculate_statistics_prepare_sql( $wpdb, $user_id, $start_ts, $end_ts, $post_type ) {
        return $wpdb->prepare( "
        SELECT SUM(new_baptisms) new_baptisms, SUM(new_groups) new_groups, SUM(shares) shares, SUM(prayers) prayers, SUM(invites) invites
            FROM (SELECT DISTINCT p.ID, (pm_baptisms.meta_value) new_baptisms, (pm_new_groups.meta_value) new_groups, (pm_shares.meta_value) shares, (pm_prayers.meta_value) prayers, (pm_invites.meta_value) invites
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to' AND pm.meta_value = CONCAT( 'user-', %s ))
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_baptisms ON (p.ID = pm_baptisms.post_id) AND (pm_baptisms.meta_key = 'new_baptisms')
            LEFT JOIN $wpdb->postmeta pm_new_groups ON (p.ID = pm_new_groups.post_id) AND (pm_new_groups.meta_key = 'new_groups')
            LEFT JOIN $wpdb->postmeta pm_shares ON (p.ID = pm_shares.post_id) AND (pm_shares.meta_key = 'shares')
            LEFT JOIN $wpdb->postmeta pm_prayers ON (p.ID = pm_prayers.post_id) AND (pm_prayers.meta_key = 'prayers')
            LEFT JOIN $wpdb->postmeta pm_invites ON (p.ID = pm_invites.post_id) AND (pm_invites.meta_key = 'invites')
            WHERE p.post_type = %s) AS user_stats
            ", $user_id, $start_ts, $end_ts, $post_type );
    }

    private function calculate_participants_statistics_prepare_sql( $wpdb, $user_id, $start_ts, $end_ts, $post_type ) {
        return $wpdb->prepare( "
        SELECT assigned_to, participants
            FROM (SELECT p.ID, (pm.meta_value) assigned_to, (pm_participants.meta_value) participants, (pm_ts.meta_value) submit_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to' AND pm.meta_value = CONCAT( 'user-', %s ))
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_participants ON (p.ID = pm_participants.post_id) AND (pm_participants.meta_key = 'participants')
            WHERE p.post_type = %s
            ORDER BY pm_ts.meta_value DESC LIMIT 1) AS global_participants_stats
            ", $user_id, $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT SUM(new_baptisms) new_baptisms, SUM(new_groups) new_groups, SUM(shares) shares, SUM(prayers) prayers, SUM(invites) invites
            FROM (SELECT DISTINCT p.ID, (pm_baptisms.meta_value) new_baptisms, (pm_new_groups.meta_value) new_groups, (pm_shares.meta_value) shares, (pm_prayers.meta_value) prayers, (pm_invites.meta_value) invites
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id)
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_baptisms ON (p.ID = pm_baptisms.post_id) AND (pm_baptisms.meta_key = 'new_baptisms')
            LEFT JOIN $wpdb->postmeta pm_new_groups ON (p.ID = pm_new_groups.post_id) AND (pm_new_groups.meta_key = 'new_groups')
            LEFT JOIN $wpdb->postmeta pm_shares ON (p.ID = pm_shares.post_id) AND (pm_shares.meta_key = 'shares')
            LEFT JOIN $wpdb->postmeta pm_prayers ON (p.ID = pm_prayers.post_id) AND (pm_prayers.meta_key = 'prayers')
            LEFT JOIN $wpdb->postmeta pm_invites ON (p.ID = pm_invites.post_id) AND (pm_invites.meta_key = 'invites')
            WHERE p.post_type = %s) AS global_stats
            ", $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_active_groups_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT assigned_to, active_groups
            FROM (SELECT p.ID, (pm.meta_value) assigned_to, (pm_groups.meta_value) active_groups, (pm_ts.meta_value) submit_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to')
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_groups ON (p.ID = pm_groups.post_id) AND (pm_groups.meta_key = 'active_groups')
            WHERE p.post_type = %s
            ORDER BY pm_ts.meta_value DESC) AS global_active_groups_stats
            ", $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_participants_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT assigned_to, participants
            FROM (SELECT p.ID, (pm.meta_value) assigned_to, (pm_participants.meta_value) participants, (pm_ts.meta_value) submit_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to')
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_participants ON (p.ID = pm_participants.post_id) AND (pm_participants.meta_key = 'participants')
            WHERE p.post_type = %s
            ORDER BY pm_ts.meta_value DESC) AS global_participants_stats
            ", $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_accountability_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT assigned_to, accountability_ts
            FROM (SELECT p.ID, (pm.meta_value) assigned_to, (pm_accountability.meta_value) accountability_ts, (pm_ts.meta_value) submit_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to')
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            LEFT JOIN $wpdb->postmeta pm_accountability ON (p.ID = pm_accountability.post_id) AND (pm_accountability.meta_key = 'accountability')
            WHERE p.post_type = %s
            ORDER BY pm_ts.meta_value DESC) AS global_accountability_stats
            ", $start_ts, $end_ts, $post_type );
    }

    /**
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/list-query.md
     */
    private static function get_my_status(){
        global $wpdb;
        $post_type = self::post_type();
        $current_user = get_current_user_id();

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT status.meta_value as status, count(pm.post_id) as count, count(un.post_id) as update_needed
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = %s and a.post_status = 'publish' )
            INNER JOIN $wpdb->postmeta status ON ( status.post_id = pm.post_id AND status.meta_key = 'status' )
            INNER JOIN $wpdb->postmeta as assigned_to ON a.ID=assigned_to.post_id
              AND assigned_to.meta_key = 'assigned_to'
              AND assigned_to.meta_value = CONCAT( 'user-', %s )
            LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
            GROUP BY status.meta_value, pm.meta_value
        ", $post_type, $current_user ), ARRAY_A);

        return $results;
    }

    //list page filters function
    private static function get_all_status_types(){
        global $wpdb;
        if ( current_user_can( 'view_any_'.self::post_type() ) ){
            $results = $wpdb->get_results($wpdb->prepare( "
                SELECT status.meta_value as status, count(status.post_id) as count, count(un.post_id) as update_needed
                FROM $wpdb->postmeta status
                INNER JOIN $wpdb->posts a ON( a.ID = status.post_id AND a.post_type = %s and a.post_status = 'publish' )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = status.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE status.meta_key = 'status'
                GROUP BY status.meta_value
            ", self::post_type() ), ARRAY_A );
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT status.meta_value as status, count(pm.post_id) as count, count(un.post_id) as update_needed
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
                INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = %s and a.post_status = 'publish' )
                LEFT JOIN $wpdb->dt_share AS shares ON ( shares.post_id = a.ID AND shares.user_id = %s )
                LEFT JOIN $wpdb->postmeta assigned_to ON ( assigned_to.post_id = pm.post_id AND assigned_to.meta_key = 'assigned_to' && assigned_to.meta_value = %s )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE ( shares.user_id IS NOT NULL OR assigned_to.meta_value IS NOT NULL )
                GROUP BY status.meta_value, pm.meta_value
            ", self::post_type(), get_current_user_id(), 'user-' . get_current_user_id() ), ARRAY_A);
        }

        return $results;
    }

    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        if ( $post_type === self::post_type() ){
            $fields = DT_Posts::get_post_field_settings( $post_type );
            /**
             * Setup my filters
             */

            // add assigned users filters
            $assigned_users    = self::get_assigned_users_filters();
            $filters['tabs'][] = [
                'key'   => 'assigned_users',
                'label' => __( 'Assigned Users', 'disciple-tools-survey-collection' ),
                'count' => count( $assigned_users ),
                'order' => 20
            ];

            foreach ( $assigned_users as $user ) {
                $filters['filters'][] = [
                    'ID'    => 'user_' . $user['user_id'],
                    'tab'   => 'assigned_users',
                    'name'  => $user['name'],
                    'query' => [
                        'assigned_to' => [ $user['user_id'] ],
                        'sort'        => 'status'
                    ]
                ];
            }

            if ( current_user_can( 'view_any_' . self::post_type() ) ){
                $counts = self::get_all_status_types();
                $active_counts = [];
                $update_needed = 0;
                $status_counts = [];
                $total_all = 0;
                foreach ( $counts as $count ){
                    $total_all += $count['count'];
                    dt_increment( $status_counts[$count['status']], $count['count'] );
                    if ( $count['status'] === 'active' ){
                        if ( isset( $count['update_needed'] ) ) {
                            $update_needed += (int) $count['update_needed'];
                        }
                        dt_increment( $active_counts[$count['status']], $count['count'] );
                    }
                }
                $filters['tabs'][] = [
                    'key' => 'all',
                    'label' => __( 'All', 'disciple-tools-survey-collection' ),
                    'count' => $total_all,
                    'order' => 10
                ];
                // add assigned to me filters
                $filters['filters'][] = [
                    'ID' => 'all',
                    'tab' => 'all',
                    'name' => __( 'All', 'disciple-tools-survey-collection' ),
                    'query' => [
                        'sort' => '-post_date'
                    ],
                    'count' => $total_all
                ];

                $my_all_total = DT_Posts::list_posts( self::post_type(), [
                    'fields' => [
                        [
                            'assigned_to' => [ 'me' ]
                        ]
                    ]
                ] );
                $filters['filters'][] = [
                    'ID'    => 'my_all',
                    'tab'   => 'all',
                    'name'  => __( 'Assigned to me', 'disciple-tools-survey-collection' ),
                    'query' => [
                        'assigned_to' => [ 'me' ],
                        'sort'        => 'status'
                    ],
                    'count' => $my_all_total['total'],
                ];

                foreach ( $fields['status']['default'] as $status_key => $status_value ) {
                    if ( isset( $status_counts[$status_key] ) ){
                        $filters['filters'][] = [
                            'ID' => 'all_' . $status_key,
                            'tab' => 'all',
                            'name' => $status_value['label'],
                            'query' => [
                                'status' => [ $status_key ],
                                'sort' => '-post_date'
                            ],
                            'count' => $status_counts[$status_key]
                        ];
                        if ( $status_key === 'active' ){
                            if ( $update_needed > 0 ){
                                $filters['filters'][] = [
                                    'ID' => 'all_update_needed',
                                    'tab' => 'all',
                                    'name' => $fields['requires_update']['name'],
                                    'query' => [
                                        'status' => [ 'active' ],
                                        'requires_update' => [ true ],
                                    ],
                                    'count' => $update_needed,
                                    'subfilter' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $filters;
    }

    // access permission
    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === self::post_type() ){
            if ( DT_Posts::can_view_all( $post_type ) ){
                $permissions = [];
            }
        }
        return $permissions;
    }

    private static function get_assigned_users_filters() {
        $assigned_users          = [];
        $current_user_id         = get_current_user_id();
        $current_user_contact_id = Disciple_Tools_Users::get_contact_for_user( $current_user_id );
        if ( ! is_wp_error( $current_user_contact_id ) ) {

            // Obtain current user contact record and identify any corresponding coaching users.
            $current_user_contact = DT_Posts::get_post( 'contacts', $current_user_contact_id, false );
            if ( ! empty( $current_user_contact ) && ! is_wp_error( $current_user_contact ) && isset( $current_user_contact['coaching'] ) ) {

                // Build returning users array.
                foreach ( $current_user_contact['coaching'] ?? [] as $coached ) {
                    $corresponds_to_user = get_post_meta( $coached['ID'], 'corresponds_to_user', true );
                    if ( ! empty( $corresponds_to_user ) && ! is_wp_error( $corresponds_to_user ) ) {
                        $assigned_users[] = [
                            'user_id'    => $corresponds_to_user,
                            'contact_id' => $coached['ID'],
                            'name'       => $coached['post_title']
                        ];
                    }
                }
            }
        }

        return $assigned_users;
    }

    public function dt_post_created( $post_type, $post_id, $initial_fields ){
        if ( ( $post_type === self::post_type() ) && !isset( $initial_fields['assigned_to'] ) ){
            DT_Posts::update_post( $post_type, $post_id, [
                'assigned_to' => 'user-' . get_current_user_id()
            ] );
        }
    }
}



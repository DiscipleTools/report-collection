<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Class Disciple_Tools_Survey_Collection_Base
 * Load the core post type hooks into the Disciple.Tools system
 */
class Disciple_Tools_Survey_Collection_Base extends DT_Module_Base {

    /**
     * Define post type variables
     * @todo update these variables with your post_type, module key, and names.
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
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        add_filter( 'dt_get_post_type_settings', [ $this, 'dt_get_post_type_settings' ], 20, 2 );

        // hooks
        add_action( 'post_connection_removed', [ $this, 'post_connection_removed' ], 10, 4 );
        add_action( 'post_connection_added', [ $this, 'post_connection_added' ], 10, 4 );
        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 10, 3 );
        add_filter( 'dt_post_create_fields', [ $this, 'dt_post_create_fields' ], 10, 2 );
        add_filter( 'dt_after_get_post_fields_filter', [ $this, 'dt_after_get_post_fields_filter' ], 10, 2 );
        add_action( 'dt_post_created', [ $this, 'dt_post_created' ], 10, 3 );
        add_action( 'dt_comment_created', [ $this, 'dt_comment_created' ], 10, 4 );
        add_filter( 'survey_collection_metrics_global_stats', [ $this, 'calculate_global_statistics' ], 10, 4 );
        add_action( 'survey_collection_metrics_dashboard_stats_html', [ $this, 'render_metrics_dashboard_stats_html' ], 10, 1 );

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
                    __( 'Survey Collection Statistics', 'disciple-tools-survey-collection' ),
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
     * @todo define the permissions for the roles
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
     * @todo define fields
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/fields.md
     */
    public function dt_custom_fields_settings( $fields, $post_type ) {
        if ( $post_type === $this->post_type ) {

            /**
             * @todo configure status appropriate to your post type
             * @todo modify strings and add elements to default array
             */

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
            $fields['submit_date']    = [
                'name'        => __( 'Submission Date', 'disciple-tools-survey-collection' ),
                'description' => __( 'Report submission date; which forms the basis of all statistical calculations.', 'disciple-tools-survey-collection' ),
                'type'        => 'date',
                'default'     => '',
                'tile'        => 'tracking',
                'icon'        => get_template_directory_uri() . '/dt-assets/images/date.svg',
            ];
            $fields['rpt_start_date'] = [
                'name'        => __( 'Report Start Date', 'disciple-tools-survey-collection' ),
                'description' => __( 'Report start date.', 'disciple-tools-survey-collection' ),
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
        }

        return $fields;
    }

    /**
     * @todo define tiles
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/field-and-tiles.md
     */
    public function dt_details_additional_tiles( $tiles, $post_type = '' ) {
        if ( $post_type === $this->post_type ) {
            $tiles['tracking'] = [ 'label' => __( 'Tracking', 'disciple-tools-survey-collection' ) ];
        }

        return $tiles;
    }

    /**
     * @todo define additional section content
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

    /**
     * action when a post connection is added during create or update
     * @todo catch field changes and do additional processing
     *
     * The next three functions are added, removed, and updated of the same field concept
     */
    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
//        if ( $post_type === $this->post_type ){
//            if ( $field_key === "members" ){
//                // @todo change 'members'
//                // execute your code here, if field key match
//            }
//            if ( $field_key === "coaches" ){
//                // @todo change 'coaches'
//                // execute your code here, if field key match
//            }
//        }
//        if ( $post_type === "contacts" && $field_key === $this->post_type ){
//            // execute your code here, if a change is made in contacts and a field key is matched
//        }
    }

    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
//        if ( $post_type === $this->post_type ){
//            // execute your code here, if connection removed
//        }
    }

    //filter when a comment is created
    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
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

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ) {
        return $fields;
    }

    public function render_metrics_dashboard_stats_html( $stats ) {
        ?>
        <div style="display: flex; flex-flow: row wrap; justify-content: center; overflow: auto;">
            <?php
            foreach ( $stats ?? [] as $stat ) {
                if ( isset( $stat['value'], $stat['label'] ) ) {
                    ?>
                    <div style="margin-right: 30px; flex: 1 1 0;">
                        <div><span
                                style="font-size: 60px; font-weight: bold; color: blue;"><?php echo esc_attr( number_format( $stat['value'] ) ) ?></span>
                        </div>
                        <div><?php echo esc_attr( $stat['label'] ) ?></div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
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
        if ( ! empty( $active_groups_global_results ) ) {
            $stats['stats_active_groups'] = $active_groups_global_results[0]['active_groups'];
        }

        return $stats;
    }

    //filter following get post requests
    public function dt_after_get_post_fields_filter( $fields, $post_type ) {
        if ( $post_type === $this->post_type ) {

            // Calculate collective report record statistics.
            $statistics = self::calculate_statistics( $fields, $post_type );

            // Accordingly update corresponding statistics fields.
            if ( isset( $statistics['ytd'] ) ) {
                $fields['stats_new_baptisms_ytd'] = $statistics['ytd']['new_baptisms'];
                $fields['stats_new_groups_ytd']   = $statistics['ytd']['new_groups'];
                $fields['stats_shares_ytd']       = $statistics['ytd']['shares'];
                $fields['stats_prayers_ytd']      = $statistics['ytd']['prayers'];
                $fields['stats_invites_ytd']      = $statistics['ytd']['invites'];
            }
            if ( isset( $statistics['all_time'] ) ) {
                $fields['stats_new_baptisms_all_time'] = $statistics['all_time']['new_baptisms'];
                $fields['stats_new_groups_all_time']   = $statistics['all_time']['new_groups'];
                $fields['stats_shares_all_time']       = $statistics['all_time']['shares'];
                $fields['stats_prayers_all_time']      = $statistics['all_time']['prayers'];
                $fields['stats_invites_all_time']      = $statistics['all_time']['invites'];
            }
            if ( $statistics['misc'] ) {
                $fields['stats_active_groups'] = $statistics['misc']['active_groups'];
            }
        }

        return $fields;
    }

    private function calculate_statistics( $fields, $post_type ) {
        global $wpdb;
        $current_user_id = get_current_user_id();
        $statistics      = [];

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
        }

        // Capture miscellaneous stats.
        $statistics['misc'] = [
            'active_groups' => DT_Posts::list_posts( 'reports', [
                    'limit'  => 1,
                    'sort'   => '-submit_date',
                    'fields' => [
                        [
                            'assigned_to' => [ 'me' ]
                        ],
                        'status' => [
                            'new',
                            'unassigned',
                            'assigned',
                            'active'
                        ]
                    ]
            ] )['posts'][0]['active_groups'] ?? 0
        ];

        return $statistics;
    }

    private function calculate_statistics_prepare_sql( $wpdb, $user_id, $start_ts, $end_ts, $post_type ) {
        return $wpdb->prepare( "
        SELECT SUM(new_baptisms) new_baptisms, SUM(new_groups) new_groups, SUM(shares) shares, SUM(prayers) prayers, SUM(invites) invites
            FROM (SELECT DISTINCT p.ID, (pm_baptisms.meta_value) new_baptisms, (pm_new_groups.meta_value) new_groups, (pm_shares.meta_value) shares, (pm_prayers.meta_value) prayers, (pm_invites.meta_value) invites
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to' AND pm.meta_value = CONCAT( 'user-', %s ))
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            INNER JOIN $wpdb->postmeta pm_baptisms ON (p.ID = pm_baptisms.post_id) AND (pm_baptisms.meta_key = 'new_baptisms')
            INNER JOIN $wpdb->postmeta pm_new_groups ON (p.ID = pm_new_groups.post_id) AND (pm_new_groups.meta_key = 'new_groups')
            INNER JOIN $wpdb->postmeta pm_shares ON (p.ID = pm_shares.post_id) AND (pm_shares.meta_key = 'shares')
            INNER JOIN $wpdb->postmeta pm_prayers ON (p.ID = pm_prayers.post_id) AND (pm_prayers.meta_key = 'prayers')
            INNER JOIN $wpdb->postmeta pm_invites ON (p.ID = pm_invites.post_id) AND (pm_invites.meta_key = 'invites')
            WHERE p.post_type = %s) AS user_stats
            ", $user_id, $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT SUM(new_baptisms) new_baptisms, SUM(new_groups) new_groups, SUM(shares) shares, SUM(prayers) prayers, SUM(invites) invites
            FROM (SELECT DISTINCT p.ID, (pm_baptisms.meta_value) new_baptisms, (pm_new_groups.meta_value) new_groups, (pm_shares.meta_value) shares, (pm_prayers.meta_value) prayers, (pm_invites.meta_value) invites
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id)
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            INNER JOIN $wpdb->postmeta pm_baptisms ON (p.ID = pm_baptisms.post_id) AND (pm_baptisms.meta_key = 'new_baptisms')
            INNER JOIN $wpdb->postmeta pm_new_groups ON (p.ID = pm_new_groups.post_id) AND (pm_new_groups.meta_key = 'new_groups')
            INNER JOIN $wpdb->postmeta pm_shares ON (p.ID = pm_shares.post_id) AND (pm_shares.meta_key = 'shares')
            INNER JOIN $wpdb->postmeta pm_prayers ON (p.ID = pm_prayers.post_id) AND (pm_prayers.meta_key = 'prayers')
            INNER JOIN $wpdb->postmeta pm_invites ON (p.ID = pm_invites.post_id) AND (pm_invites.meta_key = 'invites')
            WHERE p.post_type = %s) AS global_stats
            ", $start_ts, $end_ts, $post_type );
    }

    private function calculate_global_active_groups_statistics_prepare_sql( $wpdb, $post_type, $start_ts, $end_ts ) {
        return $wpdb->prepare( "
        SELECT SUM(active_groups) active_groups
            FROM (SELECT p.ID, (pm.meta_value) assigned_to, (pm_groups.meta_value) active_groups, (pm_ts.meta_value) submit_date
            FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) AND (pm.meta_key = 'assigned_to')
            INNER JOIN $wpdb->postmeta pm_ts ON (p.ID = pm_ts.post_id) AND (pm_ts.meta_key = 'submit_date' AND pm_ts.meta_value BETWEEN %d AND %d)
            INNER JOIN $wpdb->postmeta pm_groups ON (p.ID = pm_groups.post_id) AND (pm_groups.meta_key = 'active_groups')
            WHERE p.post_type = %s
            ORDER BY pm_ts.meta_value DESC LIMIT 1) AS global_active_groups_stats
            ", $start_ts, $end_ts, $post_type );
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
    }

    /**
     * @todo adjust queries to support list counts
     * Documentation
     * @link https://github.com/DiscipleTools/Documentation/blob/master/Theme-Core/list-query.md
     */
    private static function get_my_status(){
        /**
         * @todo adjust query to return count for update needed
         */
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
        /**
         * @todo adjust query to return count for update needed
         */
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
        /**
         * @todo process and build filter lists
         */
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
                $filters['filters'][] = [
                    'ID'    => 'my_all',
                    'tab'   => 'all',
                    'name'  => __( 'Assigned to me', 'disciple-tools-survey-collection' ),
                    'query' => [
                        'assigned_to' => [ 'me' ],
                        'sort'        => 'status'
                    ],
                    'count' => $total_all,
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

    // scripts
    public function scripts(){
        if ( is_singular( $this->post_type ) && get_the_ID() && DT_Posts::can_view( $this->post_type, get_the_ID() ) ){
            $test = '';
            // @todo add enqueue scripts
        }
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

}



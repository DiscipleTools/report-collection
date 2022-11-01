<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class Disciple_Tools_Survey_Collection_Tile {
    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields' ], 1, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_add_section' ], 30, 2 );
    }

    /**
     * This function registers a new tile to a specific post type
     *
     * @param $tiles
     * @param string $post_type
     *
     * @return mixed
     * @todo Change the tile key and tile label
     *
     * @todo Set the post-type to the target post-type (i.e. contacts, groups, trainings, etc.)
     */
    public function dt_details_additional_tiles( $tiles, $post_type = '' ) {
        if ( $post_type === 'reports' ) {
            $tiles['statistics'] = [ 'label' => __( 'Statistics', 'disciple-tools-survey-collection' ) ];
        }

        if ( $post_type === 'contacts' ) {

            // Determine if user record report stats are to be shown.
            $corresponds_to_user = get_post_meta( get_the_ID(), 'corresponds_to_user', true );
            if ( ! empty( $corresponds_to_user ) && ! is_wp_error( $corresponds_to_user ) ) {
                $tiles['reports'] = [ 'label' => __( 'Reports', 'disciple-tools-survey-collection' ) ];
            }
        }

        return $tiles;
    }

    /**
     * @param array $fields
     * @param string $post_type
     *
     * @return array
     */
    public function dt_custom_fields( array $fields, string $post_type = '' ) {
        if ( $post_type === 'contacts' ) {
            $fields['groups_count'] = [
                'name'         => __( 'Number of groups', 'disciple-tools-survey-collection' ),
                'description'  => __( 'Current count of total number of assigned groups.', 'disciple-tools-survey-collection' ),
                'type'         => 'number',
                'default'      => 0,
                'tile'         => 'reports',
                'icon'         => get_template_directory_uri() . '/dt-assets/images/groups.svg',
                'customizable' => false
            ];
        }

        return $fields;
    }

    public function dt_add_section( $section, $post_type ) {
        $stats_fields = [
            [
                'label'    => __( 'New Baptisms', 'disciple-tools-survey-collection' ),
                'ytd'      => 'stats_new_baptisms_ytd',
                'all_time' => 'stats_new_baptisms_all_time'
            ],
            [
                'label'    => __( 'New Groups', 'disciple-tools-survey-collection' ),
                'ytd'      => 'stats_new_groups_ytd',
                'all_time' => 'stats_new_groups_all_time'
            ],
            [
                'label'    => __( 'Shares', 'disciple-tools-survey-collection' ),
                'ytd'      => 'stats_shares_ytd',
                'all_time' => 'stats_shares_all_time'
            ],
            [
                'label'    => __( 'Prayers', 'disciple-tools-survey-collection' ),
                'ytd'      => 'stats_prayers_ytd',
                'all_time' => 'stats_prayers_all_time'
            ],
            [
                'label'    => __( 'Invites', 'disciple-tools-survey-collection' ),
                'ytd'      => 'stats_invites_ytd',
                'all_time' => 'stats_invites_all_time'
            ]
        ];
        if ( ( $post_type === 'reports' ) && $section === 'statistics' ) {
            $post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <div class="cell small-12 medium-4">
                <table>
                    <thead>
                    <tr>
                        <th></th>
                        <th><?php echo esc_attr( __( 'YTD', 'disciple-tools-survey-collection' ) ) ?></th>
                        <th><?php echo esc_attr( __( 'All Time', 'disciple-tools-survey-collection' ) ) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ( $stats_fields as $stats_field ) {
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_attr( $stats_field['label'] ); ?>
                            </td>
                            <td>
                                <?php echo esc_attr( $post[ $stats_field['ytd'] ] ?? '--' ); ?>
                            </td>
                            <td>
                                <?php echo esc_attr( $post[ $stats_field['all_time'] ] ?? '--' ); ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>

        <?php }

        if ( ( $post_type === 'contacts' ) && $section === 'reports' ) {

            // Determine if user record report stats are to be shown.
            $corresponds_to_user = get_post_meta( get_the_ID(), 'corresponds_to_user', true );
            if ( ! empty( $corresponds_to_user ) && ! is_wp_error( $corresponds_to_user ) ) {
                $statistics = apply_filters( 'survey_collection_metrics_user_stats', [], $corresponds_to_user );

                // Fetch corresponding magic link, currently assigned to user.
                $user_contacts_record = DT_Posts::get_post( $post_type, get_the_ID() );
                $magic_link_apps      = dt_get_registered_types();
                $rsc_app              = $magic_link_apps['rsc_magic_app']['rsc_user_app'] ?? null;
                $rsc_ml_key           = $user_contacts_record[ $rsc_app['meta_key'] ] ?? null;
                $rsc_ml_url           = isset( $rsc_ml_key ) ? esc_url( site_url() . '/' . $rsc_app['root'] . '/' . $rsc_app['type'] . '/' . $rsc_ml_key ) : null;
                ?>

                <div class="cell small-12 medium-4">
                    <?php
                    //render_field_for_display( 'groups_count', DT_Posts::get_post_field_settings( 'contacts', false ), $user_contacts_record );
                    ?>
                    <table>
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo esc_attr( __( 'All Time', 'disciple-tools-survey-collection' ) ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ( $stats_fields as $stats_field ) {
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_attr( $stats_field['label'] ); ?>
                                </td>
                                <td>
                                    <?php echo esc_attr( $statistics[ $stats_field['all_time'] ] ?? '--' ); ?>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                    <?php if ( isset( $rsc_ml_url ) ) { ?>
                        <a class="button select-button" style="min-width: 100%;"
                           href="<?php echo esc_attr( $rsc_ml_url ) ?>" target="_blank">
                            <?php echo esc_attr( __( 'View All Reports', 'disciple-tools-survey-collection' ) ) ?>
                        </a>
                    <?php } ?>
                </div>

                <?php
            }
        }
    }
}

Disciple_Tools_Survey_Collection_Tile::instance();

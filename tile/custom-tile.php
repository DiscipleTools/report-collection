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

        return $tiles;
    }

    /**
     * @param array $fields
     * @param string $post_type
     *
     * @return array
     */
    public function dt_custom_fields( array $fields, string $post_type = '' ) {
        return $fields;
    }

    public function dt_add_section( $section, $post_type ) {
        if ( ( $post_type === 'reports' ) && $section === 'statistics' ) {
            $post             = DT_Posts::get_post( $post_type, get_the_ID() );
            $post_type_fields = DT_Posts::get_post_field_settings( $post_type );
            $stats_fields     = [
                [
                    'label'    => __( 'New Baptisms', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'new_baptisms_ytd',
                    'all_time' => 'new_baptisms_all_time'
                ],
                [
                    'label'    => __( 'New Groups', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'new_groups_ytd',
                    'all_time' => 'new_groups_all_time'
                ],
                [
                    'label'    => __( 'Active Groups', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'active_groups_ytd',
                    'all_time' => 'active_groups_all_time'
                ],
                [
                    'label'    => __( 'Shares', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'shares_ytd',
                    'all_time' => 'shares_all_time'
                ],
                [
                    'label'    => __( 'Prayers', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'prayers_ytd',
                    'all_time' => 'prayers_all_time'
                ],
                [
                    'label'    => __( 'Invites', 'disciple-tools-survey-collection' ),
                    'ytd'      => 'invites_ytd',
                    'all_time' => 'invites_all_time'
                ]
            ];
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
    }
}

Disciple_Tools_Survey_Collection_Tile::instance();

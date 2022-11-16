<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

if ( class_exists( 'DT_Dashboard_Tile' ) ) {
    class Disciple_Tools_Survey_Collection_Dashboard_Tile extends DT_Dashboard_Tile {

        public function __construct( $handle, $label, $params = [] ) {
            try {
                parent::__construct( $handle, $label, $params );

            } catch ( Exception $e ) {
                dt_write_log( $e );
            }
        }

        /**
         * Register any assets the tile needs or do anything else needed on registration.
         * @return mixed
         */
        public function setup() {
            $script = 'tile/dashboard-tile-template.js';

            if ( file_exists( Disciple_Tools_Survey_Collection::dir() . $script ) ) {
                wp_enqueue_script( $this->handle, Disciple_Tools_Survey_Collection::path() . $script, [
                    'dt-dashboard-plugin',
                    'jquery',
                    'jquery-ui',
                    'lodash',
                    'amcharts-core',
                    'amcharts-charts',
                    'amcharts-animated',
                    'moment'
                ], filemtime( Disciple_Tools_Survey_Collection::dir() . $script ), true );

                wp_localize_script(
                    $this->handle, 'dt_survey_collection', array(
                        'dt_magic_link_types' => []
                    )
                );
            }
        }

        /**
         * Render the tile
         */
        public function render() {
            $handle = $this->handle;
            $label  = $this->label;
            $tile   = $this;
            include( Disciple_Tools_Survey_Collection::dir() . 'tile/dashboard-tile-template.php' );
        }

    }
}

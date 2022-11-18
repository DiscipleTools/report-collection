<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Survey_Collection_Charts
{
    private static $_instance = null;
    public static function instance(){
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct(){

        require_once( 'report-statistics.php' );
        new Disciple_Tools_Survey_Collection_Report_Statistics();

    } // End __construct
}
Disciple_Tools_Survey_Collection_Charts::instance();

<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'disciple-tools-survey-collection/disciple-tools-survey-collection.php' );

        $this->assertContains(
            'disciple-tools-survey-collection/disciple-tools-survey-collection.php',
            get_option( 'active_plugins' )
        );
    }
}

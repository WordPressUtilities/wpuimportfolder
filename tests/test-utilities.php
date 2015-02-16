<?php
class WPUImportFolder_Utilities extends WP_UnitTestCase
{

    public $demo_plugin;

    function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUImportFolder;

        // Simulate WordPress init
        do_action('init');
    }

    function test_title_from_filename() {

        $base_title_string = 'hello-les-amis.jpg';
        $base_title = 'Hello les amis';

        $this->assertEquals($base_title, $this->demo_plugin->get_title_from_filename($base_title_string));
    }
}

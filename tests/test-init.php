<?php

class WPUImportFolder_Init extends WP_UnitTestCase
{

    public $demo_plugin;

    function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUImportFolder;
    }

    function test_init_plugin() {

        // Simulate WordPress init
        do_action('init');
        $this->assertEquals(10, has_action('admin_notices', array(
            $this->demo_plugin,
            'admin_notices'
        )));
        $this->assertEquals(10, has_action('admin_menu', array(
            $this->demo_plugin,
            'admin_menu'
        )));
    }
}

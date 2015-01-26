<?php
class WPUImportFolder_Import extends WP_UnitTestCase
{

    public $demo_plugin;

    function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUImportFolder;

        // Simulate WordPress init
        do_action('init');
    }

    function set_import_dir($files_match = array()) {

        // Set import dir
        $import_dir = $this->demo_plugin->import_dir;
        $this->demo_plugin->get_files_from_import_dir($import_dir);

        // Copy assets
        $tmp_dir = dirname(__FILE__) . '/test_files/';
        $files = array();
        $files_dir = glob($tmp_dir . '*');
        foreach ($files_dir as $file) {

            $file_name = str_replace($tmp_dir, '', $file);
            // If file name is not in the list
            if (!empty($files_match) && !in_array($file_name, $files_match)) {
                continue;
            }

            $new_file = $import_dir . $file_name;
            copy($file, $new_file);
            $files[] = $file_name;
            @chmod($new_file, 0777);
        }

        return $files;
    }

    function test_import_various_files() {
        $files = $this->set_import_dir(array(
            'html_file.html',
            'image.jpg'
        ));
        $count = $this->demo_plugin->import_files($files, 'post');
        $this->assertEquals(2, $count);
    }

    function test_import_samename_files() {
        $files = $this->set_import_dir(array(
            'name.html',
            'name.jpg'
        ));
        $count = $this->demo_plugin->import_files($files, 'post');
        $this->assertEquals(1, $count);
    }
}

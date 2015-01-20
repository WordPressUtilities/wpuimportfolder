<?php

/*
Plugin Name: Import Folder
Description: Import the content of a folder
Version: 0.1
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUImportFolder
{
    private $options = array(
        'name' => 'Import folder',
        'id' => 'wpu-import-folder',
    );

    private $messages = array();

    function __construct() {

        add_action('init', array(&$this,
            'init'
        ));
    }

    function init() {

        $this->nonce_field = 'nonce_' . $this->options['id'] . '_import';
        $this->upload_dir = wp_upload_dir();
        $this->import_dir = $this->upload_dir['basedir'] . '/import/';

        // Set menu in settings
        add_action('admin_menu', array(&$this,
            'admin_menu'
        ));

        // Set post action
        add_action('admin_menu', array(&$this,
            'admin_page_postAction'
        ));

        // Display notices
        add_action('admin_notices', array(&$this,
            'admin_notices'
        ));
    }

    function admin_menu() {

        // Set page
        add_submenu_page('tools.php', $this->options['name'], $this->options['name'], 'manage_options', $this->options['id'], array(&$this,
            'admin_page'
        ));
    }

    function admin_page() {
        $files = scandir($this->import_dir);
        $has_files = is_array($files) && count($files) > 2;
        $post_types = get_post_types('', 'objects');

        echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
        echo '<h2>' . $this->options['name'] . '</h2>';
        if ($has_files) {
            echo '<p>' . (count($files) - 2) . ' files available.</p>';
            echo '<form action="" method="post">';
            wp_nonce_field('nonce_' . $this->options['id'], $this->nonce_field);

            // - Choose a post type
            echo '<p><label for="import_post_type">Post type</label><br/>';
            echo '<select name="import_post_type" id="import_post_type" required>';
            echo '<option value="" disabled selected style="display:none;">Select a post type</option>';

            foreach ($post_types as $id => $post_type) {
                echo '<option value="' . $id . '">' . $post_type->labels->name . '</option>';
            }
            echo '</select>';
            echo '</p>';

            echo '<button type="submit" class="button">Import</button>';
            echo '</form>';
        } else {
            echo '<p>No files are available.</p>';
        }
        echo '</div>';
    }

    function admin_page_postAction() {
        $unwanted_files = array(
            '.',
            '..'
        );
        $post_types = get_post_types();

        // Check nonce
        if (!isset($_POST[$this->nonce_field]) || !wp_verify_nonce($_POST[$this->nonce_field], 'nonce_' . $this->options['id'])) {
            return;
        }

        // Check post type
        if (!isset($_POST['import_post_type']) || !in_array($_POST['import_post_type'], $post_types)) {
            return;
        }
        $post_type = $_POST['import_post_type'];

        // Ensure the folder exists
        if (!is_dir($this->import_dir)) {
            @mkdir($this->import_dir, 0777);
            @chmod($this->import_dir, 0777);
        }

        $files = scandir($this->import_dir);

        $post_count = 0;

        // For each found file
        foreach ($files as $file) {
            if (in_array($file, $unwanted_files)) {
                continue;
            }

            $post_created = $this->create_post_from_file($file, $post_type);
            if ($post_created === true) {
                echo '<pre>';
                var_dump($file);
                echo '</pre>';
                die;
                $post_count++;
            }
        }

        // Display success message
        if ($post_count > 0) {
            $this->messages[] = $post_count . ' posts have been successfully created';
        }
    }

    /* ----------------------------------------------------------
      Files tools
    ---------------------------------------------------------- */

    /**
     * Create post from a file
     * @param  string   $file  Path of the file
     * @return boolean         Success creation
     */
    private function create_post_from_file($file, $post_type) {

        // Extract path & title
        $filepath = $this->import_dir . $file;
        $filetitle = $this->get_title_from_filename($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $new_filename = substr(time() . '-' . $filename, 0, 32) . '.' . $extension;
        $new_filepath = $this->upload_dir['path'] . '/' . $new_filename;

        // Move file
        $insert_post = array(
            'post_title' => $filetitle,
            'post_content' => '',
            'post_type' => $post_type,
            'post_status' => 'publish'
        );

        // Insert the post into the database
        $post_id = wp_insert_post($insert_post);

        $success_creation = is_numeric($post_id);

        if ($extension == 'jpg') {

            // Copy file
            copy($filepath, $new_filepath);

            // Check the type of file. We'll use this as the 'post_mime_type'.
            $filetype = wp_check_filetype(basename($new_filename) , null);

            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid' => $this->upload_dir['url'] . '/' . $new_filename,
                'post_mime_type' => $filetype['type'],
                'post_title' => $filetitle,
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $new_filepath, $post_id);

            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once (ABSPATH . 'wp-admin/includes/image.php');

            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_filepath);
            wp_update_attachment_metadata($attach_id, $attach_data);

            set_post_thumbnail($post_id, $attach_id);
        }

        if ($extension == 'txt') {

            // Set post content to file content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => wpautop(file_get_contents($filepath)) ,
            ));
        }

        // Delete old file
        @unlink($filepath);

        return $success_creation;
    }

    /**
     * Generate a title from a file name.
     * @param  string $file Original file name
     * @return string       Generated title
     */
    private function get_title_from_filename($file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Remove extension
        $file = str_replace('.' . $extension, '', $file);

        // Remove unwanted characters
        $filename = str_replace(array(
            '_',
            '-',
            '.'
        ) , ' ', $file);
        return ucfirst($filename);
    }

    /* ----------------------------------------------------------
      WordPress tools
    ---------------------------------------------------------- */

    /* Display notices */
    function admin_notices() {
        $return = '';
        if (!empty($this->messages)) {
            foreach ($this->messages as $message) {
                $return.= '<div class="updated"><p>' . $message . '</p></div>';
            }
        }

        // Empty messages
        $this->messages = array();
        echo $return;
    }
}

if (is_admin()) {
    $WPUImportFolder = new WPUImportFolder();
}

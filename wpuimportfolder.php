<?php

/*
Plugin Name: Import Folder
Description: Import the content of a folder
Version: 0.4
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUImportFolder
{
    private $options = array(
        'name' => '',
        'id' => 'wpuimportfolder',
    );

    private $extensions = array(
        'image' => array(
            'jpg',
            'jpeg',
            'png',
            'bmp',
            'gif'
        ) ,
        'text' => array(
            'txt',
            'htm',
            'html'
        )
    );

    private $unwanted_files = array(
        '.',
        '..'
    );

    private $messages = array();

    function __construct() {
        load_plugin_textdomain($this->options['id'], false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->options['name'] = $this->__('Import folder');
        add_action('init', array(&$this,
            'init'
        ));
    }

    function init() {
        global $current_user;
        $this->transient_msg = $current_user->ID . $this->options['id'];
        $this->nonce_field = 'nonce_' . $this->options['id'] . '_import';
        $this->upload_dir = wp_upload_dir();
        $this->import_dir = $this->upload_dir['basedir'] . '/import/';

        // Set menu in settings
        add_action('admin_menu', array(&$this,
            'admin_menu'
        ));

        // Set post action
        add_action('admin_post_' . $this->options['id'], array(&$this,
            'admin_page_postAction'
        ));

        // Display notices
        add_action('admin_notices', array(&$this,
            'admin_notices'
        ));
    }

    function admin_menu() {

        // Set page
        $page = add_management_page($this->options['name'], $this->options['name'], 'manage_options', $this->options['id'], array(&$this,
            'admin_page'
        ));

        // Set script
        add_action('admin_footer-' . $page, array(&$this,
            'admin_page_script'
        ));
    }

    function admin_page_script() {
?><script>(function() {
    jQuery('#wpuimport-choose-files').on('click', function(e) {
        e.preventDefault();
        jQuery('#wpuimport-folder-list').toggle();
    });
}()); </script><?php
    }

    function admin_page() {
        $files = $this->get_files_from_import_dir($this->import_dir);
        $nb_files = count($files);
        $has_files = is_array($files) && $nb_files > 0;
        $post_types = get_post_types('', 'objects');

        echo '<div class="wrap">';
        echo '<h2>' . $this->options['name'] . '</h2>';
        if ($has_files) {

            $str_files = $this->__('%s file available.');
            if ($nb_files > 1) {
                $str_files = $this->__('%s files available.');
            }

            echo '<p>';
            echo sprintf($str_files, $nb_files);
            if ($nb_files > 1) {
                echo ' <a id="wpuimport-choose-files" href="#" class="hide-if-no-js">' . $this->__('Edit selection') . '</a>';
            } else {
                echo ' <small>(' . $files[0] . ')</small>';
            }

            echo '</p>';
            echo '<form action="' . admin_url('admin-post.php') . '" method="post">';
            wp_nonce_field('nonce_' . $this->options['id'], $this->nonce_field);
            echo '<input type="hidden" name="action" value="wpuimportfolder">';

            if ($nb_files > 1) {
                echo '<div id="wpuimport-folder-list" class="hide-if-js">';
                echo '<ul style="margin:0;max-height:200px;overflow:auto;">';

                foreach ($files as $i => $file) {
                    if (!in_array($file, $this->unwanted_files)) {
                        $input = '<input type="checkbox" name="wpuimportfiles[]" value="' . esc_attr($file) . '" checked />';
                        echo '<li><label>' . $input . ' ' . $file . '</label></li>';
                    }
                }

                echo '</ul>';
                echo '</div>';
            }

            // - Choose a post type
            echo '<p><label for="import_post_type">' . $this->__('Post type') . '</label><br/>';
            echo '<select name="import_post_type" id="import_post_type" required>';
            echo '<option value="" disabled selected style="display:none;">' . $this->__('Select a post type') . '</option>';

            foreach ($post_types as $id => $post_type) {
                echo '<option value="' . $id . '">' . $post_type->labels->name . '</option>';
            }
            echo '</select>';
            echo '</p>';

            submit_button($this->__('Import'));
            echo '</form>';
        } else {
            echo '<p>' . $this->__('No files are available.') . '</p>';
        }
        echo '</div>';
    }

    function admin_page_postAction() {

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

        // Choose files to import
        $files = $this->get_files_from_import_dir($this->import_dir);
        $import_files = array();
        foreach ($_POST['wpuimportfiles'] as $file) {
            if (in_array($file, $files)) {
                $import_files[] = $file;
            }
        }
        // Import all files if
        if (empty($import_files)) {
            $import_files = $files;
        }

        $files_count = 0;

        // For each found file
        foreach ($import_files as $file) {
            if (in_array($file, $this->unwanted_files)) {
                continue;
            }

            $post_created = $this->create_post_from_file($file, $post_type);
            if ($post_created === true) {
                $files_count++;
            }
        }

        // Display success message
        if ($files_count > 0) {
            $str_msg = $this->__('%s post have been successfully created');
            if ($files_count > 1) {
                $str_msg = $this->__('%s posts have been successfully created');
            }

            $this->set_message(sprintf($str_msg, $files_count));
        }

        wp_safe_redirect(wp_get_referer());
        die();
    }

    /* ----------------------------------------------------------
      Files tools
    ---------------------------------------------------------- */

    private function get_files_from_import_dir($dir) {

        // Ensure the folder exists
        defined('FS_CHMOD_DIR') or define('FS_CHMOD_DIR', 0755);

        if (!is_dir($dir)) {
            @mkdir($dir, FS_CHMOD_DIR);
            @chmod($dir, FS_CHMOD_DIR);
        }

        // List the files
        $files = array();
        $files_dir = glob($dir . '*');
        foreach ($files_dir as $file) {
            $files[] = str_replace($dir, '', $file);
        }

        return $files;
    }

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

        if (in_array($extension, $this->extensions['image'])) {

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

        if (in_array($extension, $this->extensions['text'])) {

            $post_content = file_get_contents($filepath);
            if ($extension == 'txt') {
                $post_content = wpautop($post_content);
            }

            // Set post content to file content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $post_content
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

    /* Translation */
    function __($string) {
        return __($string, $this->options['id']);
    }

    /* Set notices messages */
    private function set_message($message, $created = false) {
        $messages = (array)get_transient($this->transient_msg);
        $group = $created ? 'created' : 'updated';
        $messages[$group][] = $message;
        set_transient($this->transient_msg, $messages);
    }

    /* Display notices */
    function admin_notices() {
        $messages = (array)get_transient($this->transient_msg);
        if (!empty($messages)) {
            foreach ($messages as $group_id => $group) {
                foreach ($group as $message) {
                    echo '<div class="' . $group_id . '"><p>' . $message . '</p></div>';
                }
            }
        }

        // Empty messages
        delete_transient($this->transient_msg);
    }
}

if (is_admin()) {
    $WPUImportFolder = new WPUImportFolder();
}

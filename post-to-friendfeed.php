<?php
/*
Plugin Name: Post to FriendFeed
Plugin Script: post-to-friendfeed.php
Plugin URI: http://sudarmuthu.com/wordpress/post-to-friendfeed
Description: Publish an entry to FriendFeed of your blog post with images and excerpt.
Version: 1.0.5
License: GPL
Author: Sudar
Author URI: http://sudarmuthu.com/
Donate Link: http://sudarmuthu.com/if-you-wanna-thank-me
Text Domain: post-to-friendfeed

=== RELEASE NOTES ===
2008-08-03 - v0.1 - first version
2008-08-03 - v0.2 - Fixed compatibility problem with FriendFeed Comments Plugin
2008-08-03 - v0.3 - Removed smilies from the list of images
2008-08-09 - v0.4 - Added an option to specify the number of images to be posted
2008-08-13 - v0.5 - Added support for Scheduled posts
2008-10-12 - v0.6 - Added support for FriendFeed API Core Plugin
2009-05-16 - v0.7 - Added support for rooms
2009-06-11 - v0.8 - Added support for url shortening services
2009-08-10 - v0.9 - Fixed an issue in applying filters for images and excerpts.
2009-08-15 - v1.0 - Added support for internalization.
2009-10-18 - v1.0.1 - Added translation support for Belorussian (Thanks FatCow <http://www.fatcow.com>).
2011-09-05 - v1.0.2 - Added translation support for Bulgarian
2011-12-13 - v1.0.3 - Added translation support for Spanish 
2012-03-13 - v1.0.4 - Added translation support for Romanian 
2012-07-23 - v1.0.5 - (Dev time: 0.5 hour)
                  - Added Hindi translations
                  - Added Lithuanian translations

*/
/*  Copyright 2011  Sudar Muthu  (email : sudar@sudarmuthu.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Required version of FriendFeed API
define("REQUIRED_FF_VERSION", "0.9.1");

/**
 * Send post to Friendfeed
 * @param <type> $post_id
 */
function smwpff_publish2ff($post_id) {
	$post = &get_post($post_id);

    if (get_option('smwpff_nickname') && get_option("smwpff_key")) {

        $smwpff_settings = get_post_meta($post_id, 'smwpff_settings');

        if (!$smwpff_settings) {
            $room = $_POST['smwpff_room'];
            $excerpt = $_POST['smwpff_excerpt'];
            $images = explode(",", $_POST['smwpff_images']);
        } else {
            $room = $smwpff_settings['room'];
            $excerpt = $smwpff_settings['excerpt'];
            $images = explode(",", $smwpff_settings['images']);
        }

        $friendfeed = new FriendFeed(get_option('smwpff_nickname'), get_option("smwpff_key"));

        if (!$excerpt) $excerpt = $post->post_excerpt;
        if (!$images) $images = get_images($post->post_content);

        if (get_option("smwpff_import_comments") != "Yes") {
            $excerpt = null;
        }

        $permalink = apply_filter('post2ff_blog_url', get_permalink($post->ID));
        $excerpt = apply_filter('post2ff_blog_excerpt', $excerpt);
        $images = apply_filter('post2ff_blog_images', $images);

        $friendfeed->publish_link($post->post_title,  // title
                                    $permalink,  // link
                                    $excerpt,                  // comment
                                    $images,                   // Image urls
                                    "",                        // images
                                    "",                        // via
                                    "",                        // audio urls
                                    "",                        // audio
                                    $room);                    // room
    }
}

/**
 * Helper function to get images from post content
 * @param <type> $post_content
 * @return <type>
 */
function get_images($post_content) {

	$pattern = "/(<img[^>]*>)[^>]*?>/i";
	$srcpattern = "/src=[\"']?([^\"']?.*(png|jpg|gif))[\"']?/i";
	preg_match_all($pattern, $post_content,$matches);
	
	$images_arr = array();

	for ($i = 0; $i < count($matches[1]); $i++ ) {
		$len = strlen($matches[1][$i]);
       	preg_match_all($srcpattern, $matches[1][$i],$pathmatches);
       	if (strpos($pathmatches[1][0], "/smilies") === false) {
       		array_push($images_arr, $pathmatches[1][0]);       		
       	}
	}
	
	if (get_option('smwpff_num_images')) {
		$num_images = get_option('smwpff_num_images');
	} else {
		$num_images = 3;
	}
	$images_arr = array_slice($images_arr, 0, $num_images);	
	return $images_arr;
}


if (!function_exists('smwpff_request_handler')) {
    /**
     * Request handler
     */
    function smwpff_request_handler() {
        // Load localization domain
        load_plugin_textdomain( 'post-to-friendfeed', false, dirname(plugin_basename(__FILE__)) . '/languages' );
        
        if (isset($_POST["smwpff_options_submit"]) && $_POST['smwpff_options_submit'] == "Update Options") {
			update_option("smwpff_import_comments", $_POST['smwpff_comments']);
			update_option("smwpff_nickname", $_POST['smwpff_nickname']);
			update_option("smwpff_key", $_POST['smwpff_key']);
			update_option("smwpff_num_images", $_POST['smwpff_num_images']);
			
            // hook the admin notices action
            add_action( 'admin_notices', 'smwpff_change_notice', 9 );

        }
    }
}

/**
 * Add notice
 */
function smwpff_change_notice() {
    echo '<br clear="all" /> <div id="message" class="updated fade"><p><strong>';
     _e('Setting saved.', 'post-to-friendfeed');
    echo '</strong></p></div>';
}

/**
 * Display the menu page
 */
function smwpff_display_options() {
        
    $nickname = get_option('smwpff_nickname');
    $key = get_option('smwpff_key');
    $import_comments = get_option('smwpff_import_comments');
    $num_images = get_option('smwpff_num_images');
    
	print('<div class="wrap">');
	print('<h2>' . __('Post2FF Options', 'post-to-friendfeed') . '</h2>');
    print ('<form action="'. get_bloginfo("wpurl") . '/wp-admin/options-general.php?page=post-to-friendfeed.php' .'" method="post">');
?>
	<table border="0" class="form-table">
        <tbody>
        <tr valign="top">
            <th><label for="smwpff_nickname"><?php _e('FriendFeed Nickname', 'post-to-friendfeed'); ?></label></th>
            <td>
                <input type="text" value="<?php echo $nickname; ?>" name="smwpff_nickname" id="smwpff_nickname" />
            </td>
        </tr>

        <tr valign="top">
            <th><label for="smwpff_key"><?php _e('Remote Key', 'post-to-friendfeed'); ?></label></th>
            <td>
                <input type="text" value="<?php echo $key; ?>" name="smwpff_key" id="smwpff_key" />[ <a href="http://friendfeed.com/remotekey"  target="_blank"><?php _e('find your key', 'post-to-friendfeed'); ?></a> ]
            </td>
        </tr>

        <tr valign="top">
            <th><label for="smwpff_num_images"><?php _e('Number of images to import', 'post-to-friendfeed') ?></label></th>
            <td>
                <input type="text" value="<?php echo $num_images; ?>" name="smwpff_num_images" id="smwpff_num_images" />
            </td>
        </tr>

        <tr valign="top">
            <th><label for="smwpff_comments"><?php _e('Import excerpt as first comment', 'post-to-friendfeed'); ?></label></th>
            <td>
                <input type="radio" value="Yes" name="smwpff_comments" <?php ($import_comments == "Yes") ? print "checked" : print "" ?> /> <?php _e('Yes', 'post-to-friendfeed');?>
                <input type="radio" value="No" name="smwpff_comments" <?php ($import_comments == "No") ? print "checked" : print "" ?> /> <?php _e('No', 'post-to-friendfeed'); ?>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <input type="submit" name="smwpff_options_submit" value="Update Options">
            </td>
        </tr>

        </tbody>
	</table>
	</form>
<?php
}

if (!function_exists('smwpff_install')) {
/**
 * When installed for the first time
 */
	function smwpff_install () {

      add_option("smwpff_import_comments", "Yes");
      add_option("smwpff_nickname", "");
      add_option("smwpff_key", "");
      add_option("smwpff_num_images", "3");
	      
	}
}

/**
 * Add box in the write post page
 * @global <type> $wpdb
 * @global <type> $post_meta_cache
 */
function smwpff_metabox_module() {
  	global $wpdb, $post_meta_cache;

    $friendfeed = new FriendFeed(get_option('smwpff_nickname'), get_option("smwpff_key"));

    $rooms = $friendfeed->fetch_user_rooms(get_option('smwpff_nickname'));
    
  	if(is_numeric($_GET['post'])) {
    	$post_ID = (int)$_GET['post'];

    	$meta_array = get_post_meta($post_ID,'smwpff_settings');

        $room = $meta_array['room'];
        $excerpt = $meta_array['excerpt'];
        $images = $meta_array['images'];

	} else {
        $room = "";
        $excerpt = "";
        $images = "";
	}
?>
    <div id="postvisibility" class="postbox">
        <h3> <?php _e('Post to Friendfeed', 'post-to-friendfeed')?> </h3>
		<div class="inside">
    		<div id="postvisibility">
            <p><?php _e('Post to ', 'post-to-friendfeed');?>
            <select name="smwpff_room" id="smwpff_room">
                <option value="-1"><?php _e('Home Feed', 'post-to-friendfeed');?></option>
                <?php
                foreach ($rooms as $room) {
                    echo "<option value = '$room->nickname'>$room->name</option>";
                }
                ?>
            </select>
            </p>
                <label class='selectit' for='smwpff_excerpt'>
                    <?php _e('Excerpt to be posted as first comment', 'post-to-friendfeed');?><br />
                    <textarea cols = '60' id = 'smwpff_excerpt' name = 'smwpff_excerpt'><?php echo $excerpt; ?></textarea>
				</label>
                <?php _e('(If left blank, the posts excerpt will be used.)', 'post-to-friendfeed');?>
                <br />
                <label class='selectit' for='smwpff_images'>
                    <?php _e('Image urls (seperate multiple urls by comma)', 'post-to-friendfeed');?><br />
                    <textarea cols = '60' id = 'smwpff_images' name = 'smwpff_images'><?php echo $images; ?></textarea>
				</label>
                <?php printf( __ngettext('(If left blank, the first image from the post will be used.)', '(If left blank, first %d images from the post will be used.)', get_option('smwpff_num_images'), 'post-to-friendfeed'), get_option('smwpff_num_images'));?>
                <br />
            </div>
        </div>
    </div>
<?php

}

/**
 * On Save
 * @param <type> $post_ID
 */
function smwpff_metabox_module_submit($post_ID) {
    if(is_numeric($post_ID)) {
        $smwpff_settings = array(
            'room' => $_POST['smwpff_room'],
            'excerpt' => $_POST['smwpff_excerpt'],
            'images' => $_POST['smwpff_images']);
        update_post_meta($post_ID, "smwpff_settings", $smwpff_settings);
    }
}

/**
 * Add settings link in Plugin's page
 */
function smwpff_plugins_loaded() {
    // hook the admin notices action
    add_action( 'admin_notices', 'smwpff_check_dependency' );
}

/**
 * Check for dependency
 */
function smwpff_check_dependency() {
    // if Friendfeed API Core plugin is not installed then de-activate
    if (!class_exists('FriendFeed') || !(defined("FriendFeed::VERSION") && version_compare(FriendFeed::VERSION, REQUIRED_FF_VERSION) > -1)) {
?>
        <div class = 'updated'>
            <p><?php _e('ERROR!', 'post-to-friendfeed'); ?>
                <strong><?php _e('Post to Friendfeed', 'post-to-friendfeed');?></strong> <?php _e('Plugin requires', 'post-to-friendfeed');?>
                <a href = 'http://sudarmuthu.com/wordpress/friendfeed-api-core'><?php _e('Friendfeed API Core Plugin', 'post-to-friendfeed');?> </a>.
                <?php _e('Please install it and then activate <strong>Post to Friendfeed</strong> Plugin.', 'post-to-friendfeed');?>
            </p>
        </div>
<?php
        deactivate_plugins('post-to-friendfeed/post-to-friendfeed.php'); // Deactivate ourself

        // add deactivated Plugin to the recently activated list
        $deactivated = array();
        $deactivated["post-to-friendfeed/post-to-friendfeed.php"] = time();
        update_option('recently_activated', $deactivated + (array)get_option('recently_activated'));
    }
}

if(!function_exists('smwpff_add_menu')) {
	function smwpff_add_menu() {
	    //Add a submenu to Options
        add_options_page("Post2FF", "Post2FF", 8, basename(__FILE__), "smwpff_display_options");	    
	}
}

register_activation_hook(__FILE__,'smwpff_install');
add_action('admin_menu', 'smwpff_add_menu');
add_action('init', 'smwpff_request_handler');

add_action('draft_to_publish', 'smwpff_publish2ff');
add_action('future_to_publish', 'smwpff_publish2ff');

add_action('edit_form_advanced','smwpff_metabox_module');
add_action('edit_page_form', 'smwpff_metabox_module');

add_action('edit_post', 'smwpff_metabox_module_submit');
add_action('publish_post', 'smwpff_metabox_module_submit');
add_action('save_post', 'smwpff_metabox_module_submit');
add_action('edit_page_form', 'smwpff_metabox_module_submit');

// Start this plugin once all other files and plugins are fully loaded
add_action( 'plugins_loaded', 'smwpff_plugins_loaded');
?>

<?php
/*
Plugin Name: WordChimp
Plugin URI: http://hudsoncs.com/
Description: Allows you to easily select and send a group of posts as a MailChimp campaign
Version: 1.0
Author: David Hudson
Author URI: http://hudsoncs.com/
License: GPL
*/

/*  
	Copyright 2011  David Hudson  (email : david@hudsoncs.com)

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

// Include some MailChimp API goodness
require_once 'MCAPI.class.php';

// Initialize some setup stuff
register_activation_hook(__FILE__,'wordchimp_install');

function wordchimp_install () {
	
}

// ***************** Administrator's Backend
// Create navigation buttons
add_action('admin_menu', 'wordchimp_menu');

// Initialize admin page styles
add_action( 'admin_init', 'wordchimp_admin_init' );

function wordchimp_admin_init() {
	wp_register_style( 'wordchimpStyle', '/wp-content/plugins/wordchimp/style.css' );
	wp_register_script( 'wordchimpjQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js' );
	wp_register_script( 'wordchimpScript', '/wp-content/plugins/wordchimp/js/script.js' );
}

function register_wordchimp_settings() {
	register_setting( 'wordchimp_options_group', 'mailchimp_api_key' );
	register_setting( 'wordchimp_options_group', 'google_analytics_key' );
	register_setting( 'wordchimp_options_group', 'wordchimp_logo_url' );
	register_setting( 'wordchimp_options_group', 'wordchimp_strip_images' );
	register_setting( 'wordchimp_options_group', 'wordchimp_show_author' );
	register_setting( 'wordchimp_options_group', 'wordchimp_show_timestamp' );
	register_setting( 'wordchimp_options_group', 'wordchimp_timestamp_format' );
	register_setting( 'wordchimp_options_group', 'wordchimp_template' );
}

function wordchimp_admin_styles() {
   wp_enqueue_style( 'wordchimpStyle' );
}

function wordchimp_admin_scripts() {
   wp_enqueue_script( 'wordchimpjQuery' );
   wp_enqueue_script( 'wordchimpScript' );
}

function wordchimp_menu() {
	$page = add_menu_page('WordChimp', 'WordChimp', 'manage_options', 'wordchimp', 'wordchimp_dashboard');
	$settings_page = add_submenu_page('options-general.php', 'WordChimp Settings', 'WordChimp', 'manage_options', __FILE__, 'wordchimp_settings_page');
	
	add_action( 'admin_print_styles-' . $page, 'wordchimp_admin_styles' );
	add_action( 'admin_print_scripts-' . $page, 'wordchimp_admin_scripts' );
	
	add_action( 'admin_print_styles-' . $settings_page, 'wordchimp_admin_styles' );
	add_action( 'admin_print_scripts-' . $settings_page, 'wordchimp_admin_scripts' );
	add_action( 'admin_init', 'register_wordchimp_settings' );
}

function wordchimp_settings_page() {
	global $random;
	
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_header.tpl');
	echo "<form method='post' action='options.php'> ";
	settings_fields( 'wordchimp_options_group' );
	do_settings_fields( 'wordchimp_settings_page', 'wordchimp_options_group' );
	
	$mailchimp_api_key = get_option( 'mailchimp_api_key' );
	$google_analytics_key = get_option( 'google_analytics_key' );
	$wordchimp_logo_url = get_option( 'wordchimp_logo_url' );
	$wordchimp_template = get_option( 'wordchimp_template' ) == "" ? file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/campaign_template/default.tpl') : get_option( 'wordchimp_template' );
	$wordchimp_strip_images_checked = get_option( 'wordchimp_strip_images' ) == true ? 'CHECKED' : '';
	$wordchimp_show_author_checked = get_option( 'wordchimp_show_author' ) == true ? 'CHECKED' : '';
	$wordchimp_show_timestamp_checked = get_option( 'wordchimp_show_timestamp' ) == true ? 'CHECKED' : '';
	$wordchimp_timestamp_format = get_option( 'wordchimp_timestamp_format' ) == '' ? 'm/d/Y g:ia' : get_option( 'wordchimp_timestamp_format' );
	
	if ($_GET['settings-updated'] == 'true') {
		echo "<p class='wordchimp_success'>Success! Your settings have been updated. " . $random['compliment'][rand(0, count($random['compliment'])-1)] . "</p>";
	}
	echo <<<EOF
		<h3>APIs</h3>
		<table class='wordchimp_form_table'>
			<tr valign='top'>
				<th scope='row'>MailChimp API Key</th>
				<td><input type='text' name='mailchimp_api_key' value='{$mailchimp_api_key}' /></td>
			</tr>
			<tr valign='top'>
				<th scope='row'>Google Analytics Key</th>
				<td><input type='text' name='google_analytics_key' value='{$google_analytics_key}' /></td>
			</tr>
		</table>
		<h3>Options</h3>
		<label><strong>Logo URL (includes http):</strong><br /><input type='text' name='wordchimp_logo_url' value='{$wordchimp_logo_url}' /></label><br /><br />
		<label><input type='checkbox' name='wordchimp_strip_images' value='true' {$wordchimp_strip_images_checked} /> Strip images from posts (Fixes some compatibility issues with posts that have very large images)</label><br /><br />
		<label><input type='checkbox' name='wordchimp_show_author' value='true' {$wordchimp_show_author_checked} /> Show author inside of post</label><br /><br />
		<label><input type='checkbox' name='wordchimp_show_timestamp' value='true' {$wordchimp_show_timestamp_checked} /> Show post created date/time inside of post</label><br /><br />
		<label><strong>Date/Time Format:</strong><br /><input type='text' name='wordchimp_timestamp_format' value='{$wordchimp_timestamp_format}' /></label><br /><br />
		<label><strong>E-mail Template</strong> <small>(NOTE: Only edit if you know what you're doing)</small></label><br /><br />
		<textarea name='wordchimp_template'>{$wordchimp_template}</textarea>
		<p class="submit">
			<input type="submit" class="button-primary" value="Save Changes" />
		</p>
	</form>
EOF;

	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_footer.tpl');
}

function wordchimp_dashboard() {
	global $random;
	global $wpdb;
	
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_header.tpl');
	switch ($_REQUEST['wp_cmd']) {
		default:
		case "step1":
			if (get_option( 'mailchimp_api_key' ) == "") {
				echo "<p class='wordchimp_error'>You must enter your MailChimp API key in the settings page before you can continue. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . "</p>";
			} else {
				$sql = "SELECT id, post_author, post_date, post_content, post_title, post_excerpt, post_name FROM {$wpdb->prefix}posts WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 40";
				$posts = $wpdb->get_results($sql, ARRAY_A);
				
				echo "<span class='wordchimp_notice'>Please select the posts you would like to add to this campaign. (Currently showing a maximum of 40 posts in descending order)</span><br /><br />";
				echo "<form method='post'><input type='hidden' name='wp_cmd' value='step2' />";
				foreach ($posts as $post) {
					echo "<label><input type='checkbox' name='wordchimp_post_ids[]' value='{$post['id']}' /> {$post['post_title']}</label><br />";
				}
				echo <<<EOF
				<p class="submit">
					<input type="submit" class="button-primary" value="Next" />
				</p>
			</form>
EOF;
			}
		break;
		
		case "step2":
			if (get_option( 'mailchimp_api_key' ) == "") {
				echo "<p class='wordchimp_error'>You must enter your MailChimp API key in the settings page before you can continue. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . "</p>";
			} else {
				$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key') );

				$retval = $api->lists();

				if ($api->errorCode){
					echo "<p class='wordchimp_error'>Something went wrong when trying to get your MailChimp e-mail lists.  " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
				} else {
					echo "<span class='wordchimp_notice'>Please select the MailChimp list you would like to send to.</span><br /><br />";
					echo "<form method='post'><input type='hidden' name='wp_cmd' value='step3' />";
					
					// Add the previously sent posts
					foreach ($_POST['wordchimp_post_ids'] as $key => $post_id) {
						echo "<input type='hidden' name='wordchimp_post_ids[]' value='{$post_id}' />";
					}

					// Show MailChimp lists for selection
					foreach ($retval['data'] as $list) {
						echo "<label><input type='radio' name='mailchimp_list_id' value='{$list['id']}' /> {$list['name']} ({$list['stats']['member_count']} Subscribers)</label><br />";
					}
					echo <<<EOF
					<p class="submit">
						<input type="submit" class="button-primary" value="Next" />
					</p>
				</form>
EOF;
				}
			}
		break;
		
		case "step3":
			if (get_option( 'mailchimp_api_key' ) == "") {
				echo "<p class='wordchimp_error'>You must enter your MailChimp API key in the settings page before you can continue. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . "</p>";
			} else {
				echo "<form method='post'><input type='hidden' name='wp_cmd' value='step4' />";
				
				// Add the previously sent posts
				foreach ($_POST['wordchimp_post_ids'] as $key => $post_id) {
					echo "<input type='hidden' name='wordchimp_post_ids[]' value='{$post_id}' />";
				}
				
				// Add the previously selected list
				echo "<input type='hidden' name='mailchimp_list_id' value='{$_POST['mailchimp_list_id']}' />";
				
				// Add the previously selected template
				echo "<input type='hidden' name='wordchimp_template' value='{$_POST['wordchimp_template']}' />";
				
				echo "<span class='wordchimp_notice'>Please enter the campaign information.</span><br /><br />";
				
				// Setup campaign options
				echo "
				<h3>Campaign Info</h3>
				<table class='wordchimp_form_table'>
					<tr valign='top'>
						<th scope='row'>Title</th>
						<td><input type='text' name='wordchimp_campaign_title' /></td>
					</tr>
					<tr valign='top'>
						<th scope='row'>Subject</th>
						<td><input type='text' name='wordchimp_campaign_subject' /></td>
					</tr>
					<tr valign='top'>
						<th scope='row'>From Email</th>
						<td><input type='text' name='wordchimp_campaign_from_email' /></td>
					</tr>
					<tr valign='top'>
						<th scope='row'>From Name</th>
						<td><input type='text' name='wordchimp_campaign_from_name' /></td>
					</tr>
				</table>
				<h3>Campaign Tracking</h3>
				<label><input type='checkbox' name='wordchimp_campaign_track_opens' value='true' /> Track number of times email was opened</label><br />
				<label><input type='checkbox' name='wordchimp_campaign_track_html_clicks' value='true' /> Track number of times a user clicked an HTML link</label><br />
				<label><input type='checkbox' name='wordchimp_campaign_track_text_clicks' value='true' /> Track number of times a user clicked a text link</label>";
				
				echo <<<EOF
					<p class="submit">
						<input type="submit" class="button-primary" value="Next" />
					</p>
				</form>
EOF;
			}
		break;
		
		case "step4":
			if (get_option( 'mailchimp_api_key' ) == "") {
				echo "<p class='wordchimp_error'>You must enter your MailChimp API key in the settings page before you can continue. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . "</p>";
			} else {
				// Build the campaign
				$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key' ));

				$type = 'regular';

				$opts['list_id'] = $_POST['mailchimp_list_id'];
				$opts['subject'] = $_POST['wordchimp_campaign_subject'];
				$opts['from_email'] = $_POST['wordchimp_campaign_from_email'];
				$opts['from_name'] = $_POST['wordchimp_campaign_from_name'];

				$opts['tracking'] = array('opens' => $_POST['wordchimp_campaign_track_opens'] == 'true' ? true : false, 'html_clicks' => $_POST['wordchimp_campaign_track_html_clicks'] == 'true' ? true : false, 'text_clicks' => $_POST['wordchimp_campaign_track_text_clicks'] == 'true' ? true : false);

				$opts['authenticate'] = true;
				
				if (get_option( 'google_analytics_key' ) != "") {
					$opts['analytics'] = array('google' => get_option( 'google_analytics_key' ));
				}
				
				$opts['title'] = $_POST['wordchimp_campaign_title'];
				
				// Time to generate the actual HTML of the e-mail
				
				// First, put the template into a variable
				$template = get_option( 'wordchimp_template' ) == "" ? file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/campaign_template/default.tpl') : get_option( 'wordchimp_template' );
				
				if (get_option( 'wordchimp_logo_url' ) != "") {
					$table_of_contents['html'] = "<table width='550'><tr><td><img src='" . get_option( 'wordchimp_logo_url' ) . "' /></td></tr></table>";
				}
				
				$table_of_contents['html'] .= "<strong>Table of Contents:</strong><br /><ul>";
				
				foreach ($_POST['wordchimp_post_ids'] as $key => $post_id) {
					$sql = "SELECT post_author, display_name, post_date, post_content, post_title, post_excerpt, post_name FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}users ON {$wpdb->prefix}posts.post_author = {$wpdb->prefix}users.ID WHERE {$wpdb->prefix}posts.id = {$post_id}";
					$post = $wpdb->get_row($sql, ARRAY_A);
					
					// Setup HTML email
					$content['html'] .= "<a name='{$post_id}'></a><h4>{$post['post_title']}</h4>";
					
					if (get_option( 'wordchimp_show_author')) {
						$content['html'] .= "<small>Authored by: {$post['display_name']}</small><br />";
					}
					
					if (get_option( 'wordchimp_show_timestamp' )) {
						if (get_option( 'wordchimp_timestamp_format' ) == "") {
							$post['formatted_post_date'] = date('m/d/Y g:ia', strtotime($post['post_date']));
						} else {
							$post['formatted_post_date'] = date(get_option( 'wordchimp_timestamp_format' ), strtotime($post['post_date']));
						}
						
						$content['html'] .= "<small>Posted on: <em>{$post['formatted_post_date']}</em></small><br />";
					}
					
					if (get_option( 'wordchimp_strip_images')) {
						$post['post_content'] = preg_replace("/<img[^>]+\>/i", "", $post['post_content']);
					}
					
					$content['html'] .= $post['post_content'] . "<br /><hr /><br />";
					
					// Setup text email
					$content['text'] .= strip_tags($post['post_title']) . "\r\n";

					if (get_option( 'wordchimp_show_author')) {
						$content['text'] .= "Authored by: {$post['display_name']}\r\n";
					}
					
					if (get_option( 'wordchimp_show_timestamp' )) {
						if (get_option( 'wordchimp_timestamp_format' ) == "") {
							$post['formatted_post_date'] = date('m/d/Y g:ia', strtotime($post['post_date']));
						} else {
							$post['formatted_post_date'] = date(get_option( 'wordchimp_timestamp_format' ), strtotime($post['post_date']));
						}
						
						$content['text'] .= "Posted on: {$post['formatted_post_date']}\r\n";
					}
					
					$content['text'] .= strip_tags($post['post_content']) . "\r\n\r\n----------------\r\n\r\n";
					
					// Setup HTML TOC
					$table_of_contents['html'] .= "<li><a href='#{$post_id}'>{$post['post_title']}</a></li>";
					
					// Setup text TOC
					$table_of_contents['text'] .= "{$post['post_title']}\r\n\r\n";
				}
				
				$table_of_contents['html'] .= "</ul><br /><br />";
				
				$content['html'] = $table_of_contents['html'] . $content['html'] . "<br /><br /><small><a href='http://hudsoncs.com/projects/wordchimp/'>E-mail sent using WordChimp, created by David Hudson.</a>";
				$content['html'] = str_replace('[[loop_contents]]', $content['html'], $template);	
				
				$content['text'] = $table_of_contents['text'] . $content['text'];
				
				$campaignId = $api->campaignCreate($type, $opts, $content);
				if ($api->errorCode){
					echo "<p class='wordchimp_error'>There was an error creating your campaign. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
				} else {
					echo <<<EOF
					<p class='wordchimp_success'>Success! Your campaign was created. {$random['compliment'][rand(0, count($random['compliment'])-1)]} Now what?</p>
					<form method='post'>
						<input type='hidden' name='wp_cmd' value='step5' />
						<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
						<input type='text' name='mailchimp_test_emails' value='test@email.com, test@otheremail.com' />
						<p class="submit">
							<input type="submit" class="button-primary" value="Send Test" />
						</p>
					</form>
					<form method='post'>
						<input type='hidden' name='wp_cmd' value='step6' />
						<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
						<p class="submit">
							<input type="submit" class="button-primary" value="Send Fo' Real" />
						</p>
					</form>
EOF;
				}
			}
		break;
		
		case "step5":
			$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key' ));
			$emails = explode(",", $_POST['mailchimp_test_emails']);
			$campaignId = $_POST['mailchimp_campaign_id'];
			
			$retval = $api->campaignSendTest($campaignId, $emails);
			
			if ($api->errorCode){
				echo "<p class='wordchimp_error'>Unable to send test campaign.  " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
			} else {
				echo "<span class='wordchimp_notice'>Success! A test has been sent to the e-mail addresses you provided. " . $random['compliment'][rand(0, count($random['compliment']) -1)] . "</span><br /><br />";
			}
			echo <<<EOF
			<form method='post'>
				<input type='hidden' name='wp_cmd' value='step5' />
				<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
				<input type='text' name='mailchimp_test_emails' value='test@email.com, test@otheremail.com' />
				<p class="submit">
					<input type="submit" class="button-primary" value="Send Test" />
				</p>
			</form>
			<form method='post'>
				<input type='hidden' name='wp_cmd' value='step6' />
				<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
				<p class="submit">
					<input type="submit" class="button-primary" value="Send Fo' Real" />
				</p>
			</form>
EOF;
		break;
		
		case "step6":
			$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key' ));
			$campaignId = $_POST['mailchimp_campaign_id'];
			$emails = explode(",", $_POST['mailchimp_test_emails']);
			
			$retval = $api->campaignSendNow($campaignId);
			
			if ($api->errorCode){
				echo "<p class='wordchimp_error'>Unable to send out campaign.  " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
			} else {
				echo "<p class='wordchimp_success'>Success! You're campaign has been sent out. " . $random['compliment'][rand(0, count($random['compliment']) -1)] . "</p><br /><br />";
			}
		break;
	}
	
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_footer.tpl');
}

$random['compliment'][] = "Aren't you special?";
$random['compliment'][] = "Way to go champ!";
$random['compliment'][] = "WordChimp loves you.";
$random['compliment'][] = "You're like a MailChimp/Wordpress ninja now.";
$random['compliment'][] = "Did you just get your hair did? Nice!";
$random['compliment'][] = "You're a bi-winner!";
$random['compliment'][] = "Extremely groovy!";
$random['compliment'][] = "You're making the whole office look good.";
$random['compliment'][] = "You should run for President!";

$random['whoops'][] = "Whoops!";
$random['whoops'][] = "What did you do?";
$random['whoops'][] = "It's broke-a-did.";
$random['whoops'][] = "Oh snap!";
$random['whoops'][] = "Oh geeze...";
$random['whoops'][] = "WWWWHHHHHHHYYYYYYYY?????";
?>

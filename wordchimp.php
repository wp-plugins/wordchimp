<?php
/*
Plugin Name: WordChimp
Plugin URI: http://hudsoncs.com/projects/wordchimp/
Description: Allows you to easily select and send a group of posts as a MailChimp campaign
Version: 1.6
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

// Setup ajax calls
add_action( 'wp_ajax_wordchimp_campaign_preview', 'wordchimp_campaign_preview' );

function wordchimp_admin_init() {
	wp_register_style( 'wordchimpStyle', '/wp-content/plugins/wordchimp/style.css' );
	wp_register_script( 'wordchimpjQuery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js' );
	wp_register_script( 'wordchimpScript', '/wp-content/plugins/wordchimp/js/script.js' );
}

function register_wordchimp_settings() {
	register_setting( 'wordchimp_options_group', 'mailchimp_api_key' );
	register_setting( 'wordchimp_options_group', 'wordchimp_campaign_from_name' );
	register_setting( 'wordchimp_options_group', 'wordchimp_campaign_from_email' );
	register_setting( 'wordchimp_options_group', 'wordchimp_logo_url' );
	register_setting( 'wordchimp_options_group', 'wordchimp_strip_images' );
	register_setting( 'wordchimp_options_group', 'wordchimp_show_author' );
	register_setting( 'wordchimp_options_group', 'wordchimp_show_timestamp' );
	register_setting( 'wordchimp_options_group', 'wordchimp_timestamp_format' );
	register_setting( 'wordchimp_options_group', 'wordchimp_template' );
	register_setting( 'wordchimp_options_group', 'wordchimp_use_excerpt' );
	register_setting( 'wordchimp_options_group', 'wordchimp_page_capability' );
	register_setting( 'wordchimp_options_group', 'wordchimp_campaigns_capability' );
	register_setting( 'wordchimp_options_group', 'wordchimp_settings_capability' );
}

function wordchimp_admin_styles() {
   wp_enqueue_style( 'wordchimpStyle' );
}

function wordchimp_admin_scripts() {
   wp_enqueue_script( 'wordchimpjQuery' );
   wp_enqueue_script( 'wordchimpScript' );
}

function wordchimp_menu() {
	$page_capability = get_option( 'wordchimp_page_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_page_capability' );
	$campaigns_capability = get_option( 'wordchimp_campaigns_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_campaigns_capability' );
	$settings_capability = get_option( 'wordchimp_settings_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_settings_capability' );

	$page = add_menu_page('WordChimp', 'WordChimp', $page_capability, 'wordchimp', 'wordchimp_dashboard');
	$campaigns_page = add_submenu_page('wordchimp', 'Campaign Stats', 'Campaign Stats', $campaigns_capability, 'wordchimp-campaigns', 'wordchimp_campaigns_page');
	$settings_page = add_submenu_page('options-general.php', 'WordChimp Settings', 'WordChimp', $settings_capability, __FILE__, 'wordchimp_settings_page');
	
	add_action( 'admin_print_styles-' . $page, 'wordchimp_admin_styles' );
	add_action( 'admin_print_scripts-' . $page, 'wordchimp_admin_scripts' );
	
	add_action( 'admin_print_styles-' . $campaigns_page, 'wordchimp_admin_styles' );
	add_action( 'admin_print_scripts-' . $campaigns_page, 'wordchimp_admin_scripts');
	
	add_action( 'admin_print_styles-' . $settings_page, 'wordchimp_admin_styles' );
	add_action( 'admin_print_scripts-' . $settings_page, 'wordchimp_admin_scripts' );
	
	add_action( 'admin_init', 'register_wordchimp_settings' );
}

add_filter('plugin_action_links', 'wordchimp_plugin_action_links', 10, 2);

function wordchimp_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wordchimp/wordchimp.php">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

function wordchimp_campaign_preview() {
	global $random;
	global $wpdb;
	
	$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key' ));
	
	$campaignContent = $api->campaignContent($_GET['cid']);
	
	switch ($_GET['type']) {
		default:
		case "html":
			echo $campaignContent['html'];
		break;
		
		case "text":
			echo "<html><body><pre>{$campaignContent['text']}</pre></body></html>";
		break;
	}
	die();
}

function wordchimp_campaigns_page() {
	global $random;
	global $wpdb;
	
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_header.tpl');
	echo "<h3>Campaigns</h3>";
	echo "<p class='wordchimp_notice'>Shows information and statistics for all sent campaigns (generated through WordChimp or otherwise)</p>";
	if (get_option( 'mailchimp_api_key' ) == "") {
		echo "<p class='wordchimp_error'>You must enter your MailChimp API key in the settings page before you can continue. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . "</p>";
	} else {
		$api = new MCAPI_WordChimp(get_option( 'mailchimp_api_key' ));

		$campaigns = $api->campaigns();

		if ($api->errorCode){
			echo "<p class='wordchimp_error'>Sorry, we were unable to get a list of your campaigns. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
		} elseif (sizeof($campaigns['data']) <= 0) {
			echo "<p class='wordchimp_error'>Sorry, there are no campaigns available that have been sent. Please send a campaign and come on back to take stats for a spin!</p>";
		} else {
			echo <<<EOF
				<table class='wordchimp_campaigns_analytics_table'>
					<tbody>
EOF;
			foreach ($campaigns['data'] as $campaign) {
				if ($campaign['status'] == 'sent') {
					$stats = $api->campaignStats($campaign['id']);

					if ($api->errorCode) {
						echo "<p class='wordchimp_error'>Sorry, we were unable to get analytics for a campaign. " . $random['whoops'][rand(0, count($random['whoops'])-1)] . " {$api->errorCode} {$api->errorMessage}</p>";
					} else {
						$wordchimp_timestamp_format = get_option( 'wordchimp_timestamp_format' ) == '' ? 'm/d/Y g:ia' : get_option( 'wordchimp_timestamp_format' );
						
						$campaign['send_time'] = date($wordchimp_timestamp_format, strtotime($campaign['send_time']));
						$stats['last_open'] = $stats['last_open'] != '' ? date($wordchimp_timestamp_format, strtotime($stats['last_open'])) : 'NEVA';
						$stats['last_click'] = $stats['last_click'] != '' ? date($wordchimp_timestamp_format, strtotime($stats['last_click'])) : 'NEVA';

						echo "
							<tr class='wordchimp_campaign_separate'>
								<td colspan='6'></td>
							</tr>
							<tr>
								<td colspan='6' class='wordchimp_campaign_title'><a href='" . get_bloginfo('wpurl') . "/wp-admin/admin-ajax.php?action=wordchimp_campaign_preview&cid={$campaign['id']}' target='_blank'>{$campaign['title']}</a></td>
							</tr>
							<tr>
								<td><span class='wordchimp_campaign_data_title'>Sent on:</span><br /><span class='wordchimp_campaign_data_value'>{$campaign['send_time']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Emails sent:</span><br /><span class='wordchimp_campaign_data_value'>{$campaign['emails_sent']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Hard bounces:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['hard_bounces']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Soft bounces:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['soft_bounces']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Unsubscribes:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['unsubscribes']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Forwards:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['forwards']}</span></td>
							</tr>
							<tr>
								<td><span class='wordchimp_campaign_data_title'>Forwards opens:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['forwards_opens']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Opens:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['opens']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Last open:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['last_open']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Clicks:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['clicks']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Users who clicked:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['users_who_clicked']}</span></td>
								<td><span class='wordchimp_campaign_data_title'>Last click:</span><br /><span class='wordchimp_campaign_data_value'>{$stats['last_click']}</span></td>
							</tr>";
					}
				}
			}
			echo "</tbody></table>";
		}
	}
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_footer.tpl');
}

function wordchimp_settings_page() {
	global $random;
	
	echo file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/template/adm_header.tpl');
	echo "<form method='post' action='options.php'> ";
	settings_fields( 'wordchimp_options_group' );
	do_settings_fields( 'wordchimp_settings_page', 'wordchimp_options_group' );
	
	$mailchimp_api_key = get_option( 'mailchimp_api_key' );
	$wordchimp_campaign_from_name = get_option( 'wordchimp_campaign_from_name' );
	$wordchimp_campaign_from_email = get_option( 'wordchimp_campaign_from_email' );
	$wordchimp_logo_url = get_option( 'wordchimp_logo_url' );
	$wordchimp_template = get_option( 'wordchimp_template' ) == "" ? file_get_contents(WP_PLUGIN_DIR . '/wordchimp/inc/campaign_template/default.tpl') : get_option( 'wordchimp_template' );
	$wordchimp_strip_images_checked = get_option( 'wordchimp_strip_images' ) == true ? 'CHECKED' : '';
	$wordchimp_show_author_checked = get_option( 'wordchimp_show_author' ) == true ? 'CHECKED' : '';
	$wordchimp_show_timestamp_checked = get_option( 'wordchimp_show_timestamp' ) == true ? 'CHECKED' : '';
	$wordchimp_timestamp_format = get_option( 'wordchimp_timestamp_format' ) == '' ? 'm/d/Y g:ia' : get_option( 'wordchimp_timestamp_format' );
	$wordchimp_use_excerpt_checked = get_option( 'wordchimp_use_excerpt' ) == true ? 'CHECKED' : '';
	
	$wordchimp_page_capability = get_option( 'wordchimp_page_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_page_capability' );
	$wordchimp_campaigns_capability = get_option( 'wordchimp_campaigns_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_campaigns_capability' );
	$wordchimp_settings_capability = get_option( 'wordchimp_settings_capability' ) == '' ? 'manage_options' : get_option( 'wordchimp_settings_capability' );
	
	if ($_GET['settings-updated'] == 'true') {
		echo "<p class='wordchimp_success'>Success! Your settings have been updated. " . $random['compliment'][rand(0, count($random['compliment'])-1)] . "</p>";
	}
	echo <<<EOF
		<h3>APIs</h3>
		<label><strong>MailChimp API Key</strong><br /><input type='text' name='mailchimp_api_key' value='{$mailchimp_api_key}' /></label><br/><br/>
		<h3>Security</h3>
		<small>Security is based on WordPress 'capabilities'. The 'manage_option' capability is the default and if you don't know what this is or how this works, please do not change it! For more information, visit <a href='http://codex.wordpress.org/Roles_and_Capabilities' target='_blank'>http://codex.wordpress.org/Roles_and_Capabilities</a>.</small><br />
		<label><strong>Create Campaign Through WordChimp:</strong><br />
			<select name='wordchimp_page_capability'>
				<option>{$wordchimp_page_capability}</option>
				<option disabled>-----</option>
				<option disabled value=''>Super Admin</option>
				<option>manage_network</option>
				<option>manage_sites</option>
				<option>manage_network_users</option>
				<option>manage_network_themes</option>
				<option>manage_network_options</option>
				<option>unfiltered_html when using Multisite</option>
				<option disabled value=''>Administrator</option>
				<option>activate_plugins</option>
				<option>add_users</option>
				<option>create_users</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_plugins</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>delete_themes</option>
				<option>delete_users</option>
				<option>edit_dashboard</option>
				<option>edit_files</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_plugins</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>edit_theme_options</option>
				<option>edit_themes</option>
				<option>edit_users</option>
				<option>export</option>
				<option>import</option>
				<option>install_plugins</option>
				<option>install_themes</option>
				<option>list_users</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>manage_options</option>
				<option>moderate_comments</option>
				<option>promote_users</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>read</option>
				<option>remove_users</option>
				<option>switch_themes</option>
				<option>unfiltered_html</option>
				<option>unfiltered_upload</option>
				<option>update_core</option>
				<option>update_plugins</option>
				<option>update_themes</option>
				<option>upload_files</option>
				<option disabled value=''>Editor</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>moderate_comments</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>unfiltered_html</option>
				<option>upload_files</option>
				<option disabled value=''>Author</option>
				<option>delete_posts</option>
				<option>delete_published_posts</option>
				<option>edit_posts</option>
				<option>edit_published_posts</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>upload_files</option>
				<option disabled value=''>Contributor</option>
				<option>delete_posts</option>
				<option>edit_posts</option>
				<option>read</option>
				<option disabled value=''>Subscriber</option>
				<option>read</option>
			</select>
		</label><br /><br />
		<label><strong>Access Campaign Stats Through WordChimp:</strong><br />
			<select name='wordchimp_campaigns_capability'>
				<option>{$wordchimp_campaigns_capability}</option>
				<option disabled>-----</option>
				<option disabled value=''>Super Admin</option>
				<option>manage_network</option>
				<option>manage_sites</option>
				<option>manage_network_users</option>
				<option>manage_network_themes</option>
				<option>manage_network_options</option>
				<option>unfiltered_html when using Multisite</option>
				<option disabled value=''>Administrator</option>
				<option>activate_plugins</option>
				<option>add_users</option>
				<option>create_users</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_plugins</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>delete_themes</option>
				<option>delete_users</option>
				<option>edit_dashboard</option>
				<option>edit_files</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_plugins</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>edit_theme_options</option>
				<option>edit_themes</option>
				<option>edit_users</option>
				<option>export</option>
				<option>import</option>
				<option>install_plugins</option>
				<option>install_themes</option>
				<option>list_users</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>manage_options</option>
				<option>moderate_comments</option>
				<option>promote_users</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>read</option>
				<option>remove_users</option>
				<option>switch_themes</option>
				<option>unfiltered_html</option>
				<option>unfiltered_upload</option>
				<option>update_core</option>
				<option>update_plugins</option>
				<option>update_themes</option>
				<option>upload_files</option>
				<option disabled value=''>Editor</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>moderate_comments</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>unfiltered_html</option>
				<option>upload_files</option>
				<option disabled value=''>Author</option>
				<option>delete_posts</option>
				<option>delete_published_posts</option>
				<option>edit_posts</option>
				<option>edit_published_posts</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>upload_files</option>
				<option disabled value=''>Contributor</option>
				<option>delete_posts</option>
				<option>edit_posts</option>
				<option>read</option>
				<option disabled value=''>Subscriber</option>
				<option>read</option>
			</select>
		</label><br /><br />
		<label><strong>Access to WordChimp Settings:</strong><br />
			<select name='wordchimp_settings_capability'>
				<option>{$wordchimp_settings_capability}</option>
				<option disabled>-----</option>
				<option disabled value=''>Super Admin</option>
				<option>manage_network</option>
				<option>manage_sites</option>
				<option>manage_network_users</option>
				<option>manage_network_themes</option>
				<option>manage_network_options</option>
				<option>unfiltered_html when using Multisite</option>
				<option disabled value=''>Administrator</option>
				<option>activate_plugins</option>
				<option>add_users</option>
				<option>create_users</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_plugins</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>delete_themes</option>
				<option>delete_users</option>
				<option>edit_dashboard</option>
				<option>edit_files</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_plugins</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>edit_theme_options</option>
				<option>edit_themes</option>
				<option>edit_users</option>
				<option>export</option>
				<option>import</option>
				<option>install_plugins</option>
				<option>install_themes</option>
				<option>list_users</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>manage_options</option>
				<option>moderate_comments</option>
				<option>promote_users</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>read</option>
				<option>remove_users</option>
				<option>switch_themes</option>
				<option>unfiltered_html</option>
				<option>unfiltered_upload</option>
				<option>update_core</option>
				<option>update_plugins</option>
				<option>update_themes</option>
				<option>upload_files</option>
				<option disabled value=''>Editor</option>
				<option>delete_others_pages</option>
				<option>delete_others_posts</option>
				<option>delete_pages</option>
				<option>delete_posts</option>
				<option>delete_private_pages</option>
				<option>delete_private_posts</option>
				<option>delete_published_pages</option>
				<option>delete_published_posts</option>
				<option>edit_others_pages</option>
				<option>edit_others_posts</option>
				<option>edit_pages</option>
				<option>edit_posts</option>
				<option>edit_private_pages</option>
				<option>edit_private_posts</option>
				<option>edit_published_pages</option>
				<option>edit_published_posts</option>
				<option>manage_categories</option>
				<option>manage_links</option>
				<option>moderate_comments</option>
				<option>publish_pages</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>read_private_pages</option>
				<option>read_private_posts</option>
				<option>unfiltered_html</option>
				<option>upload_files</option>
				<option disabled value=''>Author</option>
				<option>delete_posts</option>
				<option>delete_published_posts</option>
				<option>edit_posts</option>
				<option>edit_published_posts</option>
				<option>publish_posts</option>
				<option>read</option>
				<option>upload_files</option>
				<option disabled value=''>Contributor</option>
				<option>delete_posts</option>
				<option>edit_posts</option>
				<option>read</option>
				<option disabled value=''>Subscriber</option>
				<option>read</option>
			</select>
		</label><br /><br />
		<h3>Options</h3>
		<label><strong>Default From Name:</strong><br /><input type='text' name='wordchimp_campaign_from_name' value='{$wordchimp_campaign_from_name}' /></label><br /><br />
		<label><strong>Default From E-mail:</strong><br /><input type='text' name='wordchimp_campaign_from_email' value='{$wordchimp_campaign_from_email}' /></label><br /><br />
		<label><strong>Logo URL (includes http):</strong><br /><input type='text' name='wordchimp_logo_url' value='{$wordchimp_logo_url}' /></label><br /><br />
		<label><input type='checkbox' name='wordchimp_use_excerpt' value='true' {$wordchimp_use_excerpt_checked} /> Use excerpts instead of the full post content for newsletter.</label><br /><br />
		<label><input type='checkbox' name='wordchimp_strip_images' value='true' {$wordchimp_strip_images_checked} /> Strip images from posts (Fixes some compatibility issues with posts that have very large images)</label><br /><br />
		<label><input type='checkbox' name='wordchimp_show_author' value='true' {$wordchimp_show_author_checked} /> Show author inside of post</label><br /><br />
		<label><input type='checkbox' name='wordchimp_show_timestamp' value='true' {$wordchimp_show_timestamp_checked} /> Show post created date/time inside of post</label><br /><br />
		<label><strong>Date/Time Format:</strong><br /><input type='text' name='wordchimp_timestamp_format' value='{$wordchimp_timestamp_format}' /></label><br /><br />
		<label><strong>E-mail Template</strong> <small>(NOTE: Only edit if you know what you're doing)</small></label><br />
		<p class='wordchimp_notice'>For now, templates are pretty simple. The default template comes from MailChimp's free template package. Any template will do, just be sure to add the shortcode: <strong>[[loop_contents]]</strong> wherever you want the posts to show up.</p>
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
				$wordchimp_campaign_from_email = htmlspecialchars(get_option( 'wordchimp_campaign_from_email' ));
				$wordchimp_campaign_from_name = htmlspecialchars(get_option( 'wordchimp_campaign_from_name' ));
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
						<td><input type='text' name='wordchimp_campaign_from_email' value='{$wordchimp_campaign_from_email}' /></td>
					</tr>
					<tr valign='top'>
						<th scope='row'>From Name</th>
						<td><input type='text' name='wordchimp_campaign_from_name' value='{$wordchimp_campaign_from_name}' /></td>
					</tr>
				</table>
				<h3>Campaign Tracking</h3>
				<label><input type='checkbox' name='wordchimp_campaign_track_opens' value='true' checked /> Track number of times email was opened</label><br />
				<label><input type='checkbox' name='wordchimp_campaign_track_html_clicks' value='true' checked /> Track number of times a user clicked an HTML link</label><br />
				<label><input type='checkbox' name='wordchimp_campaign_track_text_clicks' value='true' checked /> Track number of times a user clicked a text link</label>";
				
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
					
					// Check to see if we're using an excerpt or the full post
					$post_type = get_option( 'wordchimp_use_excerpt' ) == 'true' ? 'post_excerpt' : 'post_content';
					
					// Strip images if necessary
					if (get_option( 'wordchimp_strip_images')) {
						$post[$post_type] = preg_replace("/<img[^>]+\>/i", "", $post[$post_type]);
					}
					
					// Remove short codes and finalize html content
					$content['html'] .= do_shortcode($post[$post_type]) . "<br /><hr /><br />";
					
					// Remove short codes and finalize text content
					$content['text'] .= strip_tags(do_shortcode($post['post_title'])) . "\r\n";

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
EOF;
					echo "<a href='" . get_bloginfo('wpurl') . "/wp-admin/admin-ajax.php?action=wordchimp_campaign_preview&cid={$campaignId}' target='_blank'>Preview Campaign in Browser</a><br /><br />";
					echo <<<EOF
					<h3>Send a test?</h3>
					<form method='post'>
						<input type='hidden' name='wp_cmd' value='step5' />
						<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
						<input type='text' name='mailchimp_test_emails' value='test@email.com, test@otheremail.com' onClick="this.value='';" />
						<p class="submit">
							<input type="submit" class="button-primary" value="Send Test" />
						</p>
					</form>
					<h3>Send fo' real?</h3>
					<form method='post'>
						<input type='hidden' name='wp_cmd' value='step6' />
						<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
						<p class="submit">
							<input type="submit" class="button-primary" value="Send Fo' Real!" />
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
			
			echo "<a href='" . get_bloginfo('wpurl') . "/wp-admin/admin-ajax.php?action=wordchimp_campaign_preview&cid={$campaignId}' target='_blank'>Preview Campaign in Browser</a><br /><br />";
			echo <<<EOF
			<h3>Send a test?</h3>
			<form method='post'>
				<input type='hidden' name='wp_cmd' value='step5' />
				<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
				<input type='text' name='mailchimp_test_emails' value='test@email.com, test@otheremail.com' onClick="this.value='';" />
				<p class="submit">
					<input type="submit" class="button-primary" value="Send Test" />
				</p>
			</form>
			<h3>Send fo' real?</h3>
			<form method='post'>
				<input type='hidden' name='wp_cmd' value='step6' />
				<input type='hidden' name='mailchimp_campaign_id' value='{$campaignId}' />
				<p class="submit">
					<input type="submit" class="button-primary" value="Send Fo' Real!" />
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
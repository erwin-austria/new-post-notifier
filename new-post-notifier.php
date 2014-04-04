<?php 	
/*
Plugin Name: New Post Notifier
Plugin URI: http://www.portalintegrators.com
Description: New Post Notifier is a plugin which will send automatic email when the post is published by a user to the selected administrator in the setting field.
Author: Portal Intergrators
Author URI: http://www.portalintegrators.com
Version: 1.00
*/

function em_get_role_post() {
	//get the role of the selected posted
	global $post;
	if ( user_can( $post->post_author, 'administrator' ) ) {
	  return 'Administrator';
	} elseif ( user_can( $post->post_author, 'editor' ) ) {
	  return 'Editor';
	} elseif ( user_can( $post->post_author, 'author' ) ) {
	  return 'Author';
	} elseif ( user_can( $post->post_author, 'contributor' ) ) {
	  return 'Contributor';
	} elseif ( user_can( $post->post_author, 'subscriber' ) ) {
	  return 'Subscriber';
	} else {
	  return 'Guest';
	}
}

function em_get_current_user() {
	//get all current user
	global $current_user;
	$current_user = $current_user;
	return $current_user;
}

function em_get_current_user_role() {
	//get current login role
	$current_user = em_get_current_user();
	$user_roles = $current_user->roles;
	$user_role = array_shift($user_roles);
	return $user_role;
}

function em_get_data_post($id) {
	//select post proprety
	global $wpdb;
	$tp = $wpdb->prefix;
	$result = array();
	$result = $wpdb->get_row("
		SELECT post_status, post_title, user_login, user_nicename, 
				display_name,post_author, user_email, post_name
		FROM {$tp}posts, {$tp}users
		WHERE {$tp}posts.post_author = {$tp}users.ID
		AND {$tp}posts.ID = '$id'
	");
	return $result;
}

function em_mail($recipient, $subject, $message) {	
	mail($recipient, $subject, $message); //email 
}

function em_setting_api_mail($subject, $message) {
	$subject = $subject;
	$message = $message;
	if (is_array(get_option('em_notification_options'))) {
	  	$data = (get_users('role=administrator&orderby=display_name&order=ASC'));
		$outputs = get_option('em_notification_options');
		foreach($data as $datum) {
			if(!empty($outputs['em_banner_heading'][$datum->ID])) {
				em_mail($datum->user_email, $subject, $message);
			}
		}
	}
}

function em_savePost($id) {
	$id = $id;
	$result = em_get_data_post($id);
	$currentLogin = em_get_current_user_role();
	if ($currentLogin != 'administrator') {	
	//filtering of adminisitrator
		if ( ($result->post_status == "pending") && ($currentLogin == 'contributor') ) {
		//sending email to administrator if the current login has a post and the current login is contributor
			$message = "Hi!\n\n" . ucwords($result->user_nicename) . 
			$message .= ' has a new post "'. $result->post_title.'" waiting for approval.' . "\n\n";
			$message .= 'Thanks!';
			$subject = "Post Waiting for Approval";
			//$recipient = get_bloginfo('admin_email');
			em_setting_api_mail($subject, $message);
		}
	  	if (($currentLogin != 'contributor') && ($result->post_status == "draft") ) {
		//sending email to administrator if the current login has a post and the current login is not contributor
			$message = "Hi! \n\n";
			$message .= ucwords($result->user_nicename) . ' has a new post "'. $result->post_title . '".' . "\n\n";
			$message .= 'Thanks!';
			$subject = "New Post";
			$recipient = get_bloginfo('admin_email');
			em_setting_api_mail($subject, $message);
		}
	}		
}
add_action('save_post', 'em_savePost');

function em_editPost($id) {
	$id = $id;
	$rolePost = em_get_role_post();
	$currentLoginUser = em_get_current_user();
	$result = em_get_data_post($id);
	$currentLogin = em_get_current_user_role();
	if (ucfirst($currentLogin) != $rolePost && 'Guest' != $rolePost ) {
	//filtering of current login role is not equal to selected post role 
		if(!wp_is_post_revision( $id)) {
		//if the post is revise or updated			
			if ($rolePost != 'Contributor') {
				if ($result->post_status != "trash") {
				//if the post is not trash or remove
					$message = "Hi " . ucfirst($rolePost) . ",\n\n";
					$message .= ucwords($currentLogin) . ' has edited your post "'. $result->post_title . '"' . "\n\n";
					$message .= 'Thanks!';
					$subject = "Post Revision";
					$recipient = $result->user_email;
					em_mail($recipient, $subject, $message);
				}
				
			}	
		}
	} 				
}
add_action('edit_post', 'em_editPost');

function em_publishPost($id) {
	$id = $id;
	$rolePost = em_get_role_post();
	$result = em_get_data_post($id);
	$currentLogin = em_get_current_user_role();
		if ((get_bloginfo('admin_email') != $result->user_email) && $rolePost == 'Contributor') {
		//publish contributor post
			$message = "Hi " . ucwords($result->user_nicename) . "," . "\n\n";
			$message .= ucwords($currentLogin) .  " has publish / edit  your post " . '"' . $result->post_title . '".' . "\n\n";
			$message .= 'Thanks!';
			$subject = "Published Post";
			$recipient = $result->user_email; 
			em_mail($recipient, $subject, $message);
		} 				
}
add_action('publish_post', 'em_publishPost');

function em_trashed($id) {
	$result = em_get_data_post($id);
	$rolePost = em_get_role_post();
	$currentLogin = em_get_current_user_role();
	if (ucfirst($currentLogin) != $rolePost) {
		$message = 'Hi ' . ucfirst($result->user_nicename) . ',' . "\n\n";
		$message .= 'Sorry your post "' . $result->post_title . '" has been rejected by the ' . $currentLogin . '.' . "\n\n";
		$message .= 'Thanks!';
		$subject = 'Rejected Post';
		$recipient = $result->user_email; 
		em_mail($recipient, $subject, $message);
	}
}
add_action('trashed_post', 'em_trashed'); 

//Setting API

function EM_Notification() {
	// adding label to the setting
	add_options_page('New Post Notifier', 'New Post Notifier', 'administrator', __FILE__, 'display_options_page');
} 
add_action( 'admin_menu', 'EM_Notification' );

function em_notification_initialize_options() {
	//adding fields, this code just simply divided into several layout of the settings 
	add_settings_section('em_main_section', 'Main Settings', 'em_main_section_cb', __FILE__);
	add_settings_field('em_banner_heading', 'Administrator:', 'em_notification_setting', __FILE__, 'em_main_section');
	register_setting('em_notification_options', 'em_notification_options' );	
}

add_action( 'admin_init', 'em_notification_initialize_options' );

function display_options_page() {
?>
	<!-- this is code is the form that you can see in the setting field -->
	<div class="wrap">
		<h2> New Post Notifier Options</h2>		
		<form method="post" action="options.php">
			<?php settings_fields('em_notification_options'); ?>
			<?php do_settings_sections(__FILE__); ?>
			<p class="submit"> <input type="submit" value="Save Changes" class="button-primary" name="submit"> </p>
		</form>
	</div>	
<?php
} 

function em_main_section_cb() {
	// Description of the setting fields
	echo "These options are designed to choose administrator to send email.";
} 

function em_notification_setting() {	
	//this code is inside the form example text checkbox
?>
	<ul>
	<?php
		$options = (array)get_option( 'em_notification_options' );
		$authors = get_users('role=administrator&orderby=display_name&order=ASC');
		foreach ($authors as $author) {
			$id = $author->ID;
				$html = "<li> <input type='checkbox' name='em_notification_options[em_banner_heading][$id]' value='1'";
				$html .= ((isset($options['em_banner_heading'][$id])) ? checked( 1, $options['em_banner_heading'][$id], false ) : '')  ."/>";
				$html .= '<label>'. $author->display_name . ' / <span <p style="color: #ff9900">'. $author->user_email . '</span></label> </li>';
			echo $html;
		}
	?>
	</ul>
<?php
} 



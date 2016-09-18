<?php
/**
 * New User Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

require_once( __DIR__ . '/admin.php' );

if ( ! current_user_can( 'create_users' ) ) {
	wp_die(
		'<h1>Cheatin&#8217; uh?</h1>' .
		'<p>You are not allowed to create users.</p>',
		403
	);
}

if ( isset($_REQUEST['action']) && 'adduser' == $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	$user_details = null;
	$user_email = wp_unslash( $_REQUEST['email'] );
	if ( false !== strpos( $user_email, '@' ) ) {
		$user_details = get_user_by( 'email', $user_email );
	} else {
		if ( is_super_admin() ) {
			$user_details = get_user_by( 'login', $user_email );
		} else {
			wp_redirect( add_query_arg( array('update' => 'enter_email'), 'user-new.php' ) );
			die();
		}
	}

	if ( !$user_details ) {
		wp_redirect( add_query_arg( array('update' => 'does_not_exist'), 'user-new.php' ) );
		die();
	}

	if ( ! current_user_can( 'promote_user', $user_details->ID ) ) {
		wp_die(
			'<h1>Cheatin&#8217; uh?</h1>' .
			'<p>You do not have sufficient permissions to add users to this network.</p>',
			403
		);
	}

	// Adding an existing user to this blog
	$new_user_email = $user_details->user_email;
	$redirect = 'user-new.php';
	$username = $user_details->user_login;
	$user_id = $user_details->ID;
	if ( ( $username != null && !is_super_admin( $user_id ) ) && ( array_key_exists($blog_id, get_blogs_of_user($user_id)) ) ) {
		$redirect = add_query_arg( array('update' => 'addexisting'), 'user-new.php' );
	} else {
		if ( isset( $_POST[ 'noconfirmation' ] ) && current_user_can( 'manage_network_users' ) ) {
			add_existing_user_to_blog( array( 'user_id' => $user_id, 'role' => $_REQUEST[ 'role' ] ) );
			$redirect = add_query_arg( array( 'update' => 'addnoconfirmation' , 'user_id' => $user_id ), 'user-new.php' );
		} else {
			$newuser_key = substr( md5( $user_id ), 0, 5 );
			add_option( 'new_user_' . $newuser_key, array( 'user_id' => $user_id, 'email' => $user_details->user_email, 'role' => $_REQUEST[ 'role' ] ) );

			$roles = get_editable_roles();
			$role = $roles[ $_REQUEST['role'] ];

			/**
			 * Fires immediately after a user is invited to join a site, but before the notification is sent.
			 *
			 * @since 4.4.0
			 *
			 * @param int    $user_id     The invited user's ID.
			 * @param array  $role        The role of invited user.
			 * @param string $newuser_key The key of the invitation.
			 */
			do_action( 'invite_user', $user_id, $role, $newuser_key );

			/* translators: 1: Site name, 2: site URL, 3: role, 4: activation URL */
			$message = __( 'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s' );
			wp_mail( $new_user_email, sprintf( __( '[%s] Joining confirmation' ), wp_specialchars_decode( get_option( 'blogname' ) ) ), sprintf( $message, get_option( 'blogname' ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) ), home_url( "/newbloguser/$newuser_key/" ) ) );
			$redirect = add_query_arg( array('update' => 'add'), 'user-new.php' );
		}
	}
	wp_redirect( $redirect );
	die();
} elseif ( isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action'] ) {
	check_admin_referer( 'create-user', '_wpnonce_create-user' );

	if ( ! current_user_can( 'create_users' ) ) {
		wp_die(
			'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
			'<p>' . __( 'You are not allowed to create users.' ) . '</p>',
			403
		);
	}

	$user_id = edit_user();

	if ( is_wp_error( $user_id ) ) {
		$add_user_errors = $user_id;
	} else {
		if ( current_user_can( 'list_users' ) )
			$redirect = 'users.php?update=add&id=' . $user_id;
		else
			$redirect = add_query_arg( 'update', 'add', 'user-new.php' );
		wp_redirect( $redirect );
		die();
	}
}

$title = 'Add New User';
$parent_file = 'users.php';

$do_both = false;

$help = '<p>' . __('To add a new user to your site, fill in the form on this screen and click the Add New User button at the bottom.') . '</p>';

$help .= '<p>' . __('You must assign a password to the new user, which they can change after logging in. The username, however, cannot be changed.') . '</p>' .
	'<p>' . __('New users will receive an email letting them know they&#8217;ve been added as a user for your site. By default, this email will also contain their password. Uncheck the box if you don&#8217;t want the password to be included in the welcome email.') . '</p>';

$help .= '<p>' . __('Remember to click the Add New User button at the bottom of this screen when you are finished.') . '</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $help,
) );

get_current_screen()->add_help_tab( array(
'id'      => 'user-roles',
'title'   => 'User Roles',
'content' => '<p>Here is a basic overview of the different user roles and the permissions associated with each one:</p>' .
				'<ul>' .
				'<li>Subscribers can read comments/comment/receive newsletters, etc. but cannot create regular site content.</li>' .
				'<li>Contributors can write and manage their posts but not publish posts or upload media files.</li>' .
				'<li>Authors can publish and manage their own posts, and are able to upload files.</li>' .
				'<li>Editors can publish posts, manage posts as well as manage other people&#8217;s posts, etc.</li>' .
				'<li>Administrators have access to all the administration features.</li>' .
				'</ul>'
) );

wp_enqueue_script('wp-ajax-response');
wp_enqueue_script( 'user-profile' );

require_once( ABSPATH . 'wp-admin/admin-header.php' );

if ( isset($_GET['update']) ) {
	$messages = array();
	if ( 'add' == $_GET['update'] )
		$messages[] = 'User added.';
}
?>
<div class="wrap">
<h1 id="add-new-user"><?php
if ( current_user_can( 'create_users' ) ) {
	echo _x( 'Add New User', 'user' );
} elseif ( current_user_can( 'promote_users' ) ) {
	echo _x( 'Add Existing User', 'user' );
} ?>
</h1>

<?php if ( isset($errors) && is_wp_error( $errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
			foreach ( $errors->get_error_messages() as $err )
				echo "<li>$err</li>\n";
		?>
		</ul>
	</div>
<?php endif;

if ( ! empty( $messages ) ) {
	foreach ( $messages as $msg )
		echo '<div id="message" class="updated notice is-dismissible"><p>' . $msg . '</p></div>';
} ?>

<?php if ( isset($add_user_errors) && is_wp_error( $add_user_errors ) ) : ?>
	<div class="error">
		<?php
			foreach ( $add_user_errors->get_error_messages() as $message )
				echo "<p>$message</p>";
		?>
	</div>
<?php endif; ?>
<div id="ajax-response"></div>

<?php

if ( current_user_can( 'create_users') ) {
	if ( $do_both )
		echo '<h2 id="create-new-user">Add New User</h2>';
?>
<p>Create a brand new user and add them to this site.</p>
<form method="post" name="createuser" id="createuser" class="validate" novalidate="novalidate"<?php
	/** This action is documented in wp-admin/user-new.php */
	do_action( 'user_new_form_tag' );
?>>
<input name="action" type="hidden" value="createuser" />
<?php wp_nonce_field( 'create-user', '_wpnonce_create-user' ); ?>
<?php
// Load up the passed data, else set to a default.
$creating = isset( $_POST['createuser'] );

$new_user_login = $creating && isset( $_POST['user_login'] ) ? wp_unslash( $_POST['user_login'] ) : '';
$new_user_firstname = $creating && isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '';
$new_user_lastname = $creating && isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '';
$new_user_email = $creating && isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
$new_user_uri = $creating && isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '';
$new_user_role = $creating && isset( $_POST['role'] ) ? wp_unslash( $_POST['role'] ) : '';
$new_user_send_notification = $creating && ! isset( $_POST['send_user_notification'] ) ? false : true;
$new_user_ignore_pass = $creating && isset( $_POST['noconfirmation'] ) ? wp_unslash( $_POST['noconfirmation'] ) : '';

?>
<table class="form-table">
	<tr class="form-field form-required">
		<th scope="row"><label for="user_login"><?php _e('Username'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
		<td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr( $new_user_login ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" maxlength="60" /></td>
	</tr>
	<tr class="form-field form-required">
		<th scope="row"><label for="email"><?php _e('Email'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
		<td><input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="role">Role</label></th>
		<td><select name="role" id="role">
			<?php
			if ( !$new_user_role )
				$new_user_role = !empty($current_role) ? $current_role : get_option('default_role');
			wp_dropdown_roles($new_user_role);
			?>
			</select>
		</td>
	</tr>
</table>

<?php
/** This action is documented in wp-admin/user-new.php */
do_action( 'user_new_form', 'add-new-user' );
?>

<?php submit_button( 'Add New User', 'primary', 'createuser', true, array( 'id' => 'createusersub' ) ); ?>

</form>
<?php } // current_user_can('create_users') ?>
</div>
<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );

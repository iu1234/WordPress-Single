<?php

require_once( __DIR__ . '/admin.php' );

wp_reset_vars( array( 'action', 'user_id', 'wp_http_referer' ) );

$user_id = (int) $user_id;
$current_user = wp_get_current_user();
if ( ! defined( 'IS_PROFILE_PAGE' ) )
	define( 'IS_PROFILE_PAGE', ( $user_id == $current_user->ID ) );

if ( ! $user_id && IS_PROFILE_PAGE )
	$user_id = $current_user->ID;
elseif ( ! $user_id && ! IS_PROFILE_PAGE )
	wp_die( 'Invalid user ID.' );
elseif ( ! get_user_by( 'id', $user_id ) )
	wp_die( 'Invalid user ID.' );

wp_enqueue_script('user-profile');

$title = IS_PROFILE_PAGE ? 'Profile' : 'Edit User';
if ( current_user_can('edit_users') && !IS_PROFILE_PAGE )
	$submenu_file = 'users.php';
else
	$submenu_file = 'profile.php';

if ( current_user_can('edit_users') && !is_user_admin() )
	$parent_file = 'users.php';
else
	$parent_file = 'profile.php';

$profile_help = '<p>Your profile contains information about you (your &#8220;account&#8221;) as well as some personal options related to using WordPress.</p>' .
	'<p>You can change your password, turn on keyboard shortcuts, change the color scheme of your WordPress administration screens, and turn off the WYSIWYG (Visual) editor, among other things. You can hide the Toolbar (formerly called the Admin Bar) from the front end of your site, however it cannot be disabled on the admin screens.</p>' .
	'<p>Your username cannot be changed, but you can use other fields to enter your real name or a nickname, and change which name to display on your posts.</p>' .
	'<p>You can log out of other devices, such as your phone or a public computer, by clicking the Log Out Everywhere Else button.</p>' .
	'<p>Required fields are indicated; the rest are optional. Profile information will only be displayed if your theme is set up to do so.</p>' .
	'<p>Remember to click the Update Profile button when you are finished.</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => 'Overview',
	'content' => $profile_help,
) );

$wp_http_referer = remove_query_arg( array( 'update', 'delete_count', 'user_id' ), $wp_http_referer );

$user_can_edit = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );

switch ($action) {
case 'update':

check_admin_referer('update-user_' . $user_id);

if ( !current_user_can('edit_user', $user_id) )
	wp_die('You do not have permission to edit this user.');

if ( IS_PROFILE_PAGE ) {
	do_action( 'personal_options_update', $user_id );
} else {
	do_action( 'edit_user_profile_update', $user_id );
}

$errors = edit_user( $user_id );

if ( !is_wp_error( $errors ) ) {
	$redirect = add_query_arg( 'updated', true, get_edit_user_link( $user_id ) );
	if ( $wp_http_referer )
		$redirect = add_query_arg('wp_http_referer', urlencode($wp_http_referer), $redirect);
	wp_redirect($redirect);
	exit;
}

default:
$profileuser = get_user_to_edit($user_id);

if ( !current_user_can('edit_user', $user_id) )
	wp_die('You do not have permission to edit this user.');

$sessions = WP_Session_Tokens::get_instance( $profileuser->ID );

include(ABSPATH . 'wp-admin/admin-header.php');
?>

<?php if ( !IS_PROFILE_PAGE && is_super_admin( $profileuser->ID ) && current_user_can( 'manage_network_options' ) ) { ?>
	<div class="updated"><p><strong>Important:</strong> This user has super admin privileges.</p></div>
<?php } ?>
<?php if ( isset($_GET['updated']) ) : ?>
<div id="message" class="updated notice is-dismissible">
	<?php if ( IS_PROFILE_PAGE ) : ?>
	<p><strong>Profile updated.</strong></p>
	<?php else: ?>
	<p><strong>User updated.</strong></p>
	<?php endif; ?>
	<?php if ( $wp_http_referer && false === strpos( $wp_http_referer, 'user-new.php' ) && ! IS_PROFILE_PAGE ) : ?>
	<p><a href="<?php echo esc_url( $wp_http_referer ); ?>">&larr; Back to Users</a></p>
	<?php endif; ?>
</div>
<?php endif; ?>
<?php if ( isset( $_GET['error'] ) ) : ?>
<div class="notice notice-error">
	<?php if ( 'new-email' == $_GET['error'] ) : ?>
	<p>Error while saving the new email address. Please try again.</p>
	<?php endif; ?>
</div>
<?php endif; ?>
<?php if ( isset( $errors ) && is_wp_error( $errors ) ) : ?>
<div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
<?php endif; ?>

<div class="wrap" id="profile-page">
<h1>
<?php
echo esc_html( $title );
if ( ! IS_PROFILE_PAGE ) {
	if ( current_user_can( 'create_users' ) ) { ?>
		<a href="user-new.php" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user' ); ?></a>
	<?php } elseif ( is_multisite() && current_user_can( 'promote_users' ) ) { ?>
		<a href="user-new.php" class="page-title-action"><?php echo esc_html_x( 'Add Existing', 'user' ); ?></a>
	<?php }
} ?>
</h1>
<form id="your-profile" action="<?php echo esc_url( self_admin_url( IS_PROFILE_PAGE ? 'profile.php' : 'user-edit.php' ) ); ?>" method="post" novalidate="novalidate"<?php
	do_action( 'user_edit_form_tag' );
?>>
<?php wp_nonce_field('update-user_' . $user_id) ?>
<?php if ( $wp_http_referer ) : ?>
	<input type="hidden" name="wp_http_referer" value="<?php echo esc_url($wp_http_referer); ?>" />
<?php endif; ?>
<p>
<input type="hidden" name="from" value="profile" />
<input type="hidden" name="checkuser_id" value="<?php echo get_current_user_id(); ?>" />
</p>

<h2>Personal Options</h2>

<table class="form-table">
<?php if ( ! ( IS_PROFILE_PAGE && ! $user_can_edit ) ) : ?>
	<tr class="user-rich-editing-wrap">
		<th scope="row"><?php _e( 'Visual Editor' ); ?></th>
		<td><label for="rich_editing"><input name="rich_editing" type="checkbox" id="rich_editing" value="false" <?php if ( ! empty( $profileuser->rich_editing ) ) checked( 'false', $profileuser->rich_editing ); ?> /> <?php _e( 'Disable the visual editor when writing' ); ?></label></td>
	</tr>
<?php endif; ?>
<?php if ( count($_wp_admin_css_colors) > 1 && has_action('admin_color_scheme_picker') ) : ?>
<tr class="user-admin-color-wrap">
<th scope="row">Admin Color Scheme</th>
<td><?php do_action( 'admin_color_scheme_picker', $user_id ); ?></td>
</tr>
<?php
endif;
if ( !( IS_PROFILE_PAGE && !$user_can_edit ) ) : ?>
<tr class="user-comment-shortcuts-wrap">
<th scope="row">Keyboard Shortcuts</th>
<td><label for="comment_shortcuts"><input type="checkbox" name="comment_shortcuts" id="comment_shortcuts" value="true" <?php if ( ! empty( $profileuser->comment_shortcuts ) ) checked( 'true', $profileuser->comment_shortcuts ); ?> /> <?php _e('Enable keyboard shortcuts for comment moderation.'); ?></label> <?php _e('<a href="https://codex.wordpress.org/Keyboard_Shortcuts" target="_blank">More information</a>'); ?></td>
</tr>
<?php endif; ?>
<tr class="show-admin-bar user-admin-bar-front-wrap">
<th scope="row">Toolbar</th>
<td><fieldset><legend class="screen-reader-text"><span>Toolbar</span></legend>
<label for="admin_bar_front">
<input name="admin_bar_front" type="checkbox" id="admin_bar_front" value="1"<?php checked( _get_admin_bar_pref( 'front', $profileuser->ID ) ); ?> />
<?php _e( 'Show Toolbar when viewing site' ); ?></label><br />
</fieldset>
</td>
</tr>
<?php
do_action( 'personal_options', $profileuser );
?>

</table>
<?php
	if ( IS_PROFILE_PAGE ) {
		do_action( 'profile_personal_options', $profileuser );
	}
?>

<h2>Name</h2>
<table class="form-table">
	<tr class="user-user-login-wrap">
		<th><label for="user_login">Username</label></th>
		<td><input type="text" name="user_login" id="user_login" value="<?php echo esc_attr($profileuser->user_login); ?>" disabled="disabled" class="regular-text" /> <span class="description"><?php _e('Usernames cannot be changed.'); ?></span></td>
	</tr>

<?php if ( !IS_PROFILE_PAGE && !is_network_admin() ) : ?>
<tr class="user-role-wrap"><th><label for="role">Role</label></th>
<td><select name="role" id="role">
<?php
$user_roles = array_intersect( array_values( $profileuser->roles ), array_keys( get_editable_roles() ) );
$user_role  = reset( $user_roles );

wp_dropdown_roles($user_role);

if ( $user_role )
	echo '<option value="">&mdash; No role for this site &mdash;</option>';
else
	echo '<option value="" selected="selected">&mdash; No role for this site &mdash;</option>';
?>
</select></td></tr>
<?php endif; //!IS_PROFILE_PAGE

if ( is_multisite() && is_network_admin() && ! IS_PROFILE_PAGE && current_user_can( 'manage_network_options' ) && !isset($super_admins) ) { ?>
<tr class="user-super-admin-wrap"><th><?php _e('Super Admin'); ?></th>
<td>
<?php if ( $profileuser->user_email != get_site_option( 'admin_email' ) || ! is_super_admin( $profileuser->ID ) ) : ?>
<p><label><input type="checkbox" id="super_admin" name="super_admin"<?php checked( is_super_admin( $profileuser->ID ) ); ?> /> Grant this user super admin privileges for the Network.</label></p>
<?php else : ?>
<p>Super admin privileges cannot be removed because this user has the network admin email.</p>
<?php endif; ?>
</td></tr>
<?php } ?>

<tr class="user-first-name-wrap">
	<th><label for="first_name">First Name</label></th>
	<td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($profileuser->first_name) ?>" class="regular-text" /></td>
</tr>

<tr class="user-last-name-wrap">
	<th><label for="last_name">Last Name</label></th>
	<td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($profileuser->last_name) ?>" class="regular-text" /></td>
</tr>

<tr class="user-nickname-wrap">
	<th><label for="nickname">Nickname <span class="description">(required)</span></label></th>
	<td><input type="text" name="nickname" id="nickname" value="<?php echo esc_attr($profileuser->nickname) ?>" class="regular-text" /></td>
</tr>

<tr class="user-display-name-wrap">
	<th><label for="display_name">Display name publicly as</label></th>
	<td>
		<select name="display_name" id="display_name">
		<?php
			$public_display = array();
			$public_display['display_nickname']  = $profileuser->nickname;
			$public_display['display_username']  = $profileuser->user_login;

			if ( !empty($profileuser->first_name) )
				$public_display['display_firstname'] = $profileuser->first_name;

			if ( !empty($profileuser->last_name) )
				$public_display['display_lastname'] = $profileuser->last_name;

			if ( !empty($profileuser->first_name) && !empty($profileuser->last_name) ) {
				$public_display['display_firstlast'] = $profileuser->first_name . ' ' . $profileuser->last_name;
				$public_display['display_lastfirst'] = $profileuser->last_name . ' ' . $profileuser->first_name;
			}

			if ( !in_array( $profileuser->display_name, $public_display ) )
				$public_display = array( 'display_displayname' => $profileuser->display_name ) + $public_display;

			$public_display = array_map( 'trim', $public_display );
			$public_display = array_unique( $public_display );

			foreach ( $public_display as $id => $item ) {
		?>
			<option <?php selected( $profileuser->display_name, $item ); ?>><?php echo $item; ?></option>
		<?php
			}
		?>
		</select>
	</td>
</tr>
</table>

<h2>Contact Info</h2>

<table class="form-table">
<tr class="user-email-wrap">
	<th><label for="email">Email <span class="description">(required)</span></label></th>
	<td><input type="email" name="email" id="email" value="<?php echo esc_attr( $profileuser->user_email ) ?>" class="regular-text ltr" />
	<?php
	$new_email = get_user_meta( $current_user->ID, '_new_email', true );
	if ( $new_email && $new_email['newemail'] != $current_user->user_email && $profileuser->ID == $current_user->ID ) : ?>
	<div class="updated inline">
	<p><?php
		printf(
			'There is a pending change of your email to %s.',
			'<code>' . esc_html( $new_email['newemail'] ) . '</code>'
		);
		printf(
			' <a href="%1$s">%2$s</a>',
			esc_url( wp_nonce_url( self_admin_url( 'profile.php?dismiss=' . $current_user->ID . '_new_email' ), 'dismiss-' . $current_user->ID . '_new_email' ) ),
			'Cancel'
		);
	?></p>
	</div>
	<?php endif; ?>
	</td>
</tr>

<tr class="user-url-wrap">
	<th><label for="url">Website</label></th>
	<td><input type="url" name="url" id="url" value="<?php echo esc_attr( $profileuser->user_url ) ?>" class="regular-text code" /></td>
</tr>

<?php
	foreach ( wp_get_user_contact_methods( $profileuser ) as $name => $desc ) {
?>
<tr class="user-<?php echo $name; ?>-wrap">
	<th><label for="<?php echo $name; ?>">
		<?php
		echo apply_filters( "user_{$name}_label", $desc );
		?>
	</label></th>
	<td><input type="text" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="<?php echo esc_attr($profileuser->$name) ?>" class="regular-text" /></td>
</tr>
<?php
	}
?>
</table>

<h2><?php IS_PROFILE_PAGE ? 'About Yourself' : 'About the user'; ?></h2>

<table class="form-table">
<tr class="user-description-wrap">
	<th><label for="description">Biographical Info</label></th>
	<td><textarea name="description" id="description" rows="5" cols="30"><?php echo $profileuser->description; // textarea_escaped ?></textarea>
	<p class="description"><?php _e('Share a little biographical information to fill out your profile. This may be shown publicly.'); ?></p></td>
</tr>

<?php if ( get_option( 'show_avatars' ) ) : ?>
<tr class="user-profile-picture">
	<th>Profile Picture</th>
	<td>
		<?php echo get_avatar( $user_id ); ?>
		<p class="description"><?php
			if ( IS_PROFILE_PAGE ) {
				$description = sprintf( 'You can change your profile picture on <a href="%s">Gravatar</a>.',
					__( 'https://en.gravatar.com/' )
				);
			} else {
				$description = '';
			}
			echo apply_filters( 'user_profile_picture_description', $description );
		?></p>
	</td>
</tr>
<?php endif; ?>

<?php
if ( $show_password_fields = apply_filters( 'show_password_fields', true, $profileuser ) ) :
?>
</table>

<h2>Account Management</h2>
<table class="form-table">
<tr id="password" class="user-pass1-wrap">
	<th><label for="pass1">New Password</label></th>
	<td>
		<input class="hidden" value=" " />
		<button type="button" class="button button-secondary wp-generate-pw hide-if-no-js">Generate Password</button>
		<div class="wp-pwd hide-if-js">
			<span class="password-input-wrapper">
				<input type="password" name="pass1" id="pass1" class="regular-text" value="" autocomplete="off" data-pw="123456" aria-describedby="pass-strength-result" />
			</span>
			<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Hide password">
				<span class="dashicons dashicons-hidden"></span>
				<span class="text">Hide</span>
			</button>
			<button type="button" class="button button-secondary wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="Cancel password change">
				<span class="text">Cancel</span>
			</button>
			<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
		</div>
	</td>
</tr>
<tr class="user-pass2-wrap hide-if-js">
	<th scope="row"><label for="pass2">Repeat New Password</label></th>
	<td>
	<input name="pass2" type="password" id="pass2" class="regular-text" value="" autocomplete="off" />
	<p class="description">Type your new password again.</p>
	</td>
</tr>
<tr class="pw-weak">
	<th>Confirm Password</th>
	<td>
		<label>
			<input type="checkbox" name="pw_weak" class="pw-checkbox" /> Confirm use of weak password</label>
	</td>
</tr>
<?php endif; ?>

<?php
if ( IS_PROFILE_PAGE && count( $sessions->get_all() ) === 1 ) : ?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th><?php _e( 'Sessions' ); ?></th>
		<td aria-live="assertive">
			<div class="destroy-sessions"><button type="button" disabled class="button button-secondary">Log Out Everywhere Else</button></div>
			<p class="description">You are only logged in at this location.</p>
		</td>
	</tr>
<?php elseif ( IS_PROFILE_PAGE && count( $sessions->get_all() ) > 1 ) : ?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th>Sessions</th>
		<td aria-live="assertive">
			<div class="destroy-sessions"><button type="button" class="button button-secondary" id="destroy-sessions">Log Out Everywhere Else</button></div>
			<p class="description">
				<?php _e( 'Did you lose your phone or leave your account logged in at a public computer? You can log out everywhere else, and stay logged in here.' ); ?>
			</p>
		</td>
	</tr>
<?php elseif ( ! IS_PROFILE_PAGE && $sessions->get_all() ) : ?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th>Sessions</th>
		<td>
			<p><button type="button" class="button button-secondary" id="destroy-sessions"><?php _e( 'Log Out Everywhere' ); ?></button></p>
			<p class="description">
				<?php
				printf( 'Log %s out of all locations.', $profileuser->display_name );
				?>
			</p>
		</td>
	</tr>
<?php endif; ?>

</table>

<?php
	if ( IS_PROFILE_PAGE ) {
		do_action( 'show_user_profile', $profileuser );
	} else {
		do_action( 'edit_user_profile', $profileuser );
	}
?>

<?php
if ( count( $profileuser->caps ) > count( $profileuser->roles )
	&& apply_filters( 'additional_capabilities_display', true, $profileuser )
) : ?>
<h2><?php _e( 'Additional Capabilities' ); ?></h2>
<table class="form-table">
<tr class="user-capabilities-wrap">
	<th scope="row"><?php _e( 'Capabilities' ); ?></th>
	<td>
<?php
	$output = '';
	foreach ( $profileuser->caps as $cap => $value ) {
		if ( ! $wp_roles->is_role( $cap ) ) {
			if ( '' != $output )
				$output .= ', ';
			$output .= $value ? $cap : sprintf( 'Denied: %s', $cap );
		}
	}
	echo $output;
?>
	</td>
</tr>
</table>
<?php endif; ?>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="user_id" id="user_id" value="<?php echo esc_attr($user_id); ?>" />

<?php submit_button( IS_PROFILE_PAGE ? 'Update Profile' : 'Update User' ); ?>

</form>
</div>
<?php
break;
}
?>
<script type="text/javascript">
	if (window.location.hash == '#password') {
		document.getElementById('pass1').focus();
	}
</script>
<?php
include( ABSPATH . 'wp-admin/admin-footer.php');

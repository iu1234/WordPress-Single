<?php
/**
 * WordPress Installer
 *
 * @package WordPress
 * @subpackage Administration
 */

error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );

require( __DIR__ '/db.php' );
nocache_headers();

require_wp_db();
wp_set_wpdb_vars();

nocache_headers();

$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 0;

function display_header( $body_classes = '' ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	if ( $body_classes ) {
		$body_classes = ' ' . $body_classes;
	}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
<head>
	<meta name="viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nofollow" />
	<title>WordPress &rsaquo; 安装</title>
	<link rel="stylesheet" href="install.css" type="text/css" />
	<link rel="stylesheet" href="dashicons.css" type="text/css" />
</head>
<body class="wp-core-ui<?php echo $body_classes ?>">
<?php
}
function display_setup_form( $error = null ) {
	global $wpdb;
	$sql = $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->users ) );
	$user_table = ( $wpdb->get_var( $sql ) != null );
	$blog_public = 1;
	if ( isset( $_POST['weblog_title'] ) ) {
		$blog_public = isset( $_POST['blog_public'] );
	}
	$weblog_title = isset( $_POST['weblog_title'] ) ? trim( wp_unslash( $_POST['weblog_title'] ) ) : '';
	$user_name = isset($_POST['user_name']) ? trim( wp_unslash( $_POST['user_name'] ) ) : '';
	$admin_email  = isset( $_POST['admin_email']  ) ? trim( wp_unslash( $_POST['admin_email'] ) ) : '';

	if ( ! is_null( $error ) ) {
?>
<h1>欢迎</h1>
<p class="message"><?php echo $error; ?></p>
<?php } ?>
<form id="setup" method="post" action="install.php?step=2" novalidate="novalidate">
	<table class="form-table">
		<tr>
			<th scope="row"><label for="weblog_title">站点标题</label></th>
			<td><input name="weblog_title" type="text" id="weblog_title" size="25" value="<?php echo esc_attr( $weblog_title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="user_login">用户名</label></th>
			<td>
			<?php
			if ( $user_table ) {
				echo 'User(s) already exists.';
				echo '<input name="user_name" type="hidden" value="admin" />';
			} else {
				?><input name="user_name" type="text" id="user_login" size="25" value="<?php echo esc_attr( sanitize_user( $user_name, true ) ); ?>" />
				<p>Usernames can have only alphanumeric characters, spaces, underscores, hyphens, periods, and the @ symbol.</p>
			<?php
			} ?>
			</td>
		</tr>
		<?php if ( ! $user_table ) : ?>
		<tr class="form-field form-required user-pass1-wrap">
			<th scope="row"><label for="pass1">密码</label></th>
			<td>
				<div class="">
					<?php $initial_password = isset( $_POST['admin_password'] ) ? stripslashes( $_POST['admin_password'] ) : '123456'; ?>
					<input type="password" name="admin_password" id="pass1" class="regular-text" autocomplete="off" data-reveal="1" data-pw="<?php echo esc_attr( $initial_password ); ?>" aria-describedby="pass-strength-result" />
					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-start-masked="<?php echo (int) isset( $_POST['admin_password'] ); ?>" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
						<span class="dashicons dashicons-hidden"></span>
						<span class="text">Hide</span>
					</button>
					<div id="pass-strength-result" aria-live="polite"></div>
				</div>
				<p><span class="description important hide-if-no-js">
				<strong>Important:</strong>
				You will need this password to log&nbsp;in. Please store it in a secure location.</span></p>
			</td>
		</tr>
		<tr class="form-field form-required user-pass2-wrap hide-if-js">
			<th scope="row">
				<label for="pass2">重复密码<span class="description">(必填)</span>
				</label>
			</th>
			<td>
				<input name="admin_password2" type="password" id="pass2" autocomplete="off" />
			</td>
		</tr>
		<tr class="pw-weak">
			<th scope="row">确认密码</th>
			<td>
				<label><input type="checkbox" name="pw_weak" class="pw-checkbox" /> 确认使用弱密码</label>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><label for="admin_email">您的电子邮件</label></th>
			<td><input name="admin_email" type="email" id="admin_email" size="25" value="<?php echo esc_attr( $admin_email ); ?>" />
			<p>请仔细检查电子邮件地址后再继续。</p></td>
		</tr>
		<tr>
			<th scope="row">Search Engine Visibility</th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span>Search Engine Visibility </span></legend>
					<label for="blog_public"><input name="blog_public" type="checkbox" id="blog_public" value="0" <?php checked( 0, $blog_public ); ?> />Discourage search engines from indexing this site</label>
					<p class="description">It is up to search engines to honor this request.</p>
				</fieldset>
			</td>
		</tr>
	</table>
	<p class="step"><?php submit_button( 'Install WordPress', 'large', 'Submit', false, array( 'id' => 'submit' ) ); ?></p>
	<input type="hidden" name="language" value="<?php echo isset( $_REQUEST['language'] ) ? esc_attr( $_REQUEST['language'] ) : ''; ?>" />
</form>
<?php
}

if ( is_blog_installed() ) {
	display_header();
	die(
		'<h1>已安装过</h1>' .
		'<p>您的WordPress看起来已经安装妥当。如果想重新安装，请删除数据库中的旧数据表。</p>' .
		'<p class="step"><a href="' . esc_url( wp_login_url() ) . '" class="button button-large">登录</a></p>' .
		'</body></html>'
	);
}

$scripts_to_print = array( 'jquery' );

switch($step) {
	case 1:
		$scripts_to_print[] = 'user-profile';
		display_header();
?>
<h1>需要信息</h1>
<p>您需要填写一些基本信息。无需担心填错，这些信息以后可以再次修改。</p>
<?php
		display_setup_form();
		break;
	case 2:
		if ( ! empty( $wpdb->error ) )
			wp_die( $wpdb->error->get_error_message() );
		$scripts_to_print[] = 'user-profile';
		display_header();
		$weblog_title = isset( $_POST['weblog_title'] ) ? trim( wp_unslash( $_POST['weblog_title'] ) ) : '';
		$user_name = isset($_POST['user_name']) ? trim( wp_unslash( $_POST['user_name'] ) ) : '';
		$admin_password = isset($_POST['admin_password']) ? wp_unslash( $_POST['admin_password'] ) : '';
		$admin_password_check = isset($_POST['admin_password2']) ? wp_unslash( $_POST['admin_password2'] ) : '';
		$admin_email  = isset( $_POST['admin_email'] ) ?trim( wp_unslash( $_POST['admin_email'] ) ) : '';

		$error = false;
		if ( empty( $user_name ) ) {
			display_setup_form( 'Please provide a valid username.' );
			$error = true;
		} elseif ( $user_name != sanitize_user( $user_name, true ) ) {
			display_setup_form( 'The username you provided has invalid characters.' );
			$error = true;
		} elseif ( $admin_password != $admin_password_check ) {
			display_setup_form( 'Your passwords do not match. Please try again.' );
			$error = true;
		} elseif ( empty( $admin_email ) ) {
			display_setup_form( 'You must provide an email address.' );
			$error = true;
		} elseif ( ! is_email( $admin_email ) ) {
			display_setup_form( 'Sorry, that isn&#8217;t a valid email address. Email addresses look like <code>username@example.com</code>.' );
			$error = true;
		}

		if ( $error === false ) {
			$wpdb->show_errors();
			$result = wp_install( $weblog_title, $user_name, $admin_email, wp_slash( $admin_password ), $loaded_language );
?>

<h1>Success!</h1>

<p>WordPress has been installed. Thank you, and enjoy!</p>

<table class="form-table install-success">
	<tr>
		<th>用户名</th>
		<td><?php echo esc_html( sanitize_user( $user_name, true ) ); ?></td>
	</tr>
	<tr>
		<th>密码</th>
		<td><?php
		if ( ! empty( $result['password'] ) && empty( $admin_password_check ) ): ?>
			<code><?php echo esc_html( $result['password'] ) ?></code><br />
		<?php endif ?>
			<p><?php echo $result['password_message'] ?></p>
		</td>
	</tr>
</table>

<p class="step"><a href="<?php echo esc_url( wp_login_url() ); ?>" class="button button-large">登录</a></p>

<?php
		}
		break;
}

if ( ! wp_is_mobile() ) {
	?>
<script type="text/javascript">var t = document.getElementById('weblog_title'); if (t){ t.focus(); }</script>
	<?php
}

wp_print_scripts( $scripts_to_print );
?>
<script type="text/javascript">
jQuery( function( $ ) {
	$( '.hide-if-no-js' ).removeClass( 'hide-if-no-js' );
} );
</script>
</body>
</html>

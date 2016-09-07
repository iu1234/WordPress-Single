<?php
/**
 * Dashboard Administration Screen
 *
 * @package WordPress
 * @subpackage Administration
 */

require_once( __DIR__ . '/admin.php' );

require_once(ABSPATH . 'wp-admin/includes/dashboard.php');

wp_dashboard_setup();

wp_enqueue_script( 'dashboard' );
if ( current_user_can( 'edit_theme_options' ) )
	wp_enqueue_script( 'customize-loader' );
if ( current_user_can( 'install_plugins' ) )
	wp_enqueue_script( 'plugin-install' );
if ( current_user_can( 'upload_files' ) )
	wp_enqueue_script( 'media-upload' );
add_thickbox();

if ( wp_is_mobile() )
	wp_enqueue_script( 'jquery-touch-punch' );

$title = 'Dashboard';
$parent_file = 'index.php';

$help = '<p>欢迎访问仪表盘！在您每次登录站点后，您都会看到本页面。您可以在这里访问各种管理页面。点击任何页面右上角的“帮助”选项卡可阅读相应帮助信息。</p>';

// Not using chaining here, so as to be parseable by PHP4.
$screen = get_current_screen();

$screen->add_help_tab( array(
	'id'      => 'overview',
	'title'   => 'Overview',
	'content' => $help,
) );

$help  = '<p>左侧的导航菜单提供了所有管理页面的链接。将鼠标移至菜单项目上，子菜单将显示出来。您可以使用最下方的“收起菜单”箭头来收起菜单，菜单项将以小图标的形式显示。</p>';
$help .= '<p>上方“工具栏”上的链接将仪表盘和站点前台连接起来，默认在站点的所有页面显示，提供您的个人资料信息以及相关的信息。</p>';

$screen->add_help_tab( array(
	'id'      => 'help-navigation',
	'title'   => 'Navigation',
	'content' => $help,
) );

$help  = '<p>您可以依您的喜好和工作方式来安排仪表盘页面的布局。大部分其它管理页面也支持重新排列功能。</p>';
$help .= '<p><strong>显示选项</strong> &mdash; 使用“显示选项”选项卡来选择要显示的仪表模块。</p>';
$help .= '<p><strong>拖放自如</strong> &mdash; 要重新排列模块，按住模块的标题栏，将其拖到您希望的位置，在灰色虚线框出现后松开鼠标即可调整模块的位置。</p>';
$help .= '<p><strong>管理模块</strong> &mdash; 点击模块的标题栏即可展开或收起它。另外，有些模块提供额外的配置选项，在您将鼠标移动到这些模块的标题栏上方时，会出现 &#8220;配置&#8221; 链接。</p>';

$screen->add_help_tab( array(
	'id'      => 'help-layout',
	'title'   => 'Layout',
	'content' => $help,
) );

$help  = '<p>仪表盘中的模块有：</p>';
if ( current_user_can( 'edit_posts' ) )
	$help .= '<p><strong>概况</strong> &mdash; 显示您站点上的内容概况。</p>';
	$help .= '<p><strong>活动</strong> &mdash; 显示即将发布和最近发布的文章，近期的若干条评论，并进行审核。</p>';
if ( is_blog_admin() && current_user_can( 'edit_posts' ) )
	$help .= '<p><strong>快速草稿</strong> &mdash; 让您创建新文章并保存为草稿，并显示5个指向最近草稿的链接。</p>';
if ( current_user_can( 'edit_theme_options' ) )
	$help .= '<p><strong>欢迎</strong> &mdash; 显示配置新站点的实用功能。</p>';

$screen->add_help_tab( array(
	'id'      => 'help-content',
	'title'   => 'Content',
	'content' => $help,
) );

unset( $help );

include( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>
	<div id="dashboard-widgets-wrap">
	<?php wp_dashboard(); ?>
	</div>
</div>

<?php
require( ABSPATH . 'wp-admin/admin-footer.php' );

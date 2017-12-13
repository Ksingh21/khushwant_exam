<?php
/*
Plugin Name: Comment Control
Plugin URI: http://rayofsolaris.net/code/comment-control-for-wordpress
Description: Gives administrators some more flexible options for controlling comments - e.g., independently setting the default comment status for Posts and Pages
Version: 0.4.1
Author: Samir Shah
Author URI: http://rayofsolaris.net/
License: GPL2
*/

if( !defined( 'ABSPATH' ) )
	exit;

class Comment_control {
	const db_version = 3;
	private $options;
	
	function __construct() {
		// load options
		$this->options = get_option( 'comment_control_options', array() );
		
		if( !isset( $this->options['db_version'] ) || $this->options['db_version'] < self::db_version ) {
			$defaults = array( 'allow_comments_on' => array(), 'allow_pings_on' => array(), 'no_tb' => false, 'attachments_inherit' => false, 'attachments_nocomment' => false );
			
			foreach( $defaults as $k => $v )
				if( !isset( $this->options[$k] ) )
					$this->options[$k] = $v;
			
			$this->options['db_version'] = self::db_version;
			update_option( 'comment_control_options', $this->options );
		}
		
		if( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'settings_menu' ) );
			add_action( 'add_meta_boxes', array( $this, 'set_default_comment_status' ), 10, 2 );
			add_action( 'admin_print_footer_scripts', array( $this, 'discussion_notice' ) );
			
			if( defined( 'DOING_AJAX' ) && DOING_AJAX )
				add_action( 'wp_ajax_comment_control_bulk_edit', array( $this, 'ajax_handler' ) );
		}
		
		if( $this->options['no_tb'] )
			add_filter( 'pings_open', array( $this, 'no_trackback' ) );
			
		if( $this->options['attachments_inherit'] || $this->options['attachments_nocomment'] )
			add_filter( 'comments_open', array( $this, 'attachment_comment_status' ), 10, 2 );
	}
	
	function discussion_notice(){
		if( get_current_screen()->id == 'options-discussion' && !empty( $this->options['allow_comments_on'] ) ) {
?>
<script>
jQuery(document).ready(function($){
	$("#default_ping_status, #default_comment_status").parent().after( "<br><span style='color: #900'>NOTE: This option is currently being overridden by the <em>Comment Control</em> plugin.</span>" );
});
</script>
<?php
		}
	}
	
	function set_default_comment_status( $post_type, $post ){
		$screen = get_current_screen();
		// only mess with new posts that support comments
		if( ! isset( $this->options['allow_comments_on'][$post_type] ) || $screen->action != 'add' || $screen->base != 'post' || ! post_type_supports( $post_type, 'comments' ) )
			return;

		$post->comment_status = $this->options['allow_comments_on'][$screen->id] ? 'open' : 'closed';
		$post->ping_status = $this->options['allow_pings_on'][$screen->id] ? 'open' : 'closed';
	}
	
	function no_trackback( $pings_open ) {
		return ( get_query_var( 'tb' ) == 1 ) ? false : $pings_open;
	}
	
	function attachment_comment_status( $status, $post_id ) {
		$post = get_post( $post_id );
		if( $post->post_type == 'attachment' ) {
			if ( $this->options['attachments_nocomment'] )
				return false;
			elseif( $this->options['attachments_inherit'] && $post->post_parent )
				return comments_open( $post->post_parent );
		}
		return $status;
	}
	
	function settings_menu() {
		add_submenu_page('options-general.php', 'Comment Control', 'Comment Control', 'manage_options', 'comment_control_settings', array( $this, 'settings_page' ) );
	}
	
	function settings_page() {
		global $wpdb;
		$types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
		foreach( array_keys( $types ) as $type ) {
			if( ! post_type_supports( $type, 'comments' ) )
				unset( $types[$type] );
		}
		
		if ( isset( $_POST['submit'] ) ) {
			check_admin_referer( 'comment_control_settings' );
			$allow_comments = empty( $_POST['allow_comments_on'] ) ? array() : (array) $_POST['allow_comments_on'];
			$allow_pings = empty( $_POST['allow_pings_on'] ) ? array() : (array) $_POST['allow_pings_on'];
			
			foreach( array_keys( $types ) as $type ) {
				$this->options['allow_comments_on'][$type] = in_array( $type, $allow_comments );	
				$this->options['allow_pings_on'][$type] = in_array( $type, $allow_pings );
			}

			$this->options['no_tb'] = isset( $_POST['no_tb'] ); 
			$this->options['attachments_inherit'] = isset( $_POST['attachments_inherit'] );
			$this->options['attachments_nocomment'] = isset( $_POST['attachments_nocomment'] ) && !isset( $_POST['attachments_inherit'] );	// can't check both

			update_option( 'comment_control_options', $this->options );
			echo '<div id="message" class="updated fade"><p>Options updated.</p></div>';
		}	
	?>
	<style> 
	.indent {padding-left: 2em} 
	.comment_control_table {border-collapse: collapse}
	.comment_control_table tr {border-bottom: 1px solid black}
	.comment_control_table td {padding: 5px 2em 5px}
	#comment-control-bulk td {padding-bottom: 5px}
	#comment_control_ajax_response p {border: 1px solid #E6DB55; border-radius: 10px; padding: 10px; background: #FFFFE0}
	</style>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Comment Control</h2>
	<form action="" method="post" id="comment-control">
	<h3>Default Comment Status</h3>
	<p>You can independently configure the default comment status for each post type:</p>
	<table class="comment_control_table">
		<?php 
		foreach( $types as $k => $v ) {
			$cs = isset( $this->options['allow_comments_on'][$k] ) ? $this->options['allow_comments_on'][$k] : ( 'open' == get_option( 'default_comment_status' ) );
			$ps = isset( $this->options['allow_pings_on'][$k] ) ? $this->options['allow_pings_on'][$k] : ( 'open' == get_option( 'default_ping_status' ) );
			
			echo "<tr><td><label for='allow-comments-$k'><input type='checkbox' name='allow_comments_on[]' value='$k' ". checked( $cs, true, false ) ." id='allow-comments-$k'> Allow people to comment on new {$v->labels->name}</label></td><td><label for='allow-pings-$k'><input type='checkbox' name='allow_pings_on[]' value='$k' ". checked( $ps, true, false ) ." id='allow-pings-$k'> Allow link notifications (pingbacks) on new {$v->labels->name}</label></td></tr>";
		}
		?>
	</table>
	<p style="color: #900">Note: these settings will override the global defaults set in <samp>Settings -> Discussion</samp>.</p>
	<h3>Other options</h3>
	<ul class="indent">
		<li><label for="no_tb"><input type="checkbox" name="no_tb" id="no_tb" <?php checked( $this->options['no_tb'] );?>> Disable trackbacks</label></li>
		<li><label for="attachments_inherit"><input type="checkbox" class="att_exclusive" name="attachments_inherit" id="attachments_inherit" <?php checked( $this->options['attachments_inherit'] );?>> Force attachments to always inherit their comment status from their parent post (if comments are closed on a post, then comments will be closed on its attachments).</label>
		<br/><strong>or</strong><br/>
		<label for="attachments_nocomment"><input type="checkbox" class="att_exclusive" name="attachments_nocomment" id="attachments_nocomment" <?php checked( $this->options['attachments_nocomment'] );?>> Keep comments closed on all attachments.</label>
		</li>
	</ul>
	<?php wp_nonce_field( 'comment_control_settings' );?>
	<p class="submit"><input class="button-primary" type="submit" name="submit" value="Update settings" /></p>
	</form>
	
	<div id="comment-tools" class="hide-if-no-js">
	<?php screen_icon(); ?>
	<h2>Tools</h2>
	<p>Use these tools to bulk edit the comment status on your posts. Use them carefully!</p>
	<?php wp_nonce_field( 'comment_control_bulk_edit', '_comment_control_ajax', false );?>
	<div id="comment_control_ajax_response" style="display:none"></div>
	<table class="comment_control_table" id="comment-control-bulk">
		<?php 
		foreach( $types as $k => $v ) {
			echo "<tr><th>{$v->labels->name}
			<td><p><a class='button-secondary' data-action='enable' data-type='$k' data-for='comments' data-name='{$v->labels->name}'>Turn <strong>on</strong> comments on all published {$v->labels->name}</a></p>
			<p><a class='button-secondary' data-action='disable' data-type='$k' data-for='comments' data-name='{$v->labels->name}'>Turn <strong>off</strong> comments on all published {$v->labels->name}</a></p>
			<td><p><a class='button-secondary' data-action='enable' data-type='$k' data-for='pings' data-name='{$v->labels->name}'>Turn <strong>on</strong> pingbacks on all published {$v->labels->name}</a></p>
			<p><a class='button-secondary' data-action='disable' data-type='$k' data-for='pings' data-name='{$v->labels->name}'>Turn <strong>off</strong> pingbacks on all published {$v->labels->name}</a></p>
			</tr>";
		}
		?>
	</table>
	</div>
	</div>
	<script>
	jQuery(function($){
		$("#comment-control :input").change(function(){
			$("#message").slideUp();
		});
		$("#comment-control-bulk a").click( function(e){
			var i = $(this),
				resp = $("#comment_control_ajax_response"),
				c_action = i.data("action"),
				onoff =  c_action == "enable" ? "on" : "off",
				target = (i.data("for") === "pings" ? "pings" : "comments");

			var ok = confirm( "You are about to turn " + onoff + " " + target + " on all published " + i.data("name") + ".\n\nThis action is not reversible and could affect a large number of posts.\n\nAre you sure you want to proceed?");

			if( !ok ) {
				return;
			}
				
			var data = {
				action: "comment_control_bulk_edit",
				_comment_control_ajax: $("#_comment_control_ajax").val(),
				type: i.data("type"),
				target: target,
				comment_action: c_action
			};
			
			resp.hide();
			$.post( ajaxurl, data, function(r){
				resp.html( r );
				resp.fadeIn();
			}, "html");
		});
		$(".att_exclusive").change( function(){
			if( $(this).is(":checked") ) {
				$(".att_exclusive").attr("checked", false);
				$(this).attr("checked", true);
			}
		});
	});
	</script>
<?php
	}
	
	function ajax_handler(){
		global $wpdb;
		check_ajax_referer( 'comment_control_bulk_edit', '_comment_control_ajax' );
		$status = ( $_POST['comment_action'] == 'enable' ) ? 'open' : 'closed';
		$onoff = ( $status == 'open' ) ? 'on' : 'off';
		$target = ( $_POST['target'] == 'pings' ) ? 'ping_status' : 'comment_status';
		$type = $_POST['type'];
		
		if( is_null( $post_type = get_post_type_object( $type ) ) ) {
			$res = false;
		}
		else {
			$name = $post_type->labels->name;	
			$res = $wpdb->update( 
				$wpdb->posts,
				array( $target => $status ),
				array( 'post_type' => $type, 'post_status' => 'publish' )
			);
		}
		
		$affected = ( $target == 'ping_status' ? 'Pingbacks' : 'Comments' );
		if( $res === false ) {
			echo '<p>An error occurred when trying to process this request. Please try again.</p>';
		}
		else if( $res === 0 ) {
			echo "<p>$affected are already turned <strong>$onoff</strong> on all published $name.";	
		}
		else {	
			echo "<p>$affected have been turned <strong>$onoff</strong> on all published $name. $res $name were modified.";	
		}

		exit;
	}
}

new Comment_Control();

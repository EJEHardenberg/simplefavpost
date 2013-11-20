<?php
/**
 * @package simplefavpost
 * @version 0.1
 */
/*
Plugin Name: simplefavpost
Plugin URI: git@github.com:EJEHardenberg/simplefavpost.git
Description: A simple plugin that allows users to favorite a post.
Author: Ethan J. Eldridge
Version: 0.1
Author URI: http://ejehardenberg.github.io
*/




class SimpleFavPost{
	const tablename = 'user_fav_posts';
	public static function register_table() {
		global $wpdb;
	    $wpdb->simplefavposts = "{$wpdb->prefix}". self::tablename;
	}

	public static function install(){
		global $wpdb;
		global $charset_collate;
		$table_name = $wpdb->prefix .  self::tablename;
		$sql = "CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					user_id BIGINT(20) NOT NULL,
					post_id BIGINT(20) NOT NULL,
					PRIMARY KEY  (id),
					KEY post_id  (post_id),
					KEY user_id  (user_id)
				) $charset_collate; ";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		self::register_table();
	}

	public static function uninstall(){
		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS $wpdb->simplefavposts");
	}

	public static function get_num_favorites_for($post_id){
		global $wpdb;
		$sql = "SELECT SUM(1) as total FROM {$wpdb->simplefavposts} s WHERE post_id=%d";
		$post_simplefavposts = $wpdb->get_results($wpdb->prepare($sql,$post_id));
		return $post_simplefavposts;
	}

	public static function get_users_favorites($user_id, $limit=5){
		global $wpdb;
		$sql = "SELECT post_id,post_title FROM {$wpdb->simplefavposts} s JOIN {$wpdb->posts} p ON s.post_id=p.ID WHERE user_id=%d LIMIT %d";
		$post_simpleuserfavposts = $wpdb->get_results($wpdb->prepare($sql,$user_id, $limit));
		return $post_simpleuserfavposts;	
	}

	public static function get_popular($limit=5){
		global $wpdb;
		$sql = "SELECT count(1) as total, post_id, post_title FROM {$wpdb->simplefavposts} sfp JOIN {$wpdb->posts} p ON sfp.post_id=p.ID GROUP BY post_id LIMIT %d";
		return $wpdb->get_results($wpdb->prepare($sql, $limit));
	}

	public static function check_exists($user_id, $post_id){
		global $wpdb;
		$sql = "SELECT id FROM {$wpdb->simplefavposts} WHERE post_id=%d AND user_id=%d";
		return count($wpdb->get_results($wpdb->prepare($sql,$post_id, $user_id)));
	}

	public static function delete_simplefavposts($user_id, $post_id){
		global $wpdb;
		$deleted = $wpdb->delete(
	 					$wpdb->simplefavposts,
 						array('post_id'=>$post_id, 'user_id' => $post_id),
	 					array( '%d', '%d')
					);
		return $deleted;
	}

	public static function insert_simplefavpost( $user_id, $post_id){
		global $wpdb;
		$inserted = $wpdb->insert(
	 		$wpdb->simplefavposts,
     		array(
	    	'user_id' => $user_id,
			'post_id' => $post_id,
      		),
     		array(
	      		'%d','%d'
	     	)	
		);
		return $inserted;
	}
}

function simplefavpost_register_js_css() { 	
	wp_register_style( 'simplefavpost_css', plugin_dir_url(__FILE__) . 'simplefavpost.css');
	wp_register_script('simplefavpost_js',  plugin_dir_url(__FILE__) . 'simplefavpost.js', array( 'jquery' ), '1', true ); 
}
function simplefav_widget(){ 	register_widget('SimpleFavWidget'); register_widget('SimpleFavWidgetPopular'); register_widget('UserFavWidget'); }

function simplefavpost_ajax(){
	check_ajax_referer('simplefavpost_ajax_security_is_a_big_deal','security');
	$user_id = $_POST['user_id'];
	$post_id = $_POST['post_id'];
	$doit = true;
	$insertion_result = false;
	if($user_id != 0/*anon*/)
		$doit = SimpleFavPost::check_exists($user_id,$post_id) == 0;
	if($doit)
		$insertion_result = SimpleFavPost::insert_simplefavpost($user_id,$post_id);
	header( "Content-Type: application/json" );
	echo json_encode(array('success' => $insertion_result !== false)	);
	exit();
}

class SimpleFavWidget extends WP_Widget{
	public function __construct(){
		parent::__construct(
			'simple_fav_widget',
			__('Simple Post Fav','text_domain'),
			array(
				'title'=>'Simple Post Fav\'s',
				'classname' => 'post-fav',
				'description' => 'Widget to display a button to favorite a post, displays total as well'
			)
		);
	}

	public function widget($args, $instance){
		wp_enqueue_style('simplefavpost_css');
		wp_enqueue_script('simplefavpost_js');
		global $post;
		$u = get_current_user_id();
		$thenonce = wp_create_nonce('simplefavpost_ajax_security_is_a_big_deal');
		wp_localize_script('simplefavpost_js','simplefavpost_js_obj',array('user_id' => $u, 'post_id' => $post->ID, 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => $thenonce,'exists' => SimpleFavPost::check_exists($u,$post->ID) != 0));		
		echo $args['before_widget'];
		$favs = SimpleFavPost::get_num_favorites_for($post->ID);
		$favs = $favs[0];
		$favs = is_null($favs->total) ? 0 : $favs->total;
		echo "<div class=\"post-fav heart counter\"><span ref=\"$favs\">$favs Favs</span></div>";
		echo $args['after_widget'];
	}
}	


class SimpleFavWidgetPopular extends WP_Widget{
	public function __construct(){
		parent::__construct(
			'popular_fav_widget',
			__('Popular Post Fav ','text_domain'),
			array(
				'title'=>'Popular Post Fav\'s',
				'classname' => 'post-fav',
				'description' => 'Displays the most popular posts'
			)
		);
	}

	public function widget($args, $instance){
		wp_enqueue_style('simplefavpost_css');
		wp_enqueue_script('simplefavpost_js');
		$limit = isset($instance['limit']) ? $instance['limit'] : 3;
		$populars = SimpleFavPost::get_popular($limit);		
		echo $args['before_widget'];
		foreach ($populars as $row) {
			echo '<div class="post-fav">';
			echo "<div class=\"post-fav small heart\"><span>{$row->total}</span></div>";
			echo "<a class=\"post-fav-link\" href=\"" . get_permalink($row->post_id) . "\">{$row->post_title}</a>";
			echo '</div>';
		}
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		// outputs the options form on admin
		$limit = 3;
		if(isset($instance['limit']))
			$limit = $instance['limit'];
		?>
		<label for="<?php echo $this->get_field_name( 'limit' ); ?>">How many posts to show?</label>
		<input type="number" min="1"  id="<?php echo $this->get_field_id( 'limit' ) ?>" 
			name="<?php echo $this->get_field_name( 'limit' ); ?>" 
			value="<?php echo $limit; ?>" /> 
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['limit'] = empty($new_instance['limit']) ? 3 : $new_instance['limit'];
		return $instance;
	}

}	

class UserFavWidget extends WP_Widget{
	public function __construct(){
		parent::__construct(
			'user_fav_widget',
			__('User Post Fav','text_domain'),
			array(
				'title'=>'User Post Fav\'s',
				'classname' => 'post-fav',
				'description' => 'Widget to display a users favorites posts'
			)
		);
	}

	public function form( $instance ) {
		// outputs the options form on admin
		$limit = 3;
		if(isset($instance['limit']))
			$limit = $instance['limit'];
		?>
		<label for="<?php echo $this->get_field_name( 'limit' ); ?>">How many posts to show?</label>
		<input type="number" min="1"  id="<?php echo $this->get_field_id( 'limit' ) ?>" 
			name="<?php echo $this->get_field_name( 'limit' ); ?>" 
			value="<?php echo $limit; ?>" /> 
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['limit'] = empty($new_instance['limit']) ? 3 : $new_instance['limit'];
		return $instance;
	}

	public function widget($args, $instance){
		wp_enqueue_style('simplefavpost_css');
		$u = get_current_user_id();
		$limit = isset($instance['limit']) ? $instance['limit'] : 3;
		echo $args['before_widget'];
		$favs = SimpleFavPost::get_users_favorites($u,$limit);
		echo $args['before_widget'];
		foreach ($favs as $row) {
			echo '<div class="post-fav">';
			echo "<div class=\"post-fav small heart\"></div>";
			echo "<a class=\"post-fav-link\" href=\"" . get_permalink($row->post_id) . "\">{$row->post_title}</a>";
			echo '</div>';
		}
		echo $args['after_widget'];
	}
}	

register_activation_hook( __FILE__, array('SimpleFavPost','install'));
register_uninstall_hook(__FILE__,array('SimpleFavPost','uninstall'));
add_action( 'init', array('SimpleFavPost', 'register_table'), 1 );
add_action( 'wp_enqueue_scripts', 'simplefavpost_register_js_css' );
add_action('widgets_init','simplefav_widget');
add_action("wp_ajax_nopriv_simplefavpost_ajax", "simplefavpost_ajax");
add_action("wp_ajax_simplefavpost_ajax", "simplefavpost_ajax");

?>

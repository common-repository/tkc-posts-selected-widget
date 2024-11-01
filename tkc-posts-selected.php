<?php
/*
Plugin Name: TKC Posts Selected Widget
Plugin URI: http://wordpress.org/plugins/tkc-posts-selected-widget/
Description: Displaying selected posts via widget.
Author: Thinh Pham
Author URI: http://thinhknowscode.com/

Version: 1.0.0

Text Domain: tkc
Domain Path: /languages

License: GNU General Public License v2.0 (or later)
License URI: http://www.opensource.org/licenses/gpl-license.php
*/


/**
 * Load textdomain
 */
if ( !function_exists('tkc_load_textdomain') ) :
function tkc_load_textdomain() {
	load_plugin_textdomain( 'tkc', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
endif;
add_action( 'plugins_loaded', 'tkc_load_textdomain' );

/**
 * TKC Posts Select
 */
class TKC_Posts_Selected_Widget extends WP_Widget {

	function __construct() {
		parent::__construct(
			'tkc_posts_selected', esc_html_x('TKC Posts Selected', 'widget name', 'tkc'),
			array(
				'classname' => 'tkc_posts_selected',
				'description' => esc_html__( 'Display list of selected posts', 'tkc' ),
				'customize_selective_refresh' => true
			)
		);
		
		/** Enqueue CSS */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
        
        /** Load color picker */
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_footer-widgets.php', array( $this, 'print_scripts' ), 9999 );
        
        add_action( 'wp_ajax_tkc_post_selected_id', array( $this, 'get_id' ) );
	}
    
    /**
     * Ajax function to get post id from url
     * @use url_to_postid 
     * @see https://codex.wordpress.org/Function_Reference/url_to_postid
     */
    public function get_id() {
        check_ajax_referer( 'tkc_url_to_id', 'security' );
        $url = isset($_GET['url']) ? sanitize_url( $_GET['url'] ) : '';
        if ( $id = url_to_postid($url) ) {
            wp_send_json( array( 'id' => $id, 'title' => get_the_title( $id ) ) );
        }
        wp_send_json( array( 'id' => 0, 'title' => '' ) );
    }
    
    /**
     * Enqueue the wplink and editor buttons scripts
     */
    public function load_scripts( $hook ) {
		if( 'widgets.php' != $hook )
			return;
        wp_enqueue_script( 'underscore' );
        wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wplink' );
        wp_enqueue_style( 'editor-buttons' );
    }
    
    /**
     * Bind function to open link editor
     */
    public function print_scripts() {
        // Require the core editor class so we can call wp_link_dialog function to print the HTML.
        // Luckly it is public static method ;)
        require_once ABSPATH . "wp-includes/class-wp-editor.php";
        _WP_Editors::wp_link_dialog(); ?>
        
        <script type="text/javascript">
            /* We need ajaxurl to send ajax to retrive links */
            var tkcSecurity = "<?php echo wp_create_nonce( 'tkc_url_to_id' ); ?>";
            
            var tkcLinkTemplate = _.template( '<div class="tkc-ps-link">\
                                                    <%- title %>\
                                                    <input type="hidden" name="tkc-ps-title[]" value="<%- title %>"/>\
                                                    <input type="hidden" name="tkc-ps-id[]" value="<%- id %>"/>\
                                                    <a href="#" class="tkc-ps-remove">X</a>\
                                                </div>' );
            jQuery(document).ready( function($) {
                
                /*** Sortable ***/
                $( ".tkc-posts-wrapper" ).sortable({
                    placeholder: "widget-placeholder"
                });
                $( ".tkc-posts-wrapper" ).disableSelection();
                
                $( 'body' ).on('click', '.tkc-ps-remove', function(event){
                    $( this ).parent( '.tkc-ps-link' ).remove();
                    event.preventDefault();
                })
                
				var $currentWidget;
                jQuery('body').on('click', '.tkc-post-focus', function (event){
					var $textarea = $( this ).siblings( 'textarea' );
					$currentWidget = $( this );
                    wpLink.open( $textarea.attr('id') ); /* Bind to opened link editor! */
                    event.preventDefault();
                });
                
                jQuery( document ).on( 'click', '#wp-link-submit', function(event){
                    var container = $currentWidget.siblings( '.tkc-posts-wrapper' );
                    var url = $( '#wp-link-url' ).val().trim();
                    var text = $( '#wp-link-text' ).val().trim();
                    if ( url.length ) {
                        $.ajax({
                            url: ajaxurl,
                            data: {
                                dataType: 'json',
                                security: tkcSecurity,
                                action: 'tkc_post_selected_id',
                                url: url
                            }
                        }).done(function( data ) {
                            if ( data.id == 0 ) {
                                alert( 'Post is not exists!' );
                            }
                            else {
                                if ( !text.length ) text = data.title;
                                container.prepend( tkcLinkTemplate( { id: data.id, title: text } ) );
                            }
                        });
                    }
                });
            })
        </script>
        <style>
        .tkc-posts-wrapper { padding: 5px; display: block; border: #ccc dotted 1px; }
        .tkc-ps-link { display: block; padding: 7px 3px; margin: 5px 0; background-color: #e1e1e1; }
        </style><?php
    }
	
	function enqueue_css() {

		$cssfile	= apply_filters( 'tkc_ps_default_css', plugin_dir_url( __FILE__ ) . 'css/tkc-posts-selected.css' );
		wp_enqueue_style( 'tkc-posts-selected-widget', esc_url( $cssfile ), array(), '1.0.0', 'all' );
	}
    
	function widget($args, $instance) {
		$defaults = array ( 
            'title'         => '',
            'posts_list'    => []
        );
        
        $instance = wp_parse_args($instance, $defaults);
        if ( !$instance['posts_list'] )
            return;
        
        echo $args['before_widget'];
            
        if (!empty($instance['title'])) {
			echo $args['before_title'];            
            echo esc_html(apply_filters('widget_title', $instance['title']));
			echo $args['after_title'];   
        }
        
        $posts_list = $instance['posts_list'];
        foreach( $posts_list as $item ) {
            $id     = $item['id'];
            $title  = $item['title'];
            $the_post   = get_post( $id );
            if ( !$the_post ) 
                continue;
            ?>
            <article class="post-<?php echo $the_post->ID; ?> tkc-posts-focus-item tkc-posts-focus-item-small clear">
				<figure class="tkc-posts-focus-thumb tkc-posts-focus-thumb-small">
					<a href="<?php echo get_permalink( $the_post->ID ); ?>" title="<?php the_title_attribute(array('post' => $the_post)); ?>"><?php
						if ( has_post_thumbnail($the_post->ID) ) {
							echo get_the_post_thumbnail($the_post->ID);
						} else {
							echo '<img class="tkc-image-placeholder" src="' . plugins_url('images/placeholder-small.png', __FILE__) . '" alt="' . esc_html__('No Picture', 'tkc') . '" />';
						} ?>
					</a>
				</figure>
				<div class="tkc-posts-focus-title tkc-posts-focus-title-small">
					<a href="<?php echo get_permalink( $the_post->ID ); ?>" title="<?php the_title_attribute(array('post' => $the_post)); ?>" rel="bookmark">
						<?php echo esc_attr($title); ?>
					</a>
				</div>
			</article>
			<div class="clear"></div><?php
        }
        
		echo $args['after_widget'];
    }
    
    /**
     * Extend 
     */ 
	function update($new_instance, $old_instance) {
        $instance = array();
        if (!empty($new_instance['title'])) {
			$instance['title'] = sanitize_text_field($new_instance['title']);
		}
        $ids = isset($_POST['tkc-ps-id']) ? (array)$_POST['tkc-ps-id'] : array();
        $ids = array_map( 'absint', $ids );
        $titles = isset($_POST['tkc-ps-title']) ? (array)$_POST['tkc-ps-title'] : array();
		$titles = array_map( 'sanitize_text_field', $titles );
        $instance['posts_list'] = array();
        foreach( $ids as $key => $id ) {
            $instance['posts_list'][] = array( 'id' => $id, 'title' => $titles[$key] );
        }
        return $instance;
    }
    
    /**
	 * Outputs the settings update form.
	 */
    function form( $instance ) {
        $defaults = array(
            'title'         => '',
            'posts_list'    => [],
        );     
        $instance = wp_parse_args( $instance, $defaults ); ?>
        <p>
        	<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'tkc'); ?></label>
			<input class="widefat" type="text" value="<?php echo esc_attr($instance['title']); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" id="<?php echo esc_attr($this->get_field_id('title')); ?>" />
        </p>
		<button class="button tkc-post-focus"><?php _e('Select post', 'tkc'); ?></button>
		<textarea id="textarea-<?php echo rand();?>" style="display: none;"></textarea>
		<div class="tkc-posts-wrapper">
			<?php
			$posts = maybe_unserialize( $instance['posts_list'] );
			if ( !is_array($posts) )
				$posts = array();
				
			foreach( $posts as $post ) {
				printf('<div class="tkc-ps-link">%s
							<input type="hidden" name="tkc-ps-title[]" value="%s"/>
							<input type="hidden" name="tkc-ps-id[]" value="%d"/>
							<a href="#" class="tkc-ps-remove">X</a>
						</div>', esc_attr($post['title']), esc_attr($post['title']), absint($post['id']) );
			}
			?>
		</div>
        <?php
    }

}

add_action( 'widgets_init', 'tkc_ps_load_widget' );
/**
 * Widget Registration.
 */
function tkc_ps_load_widget() {

	register_widget( 'TKC_Posts_Selected_Widget' );

}
<?php
/*
Plugin Name: Stanford Teasers
Description: Add teaser post type. Teasers link to offsite content; teasers are included with regular posts on the home page, on archive pages, and in feeds.
Version:     1.0.0
Author:      JB Christy
Domain Path: /languages
Text Domain: stanford-text-domain
*/

defined('ABSPATH') or die("Do not load this file directly.");

// CMB2 - Custom Meta Boxes (see https://github.com/WebDevStudios/CMB2)
require_once 'cmb2/init.php';


/**
 * Class SU_Teaser
 * Follows singleton pattern
 */
class SU_Teaser {

	const POST_TYPE   = 'teaser';
	const META_PREFIX = '_stanford_teaser_'; // metadata prefix for CMB


	/** @var SU_Teaser singleton instance of this class */
	protected static $instance = null;

  /** @var string plugin version */
  protected $ver = '1.0.0';


	/**
	 * Register the teaser post type.
	 * Invoked via the 'init' action
	 */
	public function register_post_type() {

		// teasers should have all the taxonomies that posts can have, except post_format
		$taxonomies = get_taxonomies( [ 'object_type' => [ 'post' ] ] );
		unset( $taxonomies[ 'post_format' ] );
		$taxonomies = array_values( $taxonomies );

		// register Teaser post type
		$labels = array(
			'name'               => _x('Teasers', 'Post Type General Name',  'stanford-text-domain'),
			'singular_name'      => _x('Teaser',  'Post Type Singular Name', 'stanford-text-domain'),
			'menu_name'          => __('Teasers',            'stanford-text-domain'),
			'name_admin_bar'     => __('Teaser',             'stanford-text-domain'),
			'parent_item_colon'  => __('Parent Item:',       'stanford-text-domain'),
			'all_items'          => __('All Teasers',        'stanford-text-domain'),
			'add_new_item'       => __('Add New Teaser',     'stanford-text-domain'),
			'add_new'            => __('Add New',            'stanford-text-domain'),
			'new_item'           => __('New Teaser',         'stanford-text-domain'),
			'edit_item'          => __('Edit Teaser',        'stanford-text-domain'),
			'update_item'        => __('Update Teaser',      'stanford-text-domain'),
			'view_item'          => __('View Teaser',        'stanford-text-domain'),
			'search_items'       => __('Search Teasers',     'stanford-text-domain'),
			'not_found'          => __('Not found',          'stanford-text-domain'),
			'not_found_in_trash' => __('Not found in Trash', 'stanford-text-domain'),
		);
		$args = array(
			'label'               => __('teaser', 'stanford-text-domain'),
			'description'         => __('Links to posts on other sites', 'stanford-text-domain'),
			'labels'              => $labels,
			'supports'            => [ 'title', 'thumbnail', 'excerpt', 'author' ],
			'taxonomies'          => $taxonomies,
			'hierarchical'        => FALSE,
			'public'              => TRUE,
			'show_ui'             => TRUE,
			'show_in_menu'        => TRUE,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-external',
			'show_in_admin_bar'   => TRUE,
			'show_in_nav_menus'   => FALSE,
			'show_in_rest'        => TRUE,
			'can_export'          => TRUE,
			'has_archive'         => FALSE,
			'exclude_from_search' => FALSE,
			'publicly_queryable'  => TRUE,
			'rewrite'             => FALSE, // teasers, by definition, link offsite
			'capability_type'     => 'post',
		);
		$type_obj = register_post_type( self::POST_TYPE, $args );

		add_theme_support( 'post-thumbnails', [ self::POST_TYPE ] );
	}

	/**
	 * Add CMB2 metabox to teaser edit pages. Metabox contains the following fields:
	 *   + url
	 *   + source
	 * Invoked via the cmb2_admin_init action
	 */
	public function add_metaboxes() {
		$story = new_cmb2_box([
				'id'           => 'story'
			, 'title'        => __( 'Story', 'stanford-text-domain' )
			, 'object_types' => [ self::POST_TYPE ]
			, 'context'      => 'normal'
			, 'priority'     => 'high'
			, 'show_names'   => TRUE
		]);

		$story->add_field([
				'id'         => self::META_PREFIX . 'url'
			, 'name'       => __( 'URL', 'stanford-text-domain' )
			, 'desc'       => __( 'URL of story', 'stanford-text-domain' )
			, 'type'       => 'text_url'
			, 'attributes' => [
					  'placeholder' => 'e.g. http://news.stanford.edu/election-2016'
					, 'required'    => 'required'
				]
		]);

		$story->add_field([
				'id'         => self::META_PREFIX . 'source'
			, 'name'       => __( 'Source', 'stanford-text-domain' )
			, 'desc'       => __( 'Name of website where the story is hosted', 'stanford-text-domain' )
			, 'type'       => 'text'
			, 'attributes' => [
					'placeholder' => 'e.g. Stanford News'
			]
		]);

	}

	/**
	 * Enqueue custom CSS to make CMB's text fields, esp. text_url, be wide enough
	 * Invoked via the admin_enqueue_scripts action
	 *
	 * @param string $hook - admin page being displayed
	 */
	public function admin_enqueue_css( $hook ) {
		switch ( $hook ) {
			case 'post-new.php':
			case 'post.php':
				wp_enqueue_style( 'teaser-cmb-css', plugins_url( 'css/cmb.css', __FILE__ ), [], $this->ver );
				break;
		}
	}

	/**
	 * Set columns to display for posts that refer to external content, i.e.
	 *   teasers, around the farm, in the news
	 *
	 * @param array $columns - WordPress's idea of what the columns should be
	 * @return array - our idea of what the columns should be
	 */
	public function external_story_columns ( $columns ) {
		$new_columns = [
			'cb'      => $columns['cb']
			, 'title'   => $columns['title']
			, 'source'  => _x( 'Source', 'Column head for source of an external story', 'stanford-text-domain' )
			, 'url'     => __( 'URL', 'stanford-text-domain' )
			, 'author'  => $columns['author']
			, 'date'    => $columns['date']
		];
		return $new_columns;
	}

	/**
	 * Display content in custom columns for teasers, around the farm, in the news
	 *
	 * @param string $column_name
	 * @param integer $post_id
	 */
	public function external_story_column_content( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'source':
				$source = get_post_meta( $post_id, self::META_PREFIX . 'source' , TRUE );
				echo $source;
				break;
			case 'url':
				$external_url = esc_url_raw( get_post_meta( $post_id, self::META_PREFIX . 'url' , TRUE ) );
				echo "<a href='{$external_url}' target='_blank'>{$external_url}</a>";
				break;
		};
	}

	/**
	 * Include teasers with posts on the home page, in archives, and in feeds.
	 * Invoked via the pre_get_posts action
	 *
	 * @param WP_Query $query
	 */
	public function pre_get_posts($query) {
		if ( $query->is_main_query() ) {
			if ( $query->is_home() || $query->is_archive() || $query->is_feed() ) {
				$type = $query->get( 'post_type' );
				if ( empty($type) || $type == 'post' ) {
					$query->set( 'post_type', [ 'post', self::POST_TYPE ] );
				}
			}
		}
		return;
	}

	/**
	 * Make teasers link directly to the external source.
	 * Invoked via the post_type_link filter, which is called for custom post types
	 *
	 * @param string $post_link - proposed url for post
	 * @param WP_Post $post - post we want the link for
	 * @param boolean $leavename - whether or not to retain permalink template tags
	 * @param boolean $sample - is it a sample permalink?
	 *
	 * @return string
	 */
	public function post_type_link( $post_link, $post, $leavename, $sample ) {
		if ( $post->post_type == self::POST_TYPE ) {
			$teaser_url = get_post_meta( $post->ID, self::META_PREFIX . 'url' , TRUE );
		}

		return empty( $teaser_url ) ? $post_link : esc_url_raw( $teaser_url, [ 'http', 'https' ] );
	}


	/******************************************************************************
	 *
	 * Plugin config
	 *
	 ******************************************************************************/

	/**
	 * Called when plugin is activated.
	 * Because we're registering a post type with it's own url structure, we need to flush the rewrite rules.
	 */
	public function activate() {
		$this->register_post_type();

		// clear the permalinks after the post type has been registered
		flush_rewrite_rules();
	}

	/**
	 * Called when plugin is deactivated.
	 * Because we registered a post type with it's own url structure, we need to flush the rewrite rules.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}


  /******************************************************************************
   *
   * Class setup
   *
   ******************************************************************************/

  /**
   * Called once when singleton instance is created.
   * Declared as protected to prevent using new to instantiate instances other than the singleton.
   */
  protected function __construct() {

	  register_activation_hook(   __FILE__, [ $this, 'activate'   ] );
	  register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

	  add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_css'  ], 99 );
	  add_action( 'cmb2_admin_init',       [ $this, 'add_metaboxes'      ] );
	  add_action( 'init',                  [ $this, 'register_post_type' ] );
	  add_action( 'pre_get_posts',         [ $this, 'pre_get_posts'      ] );
	  add_filter( 'post_type_link',        [ $this, 'post_type_link'     ], 99, 4 ); // for custom post types

	  add_filter( 'manage_teaser_posts_columns',       [ $this, 'external_story_columns' ] );
	  add_action( 'manage_teaser_posts_custom_column', [ $this, 'external_story_column_content' ], 10, 2);

  }

  /**
   * Create singleton instance, if necessary.
   */
  public static function init() {
    if ( !is_a( self::$instance, __CLASS__ ) ) self::$instance = new SU_Teaser();
    return self::$instance;
  }
} // class SU_Teaser

global $su_teaser;
if ( !is_a( $su_teaser, 'SU_Teaser' ) ) $su_teaser = SU_Teaser::init();
<?php 
/**
 * Plugin Name: CRB Columns Manager
 */

class Carbon_Admin_Columns_Manager {
	
	/**
	 * Column Type
	 *
	 * Available options : post_columns, taxonomy_columns, user_columns
	 *
	 * @var string $type
	 */
	protected $type;

	/**
	 * Target name
	 *
	 * The target name might be taxonomies or post types
	 *
	 * @see set_target()
	 * @see get_target()
	 * @var array|string $targets
	 */
	protected $targets;

	/**
	 * Sepcify columns for removal.
	 * The value might be a string or an array with columns
	 *
	 * @see remove()
	 * @var array|string $columns_to_remove
	 */
	protected $columns_to_remove;

	static function modify_post_type_columns( $post_types ) {
		return new Carbon_Admin_Columns_Manager_Post_Columns('post_columns', $post_types);
	}

	static function modify_users_columns() {
		return new Carbon_Admin_Columns_Manager_User_Columns('user_columns');
	}

	static function modify_taxonomy_columns( $taxonomies ) {
		return new Carbon_Admin_Columns_Manager_Taxonomy_Columns('taxonomy_columns', $taxonomies);
	}

	private function __construct($type, $targets='') {
		$this->type = $type;
		$this->set_target($targets);
	}

	public function set_target($targets) {
		$this->targets = (array) $targets;

		return $this;
	}

	public function get_targets() {
		return $this->targets;
	}

	public function get_type() {
		return $this->type;
	}

	public function unset_admin_columns($columns) {
		foreach ( $this->columns_to_remove as $column_name ) {
			unset( $columns[$column_name] );
		}

		return $columns;
	}

}

class Carbon_Admin_Columns_Manager_Post_Columns extends Carbon_Admin_Columns_Manager {

	public function remove($columns_to_remove) {
		$this->columns_to_remove = (array) $columns_to_remove;
		
		// currently available only for post types
		add_filter( 'manage_posts_columns' , array($this, 'unset_admin_columns') );
		add_filter( 'manage_pages_columns' , array($this, 'unset_admin_columns') );

		return $this;
	}

	public function add( $columns ) {
		if ( !$this->is_correct_post_type_location() ) {
			return;
		}

		foreach ($columns as $column) {
			if ( !is_a($column, 'Carbon_Admin_Column') ) {
				wp_die( 'Object must be of type Carbon_Admin_Column' );
			}

			$column->set_manager( $this );
			$column->init();
		}
	}

	public function is_correct_post_type_location() {
		$post_type = 'post';

		if ( !empty($_GET['post_type']) ) {
			$post_type = $_GET['post_type'];
		}

		return in_array($post_type, $this->get_targets());
	}

	public function get_column_filter_name( $post_type_name ) {
		return 'manage_' . $post_type_name . '_posts_columns';
	}

	public function get_column_filter_content( $post_type_name ) {
		return 'manage_' . $post_type_name . '_posts_custom_column';
	}

	public function get_column_filter_sortable( $post_type_name ) {
		return 'manage_edit-' . $post_type_name . '_sortable_columns';
	}

	public function get_meta_value($object_id, $meta_key) {
		return get_post_meta($object_id, $meta_key, true);
	}
}

class Carbon_Admin_Columns_Manager_User_Columns extends Carbon_Admin_Columns_Manager {

	public function remove($columns_to_remove) {
		$this->columns_to_remove = (array) $columns_to_remove;
		
		add_action('manage_users_columns',array($this, 'unset_admin_columns'));

		return $this;
	}

	public function add( $columns ) {

		$this->targets = array('users');

		$defined_columns = array();
		$datastore = new Carbon_Admin_Columns_Datastore();

		foreach ($columns as $column_index => $column) {
			if ( !is_a($column, 'Carbon_Admin_Column') ) {
				wp_die( 'Object must be of type Carbon_Admin_Column' );
			}

			$column->set_manager( $this );
			$column->set_column_datastore( $datastore );
			$column->init();

			$defined_columns[$column_index] = array(
				'column_name' => $column->get_column_name(),
				'meta_key' => $column->get_field(),
				'callback_function' => $column->get_callback()
			);
		}

		$datastore->set_columns($defined_columns);
	}

	public function get_column_filter_name( $null ) {
		return 'manage_users_columns';
	}

	public function get_column_filter_content( $null ) {
		return 'manage_users_custom_column';
	}

	public function get_column_filter_sortable( $null ) {
		return 'manage_users_sortable_columns';
	}

	public function get_meta_value($object_id, $meta_key) {
		return get_user_meta($object_id, $meta_key, true);
	}
}

class Carbon_Admin_Columns_Manager_Taxonomy_Columns extends Carbon_Admin_Columns_Manager {

	public function remove($columns_to_remove) {
		$this->columns_to_remove = (array) $columns_to_remove;

		$targets = $this->get_targets();
		foreach ($targets as $taxonomy) {
			add_filter( 'manage_edit-' . $taxonomy . '_columns' , array($this, 'unset_admin_columns') );
		}

		return $this;
	}

	public function add( $columns ) {
		if ( !$this->is_correct_post_type_location() ) {
			return;
		}

		foreach ($columns as $column) {
			if ( !is_a($column, 'Carbon_Admin_Column') ) {
				wp_die( 'Object must be of type Carbon_Admin_Column' );
			}

			$column->set_manager( $this );
			$column->init();
		}
	}

	public function is_correct_post_type_location() {
		$taxonomy_name = 'category';

		if ( !empty($_GET['taxonomy']) ) {
			$taxonomy_name = $_GET['taxonomy'];
		}

		return in_array($taxonomy_name, $this->get_targets());
	}

	public function get_column_filter_name( $taxonomy_name ) {
		return 'manage_edit-' . $taxonomy_name . '_columns';
	}

	public function get_column_filter_content( $taxonomy_name ) {
		return 'manage_' . $taxonomy_name . '_custom_column';
	}

	public function get_column_filter_sortable( $taxonomy_name ) {
		return 'manage_edit-' . $taxonomy_name . '_sortable_columns';
	}

	public function get_meta_value($object_id, $meta_key) {
		if ( function_exists('carbon_get_term_meta') ) {
			return carbon_get_term_meta($object_id, $meta_key);
		} else {
			return;
		}
	}
}

class Carbon_Admin_Column {

	/**
	 * Column type
	 *
	 * Available options: custom_field | callback
	 *
	 * @see remove()
	 * @var string $type
	 */
	protected $type;

	/**
	 * Contains the column label
	 *
	 * @var string $label
	 */
	protected $label;

	/**
	 * Columns name
	 *
	 * @see set_column_name()
	 * @see get_column_name()
	 * @var string $name
	 */
	protected $name;

	/**
	 * Defines if the column is sortable or not
	 *
	 * @see set_sortable()
	 * @var boolean $is_sortable as first parameter
	 */
	protected $is_sortable = false;

	/**
	 * Default ::: escaped( $label )
	 * $_GET[orderby] = $sortable_key
	 *
	 * @see set_sortable()
	 * @var boolean $sortable_key as second parameter
	 */
	protected $sortable_key;

	/**
	 * An array with the available column contains
	 *
	 * @see verify_column_container()
	 * @var array
	 */
	protected $allowed_containers = array('post_columns', 'taxonomy_columns', 'user_columns' );

	/**
	 * An instance of Main Carbon Columns Container
	 *
	 * @var object $manager
	 */
	protected $manager;

	/**
	 * Contains the targets of the main container
	 *
	 * @var string|array $container_targets
	 */
	protected $container_targets;

	/**
	 * @see set_field()
	 * @see get_field()
	 * @var string $meta_key
	 */
	protected $meta_key = null;

	/**
	 * @see set_callback()
	 * @see get_callback()
	 * @var string $callback_function
	 */
	protected $callback_function = null;

	/**
	 * Instance of Carbon_Admin_Columns_Datastore
	 * @see new Carbon_Admin_Columns_Datastore()
	 */
	protected $datastore = null;

	/**
	 * Defines if is callback
	 * @var boolean $is_callback
	 */
	protected $is_callback = false;

	static function create($label, $name = null) {

		if ( !$label ) {
			wp_die( 'Column label is required.' );
		}

		return new self($label, $name);
	}

	private function __construct($label, $name) {
		$this->label = $label;

		if ( empty($name) ) {
			$name = 'carbon-' . preg_replace('~[^a-zA-Z0-9.]~', '', $label);
		}
		$this->set_column_name($name);

		return $this;
	}

	public function set_column_name($name) {
		$this->name = $name;

		return $this;
	}

	public function get_column_name() {
		return $this->name;
	}

	public function set_field($meta_key) {
		$this->meta_key = $meta_key;

		return $this;
	}

	public function get_field() {
		if ( $this->is_callback() && !empty($this->datastore) ) {
			return $this->datastore->get_field();
		}

		return $this->meta_key;
	}

	public function set_callback($callback_function) {
		$this->callback_function = $callback_function;

		return $this;
	}

	public function get_callback() {
		if ( $this->is_callback() && !empty($this->datastore) ) {
			return $this->datastore->get_callback();
		}

		return $this->callback_function;
	}

	public function set_column_datastore($datastore) {
		$this->datastore = $datastore;

		return $this;
	}

	public function get_column_datastore() {
		return $this->datastore;
	}

	public function get_column_label() {
		return $this->label;
	}

	public function set_sortable($is_sortable, $sortable_key=null) {
		$this->sortable_key = $sortable_key;
		$this->is_sortable = $is_sortable;

		return $this;
	}

	public function get_sortable_key() {
		$sortable_key = $this->sortable_key;

		if ( !$sortable_key ) {
			$sortable_key = $this->get_column_name();
		}

		return $sortable_key;
	}

	public function is_sortable() {
		return $this->is_sortable;
	}

	public function is_callback() {
		return $this->is_callback===true;
	}

	/**
	 * @see Carbon_Admin_Columns_Manager -> add()
	 */
	public function set_manager( $manager ) {
		$this->manager = $manager;

		return $this;
	}

	public function get_container_type() {
		return $this->manager->get_type();
	}

	public function get_targets() {
		return $this->manager->get_targets();
	}

	public function verify_column_container($container) {
		return in_array($container, $this->allowed_containers);
	}

	/**
	 * Set column column hooks
	 */
	public function init() {
		$targets = $this->get_targets();
		$is_sortable = $this->is_sortable();
		$container_type = $this->get_container_type();

		if ( !$this->verify_column_container($container_type) ) {
			wp_die( 'Unknown column container "' . $container_type . '".' );
		}

		$column_header = array($this, 'init_column_label');
		$column_content = array($this, 'init_' . $container_type . '_callback');
		$column_sortable = array($this, 'init_column_sortable');

		foreach ($targets as $object) {
			$filter_name = $this->manager->get_column_filter_name( $object );
			$filter_content = $this->manager->get_column_filter_content( $object );
			$filter_sortable = $this->manager->get_column_filter_sortable( $object );

			add_filter( $filter_name, $column_header, 15);
			add_action( $filter_content, $column_content, 15, 3);

			if ( $is_sortable ) {
				add_filter( $filter_sortable, $column_sortable );
			}
		}
	}

	public function init_column_label($columns) {
		$columns[ $this->get_column_name() ] 	= $this->get_column_label();

		return $columns;
	}

	public function init_column_sortable($columns) {
		// $columns[ column_name ] 	= sortable_key;
		$columns[ $this->get_column_name() ] 	= $this->get_sortable_key();

		return $columns;  
	}

	public function init_user_columns_callback($null, $column_name, $user_id) {
		return $this->init_column_callback($this->get_column_name(), $user_id);
	}

	public function init_taxonomy_columns_callback($null, $column_name, $term_id) {
		echo $this->init_column_callback($column_name, $term_id);
	}

	public function init_post_columns_callback($column_name, $post_id) {
		echo $this->init_column_callback($column_name, $post_id);
	}

	public function init_column_callback( $column_name, $object_id ) {

		$this->is_callback = true;

		$this_column_name = $this->get_column_name();

		# check if on the right column
		if ( $this_column_name!==$column_name ) {
			return;
		}

		$meta_key = $this->get_field();

		$callback_function_name = $this->get_callback();

		if ( $meta_key && $callback_function_name ) {
			wp_die( 'You can use set_field() or set_callback(), but not both of them.' );
		}

		# Prepare the result
		$results = '';

		if ( !empty($this->datastore) ) {
			$this->datastore->increase_loop_index();

			# prevent multiple callback function calling
			if ( $this->datastore->get_loop_index()%$this->datastore->get_total_columns()!==0 ) {
				return;
			}
		}

		if ( $meta_key ) {
			
			$results = $this->manager->get_meta_value($object_id, $meta_key);

		} else if ( $callback_function_name ){

			if ( !function_exists($callback_function_name) ) {
				wp_die( 'Missing Carbon Admin Column callback function : "' . $container_type . '".' );
			}

			$results = $callback_function_name( $object_id );
		}

		return $results;
	}
}

class Carbon_Admin_Columns_Datastore {

	protected $index = 0;

	protected $loop_index = 0;

	protected $columns = array();

	protected $total_columns = 0;

	function __construct() {

	}

	public function increase_loop_index() {
		$this->loop_index++;

		if ( $this->loop_index===pow($this->get_total_columns(), 2) ) {
			$this->loop_index = 0;
		}

		return $this;
	}

	public function get_loop_index() {
		return $this->loop_index;
	}

	public function get_index() {
		return floor( $this->get_loop_index() / $this->get_total_columns() );
	}

	public function set_columns($columns) {
		$this->columns = $columns;

		$this->set_total_columns();

		return $this;
	}

	public function get_columns() {
		return $this->columns;
	}

	public function set_total_columns() {
		$this->total_columns = count($this->get_columns());

		return $this;
	}

	public function get_total_columns() {
		return $this->total_columns;
	}

	public function get_field() {
		$columns = $this->get_columns();
		$column_index = $this->get_index();
		
		return $columns[ $column_index ][ 'meta_key' ];
	}

	public function get_callback() {
		$columns = $this->get_columns();
		$column_index = $this->get_index();

		return $columns[ $column_index ][ 'callback_function' ];
	}
}

/**

*/

function callback($object_id) {
	$post_object = get_post($object_id);
	return '<span style="color: red">' . $post_object->post_name . '</span>';
}

function user_callback($object_id) {
	$user = get_user_by('id', $object_id);

	$html = '<ul>';
	$html .= '<li>ID : ' . $user->ID . '</li>';
	$html .= '<li>Email : ' . $user->data->user_email . '</li>';
	$html .= '<li>Nicename : ' . $user->data->user_nicename . '</li>';
	$html .= '</ul>';

	return $html;
}

function taxonomy_callback($object_id) {
	$tax_color = carbon_get_term_meta($object_id, 'title_color');
	return '<span style="color: ' . $tax_color . '">' . $tax_color . '</span>';
}

function testing() {

	# Post types
	Carbon_Admin_Columns_Manager::modify_post_type_columns(array('page', 'post'))
		->remove(array('author', 'date', 'comments'))
		->add(array(
				Carbon_Admin_Column::create('My Color')
					->set_field('color')
					->set_sortable(true, 'sortable_color_key'),
				Carbon_Admin_Column::create('Callback 1')
					->set_callback('callback'),
				Carbon_Admin_Column::create('My Car')
					->set_field('car'),
				Carbon_Admin_Column::create('Callback 2')
					->set_callback('callback'),
			));

	# Taxonomies
	/*
	Carbon_Container::factory('term_meta', 'Category Properties')
		->add_fields(array(
			Carbon_Field::factory('color', 'title_color'),
			Carbon_Field::factory('color', 'text_color')
		));
	*/
	Carbon_Admin_Columns_Manager::modify_taxonomy_columns(array('category', 'post_tag'))
		->remove(array('author', 'description', 'slug', 'posts'))
		->add(array(
				Carbon_Admin_Column::create('Title Color')
					->set_field('title_color')
					->set_sortable(true, 'sortable_color_key'),
				Carbon_Admin_Column::create('Callback 1')
					->set_callback('taxonomy_callback'),
				Carbon_Admin_Column::create('Text Color')
					->set_field('text_color'),
				Carbon_Admin_Column::create('Callback 2')
					->set_callback('taxonomy_callback'),
			));

	# Users
	Carbon_Admin_Columns_Manager::modify_users_columns()
		->remove(array('name', 'email', 'posts'))
		->add(array(
				Carbon_Admin_Column::create('Description')
					->set_field('description'),
				Carbon_Admin_Column::create('Nickname')
					->set_sortable(true, 'sortable_nickname_key')
					->set_field('nickname'),
				Carbon_Admin_Column::create('Misc Information')
					->set_callback('user_callback'),
			));

}
add_action('after_setup_theme', 'testing');
<?php
/**
 * The taxonomy template file
 *
 * @package AMI\Shared
 */

namespace AMI\Shared\Tealium\Taxonomies\Tag_Control;

/**
 * Sets up this file with the WordPress API.
 *
 * @return void
 */
function load() {
	add_action( 'amis_setup', __NAMESPACE__ . '\\setup' );
}

/**
 * Register various methods with the WordPress API.
 *
 * @return void
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\\create_taxonomy' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_meta_boxes', 100 );
	add_action( 'save_post', __NAMESPACE__ . '\\save' );
	add_filter( 'ami_utag_data', __NAMESPACE__ . '\\data_layer' );
	add_action( 'tealium_tag_placement', __NAMESPACE__ . '\\place_tag' );
}

/**
 * Get one of the static names for this taxonomy.
 *
 * @param  string $type The type of name to retrieve.
 * @return string       The specific name for this tax of the requested type.
 */
function get_name( $type ) {
		$names = [
			'title'        => __( 'Tealium Tag Control', 'ami-shared' ),
			'slug'         => 'tealium',
			'key'          => 'tealium_tm',
			'nonce'        => 'nonce-name',
			'nonce_action' => 'nonce-action',
			'filter'       => 'tag_control_filter',
		];

		return $names[ $type ] ?? '';
}

/**
 * Register this custom taxonomy
 *
 * @return void
 */
function create_taxonomy() {
	$args = [
		'public'            => false,
		'hierarchical'      => false,
	];

	register_taxonomy( get_name( 'slug' ), get_post_types(), $args );
}

/**
 * Display the metabox for this meta.
 *
 * @param  WP_Post $post The post object this meta box is being output for.
 * @return void
 */
function display_metabox( $post ) {
	// Output the nonce.
	wp_nonce_field( get_name( 'nonce_action' ) . $post->ID, get_name( 'nonce' ) );
	$current = get_the_terms( $post, get_name( 'slug' ) );
	if ( $current && ! is_wp_error( $current ) ) {
		$current = wp_list_pluck( $current, 'name' );
	} else {
		$current = [];
	}

	foreach ( get_tag_placements() as $tag => $details ) { ?>
		<p>
			<input
				type="checkbox"
				name="<?php echo esc_attr( get_name( 'key' ) . "[${tag}]" ); ?>"
				id="<?php echo esc_attr( get_name( 'key' ) . '_' . $tag ); ?>"
				value="on"
				<?php checked( in_array( $tag, $current, true ) ); ?>
			/>
			<label for="<?php echo esc_attr( get_name( 'key' ) . '_' . $tag ); ?>">
				<?php echo esc_html( $details['name'] ) ?>
			</label>
		</p>
	<?php }
}

/**
 * Save the this meta when the post is saved.
 *
 * @param  int $post_id The post ID being saved.
 * @return void
 */
function save( $post_id ) {
	// Don't save during autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Verify post type
	if ( ! in_array( get_post_type( $post_id ), get_post_types(), true ) ) {
		return;
	}

	// Verify nonce
	$nonce_action = get_name( 'nonce_action' ) . $post_id;
	$nonce        = $_POST[ get_name( 'nonce' ) ] ?? ''; // @codingStandardsIgnoreLine
	if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
		return;
	}

	// Verify permissions
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Save data
	$key  = get_name( 'key' );
	$tags = $_POST[ $key ] ?? []; // @codingStandardsIgnoreLine
	$tags = is_array( $tags ) ? $tags : [];
	$whitelisted_tags = [];
	$available_tags = get_tag_placements();
	foreach ( $tags as $tag => $on ) {
		if ( array_key_exists( $tag, $available_tags ) && 'on' === $on ) {
			$whitelisted_tags[] = $tag;
		}
	}
	wp_set_object_terms( $post_id, $whitelisted_tags, get_name( 'slug' ) );
}

/**
 * Register meta box for this meta.
 *
 * @return void
 */
function register_meta_boxes() {
	foreach ( get_post_types() as $post_type ) {
		add_meta_box(
			get_name( 'key' ) . '-metabox',
			get_name( 'title' ),
			__NAMESPACE__ . '\\display_metabox',
			$post_type,
			'side',
			'low'
		);
	}
}

/**
 * Gets the post types that support the this taxonomy.
 *
 * @return array The post types that support this metabox.
 */
function get_post_types() {
	return apply_filters( get_name( 'filter' ), [] );
}

/**
 * Get the supported tags.
 *
 * @return array The available tag placements.
 */
function get_tag_placements() {
	return apply_filters( 'tealium_tag_placements', [] );
}

/**
 * Add tags to the data layer.
 *
 * @param  array $data_layer The current array of datalayer data.
 * @return array             The datalayer with tags added.
 */
function data_layer( $data_layer ) {
	$tags = get_the_terms( get_post(), get_name( 'slug' ) );
	if ( $tags && ! is_wp_error( $tags ) ) {
		$tags = wp_list_pluck( $tags, 'name' );
	} else {
		$tags = [];
	}
	$whitelist = get_tag_placements();
	foreach ( $tags as $tag ) {
		if ( in_array( $tag, $tags, true ) ) {
			$data_layer[ $tag ] = 'on';
		}
	}

	return $data_layer;
}

/**
 * Place a defined tag based on an action call.
 *
 * @param  string $tag The tag slug to be placed.
 * @return void
 */
function place_tag( $tag = '' ) {
	$tags = get_tag_placements();
	if ( isset( $tags[ $tag ] ) && has_term( $tag, get_name( 'slug' ) ) ) {
		echo $tags[ $tag ]['tag']; // xss ok.
	}
}

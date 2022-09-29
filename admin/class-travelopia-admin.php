<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       #
 * @since      1.0.0
 *
 * @package    Travelopia
 * @subpackage Travelopia/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Travelopia
 * @subpackage Travelopia/admin
 * @author     Maulik Paddhariya <maulikpaddhariya@gmail.com>
 */
class Travelopia_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action( 'init', array( $this, 'custom_post_type_character' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Travelopia_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Travelopia_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/travelopia-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Travelopia_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Travelopia_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/travelopia-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function custom_post_type_character() {
		$labels = array(
			'name'                  => _x( 'Characters', 'Character', 'twentytwentytwo' ),
			'singular_name'         => _x( 'Character', 'Character', 'twentytwentytwo' ),
			'menu_name'             => _x( 'Characters', 'Characters', 'twentytwentytwo' ),
		);     
		$args = array(
			'labels'             => $labels,
			'description'        => 'Character custom post type.',
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'character' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
			'show_in_rest'       => true
		);
		 
		register_post_type( 'Character', $args );
	}

	public function register_meta_boxes() {
		add_meta_box( 'api-id', __( 'Add ID', 'twentytwentytwo' ), array( $this, 'cpt_display_callback' ), 'character' );
	}

	public function cpt_display_callback( $post ) {
		?>
		<div class="api_box">
			<style scoped>
				.api_box{
					display: grid;
					grid-template-columns: max-content 1fr;
					grid-row-gap: 10px;
					grid-column-gap: 20px;
				}
				.api_field{
					display: contents;
				}
			</style>
			<p class="meta-options api_field">
				<label for="api_id">ID</label>
				<input id="api_id" type="number" name="api_id" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'api_id', true ) ); ?>">
			</p>
		</div>
		<?php
	}

	public function save_meta_box( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

		if ( $parent_id = wp_is_post_revision( $post_id ) ) {
			$post_id = $parent_id;
		}
		$fields = [
			'api_id',
		];

		$request = wp_remote_get( esc_url_raw(API_ENDPOINT) );

		if( is_wp_error( $request ) ) {
			return false;
		}

		$responseBody = wp_remote_retrieve_body( $request );

		$data = json_decode( $responseBody );


		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $_POST ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
				
				$key = array_search($_POST[$field], array_column($data, 'id'));

				if( $key ) {
					$fullname = $data[$key]->fullName;
					$imageUrl = $data[$key]->imageUrl;
					$imageName = $data[$key]->image;
				
					$post_update = array(
						'ID'         => $post_id,
						'post_title' => $fullname,
					);
					
					wp_update_post( $post_update );

					if( $imageUrl ) {
						$image_url        = $imageUrl;
						$image_name       = $imageName;
						$upload_dir       = wp_upload_dir(); // Set upload folder
						$image_data       = file_get_contents($image_url); // Get image data
						$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
						$filename         = basename( $unique_file_name ); // Create image file name

						if( wp_mkdir_p( $upload_dir['path'] ) ) {
							$file = $upload_dir['path'] . '/' . $filename;
						} else {
							$file = $upload_dir['basedir'] . '/' . $filename;
						}
						file_put_contents( $file, $image_data );
						$wp_filetype = wp_check_filetype( $filename, null );

						$attachment = array(
							'post_mime_type' => $wp_filetype['type'],
							'post_title'     => sanitize_file_name( $filename ),
							'post_content'   => '',
							'post_status'    => 'inherit'
						);

						$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

						require_once(ABSPATH . 'wp-admin/includes/image.php');

						$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

						wp_update_attachment_metadata( $attach_id, $attach_data );

						set_post_thumbnail( $post_id, $attach_id );
					}
				}
			}
		}

	}
}

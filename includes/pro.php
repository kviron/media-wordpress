<?php
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pro {

	public static $settings_post_types = 'happyfiles_post_types';

	public static $settings_license_key = 'happyfiles_license_key';
	public static $settings_license_status = 'happyfiles_license_status';
	public static $enabled_post_types = [];

	public static $settings_svg_support = 'happyfiles_svg_support';
	public static $svg_support = false;

	public function __construct() {
		// Get all enabled post types
		self::$enabled_post_types = get_option( self::$settings_post_types, [] );

		if ( ! is_array( self::$enabled_post_types ) ) {
			self::$enabled_post_types = [];
		}

		// Enable SVG uploads & sanitization
		self::$svg_support = get_option( self::$settings_svg_support, false );

		if ( self::$svg_support ) {
			require_once HAPPYFILES_PATH . 'includes/svg.php';
			$svg = new SVG();
		}

		add_action( 'init', [$this, 'register_taxonomy_for_posts_pages'] );

		add_action( 'admin_init', [$this, 'register_pro_settings'] );
		add_action( 'happyfiles_admin_settings_top', [$this, 'render_setting_post_types' ] );
		add_action( 'happyfiles_admin_settings_bottom', [$this, 'render_setting_svg_support_sanitization' ] );

		add_action( 'admin_footer-edit.php', [$this, 'render'] );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );

		// Set tax query on initial page load
		add_action( 'pre_get_posts', [$this, 'pre_get_posts'] );

		// Set tax query on every sequential category change
		add_action( 'parse_tax_query', [$this, 'parse_tax_query'] );

		add_action( 'restrict_manage_posts', [$this, 'add_categories_filter'] );

		add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check_for_update'] );

		add_action( 'wp_ajax_happyfiles_deactivate_license', [$this, 'deactivate_license'] );

		add_action( 'added_option', [$this, 'license_key_added'] );
		add_action( 'delete_option', [$this, 'license_key_delete'] );

		add_filter( 'plugins_api', [$this, 'plugin_info'], 20, 3 );
	}

	/**
	 * Send license activation data to happyfiles.io to add active site
	 *
	 * Fires after HappyFiles license key has been saved.
	 */
	public function license_key_added( $option_name ) {
		if ( $option_name !== self::$settings_license_key ) {
			return;
		}

		$license_key = get_option( self::$settings_license_key, '' );

		$response = wp_remote_post( 'https://happyfiles.io/api/happyfiles/activate_license', [
			'body' => [
				'license_key' => $license_key,
				'site'        => get_site_url(),
			],
		] );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $response_body['message'] ) ) {
			update_option( self::$settings_license_status, $response_body );
		}
	}

	/**
	 * Send license deactivation data to happyfiles.io to remove active site
	 *
	 * Fires before license key is being deleted.
	 */
	public function license_key_delete( $option_name ) {
		if ( $option_name !== self::$settings_license_key ) {
			return;
		}

		$license_key = get_option( self::$settings_license_key, '' );

		$response = wp_remote_post( 'https://happyfiles.io/api/happyfiles/deactivate_license', [
			'body' => [
				'license_key' => $license_key,
				'url'         => get_site_url(),
			],
		] );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$license_key_deleted = delete_option( self::$settings_license_status );
	}

	/**
	 * Show categories sidebar if post type is enabled
	 */
	public function render() {
		$post_type = Helpers::get_current_post_type();

		if ( in_array( $post_type, self::$enabled_post_types ) ) {
			Admin::render();
		}
	}

	public function register_pro_settings() {
		register_setting( HAPPYFILES_SETTINGS_GROUP, self::$settings_post_types );

		if ( ! get_option( self::$settings_license_key, false ) ) {
			register_setting( HAPPYFILES_SETTINGS_GROUP, self::$settings_license_key );
		}

		register_setting( HAPPYFILES_SETTINGS_GROUP, self::$settings_svg_support );
	}

	public function render_setting_post_types() {
		$license_status = get_option( self::$settings_license_status, [] );
		$license_key = get_option( self::$settings_license_key, '' );
		$license_key_encrypted = $license_key ? substr_replace( $license_key, 'XXXXXXXXXXXXXXXXXXXXXXXX', 4, strlen( $license_key ) - 8 ) : '';
		?>
		<tr>
			<th><?php esc_html_e( 'One Click Updates', 'happyfiles' ); ?></th>
			<td>
				<input name="<?php echo ! $license_key_encrypted ? esc_attr( self::$settings_license_key ) : ''; ?>" id="<?php echo ! $license_key_encrypted ? esc_attr( self::$settings_license_key ) : ''; ?>" type="text" placeholder="<?php echo esc_attr( $license_key_encrypted ); ?>" style="width: 320px; font-family: monospace;" spellcheck="false"<?php echo $license_key_encrypted ? ' readonly' : ''; ?>>
				<?php if ( $license_key_encrypted ) { ?>
				<input type="submit" name="happyfiles_deactivate_license" id="happyfiles_deactivate_license" class="button button-secondary" value="<?php esc_html_e( 'Deactivate License', 'happyfiles' ); ?>">
				<?php } ?>

				<?php if ( $license_key === '' ) { ?>
				<p class="description"><?php echo sprintf( esc_html__( 'Paste your license key from your %s in here to enable one-click plugin updates from your WordPress dashboard.', 'happyfiles' ), '<a href="https://happyfiles.io/account/" target="_blank">' . esc_html__( 'HappyFiles account', 'happyfiles' ) .'</a>' ); ?></p>
				<?php } ?>

				<?php if ( isset( $license_status['message'] ) ) { ?>
				<div class="message hf-<?php echo isset( $license_status['type'] ) ? $license_status['type'] : 'info'; ?>"><?php echo $license_status['message']; ?></div>
				<?php } ?>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Post Types', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
					<?php
					$registered_post_types = get_post_types( ['show_ui' => true], 'objects' );

					foreach ( $registered_post_types as $post_type ) {
						$post_type_name = $post_type->name;
						$post_type_label = $post_type->label;

						if ( in_array( $post_type_name, ['attachment', 'wp_block'] ) ) {
							continue;
						}
						?>
						<label for="<?php echo esc_attr( self::$settings_post_types . '_' . $post_type_name ); ?>">
						<input name="<?php echo esc_attr( self::$settings_post_types ); ?>[]" id="<?php echo esc_attr( self::$settings_post_types . '_' . $post_type_name ); ?>" type="checkbox" <?php checked( in_array( $post_type_name, self::$enabled_post_types ) ); ?> value="<?php echo esc_attr( $post_type_name ); ?>">
						<?php echo $post_type->label; ?>
						</label>
						<br>
					<?php } ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	public function render_setting_svg_support_sanitization() {
		?>
		<tr>
			<th><?php esc_html_e( 'Allow SVG Files', 'happyfiles' ); ?></th>
			<td>
				<fieldset>
					<label for="<?php echo esc_attr( self::$settings_svg_support ); ?>">
						<input type="checkbox" name="<?php echo esc_attr( self::$settings_svg_support ); ?>" id="<?php echo esc_attr( self::$settings_svg_support ); ?>" value="1" <?php checked( self::$svg_support, true, true ); ?>>
						<?php esc_html_e( 'Enable SVG Upload, Preview & Sanitization', 'happyfiles' ); ?>
					</label>
					<br>
					<p class="description"><?php esc_html_e( 'Although HappyFiles sanitizes your SVG file on upload, please only upload SVG files from trusted sources as they can contain and execute malicious XML code.', 'happyfiles' ); ?></p>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	public function register_taxonomy_for_posts_pages() {
		$post_types = is_array( self::$enabled_post_types ) ? self::$enabled_post_types : [];

		foreach ( $post_types as $post_type ) {
			$taxonomy = "hf_cat_$post_type"; // Tax max. length of 32 characters

			register_taxonomy(
				$taxonomy,
				[$post_type],
				[
					'labels' => [
						'name'               => esc_html__( 'Category', 'happyfiles' ),
						'singular_name'      => esc_html__( 'Category', 'happyfiles' ),
						'add_new_item'       => esc_html__( 'Add New Category', 'happyfiles' ),
						'edit_item'          => esc_html__( 'Edit Category', 'happyfiles' ),
						'new_item'           => esc_html__( 'Add New Category', 'happyfiles' ),
						'search_items'       => esc_html__( 'Search Category', 'happyfiles' ),
						'not_found'          => esc_html__( 'Category not found', 'happyfiles' ),
						'not_found_in_trash' => esc_html__( 'Category not found in trash', 'happyfiles' ),
					],
					'public'             => false,
					'publicly_queryable' => false,
					'hierarchical'       => true,
					'show_ui'            => true,
					'show_in_menu'       => false,
					'show_in_nav_menus'  => false,
					'show_in_quick_edit' => false,
					'show_admin_column'  => false,
					'rewrite'            => false,
					'update_count_callback' => '_update_generic_term_count', // Update term count for attachments
				]
			);
		}
	}

	public function enqueue_scripts() {
		$current_screen = get_current_screen();

		if (
			in_array( $current_screen->post_type, self::$enabled_post_types ) || // Post type screens
			$current_screen->base === 'settings_page_happyfiles_settings'        // HappyFiles settings page to deactivate license
		) {
			// wp_enqueue_style( 'happyfiles-pro', HAPPYFILES_ASSETS_URL . '/css/pro.min.css', [], filemtime( HAPPYFILES_ASSETS_PATH .'/css/pro.min.css' ) );
			wp_enqueue_script( 'happyfiles-pro', HAPPYFILES_ASSETS_URL . '/js/pro.js', ['jquery'], filemtime( HAPPYFILES_ASSETS_PATH .'/js/pro.js' ), true );
		}
	}

	public function pre_get_posts( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;

		// Return if we are not editing a post type
    if ( $pagenow !== 'edit.php' ) {
      return;
		}

		$post_type = $query->get( 'post_type' );
		$happyfiles_taxonomy = Helpers::get_taxonomy_by( 'post_type', $post_type );

		if ( ! in_array( $post_type, self::$enabled_post_types ) ) {
			return;
		}

		if ( ! $happyfiles_taxonomy ) {
			return;
		}

		// Get user selected category from stored user meta data
		$user_category_state = get_user_meta( get_current_user_id(), 'happyfiles_category_state', true );
		$open_category = isset( $user_category_state[$happyfiles_taxonomy] ) ? $user_category_state[$happyfiles_taxonomy] : [];

		// No or all categories selected
		if ( ! $open_category || $open_category === 'all' ) {
			return;
		}

		// Uncategorized
		if ( $open_category == '-1' ) {
			$tax_query = [
				[
					'taxonomy' => $happyfiles_taxonomy,
					'operator' => 'NOT EXISTS',
				]
			];
		}

		// Custom taxonomy term
		else {
			$tax_query = [
				[
					'taxonomy' 			   => $happyfiles_taxonomy,
					'field' 			     => 'term_id',
					'terms' 			     => [intval( $open_category )],
					'include_children' => false,
				],
			];
		}

		$query->set( 'tax_query', $tax_query );
	}

  /**
   * Exclude term children for non-AJAX taxonomy requests: List View
   *
   * https://core.trac.wordpress.org/ticket/18703#comment:10
   *
   * @param object $query Already parsed query object.
   * @return void
   */
  public function parse_tax_query( $query ) {
		global $pagenow, $typenow;

    // Return if we are not editing a post type
    if ( $pagenow !== 'edit.php' ) {
      return;
		}

		$post_type = $query->get( 'post_type' );

		if ( ! in_array( $post_type, self::$enabled_post_types ) ) {
			return;
		}

    if ( ! empty( $query->tax_query->queries ) ) {
      foreach ( $query->tax_query->queries as &$tax_query ) {
				$taxonomy = $tax_query['taxonomy'];

				if ( $taxonomy === Helpers::get_taxonomy_by( 'post_type', $typenow ) ) {
					$term_id = &$query->query_vars[$taxonomy];

					// All categories
					if ( $term_id == 'all' ) {
            $tax_query['operator'] = '';
          }

          // Uncategorized
          else if ( $term_id == '-1' ) {
            $tax_query['operator'] = 'NOT EXISTS';
          }

          else {
            $tax_query['include_children'] = false;
            $tax_query['field'] = 'id';
          }
        }
      }
    }
  }

  public function add_categories_filter() {
		global $pagenow, $typenow;

    if ( $pagenow !== 'edit.php' ) {
      return;
		}

		$post_type = get_post_type();

		if ( ! $post_type ) {
			$post_type = $typenow;
		}

		if ( ! in_array( $post_type, self::$enabled_post_types ) ) {
			return;
		}

		$taxonomy = Helpers::get_taxonomy_by( 'post_type', $post_type );
		$open_term_id = Helpers::get_open_category();

		// Check if taxonomy exists
		if ( $taxonomy ) {
			wp_dropdown_categories( [
				'class'            => 'happyfiles-filter',
				'show_option_all'  => sprintf( esc_html__( 'All %s Categories', 'happyfiles' ), ucwords( str_replace( '_', ' ', $post_type ) ) ),
				'show_option_none' => esc_html__( 'Uncategorized', 'happyfiles' ),
				'taxonomy'         => $taxonomy,
				'name'             => $taxonomy,
				'selected'         => $open_term_id,
				'hierarchical'     => false,
				'hide_empty'       => false,
			] );
		}
	}

	/**
	 * Check remotely if a newer version is available
	 *
	 * @param $transient Transient for WordPress theme updates.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function check_for_update( $transient ) {
		// 'checked' is an array with all installed plugins and their version numbers
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get HappyFiles Pro license key from HappyFiles settings
		$license_key = get_option( self::$settings_license_key, '' );

		if ( ! $license_key ) {
			return $transient;
		}

		// Build theme update request URL with license_key and domain parameters
		$get_update_data_url = add_query_arg(
			[
				'license_key' => $license_key,
				'domain'      => get_site_url(),
				'time'        => time(), // Don't cache response
			],
			'https://happyfiles.io/api/happyfiles/get_update_data/'
		);

		$request = wp_remote_get( $get_update_data_url, [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json'
			]
		] );

		// Check if remote GET request has been successful (better than using is_wp_error)
		if ( wp_remote_retrieve_response_code( $request ) !== 200 ) {
			return $transient;
		}

		$request = json_decode( wp_remote_retrieve_body( $request ), true );

		$license_status = isset( $request['license_status'] ) ? $request['license_status'] : false;

		update_option( self::$settings_license_status, $license_status );

		// STEP: Check for remote whitelist/blacklist/max_sites
		if ( isset( $license_status['type'] ) && $license_status['type'] === 'error' ) {
			return $transient;
		}

		if ( isset( $request['error'] ) ) {
			return $transient;
		}

		$installed_version = HAPPYFILES_VERSION;

		// Check remotely if newer version is available
		$latest_version = isset( $request['new_version'] ) ? $request['new_version'] : $installed_version;
		$newer_version_available = version_compare( $latest_version, $installed_version, '>' );

		if ( ! $newer_version_available ) {
			return $transient;
		}

		if ( isset( $request['license_status'] ) ) {
			unset( $request['license_status'] );
		}

		$request['icons'] = [
			'1x' => 'https://ps.w.org/happyfiles/assets/icon-256x256.png?rev=2361994',
		];

		$request['banners'] = [
			'1x' => 'https://ps.w.org/happyfiles/assets/banner-772x250.jpg?rev=2248756',
		];

		$request['slug'] = 'happyfiles-pro';
		$request['plugin'] = 'happyfiles-pro/happyfiles-pro.php';
		$request['tested'] = get_bloginfo( 'version' );

		// Save HappyFiles Pro update data in transient
		$transient->response['happyfiles-pro/happyfiles-pro.php'] = (object) $request;

		return $transient;
	}

	public function deactivate_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => esc_html__( 'Only the site admin can deactivate the HappyFiles PRO license.', 'happyfiles' )] );
		}

		$license_key_deleted = delete_option( self::$settings_license_key );

		if ( $license_key_deleted ) {
			wp_send_json_success( ['message' => esc_html__( 'License key has been deleted successfully.', 'happyfiles' )] );
		} else {
			wp_send_json_error( ['message' => esc_html__( 'Error: License key could not be deleted.', 'happyfiles' )] );
		}
	}

	/**
	 * Show HappyFiles Pro plugin data in update popup
	 */
	public function plugin_info( $res, $action, $args ) {
		// Return: Action is not about getting plugin information
		if ( $action !== 'plugin_information' ) {
			return $res;
		}

		$plugin_slug = 'happyfiles-pro';

		if ( $plugin_slug !== $args->slug ) {
			return $res;
		}

		$happyfiles_pro_data = wp_remote_get( 'https://happyfiles.io/api/happyfiles/get_pro_plugin_data?time=' . time(), [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json'
			]
		] );

		if ( is_wp_error( $happyfiles_pro_data ) ) {
			return;
		}

		$happyfiles_pro_data = json_decode( wp_remote_retrieve_body( $happyfiles_pro_data ), true );

		if ( ! is_array( $happyfiles_pro_data ) || ! count( $happyfiles_pro_data ) ) {
			return;
		}

		$res = new \stdClass();

		$res->slug = $plugin_slug;
		$res->name = $happyfiles_pro_data['name'];
		$res->version = $happyfiles_pro_data['version'];
		$res->tested = $happyfiles_pro_data['tested'];
		$res->requires = $happyfiles_pro_data['requires'];
		$res->author = $happyfiles_pro_data['author'];
		$res->requires_php = $happyfiles_pro_data['requires_php'];
		$res->last_updated = $happyfiles_pro_data['last_updated'];
		$res->sections = [
			'description'  => $happyfiles_pro_data['sections']['description'],
			'installation' => $happyfiles_pro_data['sections']['installation'],
			'releases'     => $happyfiles_pro_data['sections']['releases'],
			'screenshots'  => $happyfiles_pro_data['sections']['screenshots'],
		];

		$res->banners = $happyfiles_pro_data['banners'];

		return $res;
	}

}

new Pro();

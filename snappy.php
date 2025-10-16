<?php
/*
Plugin Name: Snappy
Plugin URI: https://snappywp.me/
Description: Caching for a snappier website.
Version: 0.1
Requires at least: 5.0
Requires PHP: 7.4
Author: Web Guy
Author URI: https://webguy.io/
License: GPL
License URI: https://www.gnu.org/licenses/gpl.html
Text Domain: snappy
*/

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

class Snappy {
	private $version = '0.1';
	private $option_name = 'snappy_settings';
	private $cache_dir;
	private $field_configs = array();
	private static $rate_limits = array();

	// =============================================================================
	// CORE SETUP
	// =============================================================================
	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'snappy/';
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'init_field_configs' ) );
		add_action( 'init', array( $this, 'maybe_serve_cache' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'save_post', array( $this, 'clear_page_cache' ) );
		add_action( 'wp_trash_post', array( $this, 'clear_page_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_page_cache' ) );
		add_action( 'comment_post', array( $this, 'clear_page_cache' ) );
		add_action( 'wp_set_comment_status', array( $this, 'clear_page_cache' ) );
	}

	public function init_field_configs() {
		$this->field_configs = array(
			'cache' => array(
				'section' => __( 'Cache Settings', 'snappy' ),
				'description' => __( 'Configure cache behavior and exclusions.', 'snappy' ),
				'fields' => array(
					'cache_duration' => array( 'type' => 'number', 'label' => __( 'Cache Duration (hours)', 'snappy' ), 'default' => 1, 'description' => __( 'When should the cache auto clear? (0 for never or every 1-999 hours).', 'snappy' ), 'min' => 0, 'max' => 999 ),
					'cache_exclude' => array( 'type' => 'textarea', 'label' => __( 'Exclude from Cache', 'snappy' ), 'default' => '', 'description' => __( 'IDs for posts or pages that should never be cached (one per line).', 'snappy' ), 'placeholder' => __( 'Enter post IDs, one per line', 'snappy' ) ),
					'cache_mobile' => array( 'type' => 'checkbox', 'label' => __( 'Mobile Cache', 'snappy' ), 'default' => '1', 'description' => __( 'Optimize for phones and tablets (recommended for sites with different mobile themes)', 'snappy' ) ),
					'cache_disabled' => array( 'type' => 'checkbox', 'label' => __( 'Disable Cache', 'snappy' ), 'default' => '0', 'description' => __( 'Turn off all caching (useful for troubleshooting)', 'snappy' ) )
				)
			)
		);
	}

	// =============================================================================
	// ADMIN INTERFACE
	// =============================================================================
	public function add_settings_page() {
		add_menu_page( 
			__( 'Snappy Cache', 'snappy' ), 
			__( 'Snappy', 'snappy' ), 
			'manage_options', 
			'snappy-cache', 
			array( $this, 'render_settings_page' ), 
			'dashicons-performance', 
			100 
		);
	}

	public function render_settings_page() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page', 'snappy' ) );
		}
		$cache_stats = $this->get_cache_stats();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'snappy_messages' ); ?>
			<form action="options.php" method="post">
				<?php 
				settings_fields( 'snappy_settings_group' ); 
				do_settings_sections( 'snappy-cache-settings' ); 
				submit_button( __( 'Save Settings', 'snappy' ) ); 
				?>
			</form>
			<br>
			<h2><?php esc_html_e( 'Cache Stats', 'snappy' ); ?></h2>
			<ul>
				<?php foreach ( $cache_stats as $label => $value ) : ?>
					<li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
				<?php endforeach; ?>
			</ul>
			<br>
			<h2><?php esc_html_e( 'Speed Optimization Tips', 'snappy' ); ?></h2>
			<ol>
				<li><strong><?php esc_html_e( 'Quality Hosting', 'snappy' ); ?></strong>: <?php esc_html_e( 'Choose managed WordPress hosting with SSD storage, built-in CDN, and server-level optimizations.', 'snappy' ); ?></li>
				<li><strong><?php esc_html_e( 'Image Optimization', 'snappy' ); ?></strong>: <?php esc_html_e( 'Compress and resize images before uploading, use next-generation formats like WebP.', 'snappy' ); ?></li>
				<li><strong><?php esc_html_e( 'Lightweight Theme', 'snappy' ); ?></strong>: <?php esc_html_e( 'Select a theme with clean, minimal code optimized for performance.', 'snappy' ); ?></li>
				<li><strong><?php esc_html_e( 'Plugin Audit', 'snappy' ); ?></strong>: <?php esc_html_e( 'Regularly audit plugins, remove unnecessary ones, and replace multiple plugins with all-in-one solutions.', 'snappy' ); ?></li>
			</ol>
		</div>
		<?php
	}

	public function register_settings() {
		register_setting( 'snappy_settings_group', $this->option_name, array( $this, 'sanitize_settings' ) );
		foreach ( $this->field_configs as $section_key => $section ) {
			$section_id = "snappy_{$section_key}_section";
			add_settings_section( 
				$section_id, 
				$section['section'], 
				array( $this, 'render_section' ), 
				'snappy-cache-settings' 
			);
			foreach ( $section['fields'] as $field_key => $field ) {
				add_settings_field( 
					"snappy_{$field_key}", 
					$field['label'], 
					array( $this, 'render_field' ), 
					'snappy-cache-settings', 
					$section_id, 
					array( 'key' => $field_key, 'config' => $field ) 
				);
			}
		}
	}

	public function render_section( $args ) {
		$section_key = str_replace( array( 'snappy_', '_section' ), '', $args['id'] );
		if ( isset( $this->field_configs[$section_key] ) ) {
			echo '<p>' . esc_html( $this->field_configs[$section_key]['description'] ) . '</p>';
			if ( 'cache' === $section_key ) {
				$this->render_action_buttons( $section_key );
			}
		}
	}

	private function render_action_buttons( $section_key ) {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'cache' === $section_key ) {
			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Actions', 'snappy' ); ?></th>
						<td>
							<form method="post" action="" style="display: inline;">
								<?php wp_nonce_field( 'snappy_clear_cache', 'snappy_clear_cache_nonce' ); ?>
								<input type="submit" name="snappy_clear_cache" class="button" value="<?php esc_attr_e( 'Clear Cache', 'snappy' ); ?>" />
							</form>
							<p class="description"><?php esc_html_e( 'Clear existing cache files.', 'snappy' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}

	public function render_field( $args ) {
		$options = get_option( $this->option_name );
		$key = $args['key'];
		$config = $args['config'];
		$value = isset( $options[$key] ) ? $options[$key] : $config['default'];
		$name = esc_attr( $this->option_name ) . "[{$key}]";
		$id = "snappy_{$key}";
		switch ( $config['type'] ) {
			case 'checkbox':
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', $value, false ) . ' />';
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $config['description'] ) . '</label>';
				break;
			case 'number':
				echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
				if ( isset( $config['min'] ) ) echo ' min="' . esc_attr( $config['min'] ) . '"';
				if ( isset( $config['max'] ) ) echo ' max="' . esc_attr( $config['max'] ) . '"';
				echo ' />';
				if ( isset( $config['description'] ) ) echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" cols="50"';
				if ( isset( $config['placeholder'] ) ) echo ' placeholder="' . esc_attr( $config['placeholder'] ) . '"';
				echo '>' . esc_textarea( $value ) . '</textarea>';
				if ( isset( $config['description'] ) ) echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
				break;
		}
	}

	public function sanitize_settings( $input ) {
		$new_input = array();
		foreach ( $this->field_configs as $section ) {
			foreach ( $section['fields'] as $key => $config ) {
				switch ( $config['type'] ) {
					case 'checkbox':
						$new_input[$key] = isset( $input[$key] ) ? '1' : '0';
						break;
					case 'number':
						$new_input[$key] = isset( $input[$key] ) ? min( max( absint( $input[$key] ), $config['min'] ?? 0 ), $config['max'] ?? 999 ) : $config['default'];
						break;
					case 'textarea':
						if ( isset( $input[$key] ) ) {
							if ( 'cache_exclude' === $key ) {
								$lines = explode( "\n", $input[$key] );
								$sanitized = array();
								foreach ( $lines as $line ) {
									$line = trim( $line );
									if ( is_numeric( $line ) && absint( $line ) > 0 ) $sanitized[] = absint( $line );
								}
								$new_input[$key] = implode( "\n", $sanitized );
							} else {
								$new_input[$key] = sanitize_textarea_field( $input[$key] );
							}
						} else {
							$new_input[$key] = '';
						}
						break;
				}
			}
		}
		if ( !isset( $_POST['snappy_clear_cache'] ) ) {
			add_settings_error( 'snappy_messages', 'snappy_message', __( 'Settings saved successfully', 'snappy' ), 'updated' );
		}
		return $new_input;
	}

	public function handle_admin_actions() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$actions = array( 'clear_cache' );
		foreach ( $actions as $action ) {
			if ( isset( $_POST["snappy_{$action}"] ) && 
				 isset( $_POST["snappy_{$action}_nonce"] ) && 
				 wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST["snappy_{$action}_nonce"] ) ), "snappy_{$action}" ) ) {
				$this->{"handle_{$action}"}();
			}
		}
	}

	// =============================================================================
	// CACHE FUNCTIONALITY
	// =============================================================================
	private function get_cache_key() {
		$key_parts = array();
		$key_parts[] = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$key_parts[] = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		$options = get_option( $this->option_name );
		if ( isset( $options['cache_mobile'] ) && $options['cache_mobile'] === '1' ) {
			$key_parts[] = wp_is_mobile() ? 'mobile' : 'desktop';
		}
		$key_parts[] = is_user_logged_in() ? 'logged_in' : 'guest';
		return md5( implode( '|', $key_parts ) );
	}

	private function should_cache() {
		static $cache_result = null;
		if ( $cache_result !== null ) {
			return $cache_result;
		}
		$options = get_option( $this->option_name );
		if ( isset( $options['cache_disabled'] ) && $options['cache_disabled'] === '1' ) {
			return $cache_result = false;
		}
		if ( is_user_logged_in() || 
			 is_admin() || 
			 is_404() || 
			 is_search() || 
			 is_preview() || 
			 defined( 'DOING_AJAX' ) || 
			 defined( 'DOING_CRON' ) || 
			 defined( 'WP_CLI' ) || 
			 defined( 'REST_REQUEST' ) ) {
			return $cache_result = false;
		}
		global $pagenow;
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? 
			sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ( isset( $pagenow ) && $pagenow === 'wp-login.php' ) ||
			 ( isset( $_GET['action'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['action'] ) ), array( 'login', 'logout', 'register', 'lostpassword', 'resetpass', 'rp', 'postpass' ), true ) ) ||
			 ( strpos( $request_uri, 'wp-login' ) !== false ) ||
			 ( strpos( $request_uri, 'login' ) !== false && strpos( $request_uri, 'wp-content' ) === false ) ) {
			return $cache_result = false;
		}
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? 
			sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( $request_method !== 'GET' ) {
			return $cache_result = false;
		}
		if ( strpos( $request_uri, '/wp-json' ) !== false ) {
			return $cache_result = false;
		}
		if ( strpos( $request_uri, '?nocache' ) !== false || 
			 strpos( $request_uri, '&nocache' ) !== false ) {
			return $cache_result = false;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_functions = array( 'is_cart', 'is_checkout', 'is_account_page', 'is_wc_endpoint_url' );
			foreach ( $wc_functions as $func ) {
				if ( function_exists( $func ) && call_user_func( $func ) ) {
					return $cache_result = false;
				}
			}
		}
		if ( ( is_single() || is_page() ) ) {
			$current_post_id = get_the_ID();
			if ( $current_post_id ) {
				$cache_exclude = isset( $options['cache_exclude'] ) ? 
					array_map( 'absint', array_filter( explode( "\n", $options['cache_exclude'] ) ) ) : 
					array();
				if ( in_array( $current_post_id, $cache_exclude, true ) ) {
					return $cache_result = false;
				}
			}
		}
		return $cache_result = true;
	}

	public function maybe_serve_cache() {
		if ( !$this->should_cache() || !$this->ensure_cache_dir_writable() ) return;
		$cache_key = $this->get_cache_key();
		$cache_file = $this->cache_dir . $cache_key . '.html';
		$lock_file = $cache_file . '.lock';
		$options = get_option( $this->option_name );
		$cache_duration_hours = isset( $options['cache_duration'] ) ? absint( $options['cache_duration'] ) : 1;
		if ( 0 === $cache_duration_hours ) {
			if ( file_exists( $cache_file ) && !file_exists( $lock_file ) ) {
				$this->increment_cache_hits();
				include $cache_file;
				exit;
			}
		} else {
			$cache_duration = $cache_duration_hours * HOUR_IN_SECONDS;
			if ( file_exists( $cache_file ) && 
				 !file_exists( $lock_file ) && 
				 ( time() - filemtime( $cache_file ) ) < $cache_duration ) {
				$this->increment_cache_hits();
				include $cache_file;
				exit;
			}
		}
		$this->increment_cache_misses();
		if ( file_exists( $lock_file ) && ( time() - filemtime( $lock_file ) ) < 30 ) return;
		$filesystem = $this->get_filesystem();
		if ( !$filesystem || !$filesystem->touch( $lock_file ) ) return;
		ob_start( function( $buffer ) use ( $cache_file, $lock_file ) {
			$filesystem = $this->get_filesystem();
			if ( $filesystem && !empty( $buffer ) ) {
				$filesystem->put_contents( $cache_file, $buffer );
			}
			if ( file_exists( $lock_file ) ) {
				wp_delete_file( $lock_file );
			}
			return $buffer;
		} );
		register_shutdown_function( function() use ( $lock_file ) {
			if ( ob_get_length() > 0 ) ob_end_flush();
			if ( file_exists( $lock_file ) ) {
				wp_delete_file( $lock_file );
			}
		} );
	}

	public function clear_page_cache( $post_id = null ) {
		if ( null === $post_id ) {
			return $this->clear_all_cache();
		}
		$post = get_post( $post_id );
		if ( $post ) {
			$this->clear_specific_cache( $post );
			$this->clear_home_cache();
		}
	}

	private function clear_specific_cache( $post ) {
		$permalink = get_permalink( $post );
		$parsed_url = wp_parse_url( $permalink );
		$path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
		$query = isset( $parsed_url['query'] ) ? $parsed_url['query'] : '';
		$options = get_option( $this->option_name );
		$cache_mobile = isset( $options['cache_mobile'] ) && $options['cache_mobile'] === '1';
		if ( $cache_mobile ) {
			$files_to_remove = array( 
				$this->cache_dir . md5( $path . '|' . $query . '|mobile|guest' ) . '.html', 
				$this->cache_dir . md5( $path . '|' . $query . '|desktop|guest' ) . '.html' 
			);
		} else {
			$files_to_remove = array( 
				$this->cache_dir . md5( $path . '|' . $query . '|guest' ) . '.html' 
			);
		}
		foreach ( $files_to_remove as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	private function clear_home_cache() {
		$options = get_option( $this->option_name );
		$cache_mobile = isset( $options['cache_mobile'] ) && $options['cache_mobile'] === '1';
		$home_keys = $cache_mobile ? 
			array( md5( '/||mobile|guest' ), md5( '/||desktop|guest' ) ) : 
			array( md5( '/||guest' ) );
		foreach ( $home_keys as $key ) {
			$cache_file = $this->cache_dir . $key . '.html';
			if ( file_exists( $cache_file ) ) {
				wp_delete_file( $cache_file );
			}
		}
	}

	public function clear_all_cache() {
		$cleared_files = 0;
		if ( !file_exists( $this->cache_dir ) ) return $cleared_files;
		$files = glob( $this->cache_dir . '*.html' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) && wp_delete_file( $file ) ) {
					$cleared_files++;
				}
			}
		}
		update_option( 'snappy_cache_hits', 0 );
		update_option( 'snappy_cache_misses', 0 );
		return $cleared_files;
	}

	private function handle_clear_cache() {
		if ( !$this->check_rate_limit( 'clear_cache', 60 ) ) {
			add_settings_error( 'snappy_messages', 'rate_limit', __( 'Too many requests (please wait before clearing cache again)', 'snappy' ), 'error' );
			return;
		}
		$cleared_files = $this->clear_all_cache();
		add_settings_error( 'snappy_messages', 'snappy_message', sprintf( __( 'Cache cleared successfully (%d files cleared)', 'snappy' ), $cleared_files ), 'updated' );
	}

	private function ensure_cache_dir_writable() {
		if ( !file_exists( $this->cache_dir ) && !wp_mkdir_p( $this->cache_dir ) ) {
			return false;
		}
		$filesystem = $this->get_filesystem();
		if ( !$filesystem || !$filesystem->is_writable( $this->cache_dir ) ) {
			return false;
		}
		$htaccess_file = $this->cache_dir . '.htaccess';
		if ( !file_exists( $htaccess_file ) ) {
			$htaccess_content = "Order deny,allow\nDeny from all\n<Files ~ \"\\.html$\">\nAllow from all\n</Files>";
			$filesystem->put_contents( $htaccess_file, $htaccess_content );
		}
		return true;
	}

	private function increment_cache_hits() {
		update_option( 'snappy_cache_hits', get_option( 'snappy_cache_hits', 0 ) + 1 );
	}

	private function increment_cache_misses() {
		update_option( 'snappy_cache_misses', get_option( 'snappy_cache_misses', 0 ) + 1 );
	}

	private function get_filesystem() {
		global $wp_filesystem;
		if ( !$wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem;
	}

	private function get_cache_stats() {
		$stats = array();
		$stats[__( 'Cache Size', 'snappy' )] = '0 MB';
		$stats[__( 'Cached Files', 'snappy' )] = 0;
		$stats[__( 'Cache Hit Ratio', 'snappy' )] = '0%';
		if ( file_exists( $this->cache_dir ) ) {
			$files = glob( $this->cache_dir . '*.html' );
			if ( $files ) {
				$stats[__( 'Cached Files', 'snappy' )] = count( $files );
				$total_size = 0;
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						$total_size += filesize( $file );
					}
				}
				$stats[__( 'Cache Size', 'snappy' )] = size_format( $total_size );
			}
		}
		$cache_hits = get_option( 'snappy_cache_hits', 0 );
		$cache_misses = get_option( 'snappy_cache_misses', 0 );
		$total_requests = $cache_hits + $cache_misses;
		if ( $total_requests > 0 ) {
			$hit_ratio = round( ( $cache_hits / $total_requests ) * 100, 1 );
			$stats[__( 'Cache Hit Ratio', 'snappy' )] = $hit_ratio . '%';
		}
		return $stats;
	}

	// =============================================================================
	// UTILITY FUNCTIONS
	// =============================================================================
	private function check_rate_limit( $action, $timeout ) {
		$current_time = time();
		$rate_limit_key = $action . '_' . get_current_user_id();
		if ( isset( self::$rate_limits[$rate_limit_key] ) && 
			 ( $current_time - self::$rate_limits[$rate_limit_key] ) < $timeout ) {
			return false;
		}
		self::$rate_limits[$rate_limit_key] = $current_time;
		return true;
	}

	// =============================================================================
	// PLUGIN LIFECYCLE
	// =============================================================================
	public static function activate() {
		$upload_dir = wp_upload_dir();
		$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'snappy/';
		if ( !file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
		$default_options = array( 
			'cache_duration' => 1,
			'cache_exclude' => '',
			'cache_mobile' => '1',
			'cache_disabled' => '0'
		);
		add_option( 'snappy_settings', $default_options );
		add_option( 'snappy_cache_hits', 0 );
		add_option( 'snappy_cache_misses', 0 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$upload_dir = wp_upload_dir();
		$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'snappy/';
		if ( file_exists( $cache_dir ) ) {
			$files = glob( $cache_dir . '*' );
			if ( $files ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}
			}
			global $wp_filesystem;
			if ( !$wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem && is_dir( $cache_dir ) ) {
				$wp_filesystem->rmdir( $cache_dir );
			}
		}
		flush_rewrite_rules();
	}

	public static function uninstall() {
		$upload_dir = wp_upload_dir();
		$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'snappy/';
		if ( file_exists( $cache_dir ) ) {
			$files = glob( $cache_dir . '*' );
			if ( $files ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}
			}
			global $wp_filesystem;
			if ( !$wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem && is_dir( $cache_dir ) ) {
				$wp_filesystem->rmdir( $cache_dir );
			}
		}
		delete_option( 'snappy_settings' );
		delete_option( 'snappy_cache_hits' );
		delete_option( 'snappy_cache_misses' );
		flush_rewrite_rules();
	}
}

function snappy_init() {
	new Snappy();
}

add_action( 'plugins_loaded', 'snappy_init' );
register_activation_hook( __FILE__, array( 'Snappy', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Snappy', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Snappy', 'uninstall' ) );
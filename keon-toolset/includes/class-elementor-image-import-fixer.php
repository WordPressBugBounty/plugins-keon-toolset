<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Keon_Toolset_Elementor_Image_Import_Fixer' ) ) {
	/**
	 * Fixes Elementor image IDs/URLs after Advanced Import mapping.
	 */
	class Keon_Toolset_Elementor_Image_Import_Fixer {

		/**
		 * Option key for imported map snapshot.
		 *
		 * @var string
		 */
		const MAP_OPTION = 'keon_toolset_ai_imported_post_ids_snapshot';

		/**
		 * Option key for imported posts with Elementor data.
		 *
		 * @var string
		 */
		const POSTS_OPTION = 'keon_toolset_ai_elementor_posts';

		/**
		 * Re-entry guard.
		 *
		 * @var array<int, bool>
		 */
		private static $processing = array();

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'advanced_import_post_data', array( __CLASS__, 'capture_import_state' ), 999 );
			add_action( 'added_post_meta', array( __CLASS__, 'on_elementor_meta_write' ), 10, 4 );
			add_action( 'updated_post_meta', array( __CLASS__, 'on_elementor_meta_write' ), 10, 4 );
			add_action( 'advanced_import_before_complete_screen', array( __CLASS__, 'final_repair_pass' ), 5 );
			add_action( 'advanced_import_after_complete_screen', array( __CLASS__, 'cleanup_state' ), 99 );
		}

		/**
		 * Keep a snapshot while import is running.
		 *
		 * @param array $post_data Current post data.
		 * @return array
		 */
		public static function capture_import_state( $post_data ) {
			if ( ! is_array( $post_data ) ) {
				return $post_data;
			}

			$map = self::get_live_map();
			if ( ! empty( $map ) ) {
				update_option( self::MAP_OPTION, $map, false );
			}

			if ( isset( $post_data['post_id'], $post_data['meta']['_elementor_data'] ) ) {
				$posts = get_option( self::POSTS_OPTION, array() );
				if ( ! is_array( $posts ) ) {
					$posts = array();
				}
				$old_post_id = absint( $post_data['post_id'] );
				if ( $old_post_id > 0 && ! in_array( $old_post_id, $posts, true ) ) {
					$posts[] = $old_post_id;
					update_option( self::POSTS_OPTION, $posts, false );
				}
			}

			return $post_data;
		}

		/**
		 * Repair meta as soon as _elementor_data is written.
		 *
		 * @param int    $meta_id    Meta ID.
		 * @param int    $post_id    Post ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 * @return void
		 */
		public static function on_elementor_meta_write( $meta_id, $post_id, $meta_key, $meta_value ) {
			unset( $meta_id );

			if ( '_elementor_data' !== $meta_key ) {
				return;
			}

			$post_id = absint( $post_id );
			if ( $post_id <= 0 || isset( self::$processing[ $post_id ] ) ) {
				return;
			}

			$map = self::get_effective_map();
			if ( empty( $map ) ) {
				return;
			}

			$decoded = self::decode_elementor_data( $meta_value );
			if ( ! is_array( $decoded ) ) {
				return;
			}

			self::$processing[ $post_id ] = true;
			$changed                       = self::repair_node( $decoded, $map, '' );
			if ( $changed ) {
				update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $decoded ) ) );
				self::regenerate_css( $post_id );
			}
			unset( self::$processing[ $post_id ] );
		}

		/**
		 * Final pass before import complete screen.
		 *
		 * @return void
		 */
		public static function final_repair_pass() {
			$map   = self::get_effective_map();
			$posts = get_option( self::POSTS_OPTION, array() );

			if ( empty( $map ) || ! is_array( $posts ) || empty( $posts ) ) {
				return;
			}

			foreach ( $posts as $old_post_id ) {
				$old_post_id = absint( $old_post_id );
				if ( $old_post_id <= 0 || empty( $map[ $old_post_id ] ) ) {
					continue;
				}

				$new_post_id = absint( $map[ $old_post_id ] );
				if ( $new_post_id <= 0 || isset( self::$processing[ $new_post_id ] ) ) {
					continue;
				}

				$current = get_post_meta( $new_post_id, '_elementor_data', true );
				$decoded = self::decode_elementor_data( $current );
				if ( ! is_array( $decoded ) ) {
					continue;
				}

				self::$processing[ $new_post_id ] = true;
				$changed                          = self::repair_node( $decoded, $map, '' );
				if ( $changed ) {
					update_post_meta( $new_post_id, '_elementor_data', wp_slash( wp_json_encode( $decoded ) ) );
					self::regenerate_css( $new_post_id );
				}
				unset( self::$processing[ $new_post_id ] );
			}
		}

		/**
		 * Cleanup import state.
		 *
		 * @return void
		 */
		public static function cleanup_state() {
			delete_option( self::MAP_OPTION );
			delete_option( self::POSTS_OPTION );
		}

		/**
		 * Decode Elementor data from string or array.
		 *
		 * @param mixed $raw Raw meta value.
		 * @return array|null
		 */
		private static function decode_elementor_data( $raw ) {
			if ( is_array( $raw ) ) {
				return $raw;
			}

			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}

			$decoded = json_decode( wp_unslash( $raw ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}

			$decoded = maybe_unserialize( $raw );
			return is_array( $decoded ) ? $decoded : null;
		}

		/**
		 * Get live imported map from Advanced Import helper.
		 *
		 * @return array
		 */
		private static function get_live_map() {
			if ( ! function_exists( 'advanced_import_admin' ) ) {
				return array();
			}

			$map = advanced_import_admin()->imported_post_id();
			return is_array( $map ) ? $map : array();
		}

		/**
		 * Get map from live import or option snapshot.
		 *
		 * @return array
		 */
		private static function get_effective_map() {
			$map = self::get_live_map();
			if ( ! empty( $map ) ) {
				update_option( self::MAP_OPTION, $map, false );
				return $map;
			}

			$stored = get_option( self::MAP_OPTION, array() );
			return is_array( $stored ) ? $stored : array();
		}

		/**
		 * Recursively remap IDs/URLs in Elementor data.
		 *
		 * @param mixed  $node       Node.
		 * @param array  $map        Old->new map.
		 * @param string $currentKey Current key in parent context.
		 * @return bool
		 */
		private static function repair_node( &$node, array $map, $currentKey ) {
			if ( ! is_array( $node ) ) {
				return false;
			}

			$changed = false;

			if ( isset( $node['id'] ) && isset( $node['url'] ) ) {
				$old_id = absint( $node['id'] );
				if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
					$new_id = absint( $map[ $old_id ] );
					if ( $new_id > 0 && $new_id !== $old_id ) {
						$node['id'] = $new_id;
						$new_url    = wp_get_attachment_url( $new_id );
						if ( $new_url ) {
							$node['url'] = $new_url;
						}
						$changed = true;
					}
				}

				if ( is_string( $node['url'] ) ) {
					$mapped_url = self::map_url( $node['url'], $map );
					if ( $mapped_url && $mapped_url !== $node['url'] ) {
						$node['url'] = $mapped_url;
						$changed     = true;
					}
				}
			}

			foreach ( $node as $key => &$value ) {
				if ( is_array( $value ) ) {
					if ( self::repair_node( $value, $map, (string) $key ) ) {
						$changed = true;
					}
					continue;
				}

				if ( self::is_attachment_id_key( (string) $key ) && is_numeric( $value ) ) {
					$old_id = absint( $value );
					if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
						$new_id = absint( $map[ $old_id ] );
						if ( $new_id > 0 && $new_id !== $old_id ) {
							$value   = $new_id;
							$changed = true;
						}
					}
				}

				if ( is_string( $value ) ) {
					$mapped_url = self::map_url( $value, $map );
					if ( $mapped_url && $mapped_url !== $value ) {
						$value   = $mapped_url;
						$changed = true;
						continue;
					}

					if ( 'id' === (string) $key && is_numeric( $value ) && self::is_attachment_id_key( $currentKey ) ) {
						$old_id = absint( $value );
						if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
							$new_id = absint( $map[ $old_id ] );
							if ( $new_id > 0 && $new_id !== $old_id ) {
								$value   = (string) $new_id;
								$changed = true;
							}
						}
					}
				}
			}
			unset( $value );

			return $changed;
		}

		/**
		 * Whether a key likely stores an attachment ID.
		 *
		 * @param string $key Key name.
		 * @return bool
		 */
		private static function is_attachment_id_key( $key ) {
			$key = strtolower( $key );

			if ( in_array( $key, array( 'id', 'image_id', 'thumbnail_id', 'background_image', 'attachment_id' ), true ) ) {
				return true;
			}

			return (bool) preg_match( '/(^|_)(image|thumbnail|logo|background|icon|poster)_id$/', $key );
		}

		/**
		 * Map old URL to new URL from import map.
		 * Handles exact match and Elementor size suffix variants.
		 *
		 * @param string $url Old URL.
		 * @param array  $map Import map.
		 * @return string|null
		 */
		private static function map_url( $url, array $map ) {
			if ( '' === $url ) {
				return null;
			}

			if ( isset( $map[ $url ] ) && is_string( $map[ $url ] ) ) {
				return $map[ $url ];
			}

			$raw_url = wp_unslash( $url );
			if ( isset( $map[ $raw_url ] ) && is_string( $map[ $raw_url ] ) ) {
				return $map[ $raw_url ];
			}

			if ( preg_match( '/^(.*?)(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', $raw_url, $m ) ) {
				$base_with_ext = $m[1] . $m[3];
				if ( isset( $map[ $base_with_ext ] ) && is_string( $map[ $base_with_ext ] ) ) {
					$new_base = $map[ $base_with_ext ];
					if ( preg_match( '/^(.*?)(\.[a-zA-Z0-9]+)$/', $new_base, $new_parts ) ) {
						return $new_parts[1] . $m[2] . $new_parts[2];
					}
				}
			}

			return null;
		}

		/**
		 * Regenerate Elementor CSS for repaired post.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		private static function regenerate_css( $post_id ) {
			if ( ! class_exists( 'Elementor\Core\Files\CSS\Post' ) ) {
				return;
			}

			$post_css = new Elementor\Core\Files\CSS\Post( $post_id );
			$post_css->update();
		}
	}
}
// End of class file.
return;
 
//
	/**
	 * Fixes Elementor image IDs/URLs after Advanced Import mapping.
	 */
	class Keon_Toolset_Elementor_Image_Import_Fixer {

		/**
		 * Option key for imported map snapshot.
		 *
		 * @var string
		 */
		const MAP_OPTION = 'keon_toolset_ai_imported_post_ids_snapshot';

		/**
		 * Option key for imported posts with Elementor data.
		 *
		 * @var string
		 */
		const POSTS_OPTION = 'keon_toolset_ai_elementor_posts';

		/**
		 * Re-entry guard.
		 *
		 * @var array<int, bool>
		 */
		private static $processing = array();

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'advanced_import_post_data', array( __CLASS__, 'capture_import_state' ), 999 );
			add_action( 'added_post_meta', array( __CLASS__, 'on_elementor_meta_write' ), 10, 4 );
			add_action( 'updated_post_meta', array( __CLASS__, 'on_elementor_meta_write' ), 10, 4 );
			add_action( 'advanced_import_before_complete_screen', array( __CLASS__, 'final_repair_pass' ), 5 );
			add_action( 'advanced_import_after_complete_screen', array( __CLASS__, 'cleanup_state' ), 99 );
		}

		/**
		 * Keep a snapshot while import is running.
		 *
		 * @param array $post_data Current post data.
		 * @return array
		 */
		public static function capture_import_state( $post_data ) {
			if ( ! is_array( $post_data ) ) {
				return $post_data;
			}

			$map = self::get_live_map();
			if ( ! empty( $map ) ) {
				update_option( self::MAP_OPTION, $map, false );
			}

			if ( isset( $post_data['post_id'], $post_data['meta']['_elementor_data'] ) ) {
				$posts = get_option( self::POSTS_OPTION, array() );
				if ( ! is_array( $posts ) ) {
					$posts = array();
				}
				$old_post_id = absint( $post_data['post_id'] );
				if ( $old_post_id > 0 && ! in_array( $old_post_id, $posts, true ) ) {
					$posts[] = $old_post_id;
					update_option( self::POSTS_OPTION, $posts, false );
				}
			}

			return $post_data;
		}

		/**
		 * Repair meta as soon as _elementor_data is written.
		 *
		 * @param int    $meta_id    Meta ID.
		 * @param int    $post_id    Post ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 * @return void
		 */
		public static function on_elementor_meta_write( $meta_id, $post_id, $meta_key, $meta_value ) {
			unset( $meta_id );

			if ( '_elementor_data' !== $meta_key ) {
				return;
			}

			$post_id = absint( $post_id );
			if ( $post_id <= 0 || isset( self::$processing[ $post_id ] ) ) {
				return;
			}

			$map = self::get_effective_map();
			if ( empty( $map ) ) {
				return;
			}

			$decoded = self::decode_elementor_data( $meta_value );
			if ( ! is_array( $decoded ) ) {
				return;
			}

			self::$processing[ $post_id ] = true;
			$changed                       = self::repair_node( $decoded, $map, '' );
			if ( $changed ) {
				// Store as JSON to align with Elementor expected format.
				update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $decoded ) ) );
				self::regenerate_css( $post_id );
			}
			unset( self::$processing[ $post_id ] );
		}

		/**
		 * Final pass before import complete screen.
		 *
		 * @return void
		 */
		public static function final_repair_pass() {
			$map   = self::get_effective_map();
			$posts = get_option( self::POSTS_OPTION, array() );

			if ( empty( $map ) || ! is_array( $posts ) || empty( $posts ) ) {
				return;
			}

			foreach ( $posts as $old_post_id ) {
				$old_post_id = absint( $old_post_id );
				if ( $old_post_id <= 0 || empty( $map[ $old_post_id ] ) ) {
					continue;
				}

				$new_post_id = absint( $map[ $old_post_id ] );
				if ( $new_post_id <= 0 || isset( self::$processing[ $new_post_id ] ) ) {
					continue;
				}

				$current = get_post_meta( $new_post_id, '_elementor_data', true );
				$decoded = self::decode_elementor_data( $current );
				if ( ! is_array( $decoded ) ) {
					continue;
				}

				self::$processing[ $new_post_id ] = true;
				$changed                          = self::repair_node( $decoded, $map, '' );
				if ( $changed ) {
					update_post_meta( $new_post_id, '_elementor_data', wp_slash( wp_json_encode( $decoded ) ) );
					self::regenerate_css( $new_post_id );
				}
				unset( self::$processing[ $new_post_id ] );
			}
		}

		/**
		 * Cleanup import state.
		 *
		 * @return void
		 */
		public static function cleanup_state() {
			delete_option( self::MAP_OPTION );
			delete_option( self::POSTS_OPTION );
		}

		/**
		 * Decode Elementor data from string or array.
		 *
		 * @param mixed $raw Raw meta value.
		 * @return array|null
		 */
		private static function decode_elementor_data( $raw ) {
			if ( is_array( $raw ) ) {
				return $raw;
			}

			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}

			$decoded = json_decode( wp_unslash( $raw ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}

			$decoded = maybe_unserialize( $raw );
			return is_array( $decoded ) ? $decoded : null;
		}

		/**
		 * Get live imported map from Advanced Import transient helper.
		 *
		 * @return array
		 */
		private static function get_live_map() {
			if ( ! function_exists( 'advanced_import_admin' ) ) {
				return array();
			}

			$map = advanced_import_admin()->imported_post_id();
			return is_array( $map ) ? $map : array();
		}

		/**
		 * Get map from live import or option snapshot.
		 *
		 * @return array
		 */
		private static function get_effective_map() {
			$map = self::get_live_map();
			if ( ! empty( $map ) ) {
				update_option( self::MAP_OPTION, $map, false );
				return $map;
			}

			$stored = get_option( self::MAP_OPTION, array() );
			return is_array( $stored ) ? $stored : array();
		}

		/**
		 * Recursively remap IDs/URLs in Elementor data.
		 *
		 * @param mixed  $node       Node.
		 * @param array  $map        Old->new map.
		 * @param string $currentKey Current key in parent context.
		 * @return bool
		 */
		private static function repair_node( &$node, array $map, $currentKey ) {
			if ( ! is_array( $node ) ) {
				return false;
			}

			$changed = false;

			// Primary Elementor media shape.
			if ( isset( $node['id'] ) && isset( $node['url'] ) ) {
				$old_id = absint( $node['id'] );
				if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
					$new_id = absint( $map[ $old_id ] );
					if ( $new_id > 0 && $new_id !== $old_id ) {
						$node['id'] = $new_id;
						$new_url    = wp_get_attachment_url( $new_id );
						if ( $new_url ) {
							$node['url'] = $new_url;
						}
						$changed = true;
					}
				}

				if ( is_string( $node['url'] ) ) {
					$mapped_url = self::map_url( $node['url'], $map );
					if ( $mapped_url && $mapped_url !== $node['url'] ) {
						$node['url'] = $mapped_url;
						$changed     = true;
					}
				}
			}

			foreach ( $node as $key => &$value ) {
				if ( is_array( $value ) ) {
					if ( self::repair_node( $value, $map, (string) $key ) ) {
						$changed = true;
					}
					continue;
				}

				if ( self::is_attachment_id_key( (string) $key ) && is_numeric( $value ) ) {
					$old_id = absint( $value );
					if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
						$new_id = absint( $map[ $old_id ] );
						if ( $new_id > 0 && $new_id !== $old_id ) {
							$value   = $new_id;
							$changed = true;
						}
					}
				}

				if ( is_string( $value ) ) {
					$mapped_url = self::map_url( $value, $map );
					if ( $mapped_url && $mapped_url !== $value ) {
						$value   = $mapped_url;
						$changed = true;
						continue;
					}

					if ( 'id' === (string) $key && is_numeric( $value ) && self::is_attachment_id_key( $currentKey ) ) {
						$old_id = absint( $value );
						if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
							$new_id = absint( $map[ $old_id ] );
							if ( $new_id > 0 && $new_id !== $old_id ) {
								$value   = (string) $new_id;
								$changed = true;
							}
						}
					}
				}
			}
			unset( $value );

			return $changed;
		}

		/**
		 * Whether a key likely stores an attachment ID.
		 *
		 * @param string $key Key name.
		 * @return bool
		 */
		private static function is_attachment_id_key( $key ) {
			$key = strtolower( $key );

			if ( in_array( $key, array( 'id', 'image_id', 'thumbnail_id', 'background_image', 'attachment_id' ), true ) ) {
				return true;
			}

			return (bool) preg_match( '/(^|_)(image|thumbnail|logo|background|icon|poster)_id$/', $key );
		}

		/**
		 * Map old URL to new URL from import map.
		 * Handles exact match and Elementor size suffix variants.
		 *
		 * @param string $url Old URL.
		 * @param array  $map Import map.
		 * @return string|null
		 */
		private static function map_url( $url, array $map ) {
			if ( '' === $url ) {
				return null;
			}

			if ( isset( $map[ $url ] ) && is_string( $map[ $url ] ) ) {
				return $map[ $url ];
			}

			$raw_url = wp_unslash( $url );
			if ( isset( $map[ $raw_url ] ) && is_string( $map[ $raw_url ] ) ) {
				return $map[ $raw_url ];
			}

			// Match "-123x456" sized image variants and rebuild mapped URL.
			if ( preg_match( '/^(.*?)(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', $raw_url, $m ) ) {
				$base_with_ext = $m[1] . $m[3];
				if ( isset( $map[ $base_with_ext ] ) && is_string( $map[ $base_with_ext ] ) ) {
					$new_base = $map[ $base_with_ext ];
					if ( preg_match( '/^(.*?)(\.[a-zA-Z0-9]+)$/', $new_base, $new_parts ) ) {
						return $new_parts[1] . $m[2] . $new_parts[2];
					}
				}
			}

			return null;
		}

		/**
		 * Regenerate Elementor CSS for repaired post.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		private static function regenerate_css( $post_id ) {
			if ( ! class_exists( 'Elementor\Core\Files\CSS\Post' ) ) {
				return;
			}

			$post_css = new Elementor\Core\Files\CSS\Post( $post_id );
			$post_css->update();
		}
	}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Keon_Toolset_Elementor_Image_Import_Fixer' ) ) {
	/**
	 * Repair Elementor image references during Advanced Import flow.
	 */
	class Keon_Toolset_Elementor_Image_Import_Fixer {

		/**
		 * Option key for imported post/url map snapshot.
		 *
		 * @var string
		 */
		const MAP_OPTION = 'keon_toolset_imported_post_ids_snapshot';

		/**
		 * Option key for original post IDs that include Elementor data.
		 *
		 * @var string
		 */
		const ELEMENTOR_POSTS_OPTION = 'keon_toolset_elementor_source_post_ids';

		/**
		 * Boot hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'advanced_import_post_data', array( __CLASS__, 'capture_import_state' ), 999 );
			add_action( 'advanced_import_before_complete_screen', array( __CLASS__, 'repair_elementor_meta' ), 5 );
			add_action( 'advanced_import_after_complete_screen', array( __CLASS__, 'cleanup_import_state' ), 99 );
		}

		/**
		 * Capture source post IDs and imported ID map while import is running.
		 *
		 * @param array $post_data Current post data during import.
		 * @return array
		 */
		public static function capture_import_state( $post_data ) {
			if ( ! is_array( $post_data ) ) {
				return $post_data;
			}

			if ( isset( $post_data['post_id'], $post_data['meta']['_elementor_data'] ) ) {
				$source_ids = get_option( self::ELEMENTOR_POSTS_OPTION, array() );
				if ( ! is_array( $source_ids ) ) {
					$source_ids = array();
				}

				$source_id = absint( $post_data['post_id'] );
				if ( $source_id > 0 && ! in_array( $source_id, $source_ids, true ) ) {
					$source_ids[] = $source_id;
					update_option( self::ELEMENTOR_POSTS_OPTION, $source_ids, false );
				}
			}

			if ( function_exists( 'advanced_import_admin' ) ) {
				$map = advanced_import_admin()->imported_post_id();
				if ( is_array( $map ) ) {
					update_option( self::MAP_OPTION, $map, false );
				}
			}

			return $post_data;
		}

		/**
		 * Repair all imported Elementor posts before import completion screen.
		 *
		 * @return void
		 */
		public static function repair_elementor_meta() {
			$map        = get_option( self::MAP_OPTION, array() );
			$source_ids = get_option( self::ELEMENTOR_POSTS_OPTION, array() );

			if ( ! is_array( $map ) || ! is_array( $source_ids ) || empty( $source_ids ) ) {
				return;
			}

			foreach ( $source_ids as $source_id ) {
				$source_id = absint( $source_id );
				if ( $source_id <= 0 || empty( $map[ $source_id ] ) ) {
					continue;
				}

				$target_post_id = absint( $map[ $source_id ] );
				if ( $target_post_id <= 0 ) {
					continue;
				}

				$elementor_data = get_post_meta( $target_post_id, '_elementor_data', true );
				if ( ! is_string( $elementor_data ) || '' === $elementor_data ) {
					continue;
				}

				$decoded = json_decode( $elementor_data, true );
				if ( ! is_array( $decoded ) ) {
					continue;
				}

				$changed = self::repair_node( $decoded, $map );
				if ( $changed ) {
					update_post_meta( $target_post_id, '_elementor_data', wp_slash( wp_json_encode( $decoded ) ) );
					self::regenerate_elementor_css( $target_post_id );
				}
			}
		}

		/**
		 * Cleanup internal state after import.
		 *
		 * @return void
		 */
		public static function cleanup_import_state() {
			delete_option( self::MAP_OPTION );
			delete_option( self::ELEMENTOR_POSTS_OPTION );
		}

		/**
		 * Recursively repair Elementor nodes using imported map.
		 *
		 * @param mixed $node Node being traversed.
		 * @param array $map  Old-to-new imported map.
		 * @return bool
		 */
		private static function repair_node( &$node, array $map ) {
			$changed = false;

			if ( ! is_array( $node ) ) {
				return false;
			}

			// Common Elementor image shape: [ 'id' => 123, 'url' => '...' ].
			if ( isset( $node['id'] ) && isset( $node['url'] ) ) {
				$old_id = absint( $node['id'] );
				if ( $old_id > 0 && ! empty( $map[ $old_id ] ) ) {
					$new_id = absint( $map[ $old_id ] );
					if ( $new_id > 0 && $new_id !== $old_id ) {
						$node['id'] = $new_id;
						$new_url    = wp_get_attachment_url( $new_id );
						if ( $new_url ) {
							$node['url'] = $new_url;
						}
						$changed = true;
					}
				}

				if ( is_string( $node['url'] ) && isset( $map[ $node['url'] ] ) && is_string( $map[ $node['url'] ] ) ) {
					$node['url'] = $map[ $node['url'] ];
					$changed     = true;
				}
			}

			foreach ( $node as &$value ) {
				if ( is_array( $value ) ) {
					if ( self::repair_node( $value, $map ) ) {
						$changed = true;
					}
					continue;
				}

				if ( is_numeric( $value ) ) {
					$old_id = absint( $value );
					if ( $old_id > 0 && isset( $map[ $old_id ] ) ) {
						$value   = absint( $map[ $old_id ] );
						$changed = true;
					}
				}

				if ( is_string( $value ) && isset( $map[ $value ] ) && is_string( $map[ $value ] ) ) {
					$value   = $map[ $value ];
					$changed = true;
				}
			}
			unset( $value );

			return $changed;
		}

		/**
		 * Regenerate Elementor CSS for repaired post.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		private static function regenerate_elementor_css( $post_id ) {
			if ( ! class_exists( 'Elementor\Core\Files\CSS\Post' ) ) {
				return;
			}

			$post_css = new Elementor\Core\Files\CSS\Post( $post_id );
			$post_css->update();
		}
	}
}

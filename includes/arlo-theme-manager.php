<?php

namespace ArloTraining;

class ThemeManager {

	const THEMES_FOLDER = '../themes/';

	private static $template_names_subs = [
		'catalogue' => 'events',
		'presenter_list' => 'presenters',
		'venue_list' => 'venues',
	];

	public $themes_path;
	public $themes_url;
	
	private $themes_settings;
	private $single_theme_settings = [];
	private $plugin;
	
	public function __construct($plugin) {
		$this->plugin = $plugin;

		$this->themes_path = plugin_dir_path(__FILE__) . self::THEMES_FOLDER;
		$this->themes_url = plugins_url("", __FILE__ ) . '/' . self::THEMES_FOLDER;
	}

	public function get_themes_settings() {
		if ( is_array( $this->themes_settings ) ) return $this->themes_settings;

		$themes = [];

		$theme_setting_files = Utilities::glob_recursive($this->themes_path . 'theme.json');	

		foreach ($theme_setting_files as $theme_setting_file) {
			$theme_dir = dirname($theme_setting_file);
			$theme_id = str_replace($this->themes_path, '', $theme_dir);
			$settings_object = $this->build_theme_settings( $theme_id, $theme_setting_file );

			if ( $settings_object !== null ) {
				$themes[$theme_id] = $settings_object;
			}
		}

		//sort based on the order value
		usort($themes, function($obj1, $obj2) {
			return ($obj1->order == $obj2->order ? 0 : (($obj1->order < $obj2->order) ? -1 : 1));
		});

		//need to recreate the array as an associated array because usort screws up
		$themes_ass = [];
		foreach ($themes as $theme) {
			$themes_ass[$theme->id] = $theme;
		}

		$this->themes_settings = $themes_ass;

		return $this->themes_settings;
	}

	/**
	 * Load settings for a single theme by ID without scanning all theme
	 * directories. Returns from the full-scan cache when available.
	 *
	 * @param string $theme_id Theme directory name (e.g. 'starter').
	 * @return \stdClass|null Theme settings object, or null when the theme
	 *                        directory or its theme.json does not exist.
	 */
	public function get_single_theme_settings( $theme_id ) {
		if ( ! $this->is_valid_single_theme_id( $theme_id ) ) {
			return null;
		}

		// Return from cache if a full scan was already performed.
		if ( is_array( $this->themes_settings ) ) {
			return isset( $this->themes_settings[ $theme_id ] ) ? $this->themes_settings[ $theme_id ] : null;
		}

		if ( array_key_exists( $theme_id, $this->single_theme_settings ) ) {
			return $this->single_theme_settings[ $theme_id ];
		}

		$this->single_theme_settings[ $theme_id ] = $this->build_theme_settings( $theme_id );

		return $this->single_theme_settings[ $theme_id ];
	}

	private function is_valid_single_theme_id( $theme_id ) {
		if ( ! is_string( $theme_id ) || '' === $theme_id || '.' === $theme_id || '..' === $theme_id ) {
			return false;
		}

		return false === strpos( $theme_id, '/' ) && false === strpos( $theme_id, '\\' );
	}

	private function build_theme_settings( $theme_id, $theme_json = null ) {
		$theme_json = $this->resolve_theme_json_path( $theme_id, $theme_json );
		if ( null === $theme_json ) {
			return null;
		}

		$theme_dir = dirname( $theme_json );
		$theme_id = $this->get_theme_relative_path( $theme_dir );
		$theme_url = $this->get_theme_url_from_path( $theme_dir );
		if ( null === $theme_id || null === $theme_url ) {
			return null;
		}

		$settings_object = json_decode( file_get_contents( $theme_json ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local plugin file, not a remote URL.
		if ( ! is_object( $settings_object ) ) {
			return null;
		}

		$settings_object->id  = $theme_id;
		$settings_object->dir = $theme_dir;
		$settings_object->order = ( ! empty( $settings_object->order ) && is_numeric( $settings_object->order ) ? $settings_object->order : 1000000 );
		$settings_object->url = $theme_url;

		$stylesheets = glob( $theme_dir . '/css/*.{css,CSS}', GLOB_BRACE );
		$javascripts = glob( $theme_dir . '/js/*.{js,JS}', GLOB_BRACE );

		$settings_object->internalResources = new \stdClass();
		$settings_object->internalResources->stylesheets = array_map( function( $stylesheet_path ) {
			return $this->get_theme_url_from_path( $stylesheet_path );
		}, is_array( $stylesheets ) ? $stylesheets : [] );

		$settings_object->internalResources->javascripts = array_map( function( $script_path ) {
			return $this->get_theme_url_from_path( $script_path );
		}, is_array( $javascripts ) ? $javascripts : [] );

		return $settings_object;
	}

	private function resolve_theme_json_path( $theme_id, $theme_json = null ) {
		if ( ! $this->is_valid_single_theme_id( $theme_id ) ) {
			return null;
		}

		$theme_json = empty( $theme_json ) ? $this->themes_path . $theme_id . '/theme.json' : $theme_json;
		$theme_json = realpath( $theme_json );
		if ( false === $theme_json ) {
			return null;
		}

		$theme_relative_path = $this->get_theme_relative_path( $theme_json );
		if ( null === $theme_relative_path || basename( $theme_json ) !== 'theme.json' ) {
			return null;
		}

		$resolved_theme_id = dirname( $theme_relative_path );
		if ( '.' === $resolved_theme_id || $resolved_theme_id !== $theme_id ) {
			return null;
		}

		return $theme_json;
	}

	private function get_theme_url_from_path( $path ) {
		$theme_relative_path = $this->get_theme_relative_path( $path );

		if ( null === $theme_relative_path ) {
			return null;
		}

		return rtrim( $this->themes_url, '/' ) . '/' . $theme_relative_path;
	}

	private function get_theme_relative_path( $path ) {
		$themes_root = realpath( $this->themes_path );
		if ( false === $themes_root ) {
			return null;
		}

		$themes_root = rtrim( $this->normalize_path( $themes_root ), '/' ) . '/';
		$path = $this->normalize_path( $path );

		if ( strpos( $path, $themes_root ) !== 0 ) {
			return null;
		}

		return ltrim( substr( $path, strlen( $themes_root ) ), '/' );
	}

	private function normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}

	public function is_theme_valid($theme_id) {
		$theme_settings = $this->get_single_theme_settings( $theme_id );

		return is_object( $theme_settings ) && !empty( $theme_settings->dir ) && file_exists( $theme_settings->dir );
	}

	public function load_default_templates($theme_id) {
		$theme_settings = $this->get_single_theme_settings( $theme_id );

		if ( is_object( $theme_settings ) && !empty( $theme_settings->dir ) ) {
			$templates = [];

			$template_files = glob($theme_settings->dir . '/templates/*.tpl');

			if (is_array($template_files) && count($template_files)) {
				foreach ($template_files as $template_file) {
					$file_name = str_replace('.tpl', '', basename($template_file));
					$template_name = array_key_exists($file_name, self::$template_names_subs) ? self::$template_names_subs[$file_name] : $file_name;

					if (strpos($template_name, '_widget') !== false) {
						$templates[$template_name]['html'] = file_get_contents($template_file);
					} else {
						foreach(\Arlo_For_Wordpress::get_templates() as $template_key => $template_info) {
							if($template_info['id'] == $template_name || (array_key_exists('type', $template_info) && $template_info['type'] == $template_name)) {
								$templates[$template_key]['html'] = file_get_contents($template_file);
							}
						}	
					}
				}
				return $templates;
			}
		}

		return false;
	}
}
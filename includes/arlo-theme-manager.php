<?php

namespace Arlo;

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
	private $plugin;
	private $dbl;	

	public function __construct($plugin, $dbl) {
		$this->dbl = &$dbl; 		
		$this->plugin = $plugin;

		$this->themes_path = plugin_dir_path(__FILE__) . self::THEMES_FOLDER;
		$this->themes_url = plugins_url("", __FILE__ ) . '/' . self::THEMES_FOLDER;
	}

	public function get_themes_settings() {
		if (is_array($this->themes_settings) && count($this->themes_settings)) return $this->themes_settings;

		$themes = [];

		$theme_setting_files = Utilities::glob_recursive($this->themes_path . 'theme.json');	

		foreach ($theme_setting_files as $theme_setting_file) {
			$theme_dir = dirname($theme_setting_file);
			$theme_id = str_replace($this->themes_path, '', $theme_dir);
			$settings_object = json_decode(file_get_contents($theme_setting_file));
			$settings_object->dir = $theme_dir;
			$settings_object->url = str_replace($this->themes_path, $this->themes_url, $theme_dir);

			$settings_object->images = array_map(function($image_path) use($plugin_dir, $plugin_url) {
				return str_replace($this->themes_path, $this->themes_url, $image_path);
			}, glob($theme_dir . "/images/*.{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF}", GLOB_BRACE));

			//internal resources
			$settings_object->internalResources = new \stdClass();
			$settings_object->internalResources->stylesheets = array_map(function($stylesheet_path) use($plugin_dir, $plugin_url) {
				return str_replace($this->themes_path, $this->themes_url, $stylesheet_path);
			}, glob($theme_dir . "/css/*.{css,CSS}", GLOB_BRACE));

			$settings_object->internalResources->javascripts = array_map(function($script_path) use($plugin_dir, $plugin_url) {
				return str_replace($this->themes_path, $this->themes_url, $script_path);
			}, glob($theme_dir . "/js/*.{js,JS}", GLOB_BRACE));			

			$themes[$theme_id] = $settings_object;
		}

		$this->themes_settings = $themes;

		return $this->themes_settings;
	}

	public function is_theme_valid($theme_id) {
		$themes = $this->get_themes_settings();

		return is_array($themes) && isset($themes[$theme_id]) && isset($themes[$theme_id]->dir) && file_exists($themes[$theme_id]->dir);
	}

	public function load_default_templates($theme_id) {
		if ($this->is_theme_valid($theme_id)) {

			$theme_settings = $this->get_themes_settings();
			$templates = [];

			$template_files = glob($theme_settings[$theme_id]->dir . '/templates/*.tpl');
			if (is_array($template_files) && count($template_files)) {
				foreach ($template_files as $template_file) {
					$template_name = str_replace('.tpl', '', basename($template_file));

					$templates[str_replace(array_keys(self::$template_names_subs), array_values(self::$template_names_subs), $template_name)]['html'] = file_get_contents($template_file);
				}

				return $templates;
			}
		}

		return false;
	}
}
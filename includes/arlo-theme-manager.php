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
			$settings_object->id = $theme_id;
			$settings_object->dir = $theme_dir;
			$settings_object->order = (!empty($settings_object->order) && is_numeric($settings_object->order) ? $settings_object->order : 1000000);
			$settings_object->url = str_replace($this->themes_path, $this->themes_url, $theme_dir);

			//internal resources
			$settings_object->internalResources = new \stdClass();
			$settings_object->internalResources->stylesheets = array_map(function($stylesheet_path) {
				return str_replace($this->themes_path, $this->themes_url, $stylesheet_path);
			}, glob($theme_dir . "/css/*.{css,CSS}", GLOB_BRACE));

			$settings_object->internalResources->javascripts = array_map(function($script_path) {
				return str_replace($this->themes_path, $this->themes_url, $script_path);
			}, glob($theme_dir . "/js/*.{js,JS}", GLOB_BRACE));			

			$themes[$theme_id] = $settings_object;
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
					$file_name = str_replace('.tpl', '', basename($template_file));
					$template_name = array_key_exists($file_name, self::$template_names_subs) ? self::$template_names_subs[$file_name] : $file_name;

					if (strpos($template_name, '_widget') !== false) {
						$templates[$template_name]['html'] = file_get_contents($template_file);
					} else {
						foreach(\Arlo_For_Wordpress::$templates as $template_key => $template_info) {
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
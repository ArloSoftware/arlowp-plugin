<?php

namespace Arlo;

use Arlo\Entities\Categories as CategoriesEntity;
use Arlo\Shortcodes\Templates;

class SitemapGenerator {
	private $plugin;
	private $dbl;	
	private $generate_xml;

	public function __construct($plugin, $dbl, $generate_xml = false) {
		$this->dbl = &$dbl; 		
		$this->plugin = $plugin;
		$this->generate_xml = $generate_xml === true;
	}

	public function generate_catalogue_sitemap() {
		$post_types = arlo_get_option('post_types');
		$page_filter_settings = get_option("arlo_page_filter_settings");

		if (!empty($post_types['event']) && !empty($post_types['event']['posts_page'])) {
			$shortcode_attributes = $this->get_shortcode_attributes('arlo_event_template_list', $post_types['event']['posts_page']);

			$content = \Arlo\Shortcodes\Templates::template_list_initializer("", $shortcode_attributes, "arlo_event_template_list", "", "events");
			$showonly_categories = \Arlo\Utilities::get_filter_keys_int_array('category', $shortcode_attributes, false);
			$ignored_categories = \Arlo\Utilities::get_filter_keys_int_array('categoryhidden', $shortcode_attributes, false);

			if ($this->is_category_selector_visible($content)) {
				return $this->generate_category_sitemap(get_page_link($post_types['event']['posts_page']), $ignored_categories, $showonly_categories);
			}
		}
	}

	public function generate_schedule_sitemap() {
		$post_types = arlo_get_option('post_types');
		$page_filter_settings = get_option("arlo_page_filter_settings");
		
		if (!empty($post_types['schedule']) && !empty($post_types['schedule']['posts_page'])) {
			$shortcode_attributes = $this->get_shortcode_attributes('arlo_schedule', $post_types['schedule']['posts_page']);

			\Arlo\Shortcodes\Templates::template_list_initializer("", $shortcode_attributes, "arlo_schedule", "", "schedule");
			$showonly_categories = \Arlo\Utilities::get_filter_keys_int_array('category', $shortcode_attributes, false);
			$ignored_categories = \Arlo\Utilities::get_filter_keys_int_array('categoryhidden', $shortcode_attributes, false);

			if ($this->is_category_selector_visible($content)) {
				return $this->generate_category_sitemap(get_page_link($post_types['schedule']['posts_page']), $ignored_categories, $showonly_categories);
			}
		}
	}

	public function generate_sitemap_xml($links) {
		return '
			<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="//localhost:8080/wordpress/wp-content/plugins/wordpress-seo/css/main-sitemap.xsl"?>
			' . generate_sitemap_urlset($links) . '';
	}

	public function generate_sitemap_urlset($links) {
		$urlset = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach($links as $link) {
			$urlset .= "
		<url>
			<loc>$link</loc>
		</url>";
		}

		return $urlset . '</urlset>';
	}

	private function get_shortcode_attributes($shortcode = '', $post_id = 0) {
		$shortcode_atts = [];

		$content = get_post_field('post_content', $post_id);
		preg_match_all("(\[(?:\[??[^\[]*?\]))", $content, $content_shortcodes);
		$event_template_list_shortcode = array_filter($content_shortcodes[0], function($s) use($shortcode) {
				return strpos($s, $shortcode) !== false;
		});
		if (count($event_template_list_shortcode)) {
			preg_match("#categoryhidden=[\"']?([\d\s\w,-]+)[\"']?#", $event_template_list_shortcode[0], $categoryhidden);
			if (count($categoryhidden) == 2) {
				$shortcode_atts['categoryhidden'] = $categoryhidden[1];
			}

			preg_match("#category=[\"']?([\d\s\w,-]+)[\"']?#", $event_template_list_shortcode[0], $category);
			if (count($category) == 2) {
				$shortcode_atts['category'] = $category[1];
			}
		}

		return $shortcode_atts;
	}

	private function generate_category_sitemap($page_link = '', $ignored_categories = [], $showonly_categories= []) {
		$import_id = get_option('arlo_import_id');
		$regions = get_option('arlo_regions');
		$urls = [];

		if (!empty($page_link)) {
			$base_url = rtrim($page_link, "/");
			$categories = CategoriesEntity::get([ 'ignored' => $ignored_categories, 'id' => $showonly_categories], null, $import_id);
			$categories = array_merge($categories, CategoriesEntity::get([ 'ignored' => $ignored_categories, 'parent_id' => $showonly_categories], null, $import_id));

			if (is_array($regions) && count($regions)) {
				foreach	($regions as $region_id => $region_label) {
					$urls = array_merge($urls, $this->generate_category_paths($categories, $base_url . '/' . $this->region_path($region_id)));
				}
			} else {
				$urls = array_merge($urls, $this->generate_category_paths($categories, $base_url));
			}
		}

		if (count($urls)) {
			$sitemap = $this->generate_xml ? $this->generate_sitemap_xml($urls) : $this->generate_sitemap_urlset($urls);

			return $sitemap;
		}

		return null;
	}

	private function generate_category_paths($categories, $base_url) {
		$urls = [];
		foreach ($categories as $category) {
			$urls[] = $base_url . '/' . $this->category_path($category);
		}

		return $urls;
	}

	private function region_path($region) {
		return 'region-' . $region;
	}

	private function category_path($category) {
		return $category->c_parent_id != 0 ? 'cat-' . esc_attr($category->c_slug) : '';
	}

	private function is_category_selector_visible($content) {
		$arlo_filters_available = false;
		$arlo_categories_available = false;

		preg_match_all("(\[(?:\[??[^\[]*?\]))", $content, $content_shortcodes);

		$categories_shortcode = array_filter($content_shortcodes[0], function($s){
				return strpos($s, "arlo_categories") !== false;
		});
		if (count($categories_shortcode)) {
			$arlo_categories_available = true;
		}

		$filters_shortcode = array_filter($content_shortcodes[0], function($s){
			return strpos($s, "arlo_event_template_filters") !== false;
		});
		if (count($filters_shortcode)) {
			preg_match("#filters=[\"']?([\w\s,]+)[\"']?#", array_pop($filters_shortcode), $filters);
			if (count($filters) == 2) {
				if (strpos($filters[2], "category")) {
					$arlo_filters_available = true;
				}
			} else if (count($filters) == 0) {
				$arlo_filters_available = true;
			}
		}

		return $arlo_categories_available || $arlo_filters_available;
	}
}
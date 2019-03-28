<?php

namespace Arlo;

use Arlo\Entities\Categories as CategoriesEntity;

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

		if (!empty($post_types['event']) && !empty($post_types['event']['posts_page'])) {
			return $this->generate_category_sitemap(get_page_link($post_types['event']['posts_page']));
		}
	}

	public function generate_schedule_sitemap() {
        $post_types = arlo_get_option('post_types');

		if (!empty($post_types['schedule']) && !empty($post_types['schedule']['posts_page'])) {
			return $this->generate_category_sitemap(get_page_link($post_types['schedule']['posts_page']));
		}
	}

	private function generate_category_sitemap($page_link = '') {
		$import_id = get_option('arlo_import_id');
		$regions = get_option('arlo_regions');
		$urls = [];

		if (!empty($page_link)) {
			$base_url = rtrim($page_link, "/");
			$categories = CategoriesEntity::get([], null, $import_id);

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
}
<?php

namespace Arlo;

use Arlo_For_Wordpress;
use Arlo\Entities\Templates;
use Arlo\Entities\Venues;
use Arlo\Entities\Presenters;
use Arlo\Entities\Categories;

class Redirect {

    public static function object_post_redirect() {
        //$object_post_type = filter_input(INPUT_GET, 'object_post_type', FILTER_SANITIZE_STRING);
        $object_post_type = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'object_post_type');
        
        $arlo_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'arlo_id');

        if (!empty($object_post_type) && !empty($arlo_id) && is_numeric($arlo_id)) {
            switch($object_post_type) {
                case 'event':
                    self::private_event_redirect($arlo_id);   // potential redirect
                    self::object_post_event_redirect($arlo_id);   // redirects
                    break;

                case 'venue':
                    self::object_post_venue_redirect($arlo_id);   // redirects
                    break;

                case 'presenter':
                    self::object_post_presenter_redirect($arlo_id);   // redirects
                    break;

                case 'category':
                    self::object_post_category_redirect($arlo_id);   // redirects
                    break;
            }
        }
    }

    // Private event are not available on the pub api so they are missing from wordpress - on purpose
    // But the Arlo platform still have a link that redirects to the hypothetical wordpress page
    // So we redirect back to the Arlo website
    private static function private_event_redirect($arlo_id) {
        $e = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'e');
        $t = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 't');

        if (!empty($e) || !empty($t)) {
            $settings = get_option('arlo_settings');
            $platform_name = $settings['platform_name'];

            if (!empty($platform_name)) {
                // assuming containing a dot means specified dns
                $platform_url = (strpos($platform_name, '.') ? $platform_name : $platform_name . '.arlo.co');

                $redirect_url = 'https://' . $platform_url . '/events/' . rawurlencode($arlo_id) . '-fake-redirect-url?' . (!empty($e) ? 'e=' . rawurlencode($e) : (!empty($t) ? 't=' . rawurlencode($t) : ''));
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }


    private static function object_post_event_redirect($arlo_id) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $import_id = $plugin->get_importer()->get_current_import_id();

        $eventtemplate = Templates::get(['id' => $arlo_id], [], 1, $import_id);

        if (!empty($eventtemplate) && !empty($eventtemplate->et_post_name)) {
            $post = arlo_get_post_by_name($eventtemplate->et_post_name, 'arlo_event');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID);
                if (!empty($redirect_url)){
                    wp_redirect($redirect_url, 301);
                    exit;
                }
            }
        }
        // Cannot redirect, so 404
        self::object_404();
    }

    private static function object_post_venue_redirect($arlo_id) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $import_id = $plugin->get_importer()->get_current_import_id();

        $venue = Venues::get(['id' => $arlo_id], [], 1, $import_id);

        if (!empty($venue) && !empty($venue['v_post_name'])) {
            $post = arlo_get_post_by_name($venue['v_post_name'], 'arlo_venue');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID);
                if (!empty($redirect_url)){
                    wp_redirect($redirect_url, 301);
                    exit;
                }
            }
        }
        // Cannot redirect, so 404
        self::object_404();
    }

    private static function object_post_presenter_redirect($arlo_id) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $import_id = $plugin->get_importer()->get_current_import_id();

        $presenter = Presenters::get(['id' => $arlo_id], [], 1, $import_id);

        if (!empty($presenter) && !empty($presenter['p_post_name'])) {
            $post = arlo_get_post_by_name($presenter['p_post_name'], 'arlo_presenter');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID);
                if (!empty($redirect_url)) {
                    wp_redirect($redirect_url, 301);
                    exit;
                }
            }
        }
        // Cannot redirect, so 404
        self::object_404();
    }

    private static function object_post_category_redirect($arlo_id) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $import_id = $plugin->get_importer()->get_current_import_id();

        $category = Categories::get(['id' => $arlo_id], 1, $import_id);

        if (!empty($category) && !empty($category->c_slug)) {
            $post = arlo_get_post_by_name('events', 'page');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID) . 'cat-' . $category->c_slug;
                wp_redirect($redirect_url, 301);
                exit;
            }
            $post = arlo_get_post_by_name('schedule', 'page');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID) . 'cat-' . $category->c_slug;
                wp_redirect($redirect_url, 301);
                exit;
            }
            $post = arlo_get_post_by_name('upcoming', 'page');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID) . 'cat-' . $category->c_slug;
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
        // Cannot redirect, so 404
        self::object_404();
    }

    /**
     * Return a 404 error
     */
    private static function object_404(){
        global $wp_query;

        $wp_query->set_404();
        status_header(404);
    }

}

<?php

namespace Arlo;

use Arlo_For_Wordpress;
use Arlo\Entities\Templates;
use Arlo\Entities\Venues;
use Arlo\Entities\Presenters;
use Arlo\Entities\Categories;

class Redirect {

    public static function object_post_redirect() {
        $object_post_type = filter_input(INPUT_GET, 'object_post_type', FILTER_SANITIZE_STRING);
        $arlo_id = filter_input(INPUT_GET, 'arlo_id', FILTER_SANITIZE_STRING);

        if (!empty($object_post_type) && !empty($arlo_id)) {
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
        $e = filter_input(INPUT_GET, 'e', FILTER_SANITIZE_STRING);
        $t = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

        if (!empty($e) || !empty($t)) {
            $settings = get_option('arlo_settings');
            $platform_name = $settings['platform_name'];

            if (!empty($platform_name)) {
                // assuming containing a dot means specified dns
                $platform_url = (strpos($platform_name, '.') ? $platform_name : $platform_name . 'arlo.co');

                $redirect_url = 'http://' . $platform_url . '/events/' . $arlo_id . '-fake-redirect-url?' . (!empty($e) ? 'e=' . $e : (!empty($t) ? 't=' . $t : ''));
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
                if (!empty($redirect_url)) {
                    wp_redirect($redirect_url, 301);
                    exit;
                }
            }
        }
        http_response_code(404);
        die('Page not found (todo)');
    }

    private static function object_post_venue_redirect($arlo_id) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $import_id = $plugin->get_importer()->get_current_import_id();

        $venue = Venues::get(['id' => $arlo_id], [], 1, $import_id);

        if (!empty($venue) && !empty($venue['v_post_name'])) {
            $post = arlo_get_post_by_name($venue['v_post_name'], 'arlo_venue');
            if (!empty($post) && !empty($post->ID)) {
                $redirect_url = get_permalink($post->ID);
                if (!empty($redirect_url)) {
                    wp_redirect($redirect_url, 301);
                    exit;
                }
            }
        }
        http_response_code(404);
        die('Page not found (todo)');
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
        http_response_code(404);
        die('Page not found (todo)');
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
        http_response_code(404);
        die('Page not found (todo)');
    }

}

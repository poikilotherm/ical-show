<?php
/*
Plugin Name:  iCal Show
Plugin URI:   https://src.ipp.kfa-juelich.de/it/ical-show
Description:  Plugin to display events from an iCal feed via shortcodes
Version:      20171024
Author:       Oliver Bertuch
Author URI:   https://oliver.bertuch.eu
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  icalshow
Domain Path:  /languages

iCal Show is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

iCal Show is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with iCal Show. If not, see https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html.
*/

use Sabre\VObject;
require_once( plugin_dir_path(__FILE__)."/vendor/autoload.php" );

function icalshow_shortcodes_init()
{
    function icalshow_shortcode($atts = [], $content = null)
    {
        // normalize attribute keys, lowercase
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        // override default attributes with user attributes
        $wporg_atts = shortcode_atts([
                                      'url' => 'invalid',
                                     ], $atts);
        //ignore any enclosed content, this is only non-enclosing
        $content = null;

        // validate attributes
        if ($atts['url'] === 'invalid' || $atts['url'] === '')
          return '<div>No URL given in shortcode. Failing...</div>';

        // TODO: CACHING!!!
        // retrieve calendar data
        $request = wp_remote_get($atts['url']);
        if (is_wp_error($request))
        	return '<div>Could not fetch feed from URL "'.$atts['url'].'". Failing: "'.$request.'"</div>';
        $data = wp_remote_retrieve_body($request);

        // parse read data
        $vcalendar = VObject\Reader::read($data);

        // start output
        $o = "<div class=\"icalshow\">";
        $o .= "</div>";

        // return output
        return $o;
    }
    add_shortcode('icalshow', 'icalshow_shortcode');
}
add_action('init', 'icalshow_shortcodes_init');

?>

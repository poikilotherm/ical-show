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

function icalshow_shortcodes_init()
{
    function icalshow_shortcode($atts = [], $content = null)
    {
        // do something to $content

        // always return
        return $content;
    }
    add_shortcode('icalshow', 'icalshow_shortcode');
}
add_action('init', 'icalshow_shortcodes_init');

?>
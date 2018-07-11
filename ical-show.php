<?php
/*
Plugin Name:  iCal Show
Plugin URI:   https://github.com/poikilotherm/ical-show
Description:  Plugin to display events from an iCal feed via shortcodes
Version:      20180710
Author:       Oliver Bertuch
Author URI:   https://oliver.bertuch.eu
License:      AGPL3
License URI:  https://www.gnu.org/licenses/agpl-3.0.html
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
        $ical_atts = shortcode_atts([
                                      'url' => 'invalid',
                                      'showpastevents' => false,
                                      'showmaxfuture' => '+2 weeks', # disable by setting to false/0
                                      'limit' => false,
                                      'dateformat' => 'd.m. H:i',
                                      'dateseparator' => ' - ',
                                      'collapsetime' => true,
                                      'collapseformat' => 'H:i',
                                      'collapseseparator' => '-',
                                      'linksummary' => true,
                                      'linktarget' => '_blank',
                                      'noresults' => '(Nothing to show.)'
                                     ], $atts);
        //ignore any enclosed content, this is only non-enclosing
        $content = null;

        // validate attributes
        if ($ical_atts['url'] === 'invalid' || $ical_atts['url'] === '')
          return '<div>No URL given in shortcode. Failing...</div>';

        // TODO: CACHING!!!
        // retrieve calendar data
        $request = wp_remote_get($ical_atts['url']);
        if (is_wp_error($request))
          return '<div>Could not fetch feed from URL "'.$ical_atts['url'].'". Failing: "'.$request.'"</div>';
        $data = wp_remote_retrieve_body($request);

        // parse read data
        try {
          $vcalendar = VObject\Reader::read($data);
        } catch (Exception $e) {
          return "<div class=\"icalshow icalshow-error\">Could not parse iCal file. Please look inside your logs.</div>";
        }

        // filter events...
        // save valid values to new array
        $events = array();

        foreach($vcalendar->VEVENT as $event) {
          $start = $event->DTSTART->getDateTime();
          $end = $event->DTEND->getDateTime();
          $now = new DateTime('NOW');
          $add = false;

          // filter past events
          if ($ical_atts['showpastevents'] == true || $end > $now)
            $add = true;

          // filter future events by max future timespan
          if ($ical_atts['showmaxfuture'] != false) {
            $future = $now->modify($ical_atts['showmaxfuture']);
            if ($future === false)
              return "<div>Could not parse future time \"".$ical_atts['showmaxfuture']."\". Failing...</div>";
            if ($start < $future)
              $add = $add && true; // respect filtering past events!
            else
              $add = false;
          }

          // filter limit
          if ($ical_atts['limit'] !== false && (int)$ical_atts['limit'] > 0 && count($events) >= (int)$ical_atts['limit'])
            break; //simply abort
          if ($add)
            $events[] = $event;
        }

        // TODO: SORTING!

        // no events to show = return message
        if (count($events) == 0)
          return "<div class=\"icalshow\">".$ical_atts['noresults']."</div>";

        // start output
        $o =  "<div class=\"icalshow\"><div class=\"icalshow-table\">";
        foreach ($events as $event) {
          $o .= "<div class=\"icalshow-row\">";
          // date and time
          $start = $event->DTSTART->getDateTime();
          $end = $event->DTEND->getDateTime();

          // collapse the output if enabled and start and end on same day
          if ($ical_atts['collapsetime'] == true && $start->format('Y-m-d') == $end->format('Y-m-d'))
            $dt = $start->format($ical_atts['dateformat']).$ical_atts['collapseseparator'].$end->format($ical_atts['collapseformat']);
          else
            $dt = $start->format($ical_atts['dateformat']).$ical_atts['dateseparator'].$end->format($ical_atts['dateformat']);

          $o .= "<div class=\"icalshow-cell icalshow-date\">".esc_html($dt)."</div>";

          // details
          $detail = $ical_atts['linksummary'] == true ? '<a class="icalshow-link" href="'.esc_url((string)$event->URL).'" target="'.esc_attr($ical_atts['linktarget']).'">' : '';
          $detail .= esc_html((string)$event->SUMMARY);
          $detail .= $ical_atts['linksummary'] == true ? '</a>' : '';
          $o .= "<div class=\"icalshow-cell icalshow-detail\">".$detail."</div>";

          $o .= "</div>";
        }
        $o .= "</div></div>";

        // return output
        return $o;
    }
    add_shortcode('icalshow', 'icalshow_shortcode');
}
add_action('init', 'icalshow_shortcodes_init');

// Register style sheet.
add_action( 'wp_enqueue_scripts', 'register_icalshow_style' );
function register_icalshow_style() {
	wp_register_style( 'icalshow', plugins_url( 'ical-show/css/icalshow.css' ) );
	wp_enqueue_style( 'icalshow' );
}

?>

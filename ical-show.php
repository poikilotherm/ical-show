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
                                      'dateformat' => 'd.m.',
                                      'datetimeseparator' => ' ',
                                      'timeformat' => 'H:i',
                                      'dateseparator' => ' - ',
                                      'collapsetime' => true,
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
          $vcalendar = VObject\Reader::read($data, VObject\Reader::OPTION_FORGIVING);
        } catch (Exception $e) {
          return "<div class=\"icalshow icalshow-error\">Could not parse iCal file. Please look inside your logs.</div>";
        }

        // TODO: validate the data with $vcalendar->validate()
        // See also: http://sabre.io/vobject/icalendar/#validating-icalendar

        // TODO: expand events to respect recurrences
        // See also: http://sabre.io/vobject/recurrence/

        // filter events...
        // save valid values to new array
        $events = array();
        foreach($vcalendar->VEVENT as $event) {
          $start = $event->DTSTART->getDateTime();
          $end = ($event->DTEND == null) ? $start : $event->DTEND->getDateTime();
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

        // sort the events by start date
        usort($events, function($a, $b) {
          $adt = $a->DTSTART->getDateTime();
          $bdt = $b->DTSTART->getDateTime();
          if($adt == $bdt) return 0;
          return ($adt < $bdt) ? -1 : 1;
        });

        // no events to show = return message
        if (count($events) == 0)
          return "<div class=\"icalshow\">".esc_html($ical_atts['noresults'])."</div>";

        // simple helper function for some usefull html tag insertion
        function buildDateHtml($date, $time = false, $sep = ' ') {
          $s = '<span class="icalshow-date">'.esc_html($date).'</span>';
          if ($time != false) {
            $s .= esc_html($sep);
            $s .= '<span class="icalshow-time">'.esc_html($time).'</span>';
          }
          return $s;
        }

        $tz = new DateTimeZone(get_option('timezone_string'));

        // start output
        $o =  "<div class=\"icalshow\"><div class=\"icalshow-table\">";
        foreach ($events as $event) {
          $o .= "<div class=\"icalshow-row\">";

          // details
          $url = 'href="'.esc_url(trim((string)$event->URL)).'"';
          $target = 'target="'.esc_attr($ical_atts['linktarget']).'"';
          if ($url == 'href=""') {
            $url = "";
            $target = "";
          }

          $detail = $ical_atts['linksummary'] == true ? '<a class="icalshow-link" '.$url.' '.$target.'>' : '';
          $detail .= esc_html((string)$event->SUMMARY);
          $detail .= $ical_atts['linksummary'] == true ? '</a>' : '';
          $o .= "<div class=\"icalshow-cell icalshow-detail\">".$detail."</div>";

          // date and time
          $start = $event->DTSTART->getDateTime()->setTimezone($tz);
          $end = ($event->DTEND == null) ? $start : $event->DTEND->getDateTime()->setTimezone($tz);

          // collapse the output if enabled and start and end on same day
          if ($ical_atts['collapsetime'] == true && $start->diff($end)->d == 0) {
            // when start == endtime only print the start time.
            $time = "";
            if ($start == $end)
              $time = $start->format($ical_atts['timeformat']);
            else
              $time = $start->format($ical_atts['timeformat']) . $ical_atts['collapseseparator'] . $end->format($ical_atts['timeformat']);

            $dt = buildDateHtml(
                    $start->format($ical_atts['dateformat']),
                    $time,
                    $ical_atts['datetimeseparator']
                  );
          }
          // detect whole SINGLE days (start 00:00 to end 00:00) and collapse them
          elseif ($ical_atts['collapsetime'] == true &&
                    $start->diff($end)->d == 1 &&
                    $start->diff($end)->h == 0 &&
                    $start->diff($end)->m < 5)
            $dt = buildDateHtml($start->format($ical_atts['dateformat']), $ical_atts['datetimeseparator']);

          // detect whole MULTIPLE days (start 00:00 to end 00:00) and collapse them
          elseif ($ical_atts['collapsetime'] == true &&
                    $start->diff($end)->d > 1 &&
                    $start->diff($end)->h == 0 &&
                    $start->diff($end)->m == 0
                  )
            $dt = buildDateHtml(
                    $start->format($ical_atts['dateformat']).
                    $ical_atts['dateseparator'].
                    // let the date go from end 00:00 to end -1 day 00:00
                    $end->modify("-1 day")->format($ical_atts['dateformat']),
                    $ical_atts['datetimeseparator']
                  );
          else
            $dt = buildDateHtml($start->format($ical_atts['dateformat']), $start->format($ical_atts['timeformat']), $ical_atts['datetimeseparator']).
                  $ical_atts['dateseparator'].
                  buildDateHtml($end->format($ical_atts['dateformat']), $end->format($ical_atts['timeformat']), $ical_atts['datetimeseparator']);

          $o .= "<div class=\"icalshow-cell icalshow-datetime\">".$dt."</div>";

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

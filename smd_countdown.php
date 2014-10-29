<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_countdown';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.20';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://www.stefdawson.com/';
$plugin['description'] = 'Time until article posted/expiry or any other date is reached';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '0';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_countdown: Textpattern CMS plugin for counting down to an event
 */

/**
 * Public tag for determining the event target.
 *
 * Uses either the given date or an article field.
 *
 * @param  array $atts Tag attributes
 */
function smd_countdown($atts, $thing = null)
{
	global $thisarticle, $smd_timer;

	extract(lAtts(array(
		'to'    => 'posted', // or: expires, modified, ?some_field, or an English date string
		'debug' => 0,
	),$atts));

	$destime = '';

	if ($to === 'posted' || $to === 'expires' || $to === 'modified') {
		assert_article();
		$destime = $thisarticle[$to];
	} elseif (strpos($to, "?") === 0) {
		assert_article();
		$fldname = substr(strtolower($to), 1);
		if (isset($thisarticle[$fldname])) {
			$destime = (is_numeric($thisarticle[$fldname])) ? $thisarticle[$fldname] : strtotime($thisarticle[$fldname]);
		}
	} elseif ($to !== '') {
		$destime = strtotime($to);
	}

	if ($debug) {
		echo '++ DESTINATION ++';
		dmp($destime);
		dmp(date('d M Y H:i:s', $destime));
	}

	if ($destime) {
		$now = time();

		// Split into years/months/weeks/days/hrs/minutes/seconds
		$diff = $destime - $now;
		$absdiff = ($diff > 0) ? $diff : $now - $destime;
		$smd_timer['difference'] = $diff;
		$smd_timer['abs_difference'] = $absdiff;
		$smd_timer['destination'] = $destime;

		$lookup = array(
			'year'   => array(60 * 60 * 24 * 365),
			'month'  => array(60 * 60 * 24 * 30, 12), // month(ish)
			'week'   => array(60 * 60 * 24 * 7, 52),
			'day'    => array(60 * 60 * 24, 7),
			'hour'   => array(60 * 60, 24),
			'minute' => array(60, 60),
			'second' => array(1, 60),
		);

		foreach ($lookup as $item => $bloc) {
			$qty = floor($absdiff / $bloc[0]);
			$smd_timer[$item] = (isset($bloc[1])) ? $qty % $bloc[1] : $qty;
			$smd_timer[$item.'_total'] = $qty;
		}

		if ($debug) {
			echo '++ TIMER ++';
			dmp($smd_timer);
		}

		$result = ($diff > 0) ? false : true; // True if destination reached, false otherwise

		return parse(EvalElse($thing, $result));
	} else {
		return '';
	}
}

/**
 * Public tag for displaying portions of the timer.
 *
 * @param  array $atts Tag attributes
 * @return HTML
 */
function smd_time_info($atts)
{
	global $smd_timer;

	extract(lAtts(array(
		'display'     => '',
		'show_zeros'  => '1',
		'pad'         => '2,0',
		'label'       => '',
		'labeltag'    => null,
		'labelafter'  => 0,
		'labelspacer' => '',
		'wraptag'     => '',
		'class'       => __FUNCTION__,
		'break'       => '',
		'debug'       => 0,
	),$atts));

	$display = do_list($display);
	$label = do_list($label);
	$pad = do_list($pad);
	$pad[0] = (empty($pad[0])) ? '1' : $pad[0];
	$pad[1] = (count($pad) === 1) ? '0' : $pad[1];

	$out = array();
	$use_plural = false;

	foreach ($display as $item) {
		// Not comparing strict in this block, as the timer item is likely a string
		// (but may be a number).
		if (isset($smd_timer[$item])) {
			if ($smd_timer[$item] > 0 || ($smd_timer[$item] == 0 && (!empty($out) || $show_zeros))) {
				$out[] = str_pad($smd_timer[$item], $pad[0], $pad[1], STR_PAD_LEFT);
			}

			$use_plural = ($smd_timer[$item] != 1) ? true : false;
		}
	}

	$theLabel = ($use_plural && isset($label[1])) ? $label[1] : $label[0];

	return ($out)
		? (($labelafter == 0) ? smd_countdown_doLabel($theLabel.$labelspacer, $labeltag) : '').
			doWrap($out, $wraptag, $break, $class).
			(($labelafter == 1) ? smd_countdown_doLabel($labelspacer.$theLabel, $labeltag) : '')
		: '';
}

/**
 * More intelligent replacement for the core's doLabel().
 *
 * The core version forces a &lt;br&gt; tag after the content
 * which is undesirable.
 */
function smd_countdown_doLabel($label = '', $labeltag = '')
{
	return (empty($labeltag) ? $label : tag($label, $labeltag));
}

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_countdown

h2. Features

* Countdown to either:
** article posted, expired, modified time.
** any time given in any article field.
** any arbitrary time given as an English date.
* Supports @<txp:else />@ so you can take action when the time is reached.
* Display number of years, months, weeks, days, hours, minutes, seconds as either:
** an absolute number (10 days to go).
** a date-based number (1 week 3 days to go).

h2(#install). Installation / Uninstallation

Download the plugin from either "textpattern.org":http://textpattern.org/plugins/1115/smd_countdown, or the "software page":http://stefdawson.com/sw, paste the code into the Textpattern Admin -&gt; Plugins pane, install and enable the plugin. Visit the "forum thread":http://forum.textpattern.com/viewtopic.php?id=31494 for more info or to report on the success or otherwise of the plugin.

To uninstall, delete from the __Admin->Plugins__ page.

h2. Usage

h3. @<txp:smd_countdown>@

Place this tag in any article or page to count down to a specific date/time. If you wish to use it to count down to a date in the given article you must ensure it's used in an article context (the plugin will complain if you don't). Use the @to@ attribute to configure the destination date. Examples:

In a Page:

bc. <txp:smd_countdown to="25 Dec 2014">
  It's Christmas day!
</txp:smd_countdown>


In an article / article Form:

bc. <txp:smd_countdown>
  Posted date has arrived.
<txp:else />
  Event hasn't started yet.
</txp:smd_countdown>


Or:

bc. <txp:smd_countdown to="expires">
  Too late, you missed it :-(
<txp:else />
  There's still time to get to the show...
</txp:smd_countdown>


Or:

bc. <txp:smd_countdown to="?my_date">
  The date in custom field #1 (called my_date) has been reached
</txp:smd_countdown>


h3. @<txp:smd_time_info>@

If you wish to show visitors how much time they have left before the destination arrives, use this tag to display various time elements. The following attributes configure what you wish to show:

h4. Attributes

* *display* the item(s) you wish to display. Can use one or more (comma-separated) from the following:
** *year* (or year_total) the number of years until the event.
** *month* the number of calendar months until the event, maximum 12[1].
** *month_total* the absolute number of months until the event[1].
** *week* the number of calendar weeks until the event, maximum 52[2].
** *week_total* the absolute number of weeks until the event[2].
** *day* the number of calendar days until the event, maximum 7.
** *day_total* the absolute number of days until the event.
** *hour* the number of day-based hours until the event, maximum 24.
** *hour_total* the absolute number of hours until the event.
** *minute* the number of hour-based minutes until the event, maximum 60.
** *minute_total* the absolute number of minutes until the event.
** *second* the number of minute-based seconds until the event, maximum 60.
** *second_total* the absolute number of seconds until the event.
** *destination* the UNIX timestamp of the destination.
** *difference* the UNIX timestamp difference between now and the destination (may be negative if the destination has passed).
** *abs_difference* the UNIX timestamp difference between now and the destination irrespective of whether the date has passed or not.
* *show_zeros* if you wish to hide leading items that have zero months, weeks, days, etc, set this to 0. Default: 1.
* *pad* pad the numerical output with some text. Specify up to two comma-separated values here. The first is the total width in characters of the string you wish to display. The second is the text with which you wish to pad the numbers. Default: @2, 0@ (i.e. pad to a width of two characters, using zeros if necessary).
* *label* up to two values with which you can label the given display item(s). If you specify two values, the first is what to use for singular numbers (e.g. 1 *hour*), the second is the plural (e.g. 3 *hours*). Note that using the @singular, plural@ form in this attribute does not usually make sense when @display@ is a list of items. Default: unset.
* *labeltag* HTML tag, without brackets, to wrap the label with. Default: unset.
* *labelafter* set to 1 if you wish the label to be appended to the displayed item(s). Default: 0 (i.e. prepend).
* *labelspacer* text to put before/after the label. Very useful if @labelafter="1"@ and you wish to put a space between the number and the label. Default: unset.
* *wraptag* HTML tag, without brackets, to wrap the displayed items with. Default: unset.
* *class* CSS class name to apply to the wraptag. Default: @smd_time_info@.
* *break* HTML tag, (without brackets) or other delimiter to wrap / put between each display item. Default: unset.

fn1. Months may get a little distorted over time because a month is assumed to be 30 days.

fn2. Weeks may get a little distorted over time because some years have 53 weeks.

h2. Examples

h3(#eg1). Example 1: Countdown to posted item

The tag defaults to the posted date of the current article.

bc. The party <txp:smd_countdown>
   has arrived. Get your raving trousers on!
<txp:else />
   is <txp:smd_time_info display="day_total" />
     days away: buy a shirt.
</txp:smd_countdown>


The above will always show ‘days away', even when the last day is reached. To improve this, you can do:

bc. is <txp:smd_time_info display="day_total"
     label="day, days" labelafter="1"
     labelspacer=" " />
   away: buy a shirt.


h3(#eg2). Example 2: Displaying multiple items

bc. <txp:smd_countdown to="expires">
<txp:else />
   Time remaining:
   <txp:smd_time_info display="hour, minute, second"
     break=":" label="s" labelafter="1" />
</txp:smd_countdown>


Add @show_zeros="0"@ if you wish to remove any leading zero items as the date draws near. Note that it only removes the most significant ‘zero' items. For example, if you are just over 1 week away from an event:

bc. <txp:smd_time_info
     display="week, day, hour, minute, second"
     break=":" show_zeros="0" />


Might display: @01:00:05:00:19@ (1 week, 0 days, 5 hours, 0 minutes, 19 seconds). But at the same time next day it would show: @06:05:00:19@ (6 days, 5 hours, 0 minutes, 19 seconds).

h3(#eg3). Example 3: Using other fields

A question mark before the name of the field will use the date or timestamp given in that field.

bc. <txp:article_custom time="any" section="events">
   <txp:permlink><txp:title /></txp:permlink>
   <txp:smd_countdown to="?event_date">
      <txp:excerpt />
   <txp:else />
      Event kicks off in:
      <txp:smd_time_info display="day" pad="" show_zeros="0"
        label="day, days" labelafter="1" labelspacer=" " />
      <txp:smd_time_info display="hour, minute, second"
        break=":" label="s" labelafter="1" />
   </txp:smd_countdown>
</txp:article_custom>


The @event_date@ custom field in this case must contain either:

* an English date such as @25 Aug 2014 12:00:00@
* a UNIX timestamp value

h3(#eg4). Example 4: Chaining timers

Starting to go a little crazy now...

bc. <txp:article_custom time="any" section="zoo"
     wraptag="ul" break="ul">
   <txp:title />
   <txp:smd_countdown>
      <!-- When the article has been posted, this bit runs -->
      <txp:smd_countdown to="expires">
         <!-- When the article's expiry is reached... -->
         Time's up!
         You missed this animal by
         <txp:smd_time_info display="second_total"
           labelafter="1" label="second, seconds"
           labelspacer=" " />
      <txp:else />
         <!-- While the article is live -->
         <txp:excerpt />
         You have
         <txp:smd_time_info display="hour"
           labelafter="1" labelspacer=" "
           label="hour, hours" show_zeros="0" />
         <txp:smd_time_info display="minute"
           labelafter="1" labelspacer=" "
           label="minute, minutes" show_zeros="0" />
         <txp:smd_time_info display="second"
           labelafter="1" labelspacer=" "
           label="second, seconds" show_zeros="0" />
         left to <txp:permlink>enjoy this animal</txp:permlink>.
      </txp:smd_countdown>
   <txp:else />
      <!-- This portion is displayed before the article's
          posted time is met -->
      arrives in <txp:smd_time_info
        display="second_total" /> seconds.
   </txp:smd_countdown>
</txp:article_custom>


Very useful for competition articles or events. Note that this only works if the _publish expired articles_ setting is switched on in Advanced Prefs.

h3(#eg5). Example 5: There is no example 5...

... but as food for further study you could use the output from smd_countdown to seed the start of a javascript or flash-based timer which updated a real-time clock counting down to your event.

h2(#author). Author / credits

"Stef Dawson":http://stefdawson.com/contact. A more flexible version of glx_countdown.

h2(#changelog). Changelog

* 29 Oct 2014 | 0.20 | Fixed label br tag
* 09 Aug 2009 | 0.10 | Initial release

# --- END PLUGIN HELP ---
-->
<?php
}
?>
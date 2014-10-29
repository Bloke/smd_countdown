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

$plugin['version'] = '0.10';
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
function smd_countdown($atts, $thing=NULL) {
	global $thisarticle, $smd_timer;

	extract(lAtts(array(
		'to' => 'posted',
		'debug' => 0,
	),$atts));

	$destime = '';

	if ($to == 'posted' || $to == 'expires' || $to == 'modified') {
		assert_article();
		$destime = $thisarticle[$to];
	} else if (strpos($to, "?") === 0) {
		assert_article();
		$fldname = substr(strtolower($to), 1);
		if (isset($thisarticle[$fldname])) {
			$destime = (is_numeric($thisarticle[$fldname])) ? $thisarticle[$fldname] : strtotime($thisarticle[$fldname]);
		}
	} else if ($to != '') {
		$destime = strtotime($to);
	}

	if ($debug) {
		echo '++ DESTINATION ++';
		dmp($destime);
		dmp(date('d M Y H:i:s',$destime));
	}

	if ($destime) {
		$now = time();

		// Split into years/months/weeks/days/hrs/minutes/seconds
		$diff = $destime - $now;
		$absdiff = ($diff>0) ? $diff : $now - $destime;
		$smd_timer['difference'] = $diff;
		$smd_timer['abs_difference'] = $absdiff;
		$smd_timer['destination'] = $destime;

		$lookup = array(
			'year' => array(60 * 60 * 24 * 365),
			'month' => array(60 * 60 * 24 * 30, 12), // month(ish)
			'week' => array(60 * 60 * 24 * 7, 52),
			'day' => array(60 * 60 * 24, 7),
			'hour' => array(60 * 60, 24),
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

		$result = ($diff>0) ? false : true; // True if destination reached, false otherwise

		return parse(EvalElse($thing, $result));
	} else {
		return '';
	}
}

function smd_time_info($atts) {
	global $smd_timer;

	extract(lAtts(array(
		'display' => '',
		'show_zeros' => '1',
		'pad' => '2,0',
		'label' => '',
		'labeltag' => '',
		'labelafter' => 0,
		'labelspacer' => '',
		'wraptag' => '',
		'class' => __FUNCTION__,
		'break' => '',
		'debug' => 0,
	),$atts));

	$display = do_list($display);
	$label = do_list($label);
	$pad = do_list($pad);
	$pad[0] = (empty($pad[0])) ? '1' : $pad[0];
	$pad[1] = (count($pad)==1) ? '0' : $pad[1];

	$out = array();
	$use_plural = false;

	foreach ($display as $item) {
		if (isset($smd_timer[$item])) {
			if ($smd_timer[$item] > 0 || ($smd_timer[$item] == 0 && (!empty($out) || $show_zeros))) {
				$out[] = str_pad($smd_timer[$item], $pad[0], $pad[1], STR_PAD_LEFT);
			}
			$use_plural = ($smd_timer[$item] != 1) ? true : false;
		}
	}

	$theLabel = ($use_plural && isset($label[1])) ? $label[1] : $label[0];

	return ($out)
			? (($labelafter==0) ? doLabel($theLabel.$labelspacer, $labeltag) : '').
				doWrap($out, $wraptag, $break, $class).
				(($labelafter==1) ? doLabel($labelspacer.$theLabel, $labeltag) : '')
			: '';
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
#smd_help { line-height:1.5 ;}
#smd_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
#smd_help code.block { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
#smd_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
#smd_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
#smd_help h3 { color: #275685; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase; text-decoration:underline;}
#smd_help h4 { font: bold 11px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0 ;text-transform: uppercase; }
#smd_help .atnm { font-weight:bold; color:#33d; }
#smd_help .atval { font-style:italic; color:#33d; }
#smd_help .mand { background:#eee; border:1px dotted #999; }
#smd_help table {width:90%; text-align:center; padding-bottom:1em;}
#smd_help td, #smd_help th {border:1px solid #999; padding:.5em 0;}
#smd_help ul { list-style-type:square; }
#smd_help .required {color:red;}
#smd_help li { margin:5px 20px 5px 30px; }
#smd_help .break { margin-top:5px; }
</style>
# --- END PLUGIN CSS ---
-->
<!--
# --- BEGIN PLUGIN HELP ---
<div id="smd_help">

	<h1>smd_countdown</h1>

	<h2>Features</h2>

	<ul>
		<li>Countdown to either:
	<ul>
		<li>article posted, expired, modified time</li>
		<li>any time given in any article field</li>
		<li>any arbitrary time given as an English date</li>
	</ul></li>
		<li>Supports <code>&lt;txp:else /&gt;</code> so you can take action when the time is reached</li>
		<li>Display number of years, months, weeks, days, hours, minutes, seconds as either:
	<ul>
		<li>an absolute number (10 days to go)</li>
		<li>a date-based number (1 week 3 days to go)</li>
	</ul></li>
	</ul>

	<h2 id="author">Author / credits</h2>

	<p><a href="http://stefdawson.com/contact">Stef Dawson</a>. A more flexible version of glx_countdown.</p>

	<h2 id="install">Installation / Uninstallation</h2>

	<p>Download the plugin from either <a href="http://textpattern.org/plugins/1115/smd_countdown">textpattern.org</a>, or the <a href="http://stefdawson.com/sw">software page</a>, paste the code into the Textpattern Admin -&gt; Plugins pane, install and enable the plugin. Visit the <a href="http://forum.textpattern.com/viewtopic.php?id=31494">forum thread</a> for more info or to report on the success or otherwise of the plugin.</p>

	<p>To uninstall, delete from the Admin -&gt; Plugins page.</p>

	<h2>Usage</h2>

	<h3><code>&lt;txp:smd_countdown&gt;</code></h3>

	<p>Place this tag in any article or page to count down to a specific date/time. If you wish to use it to count down to a date in the given article you must ensure it&#8217;s used in an article context (the plugin will complain if you don&#8217;t). Use the <code>to</code> attribute to configure the destination date. Examples:</p>

	<p>In a Page:</p>

<pre class="block"><code class="block">&lt;txp:smd_countdown to=&quot;25 Dec 2009&quot;&gt;
  It&#39;s Christmas day!
&lt;/txp:smd_countdown&gt;
</code></pre>

	<p>In an article / article Form:</p>

<pre class="block"><code class="block">&lt;txp:smd_countdown&gt;
  Posted date has arrived.
&lt;txp:else /&gt;
  Event hasn&#39;t started yet.
&lt;/txp:smd_countdown&gt;
</code></pre>

	<p>Or:</p>

<pre class="block"><code class="block">&lt;txp:smd_countdown to=&quot;expires&quot;&gt;
  Too late, you missed it :-(
&lt;txp:else /&gt;
  There&#39;s still time to get to the show...
&lt;/txp:smd_countdown&gt;
</code></pre>

	<p>Or:</p>

<pre class="block"><code class="block">&lt;txp:smd_countdown to=&quot;?my_date&quot;&gt;
  The date in custom field #1 (called my_date) has been reached
&lt;/txp:smd_countdown&gt;
</code></pre>

	<h3><code>&lt;txp:smd_time_info&gt;</code></h3>

	<p>If you wish to show visitors how much time they have left before the destination arrives, use this tag to display various time elements. The following attributes configure what you wish to show:</p>

	<h4 class="atts " id="attributes">Attributes</h4>

	<ul>
		<li><span class="atnm">display</span> : the item(s) you wish to display. Can use one or more (comma-separated) from the following:
	<ul>
		<li><span class="atval">year</span> (or <span class="atval">year_total</span>) : the number of years until the event</li>
		<li><span class="atval">month</span> : the number of calendar months until the event, maximum 12<sup class="footnote"><a href="#fn312234a7eebcc821e6">1</a></sup></li>
		<li><span class="atval">month_total</span> : the absolute number of months until the event<sup class="footnote"><a href="#fn312234a7eebcc821e6">1</a></sup></li>
		<li><span class="atval">week</span> : the number of calendar weeks until the event, maximum 52<sup class="footnote"><a href="#fn246764a7eebcc82225">2</a></sup></li>
		<li><span class="atval">week_total</span> : the absolute number of weeks until the event<sup class="footnote"><a href="#fn246764a7eebcc82225">2</a></sup></li>
		<li><span class="atval">day</span> : the number of calendar days until the event, maximum 7</li>
		<li><span class="atval">day_total</span> : the absolute number of days until the event</li>
		<li><span class="atval">hour</span> : the number of day-based hours until the event, maximum 24</li>
		<li><span class="atval">hour_total</span> : the absolute number of hours until the event</li>
		<li><span class="atval">minute</span> : the number of hour-based minutes until the event, maximum 60</li>
		<li><span class="atval">minute_total</span> : the absolute number of minutes until the event</li>
		<li><span class="atval">second</span> : the number of minute-based seconds until the event, maximum 60</li>
		<li><span class="atval">second_total</span> : the absolute number of seconds until the event</li>
		<li><span class="atval">destination</span> : the <span class="caps">UNIX</span> timestamp of the destination</li>
		<li><span class="atval">difference</span> : the <span class="caps">UNIX</span> timestamp difference between now and the destination (may be negative if the destination has passed)</li>
		<li><span class="atval">abs_difference</span> : the <span class="caps">UNIX</span> timestamp difference between now and the destination irrespective of whether the date has passed or not</li>
	</ul></li>
		<li><span class="atnm">show_zeros</span> : if you wish to hide leading items that have zero months, weeks, days, etc, set this to 0. Default: 1</li>
		<li><span class="atnm">pad</span> : pad the numerical output with some text. Specify up to two comma-separated values here. The first is the total width in characters of the string you wish to display. The second is the text with which you wish to pad the numbers. Default: <code>2, 0</code> (i.e. pad to a width of two characters, using zeros if necessary)</li>
		<li><span class="atnm">label</span> : up to two values with which you can label the given display item(s). If you specify two values, the first is what to use for singular numbers (e.g. 1 <strong>hour</strong>), the second is the plural (e.g. 3 <strong>hours</strong>). Note that using the <code>singular, plural</code> form in this attribute does not usually make sense when <code>display</code> is a list of items. Default: unset</li>
		<li><span class="atnm">labeltag</span> : (X)HTML tag, without brackets, to wrap the label with. Default: unset</li>
		<li><span class="atnm">labelafter</span> : set to 1 if you wish the label to be appended to the displayed item(s). Default: 0 (i.e. prepend)</li>
		<li><span class="atnm">labelspacer</span> : text to put before/after the label. Very useful if <code>labelafter=&quot;1&quot;</code> and you wish to put a space between the number and the label. Default: unset</li>
		<li><span class="atnm">wraptag</span> : (X)HTML tag, without brackets, to wrap the displayed items with. Default: unset</li>
		<li><span class="atnm">class</span> : <span class="caps">CSS</span> class name to apply to the wraptag. Default: <code>smd_time_info</code></li>
		<li><span class="atnm">break</span> : (X)HTML tag, (without brackets) or other delimiter to wrap / put between each display item. Default: unset</li>
	</ul>

	<p id="fn312234a7eebcc821e6" class="footnote"><sup>1</sup> Months may get a little distorted over time because a month is assumed to be 30 days</p>

	<p id="fn246764a7eebcc82225" class="footnote"><sup>2</sup> Weeks may get a little distorted over time because some years have 53 weeks</p>

	<h2>Examples</h2>

	<h3 id="eg1">Example 1: Countdown to posted item</h3>

	<p>The tag defaults to the posted date of the current article.</p>

<pre class="block"><code class="block">The party &lt;txp:smd_countdown&gt;
   has arrived. Get your raving trousers on!
&lt;txp:else /&gt;
   is &lt;txp:smd_time_info display=&quot;day_total&quot; /&gt;
     days away: buy a shirt.
&lt;/txp:smd_countdown&gt;
</code></pre>

	<p>The above will always show &#8216;days away&#8217;, even when the last day is reached. To improve this, you can do:</p>

<pre class="block"><code class="block">is &lt;txp:smd_time_info display=&quot;day_total&quot;
     label=&quot;day, days&quot; labelafter=&quot;1&quot;
     labelspacer=&quot; &quot; /&gt;
   away: buy a shirt.
</code></pre>

	<h3 id="eg2">Example 2: Displaying multiple items</h3>

<pre class="block"><code class="block">&lt;txp:smd_countdown to=&quot;expires&quot;&gt;
&lt;txp:else /&gt;
   Time remaining:
   &lt;txp:smd_time_info display=&quot;hour, minute, second&quot;
     break=&quot;:&quot; label=&quot;s&quot; labelafter=&quot;1&quot; /&gt;
&lt;/txp:smd_countdown&gt;
</code></pre>

	<p>Add <code>show_zeros=&quot;0&quot;</code> if you wish to remove any leading zero items as the date draws near. Note that it only removes ther most significant &#8216;zero&#8217; items. For example, if you are just over 1 week away from an event:</p>

<pre class="block"><code class="block">&lt;txp:smd_time_info
     display=&quot;week, day, hour, minute, second&quot;
     break=&quot;:&quot; show_zeros=&quot;0&quot; /&gt;
</code></pre>

	<p>Might display: <code>01:00:05:00:19</code> (1 week, 0 days, 5 hours, 0 minutes, 19 seconds). But at the same time next day it would show: <code>06:05:00:19</code> (6 days, 5 hours, 0 minutes, 19 seconds).</p>

	<h3 id="eg3">Example 3: Using other fields</h3>

	<p>A question mark before the name of the field will use the date or timestamp given in that field.</p>

<pre class="block"><code class="block">&lt;txp:article_custom time=&quot;any&quot; section=&quot;events&quot;&gt;
   &lt;txp:permlink&gt;&lt;txp:title /&gt;&lt;/txp:permlink&gt;
   &lt;txp:smd_countdown to=&quot;?event_date&quot;&gt;
      &lt;txp:excerpt /&gt;
   &lt;txp:else /&gt;
      Event kicks off in:
      &lt;txp:smd_time_info display=&quot;day&quot; pad=&quot;&quot; show_zeros=&quot;0&quot;
        label=&quot;day, days&quot; labelafter=&quot;1&quot; labelspacer=&quot; &quot; /&gt;
      &lt;txp:smd_time_info display=&quot;hour, minute, second&quot;
        break=&quot;:&quot; label=&quot;s&quot; labelafter=&quot;1&quot; /&gt;
   &lt;/txp:smd_countdown&gt;
&lt;/txp:article_custom&gt;
</code></pre>

	<p>The <code>event_date</code> custom field in this case must contain either:</p>

	<ul>
		<li>an English date such as <code>25 Aug 2009 12:00:00</code></li>
		<li>a <span class="caps">UNIX</span> timestamp value</li>
	</ul>

	<h3 id="eg4">Example 4: Chaining timers</h3>

	<p>Starting to go a little crazy now&#8230;</p>

<pre class="block"><code class="block">&lt;txp:article_custom time=&quot;any&quot; section=&quot;zoo&quot;
     wraptag=&quot;ul&quot; break=&quot;ul&quot;&gt;
   &lt;txp:title /&gt;
   &lt;txp:smd_countdown&gt;
      &lt;!-- When the article has been posted, this bit runs --&gt;
      &lt;txp:smd_countdown to=&quot;expires&quot;&gt;
         &lt;!-- When the article&#39;s expiry is reached... --&gt;
         Time&#39;s up!
         You missed this animal by
         &lt;txp:smd_time_info display=&quot;second_total&quot;
           labelafter=&quot;1&quot; label=&quot;second, seconds&quot;
           labelspacer=&quot; &quot; /&gt;
      &lt;txp:else /&gt;
         &lt;!-- While the article is live --&gt;
         &lt;txp:excerpt /&gt;
         You have
         &lt;txp:smd_time_info display=&quot;hour&quot;
           labelafter=&quot;1&quot; labelspacer=&quot; &quot;
           label=&quot;hour, hours&quot; show_zeros=&quot;0&quot; /&gt;
         &lt;txp:smd_time_info display=&quot;minute&quot;
           labelafter=&quot;1&quot; labelspacer=&quot; &quot;
           label=&quot;minute, minutes&quot; show_zeros=&quot;0&quot; /&gt;
         &lt;txp:smd_time_info display=&quot;second&quot;
           labelafter=&quot;1&quot; labelspacer=&quot; &quot;
           label=&quot;second, seconds&quot; show_zeros=&quot;0&quot; /&gt;
         left to &lt;txp:permlink&gt;enjoy this animal&lt;/txp:permlink&gt;.
      &lt;/txp:smd_countdown&gt;
   &lt;txp:else /&gt;
      &lt;!-- This portion is displayed before the article&#39;s
          posted time is met --&gt;
      arrives in &lt;txp:smd_time_info
        display=&quot;second_total&quot; /&gt; seconds.
   &lt;/txp:smd_countdown&gt;
&lt;/txp:article_custom&gt;
</code></pre>

	<p>Very useful for competition articles or events. Note that this only works if the <em>publish expired articles</em> setting is switched on in Advanced Prefs.</p>

	<h3 id="eg5">Example 5: There is no example 5&#8230;</h3>

	<p>&#8230; but as food for further study you could use the output from smd_countdown to seed the start of a javascript or flash-based timer which updated a real-time clock counting down to your event.</p>

</div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>
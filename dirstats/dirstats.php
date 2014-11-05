<?php
/**
* Name: DirStats
* Description: Show some statistics about the directory.  
* This will list the number of RedMatrix, Friendica and Diaspora
* hubs that your own hub is aware of.  
* As the name suggets, this is intended for directory servers, where
* this will provide accurate counts of all known Red hubs and channels.
*
* If you are not a directory server - and for Friendica and Diaspora 
* even if you are - these counts are merely those your own hub is aware of
* and not all that exist in the network.
*
* Version: 1.0
* Author: Thomas Willingham <zot:beardyunixer@beardyunixer.com>
*/

function dirstats_load() {
	register_hook('cron_daily', 'addon/dirstats/dirstats.php', 'dirstats_cron');
}
function dirstats_unload() {
	unregister_hook('cron_daily', 'addon/dirstats/dirstats.php', 'dirstats_cron');
}
function dirstats_module() {}


function dirstats_init() {
	if(! get_config('dirstats','hubcount'))
        dirstats_cron($a,$b);

}

function dirstats_content(&$a) {

	$hubcount = get_config('dirstats','hubcount');
	$zotcount = get_config('dirstats','zotcount');
	$friendicacount = get_config('dirstats','friendicacount');
	$diasporacount = get_config('dirstats','diasporacount');
	$channelcount = get_config('dirstats','channelcount');
	$friendicachannelcount = get_config('dirstats','friendicachannelcount');
	$diasporachannelcount = get_config('dirstats','diasporachannelcount');
	$over35s = get_config('dirstats','over35s');
	$average = get_config('dirstats','average');
	$chatrooms = get_config('dirstats','chatrooms');
	$tags = get_config('dirstats','tags');

		$ob = $a->get_observer();
		$observer = $ob['xchan_hash'];
		// Requested by Martin
		$fountainofyouth = get_xconfig($observer, 'dirstats', 'averageage');
		if (intval($fountainofyouth))
			$average = $fountainofyouth;

if (argv(1) == 'json') { 
	$dirstats = array (
                'hubcount' => $hubcount,
                'zotcount' => $zotcount,
                'friendicacount' => $friendicacount,
                'diasporacount' => $diasporacount,
                'channelcount' => $channelcount,
                'friendicachannelcount' => $friendicachannelcount,
                'diasporachannelcount' => $diasporachannelcount,
                'over35s' => $over35s,
                'under35s' => $under35s,
                'average' => $average,
                'chatrooms' => $chatrooms,
                'tags' => $tags
		);
	echo json_return_and_die($dirstats);
	}

	// Used by RedMatrix News
	elseif (argv(1) == 'genpost') {
			$result = '[b]Hub count[/b] : ' . $hubcount . "\xA" .
			'[b]RedMatrix Hubs[/b] : ' . $zotcount . "\xA" .
			'[b]Friendica Hubs[/b] : ' . $friendicacount . "\xA" .
			'[b]Diaspora Pods[/b] : ' . $diasporacount . "\xA" .
			'[b]RedMatrix Channels[/b] : ' . $channelcount . "\xA" .
			'[b]Friendica Profiles[/b] : ' . $friendicachannelcount . "\xA" .
			'[b]Diaspora Profiles[/b] : ' . $diasporachannelcount . "\xA" .
			'[b]People aged 35 and above[/b] : ' . $over35s . "\xA" .
			'[b]People aged 34 and below[/b] : ' . $under35s . "\xA" .
			'[b]Average Age[/b] : ' . $average . "\xA" .
			'[b]Known Chatrooms[/b] : ' . $chatrooms . "\xA" .
			'[b]Unique Profile Tags[/b] : ' . $tags . "\xA";

	                file_put_contents('genpost', $result);
	}
else {
	$tpl = get_markup_template( "dirstats.tpl", "addon/dirstats/" );
	 return replace_macros($tpl, array(
		'$hubcount' => $hubcount,
		'$zotcount' => $zotcount,
		'$friendicacount' => $friendicacount,
		'$diasporacount' => $diasporacount,
		'$channelcount' => $channelcount,
		'$friendicachannelcount' => $friendicachannelcount,
		'$diasporachannelcount' => $diasporachannelcount,
		'$over35s' => $over35s,
		'$under35s' => $under35s,
		'$average' => $average,
		'$chatrooms' => $chatrooms,
		'$tags' => $tags
		));
	}

}
function dirstats_cron(&$a, $b) {
	echo "starting";
	$r = q("SELECT count(distinct hubloc_host) as total FROM `hubloc`");
		if ($r) {
		$hubcount = $r[0]['total'];
		set_config('dirstats','hubcount',$hubcount);
		}

		$r = q("SELECT count(distinct hubloc_host) as total FROM `hubloc` where hubloc_network = 'zot'");
			if ($r) {
			$zotcount = $r[0]['total'];
			set_config('dirstats','zotcount',$zotcount);
			}
		$r = q("SELECT count(distinct hubloc_host) as total FROM `hubloc` where hubloc_network = 'friendica-over-diaspora'");
		if ($r){
			$friendicacount = $r[0]['total'];
			set_config('dirstats','friendicacount',$friendicaount);
		}
		$r = q("SELECT count(distinct hubloc_host) as total FROM `hubloc` where hubloc_network = 'diaspora'");
		if ($r) {
			$diasporacount = $r[0]['total'];
			set_config('dirstats','diasporacount',$diasporacount);
		}
		$r = q("SELECT count(distinct xchan_hash) as total FROM `xchan` where xchan_network = 'zot'");
		if ($r) {
			$channelcount = $r[0]['total'];
			set_config('dirstats','channelcount',$channelcount);
		}
		$r = q("SELECT count(distinct xchan_hash) as total FROM `xchan` where xchan_network = 'friendica-over-diaspora'");
		if ($r) {
			$friendicachannelcount = $r[0]['total'];
			set_config('dirstats','friendicachannelcount',$friendicachannelcount);
		}
		$r = q("SELECT count(distinct xchan_hash) as total FROM `xchan` where xchan_network = 'diaspora'");
		if ($r) {
			$diasporachannelcount = $r[0]['total'];
			set_config('dirstats','diasporachannelcount',$diasporachannelcount);
		}
		$r = q("select count(xprof_hash) as total from `xprof` where xprof_age >=35");
		if ($r) {
			$over35s = $r[0]['total'];
			set_config('dirstats','over35s',$over35s);
		}
		$r = q("select count(xprof_hash) as total from `xprof` where xprof_age <=34 and xprof_age >=1");
		if ($r) {
			$under35s = $r[0]['total'];
			set_config('dirstats','under35s',$under35s);
		}

		$r = q("select sum(xprof_age) as sum from xprof");
			if ($r) {
				$rr = q("select count(xprof_hash) as total from `xprof` where xprof_age >=1");
				$total = $r[0]['sum'];
				$number = $rr[0]['total'];
				$average = $total / $number;
				set_config('dirstats','averageage',$average);
		}

		$r = q("select count(distinct xchat_url) as total from `xchat`");
		if ($r) {
			$chatrooms = $r[0]['total'];
			set_config('dirstats','chatrooms',$chatrooms);
		}
		$r = q("select count(distinct xtag_term) as total from `xtag`");
		if ($r) {
			$tags = $r[0]['total'];
			set_config('dirstats','tags',$tags);
		}
}
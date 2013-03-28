<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: space_index.php 25870 2011-11-24 07:05:44Z zhengqingpeng $
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

if(($_G['adminid'] == 1 && $_G['setting']['allowquickviewprofile'] && $_GET['view'] != 'admin' && $_GET['diy'] != 'yes') || defined('IN_MOBILE')) {
	dheader("Location:home.php?mod=space&uid=$space[uid]&do=profile");
}

require_once libfile('function/space');

space_merge($space, 'field_home');
$userdiy = getuserdiydata($space);

if ($_GET['op'] == 'getmusiclist') {
	if(empty($space['uid'])) {
		exit();
	}
	$reauthcode = substr(md5($_G['authkey'].$space['uid']), 6, 16);
	if($reauthcode == $_GET['hash']) {
		space_merge($space,'field_home');
		$userdiy = getuserdiydata($space);
		$musicmsgs = $userdiy['parameters']['music'];
		$outxml = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
		$outxml .= '<playlist version="1">'."\n";
		$outxml .= '<mp3config>'."\n";
		$showmod = 'big' == $musicmsgs['config']['showmod'] ? 'true' : 'false';
		$outxml .= '<showdisplay>'.$showmod.'</showdisplay>'."\n";
		$outxml .= '<autostart>'.$musicmsgs['config']['autorun'].'</autostart>'."\n";
		$outxml .= '<showplaylist>true</showplaylist>'."\n";
		$outxml .= '<shuffle>'.$musicmsgs['config']['shuffle'].'</shuffle>'."\n";
		$outxml .= '<repeat>all</repeat>'."\n";
		$outxml .= '<volume>100</volume>';
		$outxml .= '<linktarget>_top</linktarget> '."\n";
		$outxml .= '<backcolor>0x'.substr($musicmsgs['config']['crontabcolor'], -6).'</backcolor> '."\n";
		$outxml .= '<frontcolor>0x'.substr($musicmsgs['config']['buttoncolor'], -6).'</frontcolor>'."\n";
		$outxml .= '<lightcolor>0x'.substr($musicmsgs['config']['fontcolor'], -6).'</lightcolor>'."\n";
		$outxml .= '<jpgfile>'.$musicmsgs['config']['crontabbj'].'</jpgfile>'."\n";
		$outxml .= '<callback></callback> '."\n";
		$outxml .= '</mp3config>'."\n";
		$outxml .= '<trackList>'."\n";
		foreach ($musicmsgs['mp3list'] as $value){
			$outxml .= '<track><annotation>'.$value['mp3name'].'</annotation><location>'.$value['mp3url'].'</location><image>'.$value['cdbj'].'</image></track>'."\n";
		}
		$outxml .= '</trackList></playlist>';
		$outxml = diconv($outxml, CHARSET, 'UTF-8');
		obclean();
		@header("Expires: -1");
		@header("Cache-Control: no-store, private, post-check=0, pre-check=0, max-age=0", FALSE);
		@header("Pragma: no-cache");
		@header("Content-type: application/xml; charset=utf-8");
		echo $outxml;
	}
	exit();

}else{

	if(!$_G['setting']['preventrefresh'] || $_G['uid'] && !$space['self'] && $_G['cookie']['viewid'] != 'uid_'.$space['uid']) {
		member_count_update($space['uid'], array('views' => 1));
		$viewuids[$space['uid']] = $space['uid'];
		dsetcookie('viewid', 'uid_'.$space['uid']);
	}

	if(!$space['self'] && $_G['uid']) {
		$visitor = C::t('home_visitor')->fetch_by_uid_vuid($space['uid'], $_G['uid']);
		$is_anonymous = empty($_G['cookie']['anonymous_visit_'.$_G['uid'].'_'.$space['uid']]) ? 0 : 1;
		if(empty($visitor['dateline'])) {
			$setarr = array(
				'uid' => $space['uid'],
				'vuid' => $_G['uid'],
				'vusername' => $is_anonymous ? '' : $_G['username'],
				'dateline' => $_G['timestamp']
			);
			C::t('home_visitor')->insert($setarr, false, true);
			show_credit();
		} else {
			if($_G['timestamp'] - $visitor['dateline'] >= 300) {
				C::t('home_visitor')->update_by_uid_vuid($space['uid'], $_G['uid'], array('dateline'=>$_G['timestamp'], 'vusername'=>$is_anonymous ? '' : $_G['username']));
			}
			if($_G['timestamp'] - $visitor['dateline'] >= 3600) {
				show_credit();
			}
		}
		updatecreditbyaction('visit', 0, array(), $space['uid']);
	}

	if($do != 'profile' && !ckprivacy($do, 'view')) {
		$_G['privacy'] = 1;
		require_once libfile('space/profile', 'include');
		include template('home/space_privacy');
		exit();
	}

	$widths = getlayout($userdiy['currentlayout']);
	$leftlist = formatdata($userdiy, 'left', $space);
	$centerlist = formatdata($userdiy, 'center', $space);
	$rightlist = formatdata($userdiy, 'right', $space);

	dsetcookie('home_diymode', 1);
}

$navtitle = !empty($space['spacename']) ? $space['spacename'] : lang('space', 'sb_space', array('who' => $space['username']));
$metakeywords = lang('space', 'sb_space', array('who' => $space['username']));
$metadescription = lang('space', 'sb_space', array('who' => $space['username']));
$space['medals'] = getuserprofile('medals');
if($space['medals']) {
	loadcache('medals');
	foreach($space['medals'] = explode("\t", $space['medals']) as $key => $medalid) {
		list($medalid, $medalexpiration) = explode("|", $medalid);
		if(isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
			$space['medals'][$key] = $_G['cache']['medals'][$medalid];
		} else {
			unset($space['medals'][$key]);
		}
	}
}
include_once(template('home/space_index'));

function formatdata($data, $position, $space) {
	$list = array();
	foreach ((array)$data['block']['frame`frame1']['column`frame1_'.$position] as $blockname => $blockdata) {
		if (strpos($blockname, 'block`') === false || empty($blockdata) || !isset($blockdata['attr']['name'])) continue;
		$name = $blockdata['attr']['name'];
		if(check_ban_block($name, $space)) {
			$list[$name] = getblockhtml($name, $data['parameters'][$name]);
		}
	}
	return $list;
}

function show_credit() {
	global $_G, $space;

	$showinfo = C::t('home_show')->fetch($space['uid']);
	if($showinfo['credit'] > 0) {
		$showinfo['unitprice'] = intval($showinfo['unitprice']);
		if($showinfo['credit'] <= $showinfo['unitprice']) {
			notification_add($space['uid'], 'show', 'show_out');
			C::t('home_show')->delete($space['uid']);
		} else {
			C::t('home_show')->update_credit_by_uid($space['uid'], -$showinfo['unitprice']);
		}
	}
}
?>
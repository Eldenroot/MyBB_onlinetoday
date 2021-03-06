<?php
/**
 *	Copyright � 2006-2008 CraKteR, crakter [at] gmail [dot] com
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	@author CraKteR <crakter@gmail.com>
 *  @edited by Sinatra and further improved by Eldenroot <eldenroot@gmail.com>
 */

if(!defined("IN_MYBB"))
{
    die("This file cannot be accessed directly.");
}

// Hooks
$plugins->add_hook('index_start', 'add_onlinetoday', 1000000);
$plugins->add_hook('global_start', 'onlinetoday_templates');

// Plugin info
function onlinetoday_info()
{
	return array(
		"name"			=>	"Show the users that has been online today",
		"description"	=>	"Shows the users that has been online within 24 hours.",
		"website"		=>	"www.realityforums.cf",
		"author"		=>	"CraKteR - Edited by Sinatra and further improved by Eldenroot",
		"authorsite"	=>	"mailto:claustroagar@gmail.com",
		"version"		=>	"2.0.1",
		"codename"		=>	"",
		"guid"			=>	"c2f1dd8db9b4f3898cb58f5ed02f9b53",
		"compatibility" =>	"18*",
	);
}

// Cache plugin templates
function onlinetoday_templates() 
{
	global $templatelist;
	if (THIS_SCRIPT == 'index.php') {
		if (isset($templatelist)) {
			$templatelist.= ',';
		}
		$templatelist.= 'online_today_index';
	}
}

// Plugin activate
function onlinetoday_activate()
{
	global $db;
	$template = array(
		"tid"		=> NULL,
		"title"		=> "online_today_index",
		"template"	=> "<tr>
	<td class=\"tcat\"><span class=\"smalltext\"><strong>{\$lang->whos_online_today}</strong> [<a href=\"online.php?action=today\">{\$lang->complete_list}</a>]</span></td>
</tr>
<tr>
	<td class=\"trow1\"><span class=\"smalltext\">{\$lang->online_note_today}<br />{\$onlinemembers}</span></td>
</tr>",
		"sid"		=> "-1"
	);
	$db->insert_query("templates", $template);

	require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('index_boardstats', '#{\$whosonline}#', "{\$whosonline}\n{\$online_today}");
}

// Plugin deactivate
function onlinetoday_deactivate()
{
	global $db;
	$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE title='online_today_index'");

	require MYBB_ROOT."/inc/adminfunctions_templates.php";

	find_replace_templatesets('index_boardstats', '#(\n?){\$online_today}#', '', 0);
}

// Do the black magic :)
function add_onlinetoday()
{
	global $db, $mybb, $templates, $online_today, $lang, $theme;
	$online_today = '';

	if($mybb->settings['showwol'] != 0 && $mybb->usergroup['canviewonline'] != 0)
	{
		$lang->load("onlinetoday");
		$lang->load("index");
		$timesearch = time() - 24*60*60;
		$queries = array();
		$queries[] = $db->simple_select(
			"users u LEFT JOIN ".TABLE_PREFIX."sessions s ON (u.uid=s.uid)", 
			"s.sid, s.ip, s.time, s.location, u.uid, u.username, u.invisible, u.usergroup, u.displaygroup",
			"u.lastactive > $timesearch ORDER BY u.username ASC, s.time DESC"
		);
		$queries[] = $db->simple_select(
			"sessions s LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)",
			"s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup",
			"s.time>'$timesearch' ORDER BY u.username ASC, s.time DESC"
		);
		$comma = $onlinemembers = '';
		$membercount = $guestcount = $anoncount = 0;
		$doneusers = $ips = array();
		foreach($queries as $query)
		{
			while($user = $db->fetch_array($query))
			{
				if(isset($user['sid']))
				{
					$botkey = strtolower(str_replace("bot=", '', $user['sid']));
				}

				if($user['uid'] > 0)
				{
					if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
					{
						if($user['invisible'] == 1)
						{
							++$anoncount;
						}
						++$membercount;
						if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
						{
							$invisiblemark = ($user['invisible'] == 1) ? "*" : "";
							$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
							eval("\$onlinemembers .= \"$comma".$templates->get("index_whosonline_memberbit", 1, 0)."\";");
							$comma = ", ";
						}

						if(isset($user['time']))
						{
							$doneusers[$user['uid']] = $user['time'];
						}
						else
						{
							$doneusers[$user['uid']] = $user['lastactive'];
						}
					}
				}
				else if(strstr($user['sid'], "bot=") !== false && $session->bots[$botkey] && !in_array($user['ip'], $ips))
				{
					$onlinemembers .= $comma.format_name($session->bots[$botkey], $session->botgroup);
					$comma = ", ";
					++$botcount;
				}
				else
				{
					++$guestcount;
					$guests[] = $user['ip'];
				}
			}
		}

		$onlinecount = $membercount + $guestcount;
		$onlinebit = ($onlinecount != 1) ? $lang->online_online_plural : $lang->online_online_singular;
		$memberbit = ($membercount != 1) ? $lang->online_member_plural : $lang->online_member_singular;
		$anonbit = ($anoncount != 1) ? $lang->online_anon_plural : $lang->online_anon_singular;
		$guestbit = ($guestcount != 1) ? $lang->online_guest_plural : $lang->online_guest_singular;
		$lang->online_note_today = $lang->sprintf($lang->online_note_today, my_number_format($onlinecount), $onlinebit, 24, my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);
		eval("\$online_today = \"".$templates->get("online_today_index")."\";");
	}
}
<?php

require "../include/bittorrent.php";
dbconn(false);
loggedinorreturn();
require_once(get_langfile_path("blackjack.php"));
if ($CURUSER['class'] < UC_USER) {
    stderr( $lang_blackjack['std_error'], $lang_blackjack['text_Your_level_is_too_low'], "index.php", false, true, false );
}

function begin_table_bjstats() {
	return("<table class='layui-table' lay-size='sm' style='width: 30%'>");
}

function end_table_bjstats() {
	return("</table>");
}

function frame_bjstats($caption = "") {
	return(($caption ? "<thead><tr><th colspan='7'>".$caption."</th></tr></thead>" : ""));
}


function bjtable($res, $frame_caption) {
    global $lang_blackjack;
	$mb = 1000;
	$htmlout = '';
	$htmlout .= begin_table_bjstats();
    $htmlout .= frame_bjstats($frame_caption);
	$htmlout .= "<thead><tr><th>".$lang_blackjack['text_rank']."</th><th>".$lang_blackjack['text_user_name']."</th><th>".$lang_blackjack['text_winning_field']."</th><th>".$lang_blackjack['text_losing_field']."</th><th>".$lang_blackjack['total_number_of_sessions']."</th><th>".$lang_blackjack['winning_percentage']."</th><th>".$lang_blackjack['win_loss']."</th></tr></thead>";
	$num = 0;
	while ($a = mysql_fetch_assoc($res)) {
		++$num;
		//==Calculate Win %
		$win_perc = number_format(($a['wins'] / $a['games']) * 100, 1);
		//==Add a user' s +/- statistic
		$plus_minus = $a['wins'] - $a['losses'];
		if ($plus_minus >= 0) {
			$plus_minus = ($a['wins'] - $a['losses']) * 0.95 * $mb;
		} else {
			$plus_minus = "-";
			$plus_minus .= ($a['losses'] - $a['wins']) * $mb;
		}
		$htmlout .="<tr class='center'><td>$num</td><td>" .
				"<b><a href='userdetails.php?id=" . $a['id'] . "'>" . get_username($a["id"]) . "</a></b></td>" .
				"<td>" . number_format($a['wins'], 0) . "</td>" .
				"<td>" . number_format($a['losses'], 0) . "</td>" .
				"<td>" . number_format($a['games'], 0) . "</td>" .
				"<td>$win_perc%</td>" .
				"<td>$plus_minus</td>" .
				"</tr>";
	}
	$htmlout .= end_table_bjstats();
	return $htmlout;
}

$cachetime = 60 * 30; // 30 minutes
//$cachetime = 10 * 3;
$Cache->new_page('bjstats', $cachetime, true);
if (!$Cache->get_page()) {
	$Cache->add_whole_row();
	$mingames = 5;
	$HTMLOUT = '';
    $HTMLOUT .= "<div class='container'>";
	$HTMLOUT .="<fieldset class='layui-elem-field layui-field-title'><legend>".$lang_blackjack['historical_statistics']."</legend>" . "<blockquote class='layui-elem-quote'>".$lang_blackjack['text_statistics_tips_1'].$mingames.$lang_blackjack['text_statistics_tips_2']."</blockquote></fieldset>";
	//==Most Games Played
	$res = sql_query("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games FROM users WHERE bjwins + bjlosses > $mingames ORDER BY games DESC LIMIT 10") or sqlerr(__FILE__, __LINE__);
	$HTMLOUT .= bjtable($res, "最多上场榜", "Users");
	//==Most Games Played
	//==Highest Win %
	$res = sql_query("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjwins / (bjwins + bjlosses) AS winperc FROM users WHERE bjwins + bjlosses > $mingames ORDER BY winperc DESC LIMIT 10") or sqlerr(__FILE__, __LINE__);
	$HTMLOUT .= bjtable($res, "最佳胜率榜", "Users");
	//==Highest Win %
	//==Most Credit Won
	$res = sql_query("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjwins - bjlosses AS winnings FROM users WHERE bjwins + bjlosses > $mingames ORDER BY winnings DESC LIMIT 10") or sqlerr(__FILE__, __LINE__);
	$HTMLOUT .= bjtable($res, "收入最高榜", "Users");
	//==Most Credit Won
	//==Most Credit Lost
	$res = sql_query("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjlosses - bjwins AS losings FROM users WHERE bjwins + bjlosses > $mingames ORDER BY losings DESC LIMIT 10") or sqlerr(__FILE__, __LINE__);
	$HTMLOUT .= bjtable($res, "损失最多榜", "Users");
	//==Most Credit Lost
    $HTMLOUT .= "</div>";
	print $HTMLOUT;
	$Cache->end_whole_row();
	$Cache->cache_page();
}
stdhead('21点 统计');
echo $Cache->next_row();
print ("<div class='layui-footer'>");
stdfoot();
print ("</div>");

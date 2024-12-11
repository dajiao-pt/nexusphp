<?php
require "../include/bittorrent.php";
//require_once "include/user_functions.php";
dbconn();
loggedinorreturn();
require_once(get_langfile_path());
global $blackjack, $CURUSER, $lang_blackjack;
if ($blackjack === 'no' || $CURUSER['class'] < UC_USER) {
    stderr($lang_blackjack['text_error'], $lang_blackjack['text_Your_level_is_too_low'], "index.php", false);
}
$HTMLOUT = '';
//$mb = 102400 * 1024 * 1024;
$mb = 10000;
$now = sqlesc(date("Y-m-d H:i:s"));
$game = isset($_POST["game"]) ? htmlspecialchars(trim($_POST["game"])) : '';
$start = isset($_POST["start"]) ? htmlspecialchars(trim($_POST["start"])) : '';
function cheater_check($arg) {
    if ($arg) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
if ($game) {
    $cardcount = 52;
    $points = 0;
    $showcards = '';
    $aces = '';
    if ($start !== 'yes') {
        $playeres = sql_query("SELECT * FROM blackjack WHERE userid = " . sqlesc($CURUSER['id']));
        $playerarr = mysql_fetch_assoc($playeres);
        if ($game === 'hit'){
            $points = $aces = 0;
        }
        $gameover = ($playerarr[ 'gameover' ] === 'yes');
        $HTMLOUT .= cheater_check($gameover && ($game === 'hit' ^ $game === 'stop'));
        $cards = $playerarr["cards"];
        $usedcards = explode(" ", $cards);
        $arr = array();
        foreach ($usedcards as $array_list){
            $arr[] = $array_list;
        }
        foreach ($arr as $card_id) {
            $used_card = sql_query("SELECT * FROM cards WHERE id=" . sqlesc($card_id));
            $used_cards = mysql_fetch_assoc($used_card);
            $showcards .= "<img src='./pic/cards/" . $used_cards["pic"] . "' style='border: 1px' /> ";
            if ($used_cards["points"] > 1){
                $points += $used_cards['points'];
            }else{
                $aces++;
            }
        }
    }

    if ($_POST["game"] === 'hit') {
        if ($start === 'yes') {
            if ($CURUSER["seedbonus"] < $mb){
                stderr($lang_blackjack['text_error'] . $CURUSER["username"], $lang_blackjack['text_at_least'] . $mb . $lang_blackjack["text_points"], false);
            }
            /*$required_ratio = 1;
            if ($CURUSER["downloaded"] > 0){
                $ratio = number_format($CURUSER["uploaded"] / $CURUSER["downloaded"], 3);
            }elseif ($CURUSER["uploaded"] > 0){
                $ratio = 999;
            }else{
                $ratio = 0;
            }
            if ($ratio < $required_ratio){
                stderr($lang_blackjack['text_error'] . $CURUSER["username"], $lang_blackjack['text_share_rate'] . $required_ratio . " 。", false);
            }*/
            $res = sql_query("SELECT status, gameover,cards,points FROM blackjack WHERE userid = " . sqlesc($CURUSER['id']));
            $arr = mysql_fetch_assoc($res);
            $is_continue = false;
            if (!empty($arr)) {
                if ( $arr[ 'status' ] === 'waiting' ) {
                    stderr( $lang_blackjack[ 'text_error' ], $lang_blackjack[ 'text_waiting' ], false );
                } elseif ( $arr[ 'status' ] === 'playing' ) {
                    $is_continue = true;
                }
            }
            $cardids = array();
            if ($is_continue) {
                if (isset($arr['cards'])) {
                    $cardids = explode(" ",$arr['cards']);
                }
                $points = $arr['points'];
                foreach ($cardids as $cardid) {
                    $cardres = sql_query("SELECT points, pic FROM cards WHERE id='$cardid'");
                    $cardarr = mysql_fetch_assoc($cardres);
                    $showcards .= "<img src='./pic/cards/" . $cardarr['pic'] . "' /> ";
                    $cardids2[] = $cardid;
                }
            } else {
                for ($i = 0; $i <= 1; $i++) {
                    try {
                        $cardids[] = random_int(1, $cardcount);
                    } catch (Exception $e) {
                        do_log($e->getMessage());
                    }
                }
                foreach ($cardids as $cardid) {
                    while (in_array($cardid, $cardids, true)) {
                        try {
                            $cardid = random_int(1, $cardcount);
                        } catch (Exception $e) {
                            do_log($e->getMessage());
                        }
                    }
                    $cardres = sql_query("SELECT points, pic FROM cards WHERE id='$cardid'");
                    $cardarr = mysql_fetch_assoc($cardres);
                    if ($cardarr["points"] > 1) {
                        $points += $cardarr["points"];
                    } else {
                        $aces++;
                    }
                    $showcards .= "<img src='./pic/cards/" . $cardarr['pic'] . "' /> ";
                    $cardids2[] = $cardid;
                }
                for ($i = 0; $i < $aces; $i++) {
                    $points += ($points < 11 && $aces - $i === 1 ? 11 : 1);
                }
                sql_query("INSERT INTO blackjack (userid, points, cards, date) VALUES(" . sqlesc($CURUSER['id']) . ", '$points', '" . implode(" ", $cardids2) . "', " . TIMENOW . ")");
            }
            if ($points < 21) {
                $HTMLOUT .= "<div class='container'>";
                $HTMLOUT .= "<fieldset class='layui-elem-field layui-field-title'><legend>".$lang_blackjack['text_welcome'] . $CURUSER['username'] . $lang_blackjack['text_play_blackjack_game'] . "</legend></fieldset>";
                $HTMLOUT .= "<div class='layui-panel center' style='width:600px; padding: 30px; margin: 0 auto'>";
                $HTMLOUT .= trim($showcards) . "<p style='margin-top: 20px;'>" . $lang_blackjack['text_point'] . "&nbsp;=&nbsp;" . $points . "</p>";
                $HTMLOUT .= "</div>";
                $HTMLOUT .= "<div class='layui-btn-container center' style='margin-top: 20px;'>";
                $HTMLOUT .= "<div class='layui-inline'>";
                $HTMLOUT .= "<form method='post' action='" . htmlentities($_SERVER['PHP_SELF']) . "'>";
                $HTMLOUT .= "<input type='hidden' name='game' value='hit' readonly='readonly' />";
                $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='" . $lang_blackjack['text_napai'] ."' />";
                $HTMLOUT .= "</form>";
                $HTMLOUT .= "</div>";
                if ($points >= 10) {
                    $HTMLOUT .= "<div class='layui-inline'>";
                    $HTMLOUT .="<form method='post' action='" . htmlentities($_SERVER['PHP_SELF']) . "'><input type='hidden' name='game' value='stop' readonly='readonly' /><input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='". $lang_blackjack['text_tingpai'] ."' /></form>";
                    $HTMLOUT .= "</div>";
                }
                $HTMLOUT .= "</div>";
                $HTMLOUT .= "</div>";
                stdhead($lang_blackjack['text_blackjack']);
                print $HTMLOUT;
                print ("<div class='layui-footer'>");
                stdfoot();
                print ("</div>");
                die();
            }
        } elseif ( /*($start !== 'yes' && isset($_POST['continue']) !== 'yes') &&*/ !$gameover) {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $HTMLOUT .= cheater_check(empty($playerarr));
            try {
                $cardid = random_int( 1, $cardcount );
            } catch (Exception $e) {
                do_log("Blackjack: " . $e->getMessage());
            }
            while (in_array( $cardid, $arr, true )){
                try {
                    $cardid = random_int( 1, $cardcount );
                } catch (Exception $e) {
                    do_log("Blackjack: " . $e->getMessage());
                }
            }
            $cardres = sql_query("SELECT points, pic FROM cards WHERE id='$cardid'");
            $cardarr = mysql_fetch_assoc($cardres);
            $showcards .= "<img src='./pic/cards/" . $cardarr['pic'] . "'  style='border: 1px' /> ";
            if ($cardarr["points"] > 1){
                $points += $cardarr["points"];
            }else{
                $aces++;
            }
            for ($i = 0; $i < $aces; $i++){
                $points += ($points < 11 && $aces - $i === 1 ? 11 : 1);
            }
            sql_query("UPDATE blackjack SET points='$points', cards='" . $cards . " " . $cardid . "' WHERE userid=" . sqlesc($CURUSER['id']));
        }
        if ($points === 21 || $points > 21) {
            $waitres = sql_query("SELECT COUNT(userid) AS c FROM blackjack WHERE status = 'waiting' AND userid != " . sqlesc($CURUSER['id']));
            $waitarr = mysql_fetch_assoc($waitres);
            $HTMLOUT .= "<div class='container'>";
            $HTMLOUT .= "<fieldset class='layui-elem-field layui-field-title'><legend>" . $lang_blackjack['text_game_over'] . "</legend></fieldset>";
            $HTMLOUT .= "<div class='layui-panel center' style='width:600px; padding: 30px; margin: 0 auto'>";
            $HTMLOUT .= trim($showcards) . "<p style='margin-top: 20px;'>" . $lang_blackjack['text_point'] . "&nbsp;=&nbsp;" . $points . "</p>";
        }
        if ($points === 21) {
            if ($waitarr['c'] > 0) {
                $r = sql_query("SELECT bj.*, u.username FROM blackjack AS bj LEFT JOIN users AS u ON u.id=bj.userid WHERE bj.status='waiting' AND bj.userid != " . sqlesc($CURUSER['id']) . " ORDER BY bj.date ASC LIMIT 1");
                $a = mysql_fetch_assoc($r);
                if ($a["points"] !== 21) {
                    $winorlose = $lang_blackjack['text_win'] . $mb * 0.90;
                    sql_query("UPDATE users SET seedbonus = seedbonus + $mb * 0.90, bjwins = bjwins + 1 WHERE id=" . sqlesc($CURUSER['id']));
                    sql_query("UPDATE users SET seedbonus = seedbonus - $mb, bjlosses = bjlosses + 1 WHERE id=" . sqlesc($a['userid']));
                    $msg = sqlesc("[url=blackjack.php]" . $lang_blackjack['text_go_back_to'] . "[/url]");
                    $subject = sqlesc($lang_blackjack['text_losss'] . $a['points'] . $lang_blackjack['text_dian'] . $CURUSER['username'] . $lang_blackjack['text_you21dian']);
                } else {
                    $winorlose = $lang_blackjack['text_tie'];
                    $msg = sqlesc($lang_blackjack['text_tie_msg']);
                    $subject = sqlesc($lang_blackjack['text_losss_tie'] . $CURUSER['username'] . $lang_blackjack['text_dou_21_points']);
                }
                sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, " . $a['userid'] . ", $now, $msg, $subject)");
                sql_query("DELETE FROM blackjack WHERE userid IN (" . sqlesc($CURUSER['id']) . ", " . sqlesc($a['userid']) . ")");
                $HTMLOUT .= "<p style='margin-top: 20px;'>".$lang_blackjack['you_are_up_against'] . $a["username"] . $lang_blackjack['text_he_has'] . $a['points'] . $lang_blackjack['text_dian'].$winorlose."</p>";
                $HTMLOUT .= "</div>";
                $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
                $HTMLOUT .= "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'><input type='hidden' name='game' value='hit' readonly='readonly' /><input type='hidden' name='start' value='yes' readonly='readonly' /><input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_one_more_game']."' /></form>";
            } else {
                sql_query("UPDATE blackjack SET status = 'waiting', date=" . TIMENOW . ", gameover = 'yes' WHERE userid = " . sqlesc($CURUSER['id']));
                $HTMLOUT .= "<p style='margin-top: 20px;'>".$lang_blackjack['text_player_wait_msg']."<br />".$lang_blackjack['text_game_pm']."</p>";
                $HTMLOUT .= "</div>";
                $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
                $HTMLOUT .= "<a href='/blackjack.php' class='layui-btn layui-btn-sm layui-bg-black'>".$lang_blackjack['text_go_back_to']."</a>";
            }
            $HTMLOUT .= "</div>";
            $HTMLOUT .= "</div>";
            stdhead($lang_blackjack['text_blackjack']);
            print ($HTMLOUT);
        } elseif ($points > 21) {
            if ($waitarr['c'] > 0) {
                $r = sql_query("SELECT bj.*, u.username FROM blackjack AS bj LEFT JOIN users AS u ON u.id=bj.userid WHERE bj.status='waiting' AND bj.userid != " . sqlesc($CURUSER['id']) . " ORDER BY bj.date ASC LIMIT 1");
                $a = mysql_fetch_assoc($r);
                if ($a["points"] > 21) {
                    $winorlose = $lang_blackjack['text_tie'];
                    $msg = sqlesc($lang_blackjack['text_tie_msg']);
                    $subject = sqlesc($lang_blackjack['text_losss_tie'] . $CURUSER['username'] . $lang_blackjack['text_dou_21_points']);
                } else {
                    $winorlose = $lang_blackjack['text_losing_the_game_you_lose'] . $mb;
                    sql_query("UPDATE users SET seedbonus = seedbonus + $mb * 0.90, bjwins = bjwins + 1 WHERE id=" . sqlesc($a['userid']));
                    sql_query("UPDATE users SET seedbonus = seedbonus - $mb, bjlosses = bjlosses + 1 WHERE id=" . sqlesc($CURUSER['id']));
                    $msg = sqlesc("[url=blackjack.php]" . $lang_blackjack['text_go_back_to'] . "[/url]");
                    $subject = sqlesc($lang_blackjack['text_wins'] . $a['points'] . $lang_blackjack['text_dian'] . $CURUSER['username'] . $lang_blackjack['text_wins_msg']);
                }
                sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, " . $a['userid'] . ", $now, $msg, $subject)");
                sql_query("DELETE FROM blackjack WHERE userid IN (" . sqlesc($CURUSER['id']) . ", " . sqlesc($a['userid']) . ")");
                $HTMLOUT .= "<p style='margin-top: 20px;'>" . $lang_blackjack['you_are_up_against'] . $a["username"] . $lang_blackjack['text_he_has'] . $a['points'] . $lang_blackjack['text_dian']. $winorlose."</p>";
                $HTMLOUT .="</div>";
                $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
                $HTMLOUT .= "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'><input type='hidden' name='game' value='hit' readonly='readonly' /><input type='hidden' name='start' value='yes' readonly='readonly' /><input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_one_more_game']."' /></form>";
            } else {
                sql_query("UPDATE blackjack SET status = 'waiting', date=" . TIMENOW . ", gameover='yes' WHERE userid = " . sqlesc($CURUSER['id']));
                $HTMLOUT .="<p style='margin-top: 20px;'>".$lang_blackjack['text_player_wait_msg']."<br />".$lang_blackjack['text_game_pm']."</p>";
                $HTMLOUT .="</div>";
                $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
                $HTMLOUT .= "<a href='/blackjack.php' class='layui-btn layui-btn-sm layui-bg-black'>".$lang_blackjack['text_go_back_to']."</a>";
            }
            $HTMLOUT .= "</div>";
            $HTMLOUT .="</div>";
            stdhead($lang_blackjack['text_blackjack']);
            print $HTMLOUT;
        } else {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $HTMLOUT .= cheater_check(empty($playerarr));
            $HTMLOUT .= "<div class='container'>";
            $HTMLOUT .= "<fieldset class='layui-elem-field layui-field-title'><legend>".$lang_blackjack['text_welcome'] . $CURUSER['username'] . $lang_blackjack['text_play_blackjack_game'] . "</legend></fieldset>";
            $HTMLOUT .= "<div class='layui-panel center' style='width:600px; padding: 30px; margin: 0 auto'>";
            $HTMLOUT .= trim($showcards) . "<p style='margin-top: 20px;'>" . $lang_blackjack['text_point'] . "&nbsp;=&nbsp;" . $points . "</p>";
            $HTMLOUT .="</div>";
            $HTMLOUT .= "<div class='layui-btn-container center' style='margin-top: 20px;'>";
            $HTMLOUT .= "<div class='layui-inline'>";
            $HTMLOUT .= "<form method='post' action='" . htmlentities($_SERVER['PHP_SELF']) . "'>";
            $HTMLOUT .= "<input type='hidden' name='game' value='hit' readonly='readonly' />";
            $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='" . $lang_blackjack['text_napai'] ."' />";
            $HTMLOUT .= "</form>";
            $HTMLOUT .= "</div>";
            $HTMLOUT .= "<div class='layui-inline'>";
            $HTMLOUT .= "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
            $HTMLOUT .= "<input type='hidden' name='game' value='stop' readonly='readonly' />";
            $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_tingpai']."' />";
            $HTMLOUT .="</form>";
            $HTMLOUT .= "</div>";
            $HTMLOUT .="</div></div>";
            stdhead($lang_blackjack['text_blackjack']);
            print $HTMLOUT;
        }
        print ("<div class='layui-footer'>");
        stdfoot();
        print ("</div>");
    } elseif ($_POST["game"] === 'stop') {
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $HTMLOUT .= cheater_check(empty($playerarr));
        $waitres = sql_query("SELECT COUNT(userid) AS c FROM blackjack WHERE status='waiting' AND userid != " . sqlesc($CURUSER['id']));
        $waitarr = mysql_fetch_assoc($waitres);
        $HTMLOUT .= "<div class='container'>";
        $HTMLOUT .= "<fieldset class='layui-elem-field layui-field-title'><legend>" . $lang_blackjack['text_game_over'] . "</legend></fieldset>";
        $HTMLOUT .= "<div class='layui-panel center' style='width:600px; padding: 30px; margin: 0 auto'>";
        $HTMLOUT .= trim($showcards) . "<p style='margin-top: 20px;'>" . $lang_blackjack['text_point'] . "&nbsp;=&nbsp;" . $playerarr['points'] . "</p>";
        if ($waitarr['c'] > 0) {
            $r = sql_query("SELECT bj.*, u.username FROM blackjack AS bj LEFT JOIN users AS u ON u.id=bj.userid WHERE bj.status='waiting' AND bj.userid != " . sqlesc($CURUSER['id']) . " ORDER BY bj.date ASC LIMIT 1");
            $a = mysql_fetch_assoc($r);
            if ($playerarr['points']== $a["points"]) {
                $winorlose = $lang_blackjack['text_tie'];
                $msg = sqlesc("[url=blackjack.php]" . $lang_blackjack['text_go_back_to'] . "[/url]");
                $subject = sqlesc($lang_blackjack['text_losss_tie']. $CURUSER['username'] . $lang_blackjack['text_douyou'] . $a['points'] . $lang_blackjack['text_dian']);
            } else {
                if (($a["points"] < $playerarr['points'] && $a['points'] < 21) || ($a["points"] > $playerarr['points'] && $a['points'] > 21)) {
                    $msg = sqlesc("[url=blackjack.php]" . $lang_blackjack['text_go_back_to'] . "[/url]");
                    $subject = sqlesc($lang_blackjack['text_losss'] . $a['points'] . $lang_blackjack['text_dian'] . $CURUSER['username'] . $lang_blackjack['text_there_are'] . $playerarr['points'] . $lang_blackjack['text_dians']);
                    $winorlose = $lang_blackjack['text_win_you_get'] . $mb * 0.90;
                    $st_query = "+ " . $mb . ", bjwins = bjwins +";
                    $nd_query = "- " . $mb . ", bjlosses = bjlosses +";
                } elseif (($a["points"] > $playerarr['points'] && $a['points'] < 21) || $a["points"] == 21 || ($a["points"] < $playerarr['points'] && $a['points'] > 21)) {
                    $msg = sqlesc("[url=blackjack.php]" . $lang_blackjack['text_go_back_to'] . "[/url]");
                    $winorlose = $lang_blackjack['text_losss_you_lose'] . $mb;
                    $subject = sqlesc($lang_blackjack['text_wins'] . $a['points'] . $lang_blackjack['text_dian'] . $CURUSER['username'] . $lang_blackjack['text_there_are'] . $playerarr['points'] . $lang_blackjack['text_dians']);
                    $st_query = "- " . $mb . ", bjlosses = bjlosses +";
                    $nd_query = "+ " . $mb . ", bjwins = bjwins +";
                }
                sql_query("UPDATE users SET seedbonus = seedbonus " . $st_query . " 1 WHERE id=" . sqlesc($CURUSER['id']));
                sql_query("UPDATE users SET seedbonus = seedbonus " . $nd_query . " 1 WHERE id=" . sqlesc($a['userid']));
            }
            sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, " . $a['userid'] . ", $now, $msg, $subject)");
            sql_query("DELETE FROM blackjack WHERE userid IN (" . sqlesc($CURUSER['id']) . ", " . sqlesc($a['userid']) . ")");
            $HTMLOUT .= "<p style='margin-top: 20px;'>".$lang_blackjack['you_are_up_against'] . $a["username"] . $lang_blackjack['text_he_has'] . $a['points'] . $lang_blackjack['text_dian'].$winorlose."</p>";
            $HTMLOUT .= "</div>";
            $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
            $HTMLOUT .= "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'><input type='hidden' name='game' value='hit' readonly='readonly' /><input type='hidden' name='start' value='yes' readonly='readonly' /><input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_one_more_game']."' /></form>";
        } else {
            sql_query("UPDATE blackjack SET status = 'waiting', date=" . TIMENOW . ", gameover='yes' WHERE userid = " . sqlesc($CURUSER['id']));
            $HTMLOUT .="<p style='margin-top: 20px;'>".$lang_blackjack['text_player_wait_msg']."<br />".$lang_blackjack['text_game_pm']."</p>";
            $HTMLOUT .="</div>";
            $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
            $HTMLOUT .= "<a href='/blackjack.php' class='layui-btn layui-btn-sm layui-bg-black'>".$lang_blackjack['text_go_back_to']."</a>";
        }
        $HTMLOUT .= "</div>";
        $HTMLOUT .="</div>";
        stdhead($lang_blackjack['text_blackjack']);
        print $HTMLOUT;
        print ("<div class='layui-footer'>");
        stdfoot();
        print ("</div>");
    }
} else {
    $waitres = sql_query("SELECT COUNT(userid) AS c FROM blackjack WHERE  (date > " . TIMENOW . "-10 OR status ='waiting' OR status ='playing')  AND userid != " . sqlesc($CURUSER['id']));
    $waitarr = mysql_fetch_assoc($waitres);
    $res = sql_query("SELECT status, gameover FROM blackjack WHERE userid = " . sqlesc($CURUSER['id']));
    $arr = mysql_fetch_assoc($res);
    $tot_wins = $CURUSER['bjwins'];
    $tot_losses = $CURUSER['bjlosses'];
    $tot_games = $tot_wins + $tot_losses;
    $win_perc = null;
    if($tot_games === 0) {
        $win_perc = "---";
    } else {
        $win_perc = ($tot_losses === 0 ? "100%" : ($tot_wins === 0 ? "0" : number_format(($tot_wins / $tot_games) * 100, 1)) . '%');
    }
    /** @noinspection NestedTernaryOperatorInspection */
    $plus_minus = ($tot_wins - $tot_losses < 0 ? '-' : '') . (($tot_wins - $tot_losses >= 0 ? ($tot_wins - $tot_losses) * 0.90 : ($tot_losses - $tot_wins))) * $mb;
    $HTMLOUT .= "<div class='layui-panel center' style='width: 400px;padding: 30px;margin: 0 auto;'>";
    $HTMLOUT .= "<img src='pic/cards/tp.bmp' alt='card'/>&nbsp;<img src='pic/cards/vp.bmp' alt='card'/>";
    $HTMLOUT .= "<p style='margin-top: 20px;'>".$lang_blackjack['text_rules_msg'] . $waitarr['c'] . "</p>";
    if ( !empty($arr)){
        $HTMLOUT .= ($arr['status'] === 'waiting' ? "<p style='margin-top: 20px;'>".$lang_blackjack['please_wait_until_the_end_of_the_last_inning']."</p>" : "");
    }
    $HTMLOUT .= "</div>";
    $HTMLOUT .= "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
    $HTMLOUT .= "<div class='center' style='margin-top: 20px;'>";
    if ( !empty($arr)){
        if ($arr['status'] === 'waiting'){
            $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_refresh']."' />";
        } elseif ($arr['status'] === 'playing'){
            $HTMLOUT .= "<input type='hidden' name='game' value='hit' readonly='readonly' />";
            $HTMLOUT .= "<input type='hidden' name='start' value='yes' readonly='readonly' />";
            $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_playing']."'/>";
        }
    } else {
        $HTMLOUT .= "<input type='hidden' name='game' value='hit' readonly='readonly' />";
        $HTMLOUT .= "<input type='hidden' name='start' value='yes' readonly='readonly' />";
        $HTMLOUT .= "<input type='submit' class='layui-btn layui-btn-sm layui-bg-black' value='".$lang_blackjack['text_open_a_card']."'/>";
    }
    $HTMLOUT .= "</div>";
    $HTMLOUT .= "</form>";
    $HTMLOUT .= "<table class='layui-table' style='width: 30%'><thead><tr><th colspan='5'>".$lang_blackjack['text_personal_records']."</th></tr></thead>";
    $HTMLOUT .= "<thead><tr><th>".$lang_blackjack['text_winning_field']."</th><th>".$lang_blackjack['text_losing_field']."</th><th>".$lang_blackjack['total_number_of_sessions']."</th><th>".$lang_blackjack['winning_percentage']."</th><th>".$lang_blackjack['win_loss']."</th></tr></thead>";
    $HTMLOUT .= "<tr class='center'><td>".$tot_wins."</td><td>".$tot_losses."</td><td>".$tot_games."</td><td>".$win_perc."</td><td>".$plus_minus."</td></tr></table>";
    $HTMLOUT .= "<div class='center' style='margin-bottom: 10px;'><a href='/bjstats.php' class='layui-btn layui-btn-sm layui-bg-black'>".$lang_blackjack['historical_statistics']."</a></div>";
    stdhead($lang_blackjack['text_blackjack']);
    print ("<div class='container'>");
    print ("<fieldset class='layui-elem-field layui-field-title'><legend>".$lang_blackjack['text_blackjack']."</legend><blockquote class='layui-elem-quote'>".$lang_blackjack['text_rules_note']."<b>".$lang_blackjack['text_tips'] ."</b>".$lang_blackjack['text_tips_msg'] ."</blockquote></fieldset>");
    print $HTMLOUT;
    print ("</div>");
    print ("<div class='layui-footer'>");
    stdfoot();
    print ("</div>");
}

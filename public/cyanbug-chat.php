<?php /** @noinspection HtmlDeprecatedAttribute */
ob_start();
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
$langFile = ROOT_PATH . get_langfile_path();
if (file_exists($langFile)) {
    require $langFile;
}

stdhead('打胶妹设置');
if (get_user_class() < UC_MODERATOR) {
    stdmsg("Error", "Access denied!!!");
    stdfoot();
    exit;
}
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'del':
            $id = $_GET['id'];
            if (is_valid_id($_GET['id'])) {
                sql_query("DELETE FROM cyanbug_chat WHERE id=" . mysql_real_escape_string($id));
            }
            header("Location: ".get_protocol_prefix() . $BASEURL."/cyanbug-chat.php");
            die();
        case 'edit':
            $id = $_GET['id'];
            $row  = array();
            if (is_valid_id($id)) {
                $res = sql_query("SELECT * FROM cyanbug_chat WHERE id =" . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
                $row = mysql_fetch_assoc($res);
            }
        case 'add':
            echo "<h1>编辑规则</h1><form method=post action=cyanbug-chat.php?action=submit>
            <input type=hidden name=id value=".(isset($_GET['id'])?$_GET['id']:'').">
            <table border=1 cellspacing=0 cellpadding=5>
            <tr><td class=rowhead>名称</td><td><input type=text name=name size=80 value=" . (isset($row['name'])?$row['name']:'') . "></td></tr>
            <tr><td class=rowhead>权重</td><td><input type=number name=weight size=80 value=" . (isset($row['weight'])?$row['weight']:'') . "></td></tr>
            <tr><td class=rowhead>触发词</td><td><input type=text name=trigger size=80 value=" .(isset( $row['trigger'])? $row['trigger']:'') . "></td></tr>
            <tr><td class=rowhead>回复</td><td><textarea type=textarea style='resize: vertical' name=answer rows=8 cols=80>".(isset($row['answer'])?$row['answer']:'')."</textarea></td></tr>
            <tr><td class=rowhead>含奖励</td><td><input type=checkbox name=reward size=40 " . ((isset($row['reward'])?$row['reward']:'')?'checked=checked':'') . "></td></tr>
            <tr><td class=rowhead>奖励类型</td><td><select type=text name=reward_type><option value=''>未选择</option>";

            // 查询类型对照
            $type_arr = sql_query("SELECT * FROM cyanbug_reward_type") or sqlerr(__FILE__, __LINE__);
            while ($type_row = mysql_fetch_array($type_arr)) {
                $id = $type_row[0];
                $type = $type_row[1];
                if($id===$row['reward_type']){
                    echo "<option value=".$id." selected='selected'>".$type."</option>";
                }else{
                    echo "<option value=".$id.">".$type."</option>";
                }
            }
            echo "</select></td></tr>
            <tr><td class=rowhead>奖励间隔</td><td><input type=number name=reward_interval size=40 value=" . $row['reward_interval'] . "></td></tr>
            <tr><td class=rowhead>奖励数量</td><td><input type=number name=reward_amount size=40 value=" . $row['reward_amount'] . "></td></tr>
            <tr><td class=rowhead>重复警告</td><td><textarea type=textarea style='resize: none' name=reward_warning rows=4 cols=80>".$row['reward_warning']."</textarea></td></tr>
            <tr><td class=rowhead>参数说明</td><td>奖励间隔单位：天<br>奖励数量[魔力]单位：个<br>奖励数量[上传/下载]单位：比特<br>奖励数量[VIP/彩虹ID]单位：天</td></tr>
            <tr><td colspan=2 align=center><input type=submit value=保存设置 class=btn></td></tr>
            <tr><td colspan=2 align=center><a href='cyanbug-chat.php'>放弃并返回</a></td></tr>
            </table></form>";
            stdfoot();
            die();
        case 'submit':
            $post_id = $_POST['id'];
            $post_name = $_POST['name'];
            $post_weight = $_POST['weight'];
            $post_trigger = $_POST['trigger'];
            $post_answer = $_POST['answer'];
            $post_reward = ($_POST['reward']==='on'?1:0);
            $post_reward_type = intval($_POST['reward_type']);
            $post_reward_interval = intval($_POST['reward_interval']);
            $post_reward_amount = $_POST['reward_amount'];
            $post_reward_warning = $_POST['reward_warning'];
            if($post_id){
                $res = sql_query("SELECT name FROM cyanbug_chat WHERE id=" . sqlesc($post_id));
                $name = mysql_fetch_row($res)[0];
                if($name === NULL){
                    stdmsg("错误", "未找到要编辑的规则");
                    stdfoot();
                    exit;
                }
                sql_query("UPDATE cyanbug_chat SET name=".sqlesc($post_name)
                .",weight = ".sqlesc($post_weight)
                .",`trigger` = ".sqlesc($post_trigger)
                .",answer = ".sqlesc($post_answer)
                .",reward = ".$post_reward
                .",reward_type = ".$post_reward_type
                .",reward_interval = ".$post_reward_interval
                .",reward_amount = ".sqlesc($post_reward_amount)
                .",reward_warning = ".sqlesc($post_reward_warning)
                ." WHERE id = ".sqlesc($post_id));
            } else {
                sql_query("INSERT INTO cyanbug_chat(name,weight,`trigger`,answer,reward,reward_type,reward_interval,reward_amount,reward_warning) 
                VALUES(".sqlesc($post_name).","
                        .sqlesc($post_weight).","
                        .sqlesc($post_trigger).","
                        .sqlesc($post_answer).","
                        .$post_reward.","
                        .$post_reward_type.","
                        .$post_reward_interval.","
                        .sqlesc($post_reward_amount).","
                        .sqlesc($post_reward_warning).")");
            }
            header("Location: ".get_protocol_prefix() . $BASEURL."/cyanbug-chat.php");
            die();
    }
}

begin_main_frame();
echo "<h1 align=center>..:: 打胶妹设置 ::..</h1><br/>
      <span id=add><a href='?action=add' class=big><b>添加</b></a></span>
      <table width='100%' border=1 cellspacing=0 cellpadding=5 align=center>
      <td class=colhead align=left>ID</td>
      <td class=colhead align=left>名称</td>
      <td class=colhead align=left>权重</td>
      <td class=colhead align=left>触发词</td>
      <td class=colhead align=left>回复</td>
      <td class=colhead align=left>含奖励</td>
      <td class=colhead align=left>奖励类型</td>
      <td class=colhead align=left>奖励间隔</td>
      <td class=colhead align=left>奖励数量</td>
      <td class=colhead align=left>重复警告</td>
      <td class=colhead align=left>操作</td>";
// 查询类型对照
$type_map=array();
$type_arr = sql_query("SELECT * FROM cyanbug_reward_type") or sqlerr(__FILE__, __LINE__);
while ($type_row = mysql_fetch_array($type_arr)) {
    $id = $type_row[0];
    $type = $type_row[1];
    $type_map[$id] = $type;
}

// 添加分页功能
$perPage = 10;
$total = get_row_count('cyanbug_chat');
list($paginationTop, $paginationBottom, $limit) = pager($perPage, $total, "?");
$query = "SELECT * FROM cyanbug_chat order by id asc $limit";
$sql = sql_query($query);
while ($row = mysql_fetch_array($sql)) {
    $id = $row['id'];
    ?>
    <tr>
        <td class="rowfollow" align="left"><?php echo $id ?></td>
        <td class="rowfollow" align="left"><?php echo $row['name'] ?></td>
        <td class="rowfollow" align="left"><?php echo $row['weight'] ?></td>
        <td class="rowfollow" align="left"><?php echo $row['trigger'] ?></td>
        <td class="rowfollow" align="left"
            title="<?php echo $row['answer'] ?>"><?php echo formatLenght($row['answer'],15) ?></td>
        <td class="rowfollow" align="left"><?php echo $row['reward'] ?></td>
        <td class="rowfollow" align="left"><?php echo isset($type_map[$row['reward_type']])?$type_map[$row['reward_type']]:''?></td>
        <td class="rowfollow" align="left"><?php echo $row['reward_interval'] ?></td>
        <td class="rowfollow" align="left"><?php echo $row['reward_amount'] ?></td>
        <td class="rowfollow" align="left"
            title="<?php echo $row['reward_warning'] ?>"><?php echo formatLenght($row['reward_warning'],15) ?></td>
        <td class="rowfollow" align="right">
            <a href="?action=edit&id=<?php echo $id ?>"><strong>编辑</strong></a> |
            <a style="color:red;" href="javascript:confirm_delete('<?php echo $id ?>', '你确信要删除此项目吗？')">删除</a>
        </td>
    </tr>
    <?php
}
print("</table>".$paginationBottom);
end_main_frame();

function formatLenght($str,$len){
	if(mb_strlen($str)<$len){
		return $str;
	}
	return mb_substr($str,0,$len)."...";
}

stdfoot();
?>
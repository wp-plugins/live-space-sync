<?php
/*
Plugin Name: Live Space Sync
Plugin URI: http://privism.org/blog/live-sync/
Description: A Live Spaces rpc plug-in
Author: William, priv
Version: 1.01
Author URI: http://privism.org/blog/
*/
?>
<?php

$WP_MSNSYNC_PASSWORD;
$WP_MSNSYNC_URL;
$WP_MSNSYNC_ENABLE;
$WP_MSNSYNC_MSG;
$WP_MSNSYNC_TITLE;
$WP_MSNSYNC_MORE;
$WP_MSNSYNC_COOK;
$WP_MSNSYNC_PUBLISH;
$WP_MSNSYNC_DELETE;
$WP_MSNSYNC_FULL;
$WP_MSNSYNC_EXCLUDE;

//////////////////////////////////////////////////////////////
//Main code section
//////////////////////////////////////////////////////////////
function wp_msnsync_init(){
	add_option("wp_msnsync_url", 'fill-in-you-space-name');
	add_option("wp_msnsync_password", 'fill-in-your-password');
	add_option("wp_msnsync_enable", '1');
	add_option("wp_msnsync_cook", '1');
	add_option("wp_msnsync_publish", '1');
	add_option("wp_msnsync_delete", '1');
	add_option("wp_msnsync_full", '1');
	add_option("wp_msnsync_msg",  '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST]');
	add_option("wp_msnsync_title",  '[TITLE]');
	add_option("wp_msnsync_more", '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST]<div><a href=[PERMALINK] title="[TITLE]">read full story of "[TITLE]"&raquo;</a></div>');
	add_option("wp_msnsync_id", '');
	add_option("wp_msnsync_exclude", '');
}

function wp_msnsync_save(){
	//save options into databse
	update_option("wp_msnsync_url", $_POST['URL']);
	update_option("wp_msnsync_password", $_POST['PASS']);
	update_option("wp_msnsync_cook", $_POST['COOK']);
	update_option("wp_msnsync_publish", $_POST['PUBLISH']);
	update_option("wp_msnsync_delete", $_POST['DELETE']);
	update_option("wp_msnsync_full",  $_POST['FULL']);
	update_option("wp_msnsync_msg",  stripslashes($_POST['SYNCMSG']));
	update_option("wp_msnsync_title",  stripslashes($_POST['TITLE']));
	update_option("wp_msnsync_more", stripslashes($_POST['MOREMSG']));
	update_option('wp_msnsync_exclude',$_POST['exclude_category']);
}

//Toogle post sync enable
function wp_msnsync_enable(){
  $WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
  $WP_MSNSYNC_ENABLE=$WP_MSNSYNC_ENABLE?0:1;
  update_option("wp_msnsync_enable", $WP_MSNSYNC_ENABLE);
  return $WP_MSNSYNC_ENABLE;
}

function wp_msnsync_reset(){
	update_option("wp_msnsync_enable", '1');
	update_option("wp_msnsync_cook", '1');
	update_option("wp_msnsync_publish", '1');
	update_option("wp_msnsync_delete", '1');
	update_option("wp_msnsync_full", '1');
	update_option("wp_msnsync_msg",  '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST]');
	update_option("wp_msnsync_title",  '[TITLE]');
	update_option("wp_msnsync_more", '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST] <div><a href=[PERMALINK] title="[TITLE]">read full story of "[TITLE]"&raquo;</a></div>');
	update_option("wp_msnsync_exclude", '');
}

/* XML RPC Client*/
function wp_msnsync_docall($request){
	$fp = fsockopen("ssl://storage.msn.com", 443, $errno, $errstr);
	if (!$fp) {
	  return 'ConnectionFailed';
	} else {
	$data="";
	$data.="POST /storageservice/MetaWeblog.rpc  HTTP/1.1\r\n";
	$data.="Host: storage.msn.com\r\n";
	$data.="Content-Type: text/xml\r\n";
	$data.="Content-Length: ".strlen($request)."\r\n";
	$data.="Accept: */*\r\n\r\n";
	$data.="$request\r\n\r\n";
	//
	fwrite($fp,$data);
	//

	$headers = "";
	do{
		$str = trim(fgets($fp, 128));
		$headers .= "$str\n";
	}while($str!="");

	/*parse the header for content length*/
	$header_temp=split("\n",$headers);
	foreach($header_temp as $value){
		$temp=split(":",$value);
		$header_array[$temp[0]]=$temp[1];
	}
	$body ="";
	if(isset($header_array['Content-Length'])){
	//if size exist
		$size=0 + substr($header_array['Content-Length'],1);
		if($size > 0)
		  $body = fread($fp,$size);
	}else{
		while (!feof($fp)) {
			$line.= fgets($fp);
		}
	}
	return $body;
	}
}

/* Update status on the user space*/
function wp_msnsync_getinfo(){
	global $WP_MSNSYNC_PASSWORD;
	global $WP_MSNSYNC_URL;
  $request="    <methodCall>
    <methodName>blogger.getUsersBlogs</methodName>
    <params>
    <param><value><string>ignored value</string></value></param>
    <param><value><string>".$WP_MSNSYNC_URL."</string></value></param>
    <param><value><string>".$WP_MSNSYNC_PASSWORD."</string></value></param>
    </params>
    </methodCall>";

	$result=wp_msnsync_docall($request);
	if(strcmp($result, 'ConnectionFailed') == 0)
	$result_array['faultCode']='Oops, cannot establish link to Live Spaces';
	$result_array['faultString']='Try again later';

 	if(preg_match('%<name>faultCode</name><value><int>(.*)</int></value>%', $result, $match))
 	{//Error with MSN spaces
  	$result_array['faultCode']=$match[1];
  	preg_match('%<name>faultString</name><value><string>(.*)</string></value>%', $result, $match);
  	$result_array['faultString']=$match[1];
  }
  else
  {//No error
  	preg_match('%<name>url</name>\s*<value>(.*)</value>%', $result, $match);
  	$result_array['url']=$match[1];
  	preg_match('%<name>blogid</name>\s*<value>(.*)</value>%', $result, $match);
  	$result_array['blogid']=$match[1];
  	preg_match('%<name>blogName</name>\s*<value>(.*)</value>%', $result, $match);
  	$result_array['blogName']=$match[1];
  }

	return $result_array;
}

/* Generate Metaweblog post Request */
function wp_msnsync_genpost($postid, $newpost, $WP_MSNSYNC_IDS){
	$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
	$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
	$WP_MSNSYNC_MSG=get_option("wp_msnsync_msg");
	$WP_MSNSYNC_TITLE=get_option("wp_msnsync_title");
	$WP_MSNSYNC_COOK=get_option("wp_msnsync_cook");
	$WP_MSNSYNC_PUBLISH=get_option("wp_msnsync_publish");
	$WP_MSNSYNC_FULL=get_option("wp_msnsync_full");
	$WP_MSNSYNC_MORE=get_option("wp_msnsync_more");

	$user_data=get_post($postid);
	$post=$user_data->post_content;

	if($WP_MSNSYNC_FULL!='1')
	{
		//cut at <!--more--> tag
		$pos=strpos($post,"<!--more-->");
		if($pos!==FALSE)  //found more, attach the more msg
		{
			$post=substr($post,0,$pos);
			$WP_MSNSYNC_MSG = $WP_MSNSYNC_MORE;
		}
	}

	//do filter or the post may be unformatted
	$post=apply_filters('the_content', $post);

	//added in version 1.0
	//digest of post support
	$r1=array("[TITLE]","[POST]","[PERMALINK]");
	$r2=array($user_data->post_title,$post, get_permalink($postid));
	$WP_MSNSYNC_MSG=str_replace($r1,$r2,$WP_MSNSYNC_MSG);
	$WP_MSNSYNC_TITLE=str_replace($r1, $r2, $WP_MSNSYNC_TITLE);

	//In MSN Spaces, <p> doesn't create a newline, hack it
	if($WP_MSNSYNC_COOK=="1")
	{
		$r1=array("<p>", "</p>");
		$r2=array("<div>", "</div><br/>");
		$WP_MSNSYNC_MSG=str_replace($r1,$r2,$WP_MSNSYNC_MSG);
	}
	//
	//fix the HTML output problem generated by MSN API
	//replace special characters < > &
	$WP_MSNSYNC_MSG=htmlspecialchars($WP_MSNSYNC_MSG);

	//get categories, MSN Spaces only take first category though
	$categories = get_the_category($postid);
	$the_list = '';
	foreach ($categories as $category) {
		$category->cat_name = convert_chars($category->cat_name);
		$the_list .="<data><value><string>$category->cat_name</string></value></data>\n";
	}

$output="<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<methodCall>
<methodName>".($newpost?"metaWeblog.newPost":"metaWeblog.editPost")."</methodName>
<params>
 <param>
  <value>
   ".($newpost?"<string>MyBlog</string>":"<string>".$WP_MSNSYNC_IDS[$postid]."</string>")."
  </value>
 </param>
 <param>
  <value>
   <string>".$WP_MSNSYNC_URL."</string>
  </value>
 </param>
 <param>
  <value>
   <string>".$WP_MSNSYNC_PASSWORD."</string>
  </value>
 </param>
 <param>
  <value>
   <struct>
    <member>
     <name>title</name>
     <value>
      <string>".$WP_MSNSYNC_TITLE."</string>
     </value>
    </member>
    <member>
     <name>description</name>
     <value>
      <string>".($WP_MSNSYNC_MSG)."</string>
     </value>
    </member>
    <member>
     <name>dateCreated</name>
     <value>
      <dateTime.iso8601>".mysql2date('Y-m-d\TH:i:s\Z', $user_data->post_date_gmt, false)."</dateTime.iso8601>
     </value>
    </member>
    <member>
     <name>categories</name>
     <value>
      <array>
      ".$the_list."
      </array>
     </value>
    </member>
   </struct>
  </value>
 </param>
 <param>
  <value>
   <boolean>$WP_MSNSYNC_PUBLISH</boolean>
  </value>
 </param>
</params>
</methodCall>
";
	return $output;
}

/*Publishing hook*/
function wp_msnsync_post($postid){
	$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
	$WP_MSNSYNC_EXCLUDE = get_option("wp_msnsync_exclude");

	if($WP_MSNSYNC_ENABLE=="1")
	{//enable

	  //retreive the post content
	  $user_data=get_post($postid);

		//excluded categries
	  $categories = get_the_category($postid);
	  foreach ($categories as $category) {
	  	if ($WP_MSNSYNC_EXCLUDE && in_array($category->cat_ID,$WP_MSNSYNC_EXCLUDE))
	  		return $postid;
	  }

		//if contains <!--stopsync--> tag, don't sync this post
		if(strpos($user_data->post_content, '<!--stopsync-->') !== false)
		    return $postid;

		$WP_MSNSYNC_IDS=get_option("wp_msnsync_id");

		//new post or edit
		if(($WP_MSNSYNC_IDS[$postid]))
			$newpost=false;
		else
			$newpost=true;

		$request=wp_msnsync_genpost($postid, $newpost, $WP_MSNSYNC_IDS);
		$result=wp_msnsync_docall($request);

		if(strpos($result, 'faultCode') !== false)
		{
			preg_match('%<int>(.*)</int>%', $result, $match);
			//if editPost and return 40003 Directory or File Does Not Exist, try newPost again
			if($match[1]=="40003")
			{
				$newpost=true;
				$request=wp_msnsync_genpost($postid, $newpost, $WP_MSNSYNC_IDS);
				$result=wp_msnsync_docall($request);
			}
			//TODO, if other fail, how to tell user sync is fail??
		}
		//save postid if first try or second try success
		if(strpos($result, 'faultCode') === false){
		  if(preg_match('%<value>(.*)</value>%', $result, $match))
		  {
		    $WP_MSNSYNC_IDS[$postid]=$match[1];
		    update_option("wp_msnsync_id", $WP_MSNSYNC_IDS);
		  }
		}
	}
	return $postid;
}

/*Delete hook*/
function wp_msnsync_delete($postid){
	$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
	$WP_MSNSYNC_DELETE=get_option("wp_msnsync_delete");
	$WP_MSNSYNC_IDS=get_option("wp_msnsync_id");
	$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
	$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");

	if($WP_MSNSYNC_ENABLE=="1"&&$WP_MSNSYNC_DELETE=="1"&&$WP_MSNSYNC_IDS[$postid])
	{
    $request="<methodCall>
    <methodName>blogger.deletePost</methodName>
    <params>
    <param><value><string>ignored value</string></value></param>
    <param><value><string>".$WP_MSNSYNC_IDS[$postid]."</string></value></param>
    <param><value><string>".$WP_MSNSYNC_URL."</string></value></param>
    <param><value><string>".$WP_MSNSYNC_PASSWORD."</string></value></param>
    <param><value><boolean>1</boolean></value></param>
    </params>
    </methodCall>";

    $result=wp_msnsync_docall($request);
	}
	unset($WP_MSNSYNC_IDS[$postid]);
  update_option("wp_msnsync_id", $WP_MSNSYNC_IDS);

	return $postid;
}

function wp_msnsync_syncall(){
  global $wpdb;
  $posts = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type='post' ORDER BY post_date");
  if($posts)
    foreach ($posts as $post)
      wp_msnsync_post($post->ID);
}

//if first run, initialize the options
wp_msnsync_init();

/*main action function*/
function wp_msnsync_display(){

/*First, check action*/
if(isset($_POST['syncall'])){
  wp_msnsync_syncall();
}
else if(isset($_POST['enablebutton'])){
  wp_msnsync_enable();
}
else if(isset($_POST['reset'])){
	wp_msnsync_reset();
	echo '<div id="message" class="updated fade"><p>Options reseted.</p></div>';
}
else if($_POST['action']=="options"){
//this means there are new options, save into database
	wp_msnsync_save();
	echo '<div id="message" class="updated fade"><p>Options saved.</p></div>';
}

global $WP_MSNSYNC_PASSWORD;
global $WP_MSNSYNC_URL;
global $WP_MSNSYNC_ENABLE;
global $WP_MSNSYNC_MSG;
global $WP_MSNSYNC_TITLE;
global $WP_MSNSYNC_COOK;
global $WP_MSNSYNC_PUBLISH;
global $WP_MSNSYNC_DELETE;
global $WP_MSNSYNC_FULL;
global $WP_MSNSYNC_MORE;
global $WP_MSNSYNC_EXCLUDE;
global $wpdb;

$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
$WP_MSNSYNC_MSG=get_option("wp_msnsync_msg");
$WP_MSNSYNC_TITLE=get_option("wp_msnsync_title");
$WP_MSNSYNC_COOK=get_option("wp_msnsync_cook");
$WP_MSNSYNC_PUBLISH=get_option("wp_msnsync_publish");
$WP_MSNSYNC_DELETE=get_option("wp_msnsync_delete");
$WP_MSNSYNC_FULL=get_option("wp_msnsync_full");
$WP_MSNSYNC_MORE=get_option("wp_msnsync_more");
$WP_MSNSYNC_EXCLUDE = get_option("wp_msnsync_exclude");
$categories = $categories = (array) get_categories('get=all');

//get info
$response=wp_msnsync_getinfo();

?>
<div class="wrap">
	<h2>Live Sync</h2>

	<?php
	if(isset($response['faultCode'])){
	//this means there is an error
	?>
	<div class="wrap">
	Error: <font color="#FF0000"><?php echo $response['faultCode']."|".$response['faultString']?></FONT>;<BR/>
	Please check your Space Name and Password again, or see if Email Publishing option is turned on on your Live Spaces.
	</div>
	<?php
	}else{
	/*if there is no error in response, continue parsing*/
	?>
	<div class="wrap">
	Your Space: <a href="<?php echo $response['url'];?>"><?php echo $response['blogName']." ( ".$response['blogid']." )";?></a>
	<br />Seems your settings are correct, <?php echo $WP_MSNSYNC_ENABLE?'and the plug-in is ready to sync post for you.':'but your post sync is disabled.'?>
	</div>
	<?php
	}//if there is no error?>
	<br />
	<form name="msnoptions" method="post" action="<?php echo $_SERVER["REQUEST_URI"]?>">
	<table class="optiontable">
	<tbody>
	<tr><th><h2>Connections:</h2></th></tr>
	 <tr><th>Post sync is <?php echo $WP_MSNSYNC_ENABLE?'Enabled':'Disabled'?></th>
	  <td><input type="submit" name="enablebutton" value="<?php echo $WP_MSNSYNC_ENABLE?'Disable it':'Enable it'?>" /></td>
	</tr>
	<tr valign="top">
	<th scope="row">Space Name:</th><td><input name="URL" type="text" size="60" value="<?php echo  $WP_MSNSYNC_URL;?>" /><br />Make sure you enter the space name, not the URL.<br />e.g. "http://abcd.spaces.live.com" should enter "abcd"</td>
	</tr>
	<tr valign="top">
	<th scope="row">Password:</th><td> <input name="PASS" type="text" size="60" value="<?php echo  $WP_MSNSYNC_PASSWORD;?>"/><br />This is the secret word you choosed in your MSN space</td></tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Sync Delete: </th><td>
	  <label><input type="radio" name="DELETE" value="1" <?php echo $WP_MSNSYNC_DELETE?'checked="checked"':''; ?>/>Yes</label>
	  <label><input type="radio" name="DELETE" value="0" <?php echo $WP_MSNSYNC_DELETE?'':'checked="checked"'; ?>/>No</label>
	  <br />When delete post, delete post on Live Spaces as well(will be inactive when Enable Sync set to No).
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Post Status: </th><td>
	  <label><input type="radio" name="PUBLISH" value="1" <?php echo $WP_MSNSYNC_PUBLISH?'checked="checked"':''; ?>/>Published</label>
	  <label><input type="radio" name="PUBLISH" value="0" <?php echo $WP_MSNSYNC_PUBLISH?'':'checked="checked"'; ?>/>Draft</label>
	  <br />Set the status that the synchronized post will appear in MSN spaces.
		</td>
		</tr>
		<!-- -->
	<tr><th><h2>Formatting</h2></th></tr>
	<tr valign="top">
	<th scope="row">Sync Text: </th><td>
	  <label><input type="radio" name="FULL" value="1" <?php echo $WP_MSNSYNC_FULL?'checked="checked"':''; ?>/>Always Full Text</label>
	  <label><input type="radio" name="FULL" value="0" <?php echo $WP_MSNSYNC_FULL?'':'checked="checked"'; ?>/>Cut at &lt;!--more--&gt;</label>
	  <br />Always synchronize full text, or cut at &lt;!--more--&gt;
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Enable Cook: </th><td>
	  <label><input type="radio" name="COOK" value="1" <?php echo $WP_MSNSYNC_COOK?'checked="checked"':''; ?>/>Yes</label>
	  <label><input type="radio" name="COOK" value="0" <?php echo $WP_MSNSYNC_COOK?'':'checked="checked"'; ?>/>No</label>
	  <br />When set to "Yes", convert "&lt;p&gt;" tags to "&lt;div&gt;" formatters, to be better viewed with live spaces.
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Title of Sync:</th><td> <input name="TITLE" type="text" size="60" value="<?php echo htmlspecialchars($WP_MSNSYNC_TITLE);?>"/><br />This will appear to be the title of the synchronization post</td></tr>

	<tr valign="top">
	<th scope="row">
	  Content of Sync:
  </th>
  <td>
	  <textarea name="SYNCMSG" cols="60" rows="4"><?php echo htmlspecialchars($WP_MSNSYNC_MSG);?></textarea>
		<br /> This will be the content of sync when fulltext is synchronized.
	  </td>
	  </tr>
	  	<tr valign="top">
	<th scope="row">Content of Sync<br/>(For Partial Article):</th><td>
		<textarea name="MOREMSG" cols="60" rows="4"><?php echo htmlspecialchars($WP_MSNSYNC_MORE);?></textarea>
		<br /> This will be the content of sync when 'Sync Text' is set to 'Cut at &lt;!--more--&gt;' and the article contains &lt;!--more--&gt;.
		</td></tr>
	  </tbody>
	  </table>
	  	  <div class="wrap">Tips: You can use <font color="#7799FF">[TITLE]</font>/<font color="#7799FF">[POST]</font>/<font color="#7799FF">[PERMALINK]</font> in "Title of Sync" and "Content of Sync".<br />
	  They will be replaced by the <font color="#7799FF">title</font>, <font color="#7799FF">content</font>, or <font color="#7799FF">permalink</font> of your post<br />
	  If you don't want a single post to be synced, add &lt;!--stopsync--&gt; in that article.
	  </div>
	  <div class="wrap">
	  <h2>Excluded Categories</h2>
	  Select the categories that you don't want them to be synchronized.<br/>
	  <?php
	  if ($categories)
		foreach ($categories as $category) {
			$checked = '';
			if ($WP_MSNSYNC_EXCLUDE && in_array($category->cat_ID,$WP_MSNSYNC_EXCLUDE)) {
				$checked = 'checked="checked" ';
			}
			echo "<label>";
			echo "<input name=\"exclude_category[]\" type=\"checkbox\" value=\"$category->cat_ID\" $checked/>";
			echo " $category->cat_name</label><br />\n";
		}?>
	</div>
	<input type="hidden" name="action" value="options" />
	<p class="submit"><input name="syncall" value="Sync All existing Posts" type="submit"/><input name="reset" value="Restore Default Options" type="submit"/><input value="Update Options" type="submit"/></p>
	</form>
	<div class="wrap" align="right">This plug-in was originally created by <a href="http://blog.12thocean.com/">William Xu</a> and heavily modified by <a href="http://priv.tw/blog">priv</a>.</div>
</div>

<?php
}

function wp_msnsync_add_page($s){
	add_submenu_page('post.php', 'Live Sync','Live Sync',8,__FILE__,'wp_msnsync_display');
	add_options_page('Live Sync', 'Live Sync',8,__FILE__,'wp_msnsync_display');
	return $s;
}
add_action('admin_menu','wp_msnsync_add_page');
add_action('publish_post','wp_msnsync_post');
add_action('delete_post','wp_msnsync_delete');

?>

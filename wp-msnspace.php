<?php
/*
Plugin Name: MSN Space Sync 2
Plugin URI: http://priv.tw/blog/msn-sync-modified/
Description: A MSN rpc plug-in
Author: William, priv
Version: 2.1
Author URI: http://priv.tw/blog/
*/
?>
<?php

$WP_MSNSYNC_PASSWORD;
$WP_MSNSYNC_URL;
$WP_MSNSYNC_ENABLE;
$WP_MSNSYNC_MSG;
$WP_MSNSYNC_TITLE;
$WP_MSNSYNC_COOK;
$WP_MSNSYNC_PUBLISH;
$WP_MSNSYNC_DELETE;

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
	add_option("wp_msnsync_msg",  '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST]');
	add_option("wp_msnsync_title",  '[TITLE]');
	add_option("wp_msnsync_id", '');
}

function wp_msnsync_save(){
	//save options into databse
	update_option("wp_msnsync_url", $_POST['URL']);
	update_option("wp_msnsync_password", $_POST['PASS']);
	update_option("wp_msnsync_enable", $_POST['ENABLE']);
	update_option("wp_msnsync_cook", $_POST['COOK']);
	update_option("wp_msnsync_publish", $_POST['PUBLISH']);
	update_option("wp_msnsync_delete", $_POST['DELETE']);
	update_option("wp_msnsync_msg",  stripslashes($_POST['SYNCMSG']));
	update_option("wp_msnsync_title",  stripslashes($_POST['TITLE']));
}

function wp_msnsync_reset(){
	update_option("wp_msnsync_enable", '1');
	update_option("wp_msnsync_cook", '1');
	update_option("wp_msnsync_publish", '1');
	update_option("wp_msnsync_delete", '1');
	update_option("wp_msnsync_msg",  '<div>Original URL:<a href=[PERMALINK] title="[TITLE]"> [PERMALINK] </a></div> [POST]');
	update_option("wp_msnsync_title",  '[TITLE]');
}

/* XML RPC Client*/
function wp_msnsync_docall($request){
	$fp = fsockopen("ssl://storage.msn.com", 443, $errno, $errstr);
	if (!$fp) {
	  $hd=@fopen("temp.txt",'a');
	fwrite($hd,"Failed");
	fclose($hd);
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
	if(isset($header_array['Content-Length'])){
	//if size exist
		$size=0 + substr($header_array['Content-Length'],1);
		$body = fread($fp,$size);
	}else{
		$body="";
		while (!feof($fp)) {
			$line.= fgets($fp);

		} 
	}
 
	return $body;
	}

}

/*time generating function*/
function ig_iso8601_time($utc=true){
        $datestr = date('Y-m-d\TH:i:sO');
        if($utc){
                $eregStr =
                '([0-9]{4})-'.        // centuries & years CCYY-
                '([0-9]{2})-'.        // months MM-
                '([0-9]{2})'.        // days DD
                'T'.                        // separator T
                '([0-9]{2}):'.        // hours hh:
                '([0-9]{2}):'.        // minutes mm:
                '([0-9]{2})(\.[0-9]*)?'. // seconds ss.ss...
                '(Z|[+\-][0-9]{2}:?[0-9]{2})?'; // Z to indicate UTC, -/+HH:MM:SS.SS... for local tz's

                if(ereg($eregStr,$datestr,$regs)){
                        return sprintf('%04d-%02d-%02dT%02d:%02d:%02dZ',$regs[1],$regs[2],$regs[3],$regs[4],$regs[5],$regs[6]);
                }
                return false;
        } else {
                return $datestr;
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
	$user_data=get_post($postid);

	//do filter or the post may be unformatted
	$post=apply_filters('the_content', $user_data->post_content);

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
     <name>dateTime.iso8601</name>
     <value>
      <string>".ig_iso8601_time()."</string>
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
	
	if($WP_MSNSYNC_ENABLE=="1")
	{//enable
		$WP_MSNSYNC_IDS=get_option("wp_msnsync_id");
		
		//new post or edit
		if(($WP_MSNSYNC_IDS[$postid]))
			$newpost=false;
		else
			$newpost=true;
			
		$request=wp_msnsync_genpost($postid, $newpost, $WP_MSNSYNC_IDS);
		$result=wp_msnsync_docall($request);

		if(preg_match('%faultCode%', $result, $match))
		{
			preg_match('%<int>(.*)</int>%', $result, $match);
			//if editPost and return 40003 Directory or File Does Not Exist, use newPost instead, try again
			if($match[1]=="40003")
			{
				$newpost=true;
				$request=wp_msnsync_genpost($postid, $newpost, $WP_MSNSYNC_IDS);
				$result=wp_msnsync_docall($request);
			}
			//TODO, if other fail, how to tell user sync is fail??
		}
		//save postid
		if(preg_match('%<value>(.*)</value>%', $result, $match))
		{
			$WP_MSNSYNC_IDS[$postid]=$match[1];
			update_option("wp_msnsync_id", $WP_MSNSYNC_IDS);
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

//if first run, initialize the options
wp_msnsync_init();

/*main action function*/
function wp_msnsync_display(){

/*First, check action*/
if(isset($_POST['reset'])){
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

$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
$WP_MSNSYNC_MSG=get_option("wp_msnsync_msg");
$WP_MSNSYNC_TITLE=get_option("wp_msnsync_title");
$WP_MSNSYNC_COOK=get_option("wp_msnsync_cook");
$WP_MSNSYNC_PUBLISH=get_option("wp_msnsync_publish");
$WP_MSNSYNC_DELETE=get_option("wp_msnsync_delete");

//get info
$response=wp_msnsync_getinfo();

?>
<div class="wrap">
	<h2>MSN Sync</h2>
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
	</div>
	<?php
	}//if there is no error?>
	<br>
	<form name="msnoptions" method="post">
	<TABLE class=optiontable>
	<TBODY>
	<TR vAlign=top>
	<TH scope=row>Space Name:</TH><td><input name="URL" type="text" size="60" value="<?php echo  $WP_MSNSYNC_URL;?>" /><br />Make sure you enter the space name, not the URL.<br />e.g. "http://abcd.spaces.live.com" should enter "abcd"</td>
	</TR>
	<tr valign="top">
	<th scope="row">Password:</th><td> <input name="PASS" type="text" size="60" value="<?php echo  $WP_MSNSYNC_PASSWORD;?>"/><br />This is the secret word you choosed in your MSN space</td></tr>
	<tr valign="top">
	<th scope="row">Enable Sync: </th><td>
	  <label><input type="radio" name="ENABLE" value="1" <?php echo $WP_MSNSYNC_ENABLE?'checked':''; ?>/>Yes</label>
	  <label><input type="radio" name="ENABLE" value="0" <?php echo $WP_MSNSYNC_ENABLE?'':'checked'; ?>/>No</label>
	  <br />When set to "Yes", there will be a new post made to your msn space whenever you make a new publishing
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Sync Delete: </th><td>
	  <label><input type="radio" name="DELETE" value="1" <?php echo $WP_MSNSYNC_DELETE?'checked':''; ?>/>Yes</label>
	  <label><input type="radio" name="DELETE" value="0" <?php echo $WP_MSNSYNC_DELETE?'':'checked'; ?>/>No</label>
	  <br />When delete post, delete post on Live Spaces as well(will be inactive when Enable Sync set to No).
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Post Status: </th><td>
	  <label><input type="radio" name="PUBLISH" value="1" <?php echo $WP_MSNSYNC_PUBLISH?'checked':''; ?>/>Published</label>
	  <label><input type="radio" name="PUBLISH" value="0" <?php echo $WP_MSNSYNC_PUBLISH?'':'checked'; ?>/>Draft</label>
	  <br />Set the status that the synchronized post will appear in MSN spaces.
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Enable Cook: </th><td>
	  <label><input type="radio" name="COOK" value="1" <?php echo $WP_MSNSYNC_COOK?'checked':''; ?>/>Yes</label>
	  <label><input type="radio" name="COOK" value="0" <?php echo $WP_MSNSYNC_COOK?'':'checked'; ?>/>No</label>
	  <br />When set to "Yes", convert "&lt;p&gt;" tags to "&lt;div&gt;" formatters, to be better viewed with live spaces.
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Title of Sync:</th><td> <input name="TITLE" type="text" size="60" value="<?php echo  $WP_MSNSYNC_TITLE;?>"/><br />This will appear to be the title of the synchronization post</td></tr>

	<tr valign="top">
	<th scope="row">
	  Content of Sync:
  </th>
  <td>
	  <textarea name="SYNCMSG" cols="60" rows="6"><?php echo $WP_MSNSYNC_MSG;?></textarea>

	  </td>
	  </tr>
	  </TBODY>
	  </TABLE>
	  	  <div class="wrap">You can use <font color="#7799FF">[TITLE]</font>/<font color="#7799FF">[POST]</font>/<font color="#7799FF">[PERMALINK]</font> in "Title of Sync" and "Content of Sync".<br>
	  They will be replaced by the <font color="#7799FF">Title</font>, <font color="#7799FF">content</font>, or <font color="#7799FF">permalink</font> of your post<br>
	  </div>
	<input type="hidden" name="action" value="options" />
	<p class="submit"><input name="reset" value="Reset Options" type="submit"/><input value="Update Options" type="submit"/></p>
	</form>
	<div class="wrap" align="right">This plug-in is created by <a href="http://blog.12thocean.com/">William Xu</a> and Modified by <a href="http://priv.tw/blog">priv</a>.</div>
</div>

<?php
}

function wp_msnsync_add_page($s){
	add_submenu_page('post.php', 'MSN Sync','MSN Sync',1,__FILE__,'wp_msnsync_display');
	return $s;
}
add_action('admin_menu','wp_msnsync_add_page');
add_action('publish_post','wp_msnsync_post');
add_action('delete_post','wp_msnsync_delete');

?>

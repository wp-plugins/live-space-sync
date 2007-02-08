<?php
/*
Plugin Name: MSN Space Sync
Plugin URI: http://moonblue.homeip.net
Description: A MSN rpc plug-in
Author: William
Version: 1.0
Author URI: http://moonblue.homeip.net
*/
?>
<?php	

//before the world starts, check functions
$extension_array=get_loaded_extensions();
if(($array_key=array_search('xmlrpc',$extension_array))!=NULL){
	$RPC_VERSION=1;
}else{
	$RPC_VERSION=-1;
}
$WP_MSNSYNC_PASSWORD;
$WP_MSNSYNC_URL;
$WP_MSNSYNC_ENABLE;
$WP_MSNSYNC_MSG;
$WP_MSNSYNC_TITLE;

/*Publishing hook*/
function wp_msnsync_post($input){
	//
	//$input is the post id
	$data=get_post($input);
	//those two are some aviable data sets
	//$data->post_content;
	//$data->post_title;

	/*	
	$hd=@fopen("temp.txt",'a');
	fwrite($hd,"function called");
	fwrite($hd,$data);
	fclose($hd);
	*/
	//
	$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
	if($WP_MSNSYNC_ENABLE=="1"){//enable
	$request=wp_msnsync_genpost($data);
	$result=wp_msnsync_docall($request);

	}else{
	//do nothing
	}
	return $input;
}

function wp_msnsync_genpost($user_data){
	$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
	$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
	$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
	$WP_MSNSYNC_MSG=get_option("wp_msnsync_msg");
	$WP_MSNSYNC_TITLE=get_option("wp_msnsync_title");

//added in version 1.0
//digest of post support
$r1=array("[TITLE]","[POST]");
$r2=array($user_data->post_title,$user_data->post_content);
$WP_MSNSYNC_MSG=str_replace($r1,$r2,$WP_MSNSYNC_MSG);
//
//fix the HTML output problem generated by MSN API
//added in version 1.0
$r1=array("<",">");
$r2=array("&lt;","&gt;");
$WP_MSNSYNC_MSG=str_replace($r1,$r2,$WP_MSNSYNC_MSG);


$output="<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<methodCall>
<methodName>metaWeblog.newPost</methodName>
<params>
 <param>
  <value>
   <string>MyBlog</string>
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
       <data>
        <value>
         <string/>
        </value>
       </data>
      </array>
     </value>
    </member>
   </struct>
  </value>
 </param>
 <param>
  <value>
   <boolean>1</boolean>
  </value>
 </param>
</params>
</methodCall>
";

	//var_dump($result);
	
	return $output;
}
//wp_msnsync_genpost();
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

/*main action function*/
function wp_msnsync_display(){
/*First, check action*/
if($_POST['action']=="options"){
//this means there are new options, save into database
	wp_msnsync_save();
}

/*$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
$WP_MSNSYNC_URL=get_option("wp_msnsync_url");*/

global $WP_MSNSYNC_PASSWORD;
global $WP_MSNSYNC_URL;
global $WP_MSNSYNC_ENABLE;
global $WP_MSNSYNC_MSG;
global $WP_MSNSYNC_TITLE;

$WP_MSNSYNC_PASSWORD=get_option("wp_msnsync_password");
$WP_MSNSYNC_URL=get_option("wp_msnsync_url");
$WP_MSNSYNC_ENABLE=get_option("wp_msnsync_enable");
$WP_MSNSYNC_MSG=get_option("wp_msnsync_msg");
$WP_MSNSYNC_TITLE=get_option("wp_msnsync_title");

//
//wp_msnsync_genpost();

//get info
global $RPC_VERSION;
if($RPC_VERSION==1){
	$response=wp_msnsync_getinfo();
}

?>
<div class="wrap">
	<h2>MSN Sync</h2>
	<?php
	if(isset($response['faultCode'])){
	//this means there is an error
	?>
	<div class="wrap">
	Error: <?php echo $response['faultCode']."|".$response['faultString']?>;
	</div>
	<?php
	}else{
	/*if there is no error in response, continue parsing*/
	?>
	<div class="wrap">
	<?php if($RPC_VERSION==1){?>
	Your Space: <a href="<?php echo $response[0]['url'];?>"><?php echo $response[0]['blogName']." ( ".$response[0]['blogid']." )";?></a>
	</div>
	<?php }else{?>
	<div>
	<font color="red">Your PHP build does not support built-in XML-RPC. Fucntion will be limited</font>
	<br>
	You will be able to synchronize (send message to) your msn space, but can not receive response regarding error or other infomation from it.<br>
	To enable native XMLRPC, please see further the references <a href="http://ca.php.net/manual/en/ref.xmlrpc.php">here</a>
	</div>
	<?php
	}//endif $RPC_VERSION
	}//if there is no error?>
	<br>
	<form name="msnoptions" method="post">
	<TABLE class=optiontable>
	<TBODY>
	<TR vAlign=top>
	<TH scope=row>Space Name:</TH><td><input name="URL" type="text" size="60" value="<?php echo  $WP_MSNSYNC_URL;?>" /><br />Make sure you enter the space name, not the URL.<br />e.g. "http://spaces.msn.com/abcd" should enter "abcd"</td>
	</TR>
	<tr valign="top">
	<th scope="row">Password:</th><td> <input name="PASS" type="text" size="60" value="<?php echo  $WP_MSNSYNC_PASSWORD;?>"/><br />This is the secret word you choosed in your MSN space</td></tr>
	<tr valign="top">
	<th scope="row">Enable Sync: </th><td>
	<?php if($WP_MSNSYNC_ENABLE=="1"){?>
	  <label><input type="radio" name="ENABLE" value="1" checked/>Yes</label>
	  <label><input type="radio" name="ENABLE" value="0" />No</label>
	  <?php }else{ ?>
  	  <label><input type="radio" name="ENABLE" value="1" />Yes</label>
	  <label><input type="radio" name="ENABLE" value="0" checked/>No</label>
	  <?php } ?>
	  <br />When set to "Yes", there will be a new post made to your msn space whenever you make a new publishing
		</td>
		</tr>
		<!-- -->
	<tr valign="top">
	<th scope="row">Title of Sync:</th><td> <input name="TITLE" type="text" size="60" value="<?php echo  $WP_MSNSYNC_TITLE;?>"/><br />This will appear to be the title of the synchronization post</td></tr>

	<tr valign="top">
	<th scope="row">
	  Message to be send as SYNC:
  </th>
  <td>
	  <textarea name="SYNCMSG" cols="60" rows="6"><?php echo $WP_MSNSYNC_MSG;?></textarea>

	  </td>
	  </tr>
	  </TBODY>
	  </TABLE>
	  	  <div class="wrap">You can use <font color="#7799FF">[TITLE]</font> or <font color="#7799FF">[POST]</font></a> in "Message to be send as SYNC".<br>
	  They will be replaced by the <font color="#7799FF">Title of your new post</font> or <font color="#7799FF">The content of your new post</font><br>
	  Note: You can't use them the the "Title of Sync" field
	  </div>
	<input type="hidden" name="action" value="options" />
	<div align="center"><input value="Save Options" type="submit"/></div>
	</form>
	<div class="wrap" align="right">This plug-in is made by <a href="http://moonblue.homeip.net">William Xu</a></div>
</div>

<?php
}

function wp_msnsync_add_page($s){
	add_submenu_page('index.php', 'MSN Sync','MSN Sync',1,__FILE__,'wp_msnsync_display');
	return $s;
}
add_action('admin_menu','wp_msnsync_add_page');
//add_options_page("MSN Sync", "MSN Sync", 1, __FILE__, 'wp_msnsync_add_page');
//////////////////////////////////////////////////////////////
//Main code section 
//////////////////////////////////////////////////////////////
function wp_msnsync_save(){
	//save options into databse
	update_option("wp_msnsync_url", $_POST['URL']);
	update_option("wp_msnsync_password", $_POST['PASS']);
	update_option("wp_msnsync_enable", $_POST['ENABLE']);
	update_option("wp_msnsync_msg",  stripslashes($_POST['SYNCMSG']));
	update_option("wp_msnsync_title",  stripslashes($_POST['TITLE']));
}

/* Update status on the user space*/
function wp_msnsync_getinfo(){
	global $WP_MSNSYNC_PASSWORD;
	global $WP_MSNSYNC_URL;
	
	$params = array (" ",$WP_MSNSYNC_URL,$WP_MSNSYNC_PASSWORD);
	$method = "blogger.getUsersBlogs";
	$request = xmlrpc_encode_request($method,$params);
	$result=wp_msnsync_docall($request);
	$result_array=xmlrpc_decode($result);
	return $result_array;
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

//version 1.0 experiment

add_action('publish_post','wp_msnsync_post');



?>

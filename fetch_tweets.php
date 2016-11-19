<?php
//echo "<hr>${_SERVER['QUERY_STRING']}<hr>";
//exit;
//error_reporting(-1);
//ini_set('display_errors', 'On');
ini_set('max_execution_time', 3000);
ini_set('memory_limit', '1024M');


foreach (array_keys($_GET) as $p) { $_GET[$p]=trim($_GET[$p]); }
date_default_timezone_set('UTC');
//'America/New_York');
require_once("configurations.php");
require_once("cloud.php");

$per_page=100;
$debug=0;

if (!$_SESSION['authenticated'])
  {
    die("<b>You are logged out. Please <a href='index.php?id=tweets'>Return to the main page</a> to log in again.</b><br><hr>");
  }

$table=$_GET['table'];
$drill_level=$_GET['drill_level'];
$retweets_graph=$_GET['retweets_graph'];
$unique_tweeters=$_GET['unique_tweeters'];
$retweets=$_GET['retweets'];
$from=$_GET['from'];
$to=$_GET['to'];
$languages=trim($_GET['languages']);
$sources=trim($_GET['sources']);
$p=$_GET['p']; $pp=$_GET['pp'];
$top_images=$_GET['top_images'];
$top_videos=$_GET['top_videos'];
$top_links=$_GET['top_links'];
$retweeted=$_GET['retweeted'];
if (!$retweeted) $shared=1;
if ($top_images || $top_videos || $top_links) $tops=1;
$stackgraph=$_GET['stackgraph'];

//echo "(".$cases[$table]['name'].")"; exit;

if (!$p) $p=1; if (!$pp) $pp=$per_page;;
if ($_GET['startdate'])
  {

    $temp=DateTime::createFromFormat('d/m/Y', trim($_GET['startdate']));
    $startdate=$temp->format('Y-m-d');
    if ($_GET['starttime']) $starttime=$_GET['starttime'];
  }
//echo "(sd:$startdate)"; exit;
if ($_GET['enddate'])
    {
      $temp=DateTime::createFromFormat('d/m/Y', trim($_GET['enddate']));
      $enddate=$temp->format('Y-m-d');
      if ($_GET['endtime']) $endtime=$_GET['endtime'];
    }

if (!$drill_level) $drill_level="days";
if (!$content_type) $content_type="tweets";
if (!$table) { echo "No case provided...\n"; exit; }

connect_mysql();
get_cases();

$name=$cases[$table]['name'];

//echo "(${_GET['point']})";
if ($_GET['inspect'])
  {
//print_r($_GET); exit;
    $_GET['clear_text']=hyper_link(urldecode($_GET['clear_text']));
    $url=get_hd().$_SERVER[HTTP_HOST].$_SERVER[REQUEST_URI];
    $url=preg_replace('/\&inspect\=1\&.+/','',$url);

//echo "<hr>inspect<br>$url<hr>branch:".$_GET['branch'];
//print_r($_GET); echo "<hr>";

    if (!$_GET['branch'])
      {
        $data="<center><a href='#' onclick=javascript:GetDetails('$url')><small>Back to the main list</small></a></center><br>";
      }

    $data=$data."<font size=+1>".$_GET['date_time']."</font><br>";
    $data=$data."<div class='alert alert-first'>";
    $data=$data."<h4 class='alert-heading'><a href='".$_GET['tweet_permalink_path']."' target=_blank>Original tweet</a></h4><br><a href='https://twitter.com/".$_GET['user_screen_name']."' target=_blank>";
    $data=$data."<img src='".$_GET['user_image_url']."' alt='".$_GET['user_screen_name']."' align=left style='margin:10px;' onerror=\"this.style.display='none'\" width=50><br> &nbsp;<font color=white>";
    $data=$data.urldecode($_GET['user_name'])." (".$_GET['user_screen_name'].")</font></a><br><blockquote><font color=black>".$_GET['clear_text']."</font></blockquote><hr><br>";
    $data=$data."<a href='?id=tweets&table=$table&user_screen_name=".$_GET['user_screen_name']."&load=1'><font color=white>More from this tweeter</font></a> in connection to (".$cases[$table]['name'].")<br></div>";

    $first_part=$data;
    $tweet_id=$_GET['tweet_id'];
    $date_time=$_GET['date_time'];
    $user_id=$_GET['user_id'];
    $user_name=$_GET['user_screen_name'];
//    $clear_text=preg_replace("/https?\:\/\/[^\s]+?/","",$_GET[4]);

    $original_text=$_GET['clear_text'];
    $clear_text=preg_replace("/RT /i","",$_GET['clear_text']);
    $partial_text=preg_replace("/http\:\/\//i","",$_GET['clear_text']);
    $partial_text=preg_replace("/\@[^\s]+/","",$partial_text);
    $partial_text=trim(substr($partial_text,10,-10));
    if (strlen($partial_text)<20) $clear_text="";

    if ($clear_text) { $clear_text="OR clear_text='".$link->real_escape_string($clear_text).
                                    "' OR clear_text='".$link->real_escape_string($original_text).
                                    "' OR clear_text like '%".$link->real_escape_string($partial_text)."%'"; }
//    else $clear_text="";

    $query="select tweet_id,tweet_permalink_path,user_id,user_screen_name,user_name,user_image_url,user_mentions,clear_text,in_reply_to_user,in_reply_to_tweet,date_time from $table".
           " where in_reply_to_tweet='$tweet_id' OR ". //direct response
           " (date_time>'$date_time' AND date_time<='$date_time'+ INTERVAL 1 DAY AND (in_reply_to_user='$user_id' ". //response to user only
           " OR user_mentions like '%@$user_name%' ". //mentioned only
           " $clear_text)) order by date_time"; //repeated part of the tweet

//echo "<hr>$query<hr>";
    if ($result=$link->query($query))
      {
        if (!$result->num_rows) die("<b><br>This tweet has no interaction<br></b><a href='#' onclick=javascript:GetDetails('$url')><small>Back to the main list</small></a></center>");
        $total=$result->num_rows;
      }
    else die("Error in query: ". $link->error.": $query");

    $i=0; $t=0; $u=0; $m=0; $r=0; $o=0; $p=0;
//die("hi2: $query<br>\n");

    $data=""; $last_five=rand(1000,9999);
    while ($row=$result->fetch_assoc())
      {
//        if (!$row['user_id']) continue;
	$found=""; $note=""; $tweet_marker="";
//print_r($row); 

        if ($row['in_reply_to_tweet']==$tweet_id)
          {
            $found="Direct tweet reply"; $note="block"; $t++; $tweet_marker=$tweet_id."_t";
         }
        elseif ($row['in_reply_to_user']==$user_id)
          {
            $found="General reply to user (excluding direct replies)"; $note="success"; $u++; $tweet_marker=$tweet_id."_u";
          }
        elseif ($clear_text && $row['clear_text']==$clear_text)
          {
            $found="Retweeted tweet"; $note="info"; $r++; $tweet_marker=$tweet_id."_r";
          }
        elseif ($clear_text && $row['clear_text']==$original_text)
          {
            $found="Used original tweet"; $note="success"; $o++; $tweet_marker=$tweet_id."_o";
          }
        elseif ($clear_text && strpos($row['clear_text'],$partial_text)==true)
          {
            $found="Partial text match"; $note="block"; $p++; $tweet_marker=$tweet_id."_p";
          }
        elseif (strpos(strtolower($row['user_mentions']),strtolower($user_name))==true)
          {
            $found="A mention of @$user_name (excluding direct or user replies)"; $note="error"; $m++; $tweet_marker=$tweet_id."_m";
          }
          $i++;
//          if ($_GET['branch']) $data=$data;
          $data=$data."<div class='$tweet_marker'><br><font size=+1>".$row['date_time']."</font><br>";
          $data=$data."<div class='alert alert-$note'>";
          $data=$data."<h4 class='alert-heading'><a href='${row['tweet_permalink_path']}' target=_blank>$found</a></h4>";
          $data=$data."<a href='https://twitter.com/".$row['user_screen_name']."' target=_blank>".
                      "<img src='".$row['user_image_url']."' alt='".$row['user_screen_name']."' align=left onerror=\"this.style.display='none'\" width=50> &nbsp;";
          $data=$data.$row['user_name']." (".$row['user_screen_name'].")</a><br><br><blockquote><font color=black>".hyper_link($row['clear_text'])."</font></blockquote>";
          $data=$data."<center><a href='?id=tweets&table=$table&user_screen_name=".$row['user_screen_name']."&load=1' target=_blank>More from this tweeter</a> in connection to (".$cases[$table]['name'].")</center><br><a href=";
          $inspect_link="javascript:GetDetails('$url&inspect=1&tweet_id=${row['tweet_id']}&tweet_permalink_path=".rawurlencode($row['tweet_permalink_path'])."&user_id=${row['user_id']}&user_screen_name=${row['user_screen_name']}&date_time=".rawurlencode($row['date_time'])."&user_image_url=".rawurlencode($row['user_image_url'])."','".rawurlencode(addslashes($row['user_name']))."','".rawurlencode(addslashes($row['clear_text']))."','".$row['tweet_id'].$last_five."_branch')";
          $inspect_link=str_replace("%0A","%20",$inspect_link);
//if ($tweet_id=='125521403817623552') echo "<hr>$inspect_link<hr>";
//echo "($inspect_link)";
          $data=$data.$inspect_link."><img src='images/inspect.png' alt='see connected tweets'></a><br><div id='".$row['tweet_id'].$last_five."_branch'></div></div></div>";
          if ($_GET['branch']) $data=$data;
      }
//die("hi3<br>\n");

if (!$i) die("No related tweets to ($user_name) were found...");
if (!$_GET['branch'])
  {
    echo "$first_part A quick inspection for the above tweet has yielded a total of <b>$i</b> related tweets since it was posted:<br>";
    echo "<ul>";
    if ($t) echo "<li><b>$t</b> direct replies to the tweet</li>";
    if ($u) echo "<li><b>$u</b> replies to the tweeter (excludes direct replies) in the next 24 hours</li>";
    if ($m) echo "<li><b>$m</b> mentions of @$user_name (excludes direct and tweeter replies) in the next 24 hours</li>";
    if ($r) echo "<li><b>$r</b> a manual retweet (not through Twitter's retweet feature)</li>";
    if ($o) echo "<li><b>$o</b> exact copies of the tweet</li>";
    if ($p) echo "<li><b>$p</b> almost identical matches of the tweet</li>";
    echo "</ul><b>Find the replies below sorted by the time they were posted:</b><br><br>";
  }
echo "</center>";
if ($t) echo "<input type=checkbox id='show_t' name='show_t' checked onclick=toggle_tweets('$tweet_id','t');> Show tweet replies ($t)";
if ($u) echo "&nbsp; <input type=checkbox id='show_u' name='show_u' checked onclick=toggle_tweets('$tweet_id','u');> Show user replies ($u)";
if ($m) echo "&nbsp; <input type=checkbox id='show_m' name='show_m' checked onclick=toggle_tweets('$tweet_id','m');> Show user mentions ($m)";
if ($r) echo "&nbsp; <input type=checkbox id='show_r' name='show_r' checked onclick=toggle_tweets('$tweet_id','r');> Show manual retweets ($r)";
if ($o) echo "&nbsp; <input type=checkbox id='show_o' name='show_o' checked onclick=toggle_tweets('$tweet_id','o');> Show replicas ($o)";
if ($p) echo "&nbsp; <input type=checkbox id='show_p' name='show_p' checked onclick=toggle_tweets('$tweet_id','p');> Show partial matches ($p)";
echo "</center>";

echo "$data";
//echo "total1:$total, i:$i; t:$t, u:$u, m:$m, r:$r, o:$o, p:$p total2 ".($t+$u+$m+$r+$o+$p);
      $link->close();
      exit;

}
if (!$_GET['point'])
{

  $condition= "WHERE is_protected_or_deleted is null and date_time is not null and date_time is not null ";
//if ($table=="ChrChristensen2")
// echo "(".$_GET['only_tweets'].")";
	  if ($_GET['only_tweets']) $condition=$condition." AND (is_retweet<>1)";
	  if ($startdate) $from=trim("$startdate $starttime");
  if ($enddate) $to=trim("$enddate $endtime");
  //echo "$from - $to";
  if ($from) $condition=$condition." AND date_time>='$from'";
  if ($to) $condition=$condition." AND date_time<'$to'";

  if ($sources=="only_web") { $condition=$condition." AND NOT (source LIKE '%Android%' OR source LIKE '%iPad%' OR source LIKE '%BlackBerry%' OR source LIKE '%Mobile%' OR source LIKE '%Nokia%' OR source LIKE '%Symbian%' OR source LIKE '%Phone%' OR source LIKE '%Tab%' OR source LIKE '%App%') AND (source LIKE '%Web%')"; $name=$name." (web sources)"; }
  elseif ($sources=="only_mobile") { $condition=$condition." AND (source LIKE '%Android%' OR source LIKE '%iPad%' OR source LIKE '%BlackBerry%' OR source LIKE '%Mobile%' OR source LIKE '%Nokia%' OR source LIKE '%Symbian%' OR source LIKE '%Phone%' OR source LIKE '%Tab%' OR source LIKE '%App%') AND (source NOT LIKE '%Web%')"; $name=$name." (mobile sources)"; }
  elseif ($sources) { 
    $c=""; $started=false;
    $tmp=preg_split('/\,/',$sources);
    foreach ($tmp as $k)
     {
       if (!$started) $c="AND ((source is not null and LOWER(source) like '%".$link->real_escape_string(trim(strtolower($k)))."%') ";
       else $c=$c." OR (source is not null and LOWER(source) like '%".$link->real_escape_string(trim(strtolower($k)))."%') ";
       $started=true;
     }
    $condition=$condition." $c) ";
$name=$name." (other sources)";// echo "($condition)"; 
}

  if ($languages=="en") $condition=$condition." AND (tweet_language='en')";
  elseif ($languages) $condition=$condition." AND (tweet_language like '%".strtolower($languages)."%')";

  if ($_GET['types']=="some")
        {
          if ($_GET['image_tweets']) { $condition=$condition." AND has_image=1 "; $name=$name." (with image only)"; }
          if ($_GET['video_tweets']) { $condition=$condition." AND has_video=1 "; $name=$name." (with video only)"; }
          if ($_GET['link_tweets']) { $condition=$condition." AND has_link=1 "; $name=$name." (with link only)"; }
          if ($_GET['retweet_tweets']) { $condition=$condition." AND (is_retweet=1) ";  $name=$name." (are retweets)"; }
          if ($_GET['response_tweets']) { $condition=$condition." AND (in_reply_to_tweet is not null OR in_reply_to_user is not null) ";  $name=$name." (are responses)"; }
          if ($_GET['quoting_tweets']) { $condition=$condition." AND (quoted_tweet_id is not null) ";  $name=$name." (quoting a tweet)"; }
          if ($_GET['mentions_tweets']) { $condition=$condition." AND (clear_text like '@%') ";  $name=$name." (are responses)"; }
          if ($_GET['responded_tweets']) { $condition=$condition." AND (responses is not null AND responses>0) ";  $name=$name." (are responsed to)"; }
          if ($_GET['exact_phrase']) { $condition=$condition." AND LOWER(clear_text) REGEXP '([[[:blank:][:punct:]]|^)".$link->real_escape_string($_GET['exact_phrase'])."([[:blank:][:punct:]]|$)'"; $name=$name." (exact phrase search)"; }
          if ($_GET['user_verified']) { $condition=$condition." AND user_verified=1 "; $name=$name." (from a verified tweeter)"; }
          if ($_GET['min_retweets']) { $condition=$condition." AND retweets>=".$_GET['min_retweets']." "; $name=$name." (with minimum # of ".$_GET['min_retweets']." retweets)"; }
          if ($_GET['any_hashtags'])
            { 
              $_GET['any_hashtags']=str_replace("#"," ",$_GET['any_hashtags']);
              $_GET['any_hashtags']=str_replace(","," ",$_GET['any_hashtags']);
	      $tmp=preg_split('/[\s+\,]/',$_GET['any_hashtags']);
              $c=""; $started=false;
              foreach ($tmp as $k)
                {
                  if (!$started) $c="AND (LOWER($table.hashtags) like '%#".$link->real_escape_string(trim(strtolower($k)))."%' ";
                  else $c=$c." OR LOWER($table.hashtags) like '%#".$link->real_escape_string(trim(strtolower($k)))."%' ";
                  $started=true;
                }
              $condition=$condition." $c) ";
            }
          if ($_GET['any_keywords'])
            {
              $name=$name." (with any of keywords: ".$_GET['any_keywords'].")";
              $tmp=preg_split('/[\s+\,]/',$_GET['any_keywords']);
              $c=""; $started=false;
              foreach ($tmp as $k)
                {
                  if (!$started) $c="AND (LOWER(clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                  else $c=$c." OR LOWER(clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                  $started=true;
                }
              $condition=$condition." $c) ";
            }
          if ($_GET['all_keywords'])
            {
                $name=$name." (with any of keywords: ".$_GET['all_keywords'].")";
                $tmp=preg_split('/[\s+\,]/',$_GET['all_keywords']);
                $c=""; $started=false;
                foreach ($tmp as $k)
                  {
                    if (!$started) $c="AND (LOWER(clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                    else $c=$c." AND LOWER(clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                  $started=true;
                  }
                $condition=$condition." $c) ";
            }
          if ($_GET['from_accounts'])
            {
		  $_GET['from_accounts']=str_replace("@","",$_GET['from_accounts']);
                  $name=$name." (from accounts: ".$_GET['from_accounts'].")";
                  $tmp=preg_split('/[\s+\,]/',$_GET['from_accounts']);
                  $c=""; $started=false;
                  foreach ($tmp as $k)
                    {
                      if (!$started) $c="AND ((LOWER($table.user_screen_name)='".$link->real_escape_string(trim($k))."' OR lower($table.user_name) like '%".$link->real_escape_string(trim($k))."%') ";
                      else $c=$c." OR (LOWER($table.user_screen_name)='".$link->real_escape_string(trim($k))."'  OR lower($table.user_name) like '%".$link->real_escape_string(trim($k))."%') ";
                  $started=true;
                    }
                  $condition=$condition." $c) ";
            }
          if ($_GET['in_reply_to_tweet_id'])
            {
                  $name=$name." (reply to tweet: ".$_GET['in_reply_to_tweet_id'].")";
                  $c="AND ($table.in_reply_to_tweet='${_GET['in_reply_to_tweet_id']}'";
                  $condition=$condition." $c) ";
            }

          if ($_GET['location'])
                {
                  $name=$name." (from locations: ".$_GET['location'].")";
                  $_GET['location']=preg_replace("/\,\s+/",",",$_GET['location']);
                  $_GET['location']=preg_replace("/\s+\,/",",",$_GET['location']);
                  $tmp=explode(",",$_GET['location']);
                  $c=""; $started=false;
                  foreach ($tmp as $k)
                    {
                      if (!$started) $c="AND ((LOWER(location_fullname) like '%".$k."%' OR LOWER(location_name) like '%".$k."%' OR LOWER(user_location) like '%".$k."%' OR LOWER(user_timezone) like '%".$k."%')";
                      else $c=$c." OR (LOWER(location_fullname) like '%".$k."%' OR LOWER(location_name) like '%".$k."%' OR LOWER(user_location) like '%".$k."%' OR LOWER(user_timezone) like '%".$k."%')";
                  $started=true;
                    }
                  $condition=$condition." $c) ";
                }
     }
 
       $started=false;
        $name="total # ";
	$g_params=array("image_tweets","video_tweets","link_tweets","retweet_tweets","response_tweets","mentions_tweets","responded_tweets","quoting_tweet","any_hashtags","any_keywords","exact_phrase","from_accounts","in_reply_to_tweet_id","location","min_retweets","user_verified","languages","sources");
	 $params="";
	 if ($retweets) $name=$name." of tweets+retweets ";
	 elseif ($unique_tweeters) $name=$name." of unique tweeters ";
	 else  $name=$name." of tweets ";
	 foreach (array_keys($_GET) as $g)
		{
            	  if ($_GET[$g] && in_array($g,$g_params)) $params=$params." $g:".$_GET[$g].",";
		}
        $params=rtrim($params,",");	
	if ($params) $name="[$params]";
        if (!$from || !$to) $prd=" (for full period)";
        else $prd=" (from $from to $to)";
        if ($drill_level=="days")
            {
              $datetime_format="%Y-%m-%d";
              $pointInterval="24 * 3600 * 1000";
            }
        elseif ($drill_level=="hours")
            {
              $datetime_format="%Y-%m-%d %H:00";
              $pointInterval="3600 * 1000";
            }
        elseif ($drill_level=="minutes")
            {
              $datetime_format="%Y-%m-%d %H:%M:00";
              $pointInterval="60 * 1000";
            }
        elseif ($drill_level=="seconds")
            {
              $datetime_format="%Y-%m-%d %H:%M:%S";
              $pointInterval="1000";
            }
        if ($drill_level=="days")  { $param1="CONVERT_TZ(DATE_FORMAT(date_time,'%Y-%m-%d 00:00:00')"; $param2="tweet_date"; }
        elseif ($drill_level=="hours")  { $param1="CONVERT_TZ(DATE_FORMAT(date_time,'%Y-%m-%d %H:00:00')"; $param2="DATE_FORMAT(date_time,'%Y-%m-%d %H:00:00')"; }
        elseif ($drill_level=="minutes"){ $param1="CONVERT_TZ(DATE_FORMAT(date_time,'%Y-%m-%d %H:%i:00')"; $param2="DATE_FORMAT(date_time,'%Y-%m-%d %H:%i:00')"; }
        elseif ($drill_level=="seconds"){ $param1="CONVERT_TZ(DATE_FORMAT(date_time,'%Y-%m-%d %H:%i:%S')"; $param2="date_time"; }
        else { $param1="tweet_date"; $param2="tweet_date"; }

        $graph_data="{ color: '<!--color-->', name: '<!--name-->', data: [ <!--data--> ], id : '<!--hashkey-->' } ,";

        if ($unique_tweeters)
	   $query=$query.$link2."SELECT UNIX_TIMESTAMP($param1, '+00:00', @@session.time_zone))*1000,count(distinct user_id) from $table $condition group by $param2";
        elseif ($retweets)
	   $query=$query.$link2."SELECT UNIX_TIMESTAMP($param1, '+00:00', @@session.time_zone))*1000,count(tweet_id)+sum(retweets) from $table $condition group by $param2";
	else            
	  $query=$query.$link2."SELECT UNIX_TIMESTAMP($param1, '+00:00', @@session.time_zone))*1000,count(tweet_id) from $table $condition group by $param2";
        $started=true;
        $query=$query." order by date_time";
//echo "<hr>$query<hr>";
        if ($result = $link->query($query))
          {
            if (!$result->num_rows) die("No results in the database matched your query.<br>\n");
            $start_from=$total-$result->num_rows;
          }
        else die("Error in query: ". $link->error.": $query");

        $started=false;
        while ($row = $result->fetch_array())
          {
            $data=$data."[".$row[0].",".$row[1]."],";
            if (!$started) $pointStart=$row[0];
          }

        if ($_GET['last_graph_hash'] && $stackgraph)
          {
            if (file_exists("cache/".$_GET['last_graph_hash'].".htm"))
              {
                $template=file_get_contents("cache/".$_GET['last_graph_hash'].".htm");
              }
            else {
                  $template=file_get_contents("templates/template.htm");
                 }
          }
        else $template=file_get_contents("templates/template.htm");

$flags="";
$qry="SELECT flags FROM cases WHERE id='$table'";
//echo "[$qry]";
if ($result=$link->query($qry))
  {
	$flags_arr = $result->fetch_array();
	if ($flags_arr) { $flags_data=json_decode($flags_arr[0], true); }
  }
$link->close();

foreach ($flags_data['flags'] as $pd) 
   {
	if ($pd['date_and_time']>$to || $pd['date_and_time']<$from) continue;
	$flags=$flags." { x : ".strtotime($pd['date_and_time'])."000 , title : '${pd['title']}' , text : '${pd['description']}' },\n";
   }

//echo $flags; exit;
if ($flags)
  {
   $temp_flags="{\n\ttype : 'flags',\n\tdata : [<!--flags-->],\n\tonSeries : '<!--hashkey-->',\n\tname : 'Timeline flags',\n\tshape : 'flag',\n\twidth : 70\n}\n";
   $flags=str_replace("<!--flags-->",$flags,$temp_flags);
  }


        $colors =array('7cb5ec', '434348', '90ed7d', 'f7a35c', '8085e9', 'f15c80', 'e4d354', '8085e8', '8d4653', '91e8e1', '4572A7', 'AA4643', '89A54E', '80699B', '3D96AE', 'DB843D', '92A8CD', 'A47D7C', 'B5CA92');

        $url=basename($_SERVER['PHP_SELF']) . "?" . $_SERVER['QUERY_STRING'];
        $s=substr_count($template," series_urls[");
        $template=str_replace("function update_series_urls(){ ", "function update_series_urls(){ series_urls['".$colors[$s]."']='$url'; " ,$template);

        $graph_data=str_replace("<!--name-->",addslashes(ucfirst(trim($name))),$graph_data);
        $graph_data=str_replace('<!--color-->','#'.$colors[$s],$graph_data);
        $graph_data=str_replace("<!--data-->",$data,$graph_data);
	$graph_data2=$graph_data;
        $graph_data=$graph_data."\n$flags\n/*<!--graph_data-->*/";
        $graph_data2=$graph_data2."/*<!--graph_data-->*/";

        $hashkey=base_convert(md5($_SERVER[REQUEST_URI]), 16, 10);
        $graph_data=str_replace("<!--hashkey-->","data".substr($hashkey,0,10),$graph_data);

//echo "<hr>$graph_data<hr>"; exit;

        if ($_GET['demo'])
          {
            $template=preg_replace("/<script type='text\/javascript' src.+/","",$template);
          }
        //echo "($template)";
        $template=str_replace("<!--content_type-->",$content_type,$template);
        $template=str_replace("<!--drill_level-->",$drill_level,$template);
        $template=str_replace("<!--datetime_format-->",$datetime_format,$template);
        $template=str_replace("<!--pointStart-->",$pointStart,$template);
        $template=str_replace("<!--pointInterval>",$pointInterval,$template);
	$template2=$template;
        $template=str_replace('/*<!--graph_data-->*/',$graph_data,$template);
        $template2=str_replace('/*<!--graph_data-->*/',$graph_data2,$template2);
        $template=str_replace("<!--url-->",$url,$template);
        $template2=str_replace("<!--url-->",$url,$template2);
//echo "($hashkey)"; exit;
        file_put_contents("cache/$hashkey.htm",$template2);
        echo $template."<form><input type='hidden' id='last_graph_hash' value='$hashkey'></form>";
 }
else
{
	$main_url=preg_replace("/\&last_graph_hash=[\d]+/","",$_SERVER[REQUEST_URI]);
//echo "<hr>$main_url<hr>";

        $hashkey2=base_convert(md5($main_url), 16, 10);

        if (file_exists("cache/$table$hashkey2.tab"))
          {
echo "Using cached table<br>";
            $file_updated=gmdate("Y-m-d H:i:s", filemtime("cache/$table$hashkey2.tab"));

            if ($result=$link->query("SELECT last_process_updated FROM cases WHERE id='$table'")) 
		{ 
		   if (!$result->num_rows) die("Error in DB");
		   $row=$result->fetch_array();
		   if ($row[0]<$file_updated)
			{
			  //echo "<span style='text-align:right; text-size:10pt;'>Note: * Cached table</span><br>";
			  if (file_exists("cache/$table$hashkey2-slides.html")) 
			     { 
	  			echo "<center><a href=\"cache/$table$hashkey2-slides.html\" target=_blank><img src=\"images/slideshow.png\" width=100> Interactive slides interface (under development)</a></center><br>";
			     }
			  echo file_get_contents("cache/$table$hashkey2.tab");
			  exit;
			}
		}
            else die("Error in query: ". $link->error.": $query");
          }

    if ($_GET['point']>1)
    {
	
      $per_page=100;

      $point=$_GET['point']/1000;
      if ($drill_level=="days") $point=gmdate("Y-m-d 00:00:00", $point);
      elseif ($drill_level=="hours") $point=gmdate("Y-m-d H:00:00",$point);
      elseif ($drill_level=="minutes") $point=gmdate("Y-m-d H:i:00",$point);
      elseif ($drill_level=="seconds") $point=gmdate("Y-m-d H:i:s",$point);
    }
    else $point=1;
    //  echo "new point for ($drill_level):".$point; exit;
    //echo $table; exit;
    $started=false;

  $condition= "WHERE $table.is_protected_or_deleted is null and $table.date_time is not null ";

  if ($_GET['only_tweets']) $condition=$condition." AND (is_retweet<>1)";

  if ($startdate) $from=trim("$startdate $starttime");
  if ($enddate) $to=trim("$enddate $endtime");
  //echo "$from - $to";
  if ($from) $condition=$condition." AND $table.date_time>='$from'";
  if ($to) $condition=$condition." AND $table.date_time<'$to'";

  if ($sources=="only_web") $condition=$condition." AND NOT (source LIKE '%Android%' OR source LIKE '%iPad%' OR source LIKE '%BlackBerry%' OR source LIKE '%Mobile%' OR source LIKE '%Nokia%' OR source LIKE '%Symbian%' OR source LIKE '%Phone%' OR source LIKE '%Tab%' OR source LIKE '%App%') AND (source LIKE '%Web%')";
  elseif ($sources=="only_mobile") $condition=$condition." AND (source LIKE '%Android%' OR source LIKE '%iPad%' OR source LIKE '%BlackBerry%' OR source LIKE '%Mobile%' OR source LIKE '%Nokia%' OR source LIKE '%Symbian%' OR source LIKE '%Phone%' OR source LIKE '%Tab%' OR source LIKE '%App%') AND (source NOT LIKE '%Web%')";
  elseif ($sources) 
   { 
    $c=""; $started=false;
    $tmp=preg_split('/\,/',$sources);
    foreach ($tmp as $k)
     {
       if (!$started) $c="AND ((source is not null and LOWER(source) like '%".$link->real_escape_string(trim(strtolower($k)))."%') ";
       else $c=$c." OR (source is not null and LOWER(source) like '%".$link->real_escape_string(trim(strtolower($k)))."%') ";
       $started=true;
     }
    $condition=$condition." $c) ";
    $name=$name." (other sources)";// echo "($condition)";
   }
  if ($languages=="en") $condition=$condition." AND (tweet_language='en')";
  elseif ($languages) $condition=$condition." AND (tweet_language like '%".strtolower($languages)."%')";

  if ($_GET['image']) $condition=$condition." AND ($table.expanded_links like '%".$_GET['image']."%' OR $table.media_link like '%".$_GET['image']."%')";
  if ($_GET['video'] || $_GET['image2']) $condition=$condition." AND ($table.expanded_links like '%".$_GET['video']."%' OR $table.media_link like '%".$_GET['video']."%' OR $table.media_link like '%".$_GET['image2']."%')";
  if ($_GET['link']) $condition=$condition." AND ($table.expanded_links='".$_GET['link']."')";

  if ($_GET['types']=="some")
  {
    if ($_GET['image_tweets']) $condition=$condition." AND $table.has_image=1 ";
    if ($_GET['video_tweets']) $condition=$condition." AND $table.has_video=1 ";
    if ($_GET['link_tweets']) $condition=$condition." AND $table.has_link=1 ";
    if ($_GET['retweet_tweets']) $condition=$condition." AND ($table.is_retweet=1) ";
    if ($_GET['response_tweets']) $condition=$condition." AND ($table.in_reply_to_tweet is not null OR $table.in_reply_to_user is not null) ";
    if ($_GET['quoting_tweets']) { $condition=$condition." AND (quoted_tweet_id is not null) ";  $name=$name." (quoting a tweet)"; }
    if ($_GET['mentions_tweets']) $condition=$condition." AND ($table.clear_text like '@%') ";
    if ($_GET['responded_tweets']) { $condition=$condition." AND (responses is not null AND responses>0) ";  $name=$name." are responsed to"; }
    if ($_GET['exact_phrase']) { $condition=$condition." AND LOWER(clear_text) REGEXP '([[[:blank:][:punct:]]|^)".$link->real_escape_string($_GET['exact_phrase'])."([[:blank:][:punct:]]|$)'"; $name=$name." (exact phrase search)"; }
    if ($_GET['user_verified']) $condition=$condition." AND $table.user_verified=1 ";
    if ($_GET['min_retweets']) $condition=$condition." AND $table.retweets>=".$_GET['min_retweets']." ";
    if ($_GET['any_hashtags'])
      { 
              $_GET['any_hashtags']=str_replace("#"," ",$_GET['any_hashtags']);
              $_GET['any_hashtags']=trim($_GET['any_hashtags']);
              $tmp=preg_split('/[\s+\,]/',$_GET['any_hashtags']);
              $c=""; $started=false;
              foreach ($tmp as $k)
                {
                  if (!$started) $c="AND (LOWER($table.hashtags) like '%#".$link->real_escape_string(trim(strtolower($k)))."%' ";
                  else $c=$c." OR LOWER($table.hashtags) like '%#".$link->real_escape_string(trim(strtolower($k)))."%' ";
                  $started=true;
                }
              $condition=$condition." $c) ";
      }
    if ($_GET['any_keywords'])
      { $tmp=preg_split('/[\s+\,]/',$_GET['any_keywords']);
        $c=""; $started=false;
        foreach ($tmp as $k)
          {
            if (!$started) $c="AND (LOWER($table.clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
            else $c=$c." OR LOWER($table.clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                  $started=true;
          }
        $condition=$condition." $c) ";
//echo "[$condition]"; exit;
      }
    if ($_GET['all_keywords'])
      { $tmp=preg_split('/[\s+\,]/',$_GET['all_keywords']);
          $c=""; $started=false;
          foreach ($tmp as $k)
            {
              if (!$started) $c="AND (LOWER($table.clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
              else $c=$c." AND LOWER($table.clear_text) like '%".$link->real_escape_string(trim($k))."%' ";
                  $started=true;
            }
          $condition=$condition." $c) ";
      }
    if ($_GET['from_accounts'])
      { $tmp=preg_split('/[\s+\,]/',$_GET['from_accounts']);
            $c=""; $started=false;
            foreach ($tmp as $k)
              {
                $k=ltrim($k,'@');
                if (!$started) $c="AND ((LOWER($table.user_screen_name)='".$link->real_escape_string(trim($k))."' OR lower($table.user_name) like '%".$link->real_escape_string(trim($k))."%') ";
                else $c=$c." OR (LOWER($table.user_screen_name)='".$link->real_escape_string(trim($k))."'  OR lower($table.user_name) like '%".$link->real_escape_string(trim($k))."%') ";
                  $started=true;
              }
            $condition=$condition." $c) ";
      }
    if ($_GET['in_reply_to_tweet_id'])
            {
                  $name=$name." (reply to tweet: ".$_GET['in_reply_to_tweet_id'].")";
                  $c="AND ($table.in_reply_to_tweet='${_GET['in_reply_to_tweet_id']}'";
                  $condition=$condition." $c) ";
            }

    if ($_GET['location'])
        {
          $_GET['location']=preg_replace("/\,\s+/",",",$_GET['location']);
          $_GET['location']=preg_replace("/\s+\,/",",",$_GET['location']);
          $tmp=explode(",",$_GET['location']);
          $c=""; $started=false;
          foreach ($tmp as $k)
            {
              if (!$started) $c="AND ((LOWER($table.location_fullname) like '%".$k."%' OR LOWER($table.location_name) like '%".$k."%' OR LOWER(users_".$table.".user_location) like '%".$k."%' OR LOWER(users_".$table.".user_timezone) like '%".$k."%')";
              else $c=$c." OR (LOWER($table.location_fullname) like '%".$k."%' OR LOWER($table.location_name) like '%".$k."%' OR LOWER($table.user_location) like '%".$k."%' OR LOWER($table.user_timezone) like '%".$k."%')";
                  $started=true;
            }
          $condition=$condition." $c) ";
        }
        }
    if ($_GET['response_to'])
        {
          $condition=" WHERE (in_reply_to_tweet='".$_GET['response_to']."') ";
        }
//            $_GET['hashtag_cloud']=1;

if ($tops)
  {
      if ($top_images)
        {
          $element="$table.expanded_links,$table.media_link";
          $condition=$condition." AND has_image=1 AND ($table.media_link<>'' OR $table.expanded_links<>'') ";
        }
      elseif ($top_videos)
        {
          $element="$table.expanded_links,$table.media_link";
          $condition=$condition." AND ((has_video=1 AND ($table.expanded_links<>'' OR $table.media_link<>'')) OR $table.expanded_links like '%youtu%') ";
        }
      else
        {
          $element="$table.expanded_links";
          $condition=$condition." AND has_link=1 AND $table.expanded_links<>''";
        }
      if ($retweeted) $order="sum($table.retweets)"; else $order="count(tweet_id)";

      $qry1="SELECT $element,$order,$table.tweet_permalink_path from $table $condition ";
      if ($point>1)
      {
        if ($drill_level=="days")
          $query="$qry1 and $table.tweet_date='$point' ";
        elseif ($drill_level=="hours")
          $query="$qry1 and DATE_FORMAT($table.date_time,'%Y-%m-%d %H:00:00')='$point' ";
        elseif ($drill_level=="minutes")
          $query="$qry1 and DATE_FORMAT($table.date_time,'%Y-%m-%d %H:%i:00')='$point' ";
        elseif ($drill_level=="seconds")
          $query="$qry1 and $table.date_time='$point' ";
      }
      else $query=$qry1;
      $query="$query group by $element order by $order DESC LIMIT $per_page";


      $url=get_hd().$_SERVER[HTTP_HOST].$_SERVER[REQUEST_URI];

      if ($point==1)
        {
          $url=preg_replace("/\&point=[\d\d]+/","&point=1",$url);
        }

//echo "[1:$url]<br>";
//echo "<hr>before: $url<hr>";
//                $url=preg_replace('/&asc=[\d]&order.+?=[\d]&point/','&point',$url);
//echo "<hr>after: $url<hr>";
      $url=preg_replace('/\&top_videos=[\d]/','',$url);
      $url=preg_replace('/\&top_images=[\d]/','',$url);
      $url=preg_replace('/\&top_links=[\d]/','',$url);
      $url=preg_replace('/\&shared=[\d]/','',$url);
      $url=preg_replace('/\&retweeted=[\d]/','',$url);
      $url=preg_replace('/\&image[2]?=.*?\&/','&',$url);
      $url=preg_replace('/\&video=.*?\&/','&',$url);
      $url=preg_replace('/\&link=.*?\&/','&',$url);
      $url=preg_replace('/\&response_to=[\d]+/','',$url);
      $url=preg_replace('/\&\&/','&',$url);

//echo "[qry:$query]<br>";
if ($debug && $_SESSION['email']==$admin_email) echo "<hr>(".$query.")";

      if ($result = $link->query($query))
        {
          $total_rows=$result->num_rows;
          if (!$total_rows) die("No results in the database matched your query. - <a href='#' onclick=javascript:GetDetails('$url')><small>Back to the main list</small></a></center><br>\n");
        }
      else die("Error in query: ". $link->error.": $query");
      if ($top_images) $data="<center><b>Top images from $table</b> ";
      elseif ($top_videos) $data="<b>Top videos from $table</b> ";
      elseif ($top_links) $data="<b>Top links from $table</b> ";
      if ($shared) $data=$data." <i>sorted by the number of shares</i> ";
      else $data=$data." <i>sorted by the number of retweets</i> ";
      $data=$data." - <a href='#' onclick=javascript:GetDetails('$url')><small>Back to the main list</small></a></center>";
      $data=$data."<br><table style='font-size:8pt; width:500px; background-color:#FFFFFF;'><tr><td><b>Rank</b></td>";
      if ($top_images) $data=$data."<td><b>Image</b> (click to see list of tweets)</td>";
      elseif ($top_videos) $data=$data."<td><b>Video</b></td>";
      else $data=$data."<td><b>Link</b></td>";
      if ($shared) $data=$data."<td><b>Shares</b></td></tr>";
      else $data=$data."<td><b>Retweets</b></td></tr>";
      $cnt=0;

      while ($row = $result->fetch_array())
       {
//         print_r($row);
          $data=$data."<tr><td><b>".($cnt+1).")</b></td><td>";
          if ($top_images)
            {
if ($debug && $_SESSION['email']==$admin_email) { echo "(${row[0]},${row[1]})<br>\n"; }
	      $temp_list=explode(" ",$row[1]); $links="";
	      foreach ($temp_list as $temp_link) { $links=$links."<a href='#' onclick=javascript:GetDetails('$url&image=".rawurlencode($temp_link)."&')><img src='$temp_link' height=250></a> <a href='${row[3]}' target=_blank><img src='images/link.gif'></a><br> <br>"; }
              $data=$data."$links</td><td><center>${row[2]}</center></td></tr>\n";
            }
          elseif ($top_videos)
            {
	      if ($row[1]) { $img=1; $temp_list=explode(" ",$row[1]); }
	      else { $img=0; $temp_list=explode(" ",$row[0]); } 
	      $links="";
              foreach ($temp_list as $temp_link) { $links=$links."<a href='#' onclick=javascript:GetDetails('$url&video=".rawurlencode($temp_link)."&image2=".rawurlencode($temp_link)."&')>".image_exists($img,$temp_link)."${row[0]}</a> <a href='${row[3]}' target=_blank><img src='images/link.gif'></a>"; }
              $data=$data."$links</td><td><center>${row[2]}</center></td></tr>\n";
            }
          else
            {
	      $temp_list=explode(" ",$row[0]); $links="";
              foreach ($temp_list as $temp_link) { $links=$links."<a <a href='#' onclick=javascript:GetDetails('$url&link=".rawurlencode($temp_link)."&')>$temp_link</a> <a href='${row[2]}' target=_blank><img src='images/link.gif'></a>"; }
              $data=$data."$links</td><td><center>${row[1]}</center></td></tr>\n";
            }
          $cnt++;
       }
      $part1_data="$data</table>";
      $link->close();
  }
  else
  {
            $qry1="SELECT SQL_CACHE 
            $table.date_time,
            $table.tweet_id,
            $table.tweet_permalink_path,
            $table.user_screen_name,
            users_".$table.".user_name,
            users_".$table.".user_id,
            users_".$table.".user_image_url,
            users_".$table.".user_followers,
            users_".$table.".user_following,
            users_".$table.".user_created,
            users_".$table.".user_tweets,
            $table.user_verified,
            $table.clear_text,
            $table.retweets,
            $table.favorites,
            $table.responses,
            $table.source,
            $table.tweet_language,
            $table.media_link,
            $table.location_name,
            $table.location_fullname,
            users_".$table.".user_location,
            users_".$table.".user_timezone
            ".hashtags($_GET['hashtag_cloud'],$table,1).
	    ", $table.responses_to_tweeter, $table.mentions_of_tweeter ".
	    "from $table,users_".$table." $condition AND $table.user_id = users_".$table.".user_id ";

            $order="";
            if ($_GET['order_d']) $order="order by $table.date_time";
            elseif ($_GET['order_u']) $order="order by $table.user_screen_name";
            elseif ($_GET['order_t']) $order="order by $table.clear_text";
            elseif ($_GET['order_r']) $order="order by $table.retweets";
            elseif ($_GET['order_f']) $order="order by $table.favorites";
            elseif ($_GET['order_rs']) $order="order by $table.responses";
            elseif ($_GET['order_s']) $order="order by $table.source";
            elseif ($_GET['order_l']) $order="order by $table.tweet_language";
            elseif ($_GET['order_i']) $order="order by $table.media_link";
            elseif ($_GET['order_v']) $order="order by $table.user_verified";

//            if (!$order) $order="order by $table.retweets";
            if (!$order) { $order="order by $table.date_time"; $_GET['asc']=1; }

//echo $_GET['order_r']." ".$order; exit;
    //echo "order is:($order),asc:".$_GET['asc']."-";
            if ($_GET['asc']) $order="$order ASC";
            else $order="$order DESC";
	    if ($order) $order=$order.", $table.date_time ASC"; else $order="order by $table.date_time ASC";
            if ($_GET['limit']==-1) $limit=1000000;
            elseif ($_GET['limit']) $limit=$_GET['limit'];
            else $limit=$per_page;

            if ($point>1)
            {
              if ($drill_level=="days")
                $query="$qry1 and $table.tweet_date='$point' ";
              elseif ($drill_level=="hours")
                $query="$qry1 and DATE_FORMAT($table.date_time,'%Y-%m-%d %H:00:00')='$point' ";
              elseif ($drill_level=="minutes")
                $query="$qry1 and DATE_FORMAT($table.date_time,'%Y-%m-%d %H:%i:00')='$point' ";
              elseif ($drill_level=="seconds")
                $query="$qry1 and $table.date_time='$point' ";
            }
            else $query=$qry1;
            $query="$query $order";

if ($debug && $_SESSION['email']==$admin_email) echo "<hr>(".$query.")";

//echo "$query\n"; //exit;
            if ($result = $link->query($query))
              {
                $total_rows=$result->num_rows;
                if (!$total_rows) die("No results in the database matched your query.<br>\n");
              }
            else die("Error in query: ". $link->error.": $query");
            if (!$_GET['export'])
             {
                if ($_GET['asc']) $asc=1; else $asc=0;
                $url=get_hd().$_SERVER[HTTP_HOST].$_SERVER[REQUEST_URI];
                $old_url=$url;

                if ($point==1)
                  {
                    $url=preg_replace("/\&point=[\d\d]+/","&point=1",$url);
                  }

//echo "<hr>before: $url<hr>";
//                $url=preg_replace('/&point=1&asc=[\d]&order.+?=[\d]/','&point=1',$url);
                $url=preg_replace('/&top_videos=[\d]/','',$url);
                $url=preg_replace('/&top_images=[\d]/','',$url);
                $url=preg_replace('/&top_links=[\d]/','',$url);
                $url=preg_replace('/&shared=[\d]/','',$url);
                $url=preg_replace('/&retweeted=[\d]/','',$url);
                $url=preg_replace('/&image[2]?=.+?\&/','&',$url);
                $url=preg_replace('/&video=.+?\&/','&',$url);
                $url=preg_replace('/&link=.+?\&/','&',$url);
                $url=preg_replace('/\&response_to=[\d]+/','',$url);
	        $url=preg_replace('/\&\&/','&',$url);

//echo "<hr>after: $url<hr>";

                if ($asc) $oppasc="0"; else $oppasc="1";

                if ($_GET['image'] || $_GET['video'] || $_GET['link'] || $_GET['response_to'])
                  {
                    $heading="<a href='#' onclick=javascript:GetDetails('$url')>Back to the main list</a><br>";
                  }
                else
                  $heading="<br><center><div style='vertical-align: middle;display:inline-block;border-radius:5px;border:1px solid #0033cc;padding:5px; height:auto'><b>Show</b>: Top images (<a href='#' onclick=javascript:GetDetails('$url&top_images=1')>Shared</a> | <a href='#' onclick=javascript:GetDetails('$url&top_images=1&retweeted=1')>Retweeted</a>) - ".
                       "<b>Top videos (<a href='#' onclick=javascript:GetDetails('$url&top_videos=1')>Shared</a> | <a href='#' onclick=javascript:GetDetails('$url&top_videos=1&retweeted=1')>Retweeted</a>) - ".
                       "Top links (<a href='#' onclick=javascript:GetDetails('$url&top_links=1')>Shared</a> | <a href='#' onclick=javascript:GetDetails('$url&top_links=1&retweeted=1')>Retweeted</a>)</div></center>";

                if ($_GET['response_to']) { $url=$old_url; }
		$orig_url=$url;
		$url=preg_replace('/&asc=[\d]&order.+?=[\d]/','',$url);

                $heading=$heading."<table style='font-size:8pt; background-color:#FFFFFF; width: 950px; table-layout: fixed;'><tr><td width=25>#</td><td width=75><a href='#' onclick=javascript:GetDetails('$url&asc=$oppasc&order_d=1')>Date & Time (GMT)".arrowdir($oppasc,'d')."</a></td>".
                "<td width=120><a href='#' onclick=javascript:GetDetails('$url&asc=".$oppasc."&order_u=1')>Tweeter's details ".arrowdir($oppasc,'u')."</a></td><td width=150><a href='#' onclick=javascript:GetDetails('$url&asc=".$oppasc."&order_t=1')>".
                "Tweet text".arrowdir($oppasc,'t')."</a></td><td width=60><a href='#' onclick=javascript:GetDetails('$url&asc=".proper_order("min_retweets")."&order_r=1')>retweets".arrowdir($oppasc,'r')."</a></td>".
                "<td width=70><a href='#' onclick=javascript:GetDetails('$url&asc=$oppasc&order_f=1')>favorites".arrowdir($oppasc,'f')."</a></td><td width=70><a href='#' onclick=javascript:GetDetails('$url&asc=".proper_order("responded_tweets")."&order_rs=1')>".
                "Interaction<br>(if any)".arrowdir($oppasc,'rs')."</a></td><td width=$per_page><a href='#' onclick=javascript:GetDetails('$url&asc=".$oppasc."&order_s=1')>Source".arrowdir($oppasc,'s')."</a></td><td width=40><a href='#' onclick=javascript:GetDetails('$url&asc=".$oppasc."&order_l=1')>Lang".arrowdir($oppasc,'l')."</a>".
                "</td><td width=50><a href='#' onclick=javascript:GetDetails('$url&asc=".proper_order("user_verified")."&order_v=1')>Verified user".arrowdir($oppasc,'v')."</a></td><td style='min-width:200px'>".
                "<a href='#' onclick=javascript:GetDetails('$url&asc=".proper_order("image_retweets")."&order_i=1')>Image (if any)".arrowdir($oppasc,'i')."</a></td></tr>\n";
		$url=$orig_url;
             }
            else
             {
               header( 'Content-Type: text/csv' );
               header( 'Content-Disposition: attachment;filename=twitter_data.csv');
               $fp = fopen('php://output', 'w');

              if($row = $result->fetch_assoc())
                {
                 // fwrite($fp,'sep=,' . "\r\n");
                  fputcsv($fp, array_keys($row));
                  $result->data_seek(0);
                }
             }
            $cnt=0; $inserted=0; $dataset="";
            while ($row = $result->fetch_assoc())
            {
              if (!$_GET['export'])
               {
//                  if ($point!=1 || $_GET['any_hashtags'] || $_GET['from_accounts']) { 
if ($_GET['hashtag_cloud']) $hashtag_cloud=$hashtag_cloud." ".$row['hashtags']; 
//}
                  if ($cnt+1>=$p && $cnt<$pp)
                   {
		    $dataset=$dataset."data_set[$cnt]=[new Date(\"${row['date_time']}\"),${row['retweets']}];\n"."status[$cnt]=\"${row['tweet_id']}\";\n";
                    if ($row['responses']) {$dir_replies="<br>(with ".$row['responses']." direct replies)"; } else $dir_replies="";
                    $data=$data."<tr><td>".($cnt+1)."</td><td>${row['date_time']}</td><td>".
                    "<img src='${row['user_image_url']}'><a href='https://twitter.com/${row['user_screen_name']}' target=_blank onerror=\"this.style.display='none'\" width=50></a><br><a href='?id=tweets&table=$table&user_screen_name=${row['user_screen_name']}&load=1'>@${row['user_screen_name']}<br>${row['user_name']}</a><br><b>Followers:</b> ${row['user_followers']}<br><b>Following:</b> ${row['user_following']}<br><b>Created:</b> ".get_date($row['user_created'])."<br><b>Tweets:</b> ${row['user_tweets']}".profile_location($row['user_location'],$row['user_timezone'])."</td>".
                    "<td>".hyper_link($row['clear_text'])." - (<a href='${row['tweet_permalink_path']}' target=_blank>Link</a>)".location($row['location_name'],$row['location_fullname'])."</td>".
                    "<td><center>${row['retweets']}</center></td><td><center>${row['favorites']}</center></td><td><center>$response_str<br><a href=";
                    $inspect_link="javascript:GetDetails('$url&inspect=1&tweet_id=${row['tweet_id']}&tweet_permalink_path=".rawurlencode($row['tweet_permalink_path'])."&user_id=${row['user_id']}&user_screen_name=${row['user_screen_name']}&date_time=".rawurlencode($row['date_time'])."&user_image_url=".rawurlencode($row['user_image_url'])."','".rawurlencode(addslashes($row['user_name']))."','".rawurlencode(addslashes($row['clear_text']))."')";
                    $inspect_link=str_replace("%0A","%20",$inspect_link);
//echo "<hr>$inspect_link<hr>"; exit;
//echo "($inspect_link)";
                    $data=$data.$inspect_link."><img src='images/inspect.png' alt='see connected tweets'>$response_str$dir_replies</a></center></td><td><center>${row['source']}</center></td><td><center>".answer2($row['tweet_language'])."</center></td>".
                    "<td><center>".answer($row['user_verified'])."</center></td><td>".put_img($row['tweet_permalink_path'],$row['media_link'])."</td></tr>\n";
                    $inserted++;
                  if ($inserted==$limit-1) continue;
                   }
                  $cnt++;
//if ($_SESSION['email']==$admin_email && $row['retweets']>0) { echo "retweets: $total_retweets + ".$row['retweets']."<br>\n"; }
//               if ($retweets && $row['retweets']) $total_retweets=$total_retweets+$row['retweets'];
              }
            else
              {
                fputcsv($fp, $row);
              }
            }
if ($retweets)
	{
	   $qry="SELECT sum(retweets) from $table $condition AND is_protected_or_deleted is null and date_time is not null";
	   if ($result = $link->query($qry))
	     { $row=$result->fetch_array(); $total_retweets=$row[0]; }
if ($debug && $_SESSION['email']==$admin_email) { echo "qry:$qry - retweets: $total_retweets <br>\n"; }
	}

//die("HERE");
          if (!$_GET['export'])
            {
              $data=$data."</table><hr>";
              if ($total_rows<$pp)
                    {
                  $results="<ul><li>Case id: <b>$table</b></li><li> Title: <b>".$cases[$table]['name']."</b></li><li>Search query: <b>".$cases[$table]['query']."</b></li>";
		  if ($cases[$table]['from']!='0000-00-00' && $cases[$table]['to']!='0000-00-00') $results=$results."<li>Period: from (<b>".$cases[$table]['from']."</b>) to (<b>".$cases[$table]['to']."</b>)</li>";
                  if ($cases[$table]['details']) $results.="<li>Details:<b> ".$cases[$table]['details']."</b></li>";
                      $results.="</ul><br>$heading";
                      $url=preg_replace('/\&p=[\d]+\&pp=[\d]+/','',$url);
                      $results= $results."<br><p align=right><a href='$url&export=1')><b>Export</b></a> all $total_rows records to CSV file</p>";
                      $results=$results."<b>Showing results $p - $total_rows of $total_rows";
                      if ($retweets) $results=$results." [".($total_retweets+$total_rows)." with retweets] ";
			
                      if ($p>$per_page) 
			{
			 if (!(($p-$per_page==1) && ($pp-$per_page==$per_page))) $ppstr="&p=".($p-$per_page)."&pp=".($pp-$per_page); else $ppstr="";
			 $results=$results."<center> <a href='#' onclick=javascript:GetDetails('$url&$ppstr')> &lt;&lt; </a> &nbsp;&nbsp; <a href='#' onclick=javascript:GetDetails('$url')>><small>Back to first page</small></a> </center><br>";
			}
                      $data=$results.$data;
                    }
              else
                  {
                  $results="<ul><li>Case id: <b>$table</b></li><li> Title: <b>".$cases[$table]['name']."</b></li><li>Search query: <b>".$cases[$table]['query']."</b></li>";
                  if ($cases[$table]['from']!='0000-00-00' && $cases[$table]['to']!='0000-00-00') $results=$results."<li>Period: from (<b>".$cases[$table]['from']."</b>) to (<b>".$cases[$table]['to']."</b>)</li>";
                  if ($cases[$table]['details']) $results.="<li>Details:<b> ".$cases[$table]['details']."</b></li>";
                      $results.="</ul><br>$heading";
                    $url=preg_replace('/\&p=[\d]+\&pp=[\d]+/','',$url);
                    $results= $results."<br><p align=right><a href='$url&export=1')><b>Export</b></a> all $total_rows records to CSV file</p>";
                    $results=$results."<b>Showing results $p - $pp of $total_rows ";
                    if ($retweets) $results=$results." [".($total_retweets+$total_rows)." with retweets] ";
                    if (!(($p-$per_page==1) && ($pp-$per_page==$per_page))) $ppstr="&p=".($p-$per_page)."&pp=".($pp-$per_page); else $ppstr="";
                    if ($p>$per_page) $results=$results."<center> <a href='#' onclick=javascript:GetDetails('$url$ppstr')> &lt; </a>  &nbsp;&nbsp; <a href='#' onclick=javascript:GetDetails('$url')><small>Back to first page</small></a> ";
                    $results=$results."	&nbsp;&nbsp; <a href='#' onclick=javascript:GetDetails('$url&p=".($p+$per_page)."&pp=".($pp+$per_page)."')> 	&gt; </a>	 </center><br>";
                    $data=$results.$data;
                }

              if ($_GET['hashtag_cloud'])
                {
		   if ($point==1 && !$_GET['any_hashtags'] && !$_GET['from_accounts'] && $_GET['types']!="some"
				 && !$_GET['language'] && !$_GET['sources'] && !$_GET['startdate'] && !$_GET['enddate']) 
		     { $hashtag_cloud=get_cloud($table); }
		   else
		     {
   	                $cloud = new PTagCloud(50);
        	        $cloud->addTagsFromText($hashtag_cloud);
              	        $cloud->setWidth("900px");
			$hashtag_cloud=$cloud->emitCloud();
                     }
                   $part1_data=$part1_data."<br><b>Hashtag cloud:</b> <center>$hashtag_cloud</center><br>";
                }
              $part1_data=$part1_data.$data;
            }
        else
        {
          fclose($fp);
        }
   }
    $link->close();
    $slide_file=file_get_contents("templates/slideshow.html");
    $slide_file=str_replace("<!--case-->",$cases[$table]['name'],$slide_file);
    $slide_file=str_replace("<!--title-->"," Search query: <b>".$cases[$table]['query']."</b>",$slide_file);
    $slide_file=str_replace("<!--dataset-->",$dataset,$slide_file);
    file_put_contents("cache/$table$hashkey2-slides.html",$slide_file);
    if (file_exists("cache/$table$hashkey2-slides.html"))
       {
                                echo "<center><a href=\"cache/$table$hashkey2-slides.html\" target=_blank><img src=\"images/slideshow.png\" width=100> Interactive slides interface (under development)</a></center><br>";
       }
    echo $part1_data;
    file_put_contents("cache/$table$hashkey2.tab",$part1_data);
}

function get_cloud($table)
 {  global $link;
            $query= "SELECT hashtag_cloud from cases where id='$table'";
//echo $query;
            if ($result = $link->query($query)) 
              {
		$row = $result->fetch_array();
		return ($row[0]);	
	      }
//else die("Error in query: ". $link->error.": $query");
	return "no hashtags";
 }
function arrowdir($oppasc,$col)
 {
	if ($_GET['order_'.$col])
	  {
		if ($oppasc) return " <img src='images/arrow_down.png'>";
		else return " <img src='images/arrow_up.png'>";
	  }
	return "";
 }
function image_exists($img,$url)
  {
    if (!$img) return "";
    $images=explode(" ",$url);
    $url=$images[0];
    if ($url) return "<img src='$url'>";
    return "";
  }
function proper_order($mode)
  { global $oppasc; global $_GET;
    if ($_GET[$mode]) return $oppasc;
    return 0;
  }
function put_img($link,$img)
  {
    if ($img) 
	{
	  $images="";
	  $img_list=explode(' ',$img);
	  foreach ($img_list as $im) { $images=$images."<img src='$im' width=200><br> <br>"; }
	  return "<center><a href='$link' target=_blank>$images</center>";
	}
    return "";
  }
function hashtags($hashtag,$table,$mode)
  { global $_GET;
    if ($hashtag || $_GET['export'])
      {
        if ($mode==1)
          {
            return ",$table.hashtags ";
          }
      }
    return "";
  }
function location($l1,$l2)
  {
    $l="";
    if ($l1) $l=" - <b><i>[Tweeted from $l1]</b></i>";
    if ($l2) $l="$l, $l2";
    return $l;
  }
function profile_location($l1,$l2)
  {
    $l="";
    if ($l1) $l="<br><b>Based in:</b> $l1";
    if ($l2) $l="$l<br><b>Timezone:</b> $l2";
    return $l;
  }

function opp($v)
  {
//    if ($v==0) return 1;
    return 1;
  }
function get_date($d)
{
  $date = new DateTime($d);
  return $date->format('Y-m-d');
}
function hyper_link($s)
{
  return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $s);
}
function answer($verified) { if ($verified) return "Yes"; return "No"; }
function answer2($location) { if ($location=="NULL") return ""; return $location; }
function get_hd()
{
	if ($_SERVER['HTTPS']) return "https://";
	return $_SERVER['REQUEST_SCHEME']."://";
}
?>


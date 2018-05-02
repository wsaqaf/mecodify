<?php
//draw_network($table);
//exit;
//error_reporting(E_ALL);
error_reporting(E_ERROR);

ini_set('max_execution_time', 3000);
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');

require_once("configurations.php");
//print_r($cases);

require_once("cloud.php");

//example: egypt 1 2011-09-25ß

if (!$argv[1]) die("Missing case...\n\n");

$table=$argv[1];
$from=$argv[2];
$to=$argv[3];
$top_only=$argv[4];
$starting_point=$argv[5];
$step1=1; $step2=1; $step3=1; $step4=1;
if ($from=="step1") { $step1=1; $step2=0; $step3=0; $step4=0; $from=''; $to=''; }
if ($from=="step2") { $step1=0; $step2=1; $step3=0; $step4=0; $from=''; $to=''; }
if ($from=="step3") { $step1=0; $step2=0; $step3=1; $step4=0; $from=''; $to=''; }
if ($from=="step4") { $step1=0; $step2=0; $step3=0; $step4=1; $from=''; $to=''; }
$top_limit=array(1000,5000,10000);

$verbose=1;
$overwrite=0;
$use_api=0;
$global_step=0;
$last_tweet_id="";
$first_tweet_id="";
$max_list=1000000;
$max_per_page=1000;
$global_step_limit=100;
$start_from=1;
$track_stream=1;
$list_count=1;
$started=false;
$step=0;
$failed_proxy=0;
$hash_cloud="";
//note("\nhi!\n");

if (!$cases[$table]['query']) die("Could not find case ($table) in DB or it is inaccessible to you.");

update_cases_table("started");

if ($step1)
  {
    $keywords=urlencode($cases[$table]['query']);
    $mode="INSERT IGNORE"; if ($overwrite) $mode="REPLACE";

//$type=""; //top tweets
    if ($mode=="log") $log=1; else $log=0;
    if ($starting_point=="purge") $resume=0; else $resume=1;
    $log=0;

    echo "to: $to , real to:".$cases[$table]['to']."\n";
    echo "from: $from , real from:".$cases[$table]['from']."\n";

    $dates=array();
    $c=0;

    if ($cases[$table]['to']=='0000-00-00')
     {
	$datetime = new DateTime('tomorrow');
        $cases[$table]['to']=$datetime->format('Y-m-d');
     }
    if ($cases[$table]['from']=='0000-00-00')
     {
      $cases[$table]['from']='2006-03-21'; //since twitter created
     }
    if (!$argv[4]) $top_only=$cases[$table]['top_only'];

    if (!$top_only)
     {
      $wait_one_more=0;
      $type="f=tweets&"; //all tweets
     }
    else
     {
      $type=""; //top tweets only
      $wait_one_more=1;
     }
//      $d = new DateTime($cases[$table]['to'], new DateTimeZone('UTC'));
      $d = new DateTime($cases[$table]['from'], new DateTimeZone('UTC'));
//      $d->modify("-1 day");
      array_push($dates,$d->format('Y-m-d'));
      $total_days=0;
      while (1)
        {
          $total_days++;
          $d->modify("+1 day");
//          $d->modify("-1 day");
          $new_date=$d->format('Y-m-d');
          array_push($dates,$new_date);
          if ($new_date==$cases[$table]['to']) break;
//          if ($new_date==$cases[$table]['from']) break;
        }

       if (!file_exists("tmp/log/$table"))
            {
                mkdir("tmp/log/$table");
        if (!file_exists("tmp/log/$table"))
           die("Fatal error: Failed to create dir tmp/log/$table");
            }
        for ($k1=$total_days-1; $k1>=0; $k1--)
         {
           $from=$dates[$k1];
           $to=$dates[$k1+1];

            $log_file="tmp/log/$table/$table"."_".$from."_to_".$to.".txt";

            $i=1;

            if ($resume && file_exists($log_file))
             {
            //  del_last_line($log_file,"");
              $tmp=get_last_line($log_file);
              if ($tmp=="no more tweets")
                {
                  echo "Search finished already. To restart, delete ($log_file)\n";
                  continue;
                }
              $tmp2=explode(" ",$tmp);
              if (is_numeric($tmp2[0]) && is_numeric($tmp2[1]) && is_numeric($tmp2[2]))
              {
                $start_from=$tmp2[0];
                $last_tweet_id=$tmp2[1];
                $first_tweet_id=$tmp2[2];
                $i=$start_from; // if more than 1, must also set the next two variables
                del_last_line($log_file,$tmp2[1]." ".$tmp2[2]);
                echo "found records ($last_tweet_id $first_tweet_id) in log, resuming from $start_from\n";
              }
              //else { echo "Starting a new file!\n"; unlink($log_file); }
            //  die("corrupt file ($log_file).\n");
            }
            get_tweet_ids($type, $table,$keywords,$from,$to);
          }
   }
if ($step2) 
	{
	  $retries=0;
	  get_other_fields($table);
          if ($step1)
 	   {
$query= "SELECT hashtags FROM $table where hashtags is not null";
    if ($result = $link->query($query))
        {
          if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
          $total=$result->num_rows;
        }
    else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; exit; }
while ($row=$result->fetch_assoc())
  {
     $hash_cloud=$hash_cloud." ".$row['hashtags'];
  }
            file_put_contents("tmp/cache/$table-hashcloud.html","<html><meta http-equiv='content-type' content='text/html; charset=utf-8' />\n$hash_cloud</html>");
    	    $cloud = new PTagCloud(100);
    	    $cloud->addTagsFromText($hash_cloud);
    	    $cloud->setWidth("900px");
    	    $temp=$link->real_escape_string($cloud->emitCloud());
    	    $query= "UPDATE cases SET hashtag_cloud='$temp' where id='$table'";
    	    if ($result = $link->query($query)) echo "updated $table\n";
    	    else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; exit; }
	   }
	  tweeter_data($table);
	}

if ($step4)
        {
          $query= "SELECT hashtags FROM $table where hashtags is not null";
          if ($result = $link->query($query))
           {
            if (!$result->num_rows) { echo "No hashtags in the database matched your query.<br>\n";  }
            $total=$result->num_rows;
           }
          else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; exit; }
          while ($row=$result->fetch_assoc())
            {
              $hash_cloud=$hash_cloud." ".$row['hashtags'];
            }
          file_put_contents("tmp/cache/$table-hashcloud.html","<html><meta http-equiv='content-type' content='text/html; charset=utf-8' />\n$hash_cloud</html>");
          $cloud = new PTagCloud(100);
          $cloud->addTagsFromText($hash_cloud);
          $cloud->setWidth("900px");
          $temp=$link->real_escape_string($cloud->emitCloud());
          $query= "UPDATE cases SET hashtag_cloud='$temp' where id='$table'";
          if ($result = $link->query($query)) echo "updated $table\n";
          else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; exit; }
          tweeter_data($table);
          exit();
        }

$link->close();

function get_tweet_ids($type, $table,$keywords,$from,$to)
  {
    global $link; global $mode; global $mysql_db; global $i; global $step2; global $wait_one_more;
    global $global_step;  global $last_tweet_id; global $first_tweet_id; global $max_list;
    global $list_count; global $start_from; global $added; global $skipped; global $top_only;
    global $global_step_limit; global $max_per_page; global $first_page; global $total_added;

    echo "$table, keywords: ($keywords) from: $from to: $to\n";

    $separator='<div class="ProfileTweet-actionList js-actions" role="group" aria-label=';

    $specific_period="%20since%3A".$from."%20until%3A".$to;

    while ($list_count<=$max_list)
     {
        if ($list_count==1 && $start_from==1)
        {
          $url="https://twitter.com/search?q=".$keywords.$specific_period."%20include%3Aretweets&src=typd&".$type."vertical=default";
 echo "Starting new search: $url \n";
          $html=url_get_contents($url);
          $html=preg_replace('/[[:blank:]]+/',' ',$html);
          $html=preg_replace('/\t+/',"\t",$html);
          $html=preg_replace('/\n+/',"\n",$html);
//echo "\n---$html---\n";
//          if (preg_match("/data-max-position=[^\"<>]+?-([\d+])-([\d+])\"/si",$html,$t))
 	    if (preg_match("/data-max-position=\"TWEET-(\d+)-(\d+)\"/",$html,$t))
		{ $last_tweet_id=$t[1]; $first_tweet_id=$t[2]; echo "l_t:".$t[1]." f_t:".$t[2]."\n\n"; } //echo "\n\n--\n\n max:$max_position\n\n---\n\n"; }
          else { echo "problem"; }
//exit;
	  $first_page=1;
        }
        elseif ($last_tweet_id && $first_tweet_id)
        {
	   $url="https://twitter.com/i/search/timeline?$type"."vertical=default&q=".$keywords.$specific_period."%20include%3Aretweets&src=typd&include_available_features=1&include_entities=1&max_position=TWEET-".$last_tweet_id."-".$first_tweet_id."-BD1UO2FFu9QAAAAAAAAETAAAAAcAAAASAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA&reset_error_state=false"; 
$first_page=0;
echo "$url\n---\n";
          note("$i $last_tweet_id $first_tweet_id\n");
          $json=url_get_contents($url);
          $html1=json_decode($json);
          $html=stripslashes($html1->items_html);

          if (strpos($json,"\"has_more_items\""))
            {
	      if (strlen(trim($html))<100)
		{
                  note("no more tweets\n");
                  $list_count=1; $start_from=1; //comment this line to use the JSON rather than html method for fetching new pages.
                  return;
		}
            }
//echo "\n---$html---\n"; exit;
          if (strpos($json,"\"has_more_items\"")===false)
            {
                note("Empty returned value. Network error possible... Retrying!");
                $json=url_get_contents($url);
                $html1=json_decode($json);
                $html=stripslashes($html1->items_html);
                if (strpos($json,"\"has_more_items\":false"))
                 {
                  note("no more tweets\n");
                  $list_count=1; $start_from=1; //comment this line to use the JSON rather than html method for fetching new pages.
                  return;
                 }
                if (strpos($json,"\"has_more_items\"")===false)
                    {
                         note("Problem persists. Aborting!");
                         echo "Network problem while fetching records!";
                         exit;
                    }
            }

          $html=preg_replace('/[[:blank:]]+/',' ',$html);
          $html=preg_replace('/\t+/',"\t",$html);
          $html=preg_replace('/\n+/',"\n",$html);
    //      echo($html); exit;
        }
       else { 
echo "no tweet ids\n"; 
$list_count=1; $start_from=1; 
return; }
    //    sleep(0.5);
        $tweet=array();
        $added=0; $skipped=0;

        while (true)
        {
    //file_put_contents("temp.htm",$html);
            $pos_step=strpos($html,$separator);
//    if ($table=="Kas14") { echo "\n$html\n($pos_step)"; exit; }
            $block=substr($html,0,$pos_step);
            $html=substr($html,$pos_step+10);
            $tweet=array();
//echo "\n---\n$block\n---\n";
//exit;
//            if (!preg_match("/retweet-id=\"[^\"<>]+?\/([\d]+)\"/",$block,$t)) { preg_match("/-tweet-id=\"[^\"<>]+?\/([\d]+)\"/",$block,$t); }
//	    else echo "\n\n retweet:".$t[1]."\n\n";
            if (preg_match("/data-permalink-path=\"[^\"<>]+?\/([\d]+)\"/",$block,$t))
              {
                $tweet['tweet_id']=$t[1];
                echo "i:".$i." t_id: ".$t[1]." ";
                if (!$started)
                  {
                    if (!$wait_one_more) { if (!$first_page) { $first_tweet_id=$t[1]; } $started=true; }
//                    die("w:$wait_one_more, $first_tweet_id");
                    $wait_one_more=0;
                  }
if (!$first_page) 
   $last_tweet_id=$t[1];
//		$list_count++;
              }
            else
            {
	      $total_added=$total_added+$added;
              echo "done $table (added: $added, skipped: $skipped, total_added:$total_added)\n";
              $list_count++; break; //return;
            }
            if ($verbose==2) echo "tweet-id:".$tweet['tweet_id']."\n";
            put_in_database($tweet);
            echo " g_step: $global_step ";
            if ($step2 && $total_added)
              {
                if ($global_step>=$global_step_limit)
                  {
                echo "\n---\n Getting other fields \n---\n";
                   $retries=0; 
		   get_other_fields($table);
		echo "\n---\n Continuing \n---\n";
                    $global_step=0;
                  }
                $global_step++;
              }
            $i++;
            if ($i>$max_per_page && $top_only)
              {
                note("no more tweets\n");
                $list_count=1;
		echo "reached limit per page (for top_only)\n";
		return;
              }
        }
    }
 }

function get_other_fields($table)
  {
    global $link; global $mysql_db; global $j; global $twitter_api_settings; global $last_setting; global $retries;
//    if ($argv[2]) $last_setting=0; else
    $last_setting=rand(0,sizeof($twitter_api_settings)-1);;
//    if ($argv[2]=="fix") $fix_utf8=1;

    $regex = '$\b(https?|ftp|file)://[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|]$i';

    if (!$table)
    {
      echo "No country or case number provided...\n";
      exit;
    }

    $list_count=1;
    $started=false;

    if ($users_only)
      $query="SELECT tweet_id AS num_rows FROM `$mysql_db`.`$table` WHERE `is_protected_or_deleted` IS NULL group by user_id ";
    else
      $query="SELECT tweet_id AS num_rows FROM `$mysql_db`.`$table` WHERE `is_protected_or_deleted` IS NULL ";
    if ($result = $link->query($query)) $total=$result->num_rows;
    else die("Error in query: ". $link->error.": $query\n");
    
$query="SELECT tweet_id AS num_rows FROM `$mysql_db`.`$table` WHERE `is_protected_or_deleted` IS NULL and (`$mysql_db`.`$table`.date_time is null OR `$mysql_db`.`$table`.raw_text='' OR `$mysql_db`.`$table`.`user_id` not in (SELECT `user_id` FROM `$mysql_db`.`users_"."$table` WHERE is_deleted is null and is_suspended is null)) ";
    //if ($fix_utf8) { echo "$query\n"; exit; }

    if ($result = $link->query($query))
      {
        if (!$result->num_rows)
          {
            echo "No data found in DB\n";
            update_response_mentions();
            return;
          }
        $start_from=$total-$result->num_rows;
        $total_to_do=$result->num_rows;
      }
    else die("Error in query: ". $link->error." : $query\n");
    //echo "$query\n\n";
//echo var_dump($result); exit;
    echo "Continuing from ".($start_from)." out of $total for $table\n";
    $last_rec=0; $new_rec=0; //$tweet_updated_rows=0; $user_updated_rows=0;
    $j=0;
    $jj=0;
    $records="";
    $total_records=0;
    while (true)
    {
      $new_rec++;
     $row = $result->fetch_array();
//  print_r($row); //exit;
      if ($row)
        {
              if ($j==1) $records=$row[0];
              else $records="$records,".$row[0];
	      $j++;
	      $total_records++;
        }
      if (!$row || $j==100)
      {
    $postfields= array('id' => $records, 'include_entities'=> true, 'map' => 1, 'tweet_mode'=>'extended');
//  print_r($postfields); echo "($i:".sizeof(explode(",",$records)).")\n"; //exit;
$records="";
//echo "\n1\n";
          $response=getapi_record($postfields);
//echo "\n2\n";
          $record=json_decode($response);
          $r=get_object_vars($record->id);
          $keys=array_keys($r);
          foreach($keys as $k)
            {
              if (!$r[$k])
               {
                 $result2 = $link->query("UPDATE `$table` SET \n`is_protected_or_deleted`='1' where tweet_id='$k'");
                 if (!$result2) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
                 echo "$k: protected or deleted tweet, marked in DB & skipped\n";
               }
              else
                {
                  echo "$k\n"; //continue;
                  extract_and_store_data($r[$k]);
		 if ($total_records==10000) { tweeter_data($table); $total_records=0; }
               } 
            }

          echo "Processed (".($last_rec+1)." - ".($new_rec) ." out of $total_to_do in $table)\n";// for ($tweet_updated_rows tweets, $user_updated_rows users updated)\n";
          $last_rec=$new_rec;
          if ($j==100) $j=0;
          if ($last_rec>=$total_to_do) 
	    { 
	     if ($result = $link->query("SELECT tweet_id AS num_rows FROM `$table` WHERE `is_protected_or_deleted` IS NULL")) $st1=$result->num_rows;
             if ($result = $link->query("SELECT date_time AS num_rows FROM `$table` WHERE `date_time` IS NOT NULL")) $st2=$result->num_rows;
	     if ($st1>$st2)
		{
		  if ($retries<10) { echo "Getting missing records (received just $st2 from $st1)\n"; get_other_fields($table); $retries++; }
		  else { echo "failed to get all records after 10 attempts\n"; break; }
		}
             echo "finished!\n"; $retries=0; break;
	   }
       }
    }
  }

function tweeter_data($table)
 {
    update_response_mentions();
    clean_data($table);
    draw_network($table);
 }


function clean_data($table)
{
  $started=false; global $k; global $link;

  $query="SELECT user_screen_name FROM `users_"."$table` WHERE is_deleted is null and is_suspended is null and user_name is null and restricted_to_public is null and user_screen_name in (select user_screen_name from $table where is_protected_or_deleted=1)";
  //echo "\n$query\n"; exit;
  if ($result = $link->query($query))
    {
      if (!$result->num_rows) return "No data found in DB\n";
      $total=$result->num_rows;
    }
  if ($total<100) $max_list=$total; else $max_list=100;

  echo "Total of $total records to process for $table\n";
  //exit;
  $last_rec=0; $new_rec=0; //$tweet_updated_rows=0; $user_updated_rows=0;
  $k=0;

  while (true)
  {
    $k++;
    $new_rec++;
    $row = $result->fetch_assoc();
  // print_r($row); exit;
    if ($row)
      {
        foreach($row as $screen_name)
          {
            if ($k==1) $records=$screen_name;
            else $records="$records,".$screen_name;
          }
      }
  // else { echo "No more records in DB\n"; exit; }

  //echo $records; continue;
   if (!$row || $k==$max_list)
    {
  //echo "\n$i: $records\n"; exit;
      $postfields= array('screen_name' => $records);
      $response=getapi_record2($postfields);
      if (!$response)
        {
          echo "No more records...\n";
          return;
        }
  //echo "\n($response)\n"; exit;
     else
       {
        $record=json_decode($response);
    //print_r($record); exit;
    //    $records=$record->screen_name;
    //    print_r($records); exit;
    //    $r=get_object_vars($records);
    //    $keys=array_keys($r);
    //print_r($r); exit;
    //  foreach($records as $tweet)
        foreach($record as $usr)
          {
    //        print_r($usr); exit;
            echo "got in, changing record: ".$usr->screen_name.", ".$usr->name."\n";
            if (!$usr->name)
             {
    //echo "got in, changing record: ".$usr->screen_name."\n";
               $result2 = $link->query("UPDATE `users_"."$table` SET \n`restricted_to_public`='1' where user_screen_name='".$usr->screen_name."'");
               if (!$result2) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
               echo $usr->screen_name.": protected or deleted in DB & skipped\n";
             }
            else
              {
                extract_and_store_data2($usr);
              }
          }
        }
  //exit;

      echo "Processed (".($last_rec+1)."-$new_rec)!\n";// for ($tweet_updated_rows tweets, $user_updated_rows users updated)\n";
  //    sleep(5);
      $last_rec=$new_rec;
      $k=0;
     }
  }
}

function extract_and_store_data($tweet)
    {
        global $table; global $regex; global $cases; global $link; global $hash_cloud;
        global $from; global $to;
    //  if ($tweet->id=="26927240864") {
    //    print_r($tweet); exit; //}
        $tw=array();
        $user=array();
        if (preg_match("/([A-Za-z]+) ([\d]+) ([\d]+\:[\d]+\:[\d]+) \+0000 ([\d]+)/si",$tweet->created_at,$d))
            {
              $datetime=$d[2]." ".$d[1]." ".$d[4]." ".$d[3];
              $tw['date_time']=date('Y-m-d H:i:s',strtotime($datetime));
            }
        $date=date('Y-m-d',strtotime($datetime));

/*
        if ($to!=$date)
          {
echo "(fr:$from, to:$to, dt:$date)";
            $result2 = $link->query("DELETE FROM `$table` WHERE tweet_id='".$tweet->id."'");
            if (!$result2) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
            echo $tweet->id." : Deleted tweet because it is out of date range\n";
            return;
          }
*/
        $tw['tweet_id']=$tweet->id;
        $tw['raw_text']=$tweet->full_text;
        $tw['clear_text']=strip_tags($tweet->full_text);
    //    $tmp=file_get_contents("https://api.twitter.com/1.1/statuses/oembed.json?id=".$tw['tweet_id']);
    //    $tmp2=json_decode($tmp);
    //    $tw['embeddable_text']=$tmp2->full_text;
        $tw['full_source']=$tweet->source;
        if ($tweet->truncated) $tw['is_truncated']=1; else $tw['is_truncated']=0;
        if ($tweet->in_reply_to_status_id) $tw['in_reply_to_tweet']=$tweet->in_reply_to_status_id;
        if ($tweet->in_reply_to_user_id) $tw['in_reply_to_user']=$tweet->in_reply_to_user_id;
        $u=$tweet->user;
        $tw['user_id']=$u->id;
        $tw['tweet_permalink_path']="https://twitter.com/".$u->screen_name."/status/".$tweet->id;
        /******user data******/
        if ($u->id) $user['user_id']=$u->id;
        if ($u->screen_name) $user['user_screen_name']=ltrim($u->screen_name,'@');
        $tw['user_screen_name']=$user['user_screen_name'];
        if ($u->name) $user['user_name']=$u->name;
        if ($u->location) $user['user_location']=$u->location;
        $user['user_followers']=0;
        $user['user_following']=0;
        $user['user_friends']=0;
        $user['user_lists']=0;
        $user['user_lists']=0;
        $user['user_tweets']=0;
        if ($u->followers_count>0) $user['user_followers']=$u->followers_count;
        if ($u->following_count>0) $user['user_following']=$u->following_count;
        if ($u->friends_count>0) $user['user_following']=$u->friends_count;
        if ($u->protected) $user['user_protected']=$u->protected;
        else  $user['user_protected']=0;
        if ($u->listed_count>0) $user['user_lists']=$u->listed_count;
        if (preg_match("/([A-Za-z]+) ([\d]+) ([\d]+\:[\d]+\:[\d]+) \+0000 ([\d]+)/si",$u->created_at,$cr))
          {
            $datetime=$cr[2]." ".$cr[1]." ".$cr[4]." ".$cr[3];
            $user['user_created']=date('Y-m-d H:i:s',strtotime($datetime));
          }
        if ($u->favourites_count===0 || $u->favourites_count) $user['user_favorites']=$u->favourites_count;
        if ($u->utc_offset) $user['user_utc_offset']=$u->utc_offset;
        if ($u->time_zone) $user['user_timezone']=$u->time_zone;
        if ($u->geo_enabled) $user['user_geo_enabled']=1; else $user['user_geo_enabled']=0;
        if ($u->verified) $user['user_verified']=$u->verified; else $user['user_verified']=0;
        $tw['user_verified']=$user['user_verified'];
        if ($u->statuses_count>0) $user['user_tweets']=$u->statuses_count;
        if ($u->lang) $user['user_lang']=$u->lang;
        if ($u->description) $user['user_bio']=$u->description;
        if ($u->profile_image_url_https) $user['user_image_url']=str_replace("_normal.jpg","_400x400.jpg",$u->profile_image_url_https);
        if ($u->entities)
         {
           $en=$u->entities;
           if ($en->url)
            {
              $urls=$en->url;
              if (sizeof($urls)>0)
              {
                foreach($urls as $url1)
                 {
                   foreach ($url1 as $url2)
                     {
                      $user['user_url']=$user['user_url']." ".$url2->expanded_url;
                     }
                 }
                $user['user_url']=trim($user['user_url']);
              }
            }
         }
        if (!$user['user_url'] && $u->url) { $user['user_url']=$u->url; }
        if ($u->withheld_in_countries) $user['user_withheld_in_countries']=$u->withheld_in_countries;
        if ($u->withheld_scope) $user['user_withheld_scope']=$u->withheld_scope;

        /******resume twitter data******/
        if (preg_match("/([\-\.\d]+)\,\s*([\-\.\d]+)/si","{$tweet->coordinates->coordinates}",$c))
             {  $tw['coordinates_long']=$c[1]; $tweet['coordinates_lat']=$c[2]; }
        if ($tweet->contributors) $tw['contributors']=$tweet->contributors;
        if ($tweet->quoted_status_id) $tw['quoted_tweet_id']=$tweet->quoted_status_id;
        if ($tweet->retweet_count===0 || $tweet->retweet_count) $tw['retweets']=$tweet->retweet_count;
        if ($tweet->favorite_count===0 || $tweet->favorite_count) $tw['favorites']=$tweet->favorite_count;
        if ($tweet->place)
         {
           $p=$tweet->place;
           if ($p->country) $tw['country']=$p->country;
           if ($p->fullname) $tw['location_fullname']=$p->full_name;
           if ($p->name) $tw['location_name']=$p->name;
           if ($p->place_type) $tw['location_type']=$p->place_type;
         }
if ($tweet->entities)
 {
   $en=$tweet->entities;
    if (sizeof($en->hashtags)>0)
       {
         foreach($en->hashtags as $h)
            $tw['hashtags']=$tw['hashtags']." #".strtolower($h->text);
         $tw['hashtags']=trim($tw['hashtags']);
         $hash_cloud=$hash_cloud." ".$tw['hashtags'];
       }
    if (sizeof($en->user_mentions)>0)
      {
        foreach($en->user_mentions as $men)
            $tw['user_mentions']=$tw['user_mentions']." @".strtolower($men->screen_name);
        $tw['user_mentions']=trim($tw['user_mentions']);
      }
    if (sizeof($en->urls)>0)
      {
        $tw['has_link']=1;
        foreach($en->urls as $ur)
          {
            if (startswith($ur->expanded_url,"http://youtube.com/") || startswith($ur->expanded_url,"https://youtube.com/")
              ||startswith($ur->expanded_url,"http://youtu.be/") || startswith($ur->expanded_url,"https://youtu.be/")) $tw['has_video']=1;
            $tw['links']=$tw['links']." ".$ur->url;
            if (strpos($tw['expanded_links'],$ur->expanded_url)===false) $tw['expanded_links']=$tw['expanded_links']." ".$ur->expanded_url;
          }
          $tw['links']=trim($tw['links']);
          $tw['expanded_links']=trim($tw['expanded_links']);
        }
      if ($en->media)
      {
//echo "media!\n"; sleep(3);
        foreach($en->media as $m)
          {
//            echo "started\n"; print_r($m); sleep(3);
            if ($m->type=="photo")
                {
//echo "photo!\n"; sleep(3);
                  $tw['has_image']=1;
                   if (strpos($tw['media_link'],$m->media_url_https)===false) $tw['media_link']=$tw['media_link']." ".$m->media_url_https;
                }
            else $tw['has_image']=0;
            if ($m->type=="video")
                {
//echo "video!\n"; sleep(3);
                  $tw['has_video']=1;
                  if (strpos($tw['media_link'],$m->media_url_https)===false) $tw['media_link']=$tw['media_link']." ".$m->media_url_https;
                }
            else $tw['has_video']=0;
          }
      }
     $tw['media_link']=trim($tw['media_link']);
   }
//  echo "h_img:".$tw['has_image']."\n"; sleep(3);
if ($tweet->extended_entities)
{
//echo "extended!"; print_r($tweet->extended_entities); sleep(3);
  foreach($tweet->extended_entities as $ex1)
    {
      foreach($ex1 as $ex)
        {
//                print_r($ex); exit;
          if ($ex->type=="photo" || $ex->type=="multi photo" ||$ex->type=="animated gif")
            {
              $tw['has_image']=1;
              if (strpos($tw['media_link'],$ex->media_url_https)===false) $tw['media_link']=$tw['media_link']." ".$ex->media_url_https;
            }
          else $tw['has_image']=0;
          if ($ex->type=="video")
              {
                $tw['has_video']=1;
                if (strpos($tw['media_link'],$ex->media_url_https)===false) $tw['media_link']=$tw['media_link']." ".$ex->media_url_https;
              }
          else $tw['has_video']=0;

       }
   }
  $tw['media_link']=trim($tw['media_link']);

}
        if (!$tw['has_image'] && !$tw['has_video'] && !$tw['has_link'])
          {
            preg_match_all($regex, $tw['raw_text'], $result, PREG_PATTERN_ORDER);
            $A = $result[0];
            foreach($A as $B)
            {
    /*
    //          echo "Checking ($B)\n";
               $URL = GetRealURL($B);
               if (!$URL) continue;
    echo "Got ($URL)\n"; //exit;
    */
               $elnt=explode(' ',$URL);

              $u_t=url_type($elnt[1]);
               if ($u_t== "image")
                {
                    $tw['has_image']=1;
                    $tw['media_link']=$tw['media_link']." ".$elnt[0];
                }
               elseif ($u_t== "video")
                 {
                     $tw['has_video']=1;
                     $tw['media_link']=$tw['media_link']." ".$elnt[0];
                 }
               elseif ($u_t=="link" && $elnt[0])
                {
                    $tw['has_link']=1;
        	    if (startswith($ur->expanded_url,"http://youtube.com/") || startswith($ur->expanded_url,"https://youtube.com/")
	              ||startswith($ur->expanded_url,"http://youtu.be/") || startswith($ur->expanded_url,"https://youtu.be/")) $tw['has_video']=1;
                    $tw['expanded_links']=$tw['expanded_links']." ".$elnt[0];
                }
            }
            $tw['media_link']=trim($tw['media_link']);
            $tw['expanded_links']=trim($tw['expanded_links']);
          }
        if ($tweet->filter_level) $tw['filter_level']=$tweet->filter_level;
        if ($tweet->retweeted_status)
        {
          $tw['is_retweet']=1;
          $retweeted=$tweet->retweeted_status;
	  $tw['retweets']=0;
          if ($retweeted->id) $tw['retweeted_tweet_id']=$retweeted->id;
          if ($retweeted->user)
            {
                $ruser=$retweeted->user;
                if ($ruser->id) $tw['retweeted_user_id']=$ruser->id;
            }
        }
        else $tw['is_retweet']=0;
        if (strpos($tw['clear_text'],"@")===0) $tw['is_message']=1; else $tw['is_message']=0;
        if ($tweet->possibly_sensitive) $tw['possibly_sensitive']=$tweet->possibly_sensitive;
        if ($tweet->withheld_copyright) $tw['withheld_copyright']=$tweet->withheld_copyright;
        if ($tweet->withheld_in_countries) $tw['withheld_in_countries']=$tweet->withheld_in_countries;
        if ($tweet->withheld_scope) $tw['withheld_scope']=$tweet->withheld_scope;
        if ($tweet->lang) $tw['tweet_language']=$tweet->lang;
        if ($tweet->source) $tw['source']=preg_replace('/.+>([^<>]+?)<.+/','$1',$tweet->source);
        $tw['tweet_date']=date('Y-m-d',strtotime($tw['date_time']));
        $tw['user_location']=$user['user_location'];
        $tw['user_timezone']=$user['user_timezone'];
        $tw['user_lang']=$user['user_lang'];
        $tw['user_bio']=$user['user_bio'];
        $tw['user_image_url']=$user['user_image_url'];
        $tw['user_name']=$user['user_name'];

    //echo "\narrived\n";
        if ($tweet)
         {
    //      var_dump($tweet); return;
        //  print_r($tw);
    //      print_r($tw); print_r($user); exit;
          put_in_database1($tw,$user);
    //exit;
         }
        else
          { echo "No tweet found: ".$tw['user_screen_name'].": ".$tw['clear_text']."\n";
            sleep(2);
          }
/*
        if ($tw['tweet_id'])
          {
            update_cases_table("completed");
          }
*/
        }

function url_type($url)
    {
      $supported_image = array('gif','jpg','jpeg','png','bmp','jpe','tiff','pcx','ico');
      $supported_video = array('mpg','mp4','mpeg','mov','m4v','3gp','3g2','swf','flv','f4v','avi','wmv','ram','asf');

      $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
      if (in_array($ext, $supported_image)) return "image";
      if (in_array($ext, $supported_video)) return "video";
      return "link";
    }
function put_in_database1($tweet,$user)
      {
        global $table; global $i; //global $tweet_updated_rows; global $user_updated_rows;
        global $link; global $users_only; global $fix_utf8;
    //    echo "($tweet)\n"; exit;
    if ($tweet && !$users_only)
       {
    //     print_r($tweet); exit;
        $query = "UPDATE `$table` SET \n`date_time`='${tweet['date_time']}'";
        $fields=array_keys($tweet);
        foreach ($fields as $field)
         {
           if ($field!='date_time' && $field!='tweet_id' && strlen($tweet[$field])>0)
            $query="$query, \n`$field`='".$link->real_escape_string($tweet[$field])."'";
         }
        $query="$query WHERE tweet_id='".$tweet['tweet_id']."';";
    //        echo "\ntweet query:\n\n$query\n";
       $result = $link->query($query);
       if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
    //   echo "Affected rows: ".mysql_affected_rows()."\n"; exit;
    //   $a_rows=$link->affected_rows;
    //   if ($link->affected_rows==1) $tweet_updated_rows++;
    }

    if ($user && !$fix_utf8)
      {
        $insert_part="INSERT INTO `users_"."$table` ";
        $update_part = "ON DUPLICATE KEY UPDATE \n";
        $query="`user_name`='".$link->real_escape_string($user['user_name'])."'";
        $fields=array_keys($user);
        $names="`user_id`";
        $values="'".$user['user_id']."'";
        foreach ($fields as $field)
         {
           if ($field!='user_name' && $field!='user_id')
             $query="$query, \n`$field`='".$link->real_escape_string($user[$field])."'";
           if ($field!='user_id')
             {
               $names="$names, `$field`";
               $values="$values, '".$link->real_escape_string($user[$field])."'";
             }
         }
        $query="$insert_part ($names) values ($values) $update_part $query;";
    //       echo "\nuser query:\n\n$query\n"; exit;
       $result = $link->query($query);
       if (!$result) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
    //   if ($link->affected_rows>$a_rows) $user_updated_rows++;
    //   echo "\n---\n".$tweet_updated_rows." ".$user_updated_rows." (".$link->affected_rows.")\n---\n";
     }
    }

function update_response_mentions()
      {
        global $table; global $link; global $mysql_db;
        $tmp="user_all_mentions_"."$table"; $u_m="user_mentions_".$table;

        echo "Adding responses, responses to tweeter and mentions of tweeter data to table ...<br>\n";

        $query="CREATE TABLE IF NOT EXISTS $tmp like 1_empty_all_mentions";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="TRUNCATE TABLE $tmp";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="INSERT INTO $tmp (tweet_id,responses) (SELECT $table.in_reply_to_tweet,count($table.tweet_id) FROM $table WHERE $table.in_reply_to_tweet is not null AND $table.is_protected_or_deleted is null and $table.date_time is not null group by $table.in_reply_to_tweet order by count($table.tweet_id) desc)";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="UPDATE IGNORE $tmp,$table SET $tmp.user_screen_name = LOWER($table.user_screen_name), $tmp.user_id = $table.user_id  WHERE $tmp.tweet_id = $table.tweet_id";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="UPDATE $tmp,$table SET $tmp.responses_to_tweeter=(SELECT count($table.tweet_id) FROM $table WHERE $table.in_reply_to_user is not null AND $tmp.user_id=$table.in_reply_to_user group by $table.in_reply_to_user) WHERE $tmp.user_id=$table.in_reply_to_user";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

	$query="CREATE TABLE IF NOT EXISTS $u_m LIKE 1_empty_user_mentions";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="TRUNCATE TABLE $u_m";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="DROP FUNCTION IF EXISTS SPLIT_STRING";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="CREATE FUNCTION SPLIT_STRING(str VARCHAR(255), delim VARCHAR(12), pos INT) RETURNS VARCHAR(255) RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos), LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1), delim, '')";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
        $query="INSERT INTO $u_m(tweet_id,user_id,user_screen_name, mention1, mention2, mention3, mention4, mention5, mention6, mention7, mention8, mention9, mention10) select $table.tweet_id, $table.user_id, LOWER($table.user_screen_name), ".
        "SPLIT_STRING($table.user_mentions, ' ', 1),SPLIT_STRING($table.user_mentions, ' ', 2),SPLIT_STRING($table.user_mentions, ' ', 3),".
        "SPLIT_STRING($table.user_mentions, ' ', 4),SPLIT_STRING($table.user_mentions, ' ', 5),SPLIT_STRING($table.user_mentions, ' ', 6),".
        "SPLIT_STRING($table.user_mentions, ' ', 7),SPLIT_STRING($table.user_mentions, ' ', 8),SPLIT_STRING($table.user_mentions, ' ', 9),".
        "SPLIT_STRING($table.user_mentions, ' ', 10) from $table where $table.user_mentions is not null or ($table.in_reply_to_tweet is not null and $table.in_reply_to_user is not null)";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

for ($i=1; $i<=10; $i++)
        {
          $query="INSERT INTO `$tmp` (user_screen_name,mention$i) (SELECT SUBSTR($u_m.mention$i,2),count($u_m.tweet_id) AS counts FROM $u_m WHERE $u_m.mention$i<>'' group by $u_m.mention$i order by count($u_m.tweet_id) desc) on duplicate key update $tmp.mention$i=VALUES(mention$i)";
          $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
        }
        $query="UPDATE $tmp SET mentions_of_tweeter=(mention1+mention2+mention3+mention4+mention5+mention6+mention7+mention8+mention9+mention10)";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$tmp set $table.responses=$tmp.responses where $table.tweet_id=$tmp.tweet_id and $tmp.tweet_id is not null";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$tmp set $table.responses_to_tweeter=$tmp.responses_to_tweeter where $table.user_id=$tmp.user_id and $tmp.user_id is not null";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$tmp set $table.mentions_of_tweeter=$tmp.mentions_of_tweeter where LOWER($table.user_screen_name)=LOWER($tmp.user_screen_name) and $tmp.user_screen_name is not null";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

//        $link->query("DROP TABLE $tmp");

        $query="UPDATE user_mentions_".$table.",$table "."
        SET user_mentions_".$table.".in_response_to_tweet=$table.in_reply_to_tweet,user_mentions_".$table.".in_response_to_user_id=$table.in_reply_to_user
        WHERE user_mentions_".$table.".tweet_id=$table.tweet_id;";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

	$query="UPDATE user_mentions_".$table.",users_".$table."
	SET user_mentions_".$table.".user_name=users_".$table.".user_name, 
	user_mentions_".$table.".user_verified=users_".$table.".user_verified, 
	user_mentions_".$table.".user_followers=users_".$table.".user_followers
	WHERE user_mentions_".$table.".user_id=users_".$table.".user_id;";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

	$query="UPDATE user_mentions_".$table.",users_".$table."
	SET user_mentions_".$table.".in_response_to_user_screen_name=LOWER(users_".$table.".user_screen_name), 
	user_mentions_".$table.".in_response_to_user_name=users_".$table.".user_name, 
	user_mentions_".$table.".in_response_to_user_verified=users_".$table.".user_verified, 
	user_mentions_".$table.".in_response_to_user_followers=users_".$table.".user_followers
	WHERE user_mentions_".$table.".in_response_to_user_id=users_".$table.".user_id;";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

	$query="UPDATE `user_mentions_".$table."`,`".$table."` SET `user_mentions_".$table.
	"`.`tweet_datetime`=`".$table."`.`date_time` WHERE `user_mentions_".$table."`.`tweet_id`=`".$table."`.`tweet_id`";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        echo "\n"."Done with updating user mentions...\n";

        echo "\n"."Done with updating responses...\n";
      }



function update_kumu_files($table)
      {
global $link;
$maximum_strength=5;
$minimum_strength=0;
$top_limit=array("1000","5000","10000");
$max_kumu_size=2000;

        echo "Kumu: Doing $table <br>\nCreating element for top tweets...";

foreach ($top_limit as $toplimit)
  {
    echo "\nTop $toplimit :\n";
        $query= "SELECT 
        LOWER($table.user_screen_name),
        $table.user_image_url,'','',
        $table.date_time,
        $table.raw_text,
        $table.tweet_permalink_path,
        $table.hashtags,
        $table.tweet_language,
        $table.source,
        $table.retweets,
        $table.favorites,
        $table.responses,
        LOWER($table.user_mentions),
        $table.user_name,
        $table.user_location,
        $table.user_lang,
        $table.user_bio,
        $table.user_verified,
        $table.in_reply_to_user,
        $table.is_retweet,
        $table.has_image,
        $table.has_video,
        $table.has_link,
        $table.media_link,
        $table.expanded_links,
        $table.location_name,
        $table.country
        FROM $table WHERE $table.is_protected_or_deleted is null and $table.date_time is not null ORDER BY retweets DESC";

	$first_line=array("Label","Image","Profile Link","Type","Date","Tweet Text","Tweet Link","Tags","Tweet Language","Source",
	"Retweets","Favorites","Responses","User Mentions","User Full Name","User Location","User Language","User Bio",
	"User Verified","In Reply to User","Is a Retweet","Has an Image","Has a Video","Has a Link","Media Link",
	"Other Links","Tweeted From Location","Tweeted from Country");

            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");

$fp=fopen("tmp/kumu/$table"."_"."top_tweets_".$toplimit.".csv",'w');
fputcsv($fp, $first_line);
	    $ind=0;
            while ($row = $result->fetch_array(MYSQLI_NUM))
              {
		if ($ind==$toplimit) break;
		$row[2]="https://twitter.com/".$row[0];
		if ($row[20]) $row[3]="Retweet"; 
		elseif ($row[21]) $row[3]="Tweet with image";
		elseif ($row[22]) $row[3]="Tweet with video";
		elseif ($row[23]) $row[3]="Tweet with link";
		elseif ($row[20]) $row[3]="Tweet with reply";
		else $row[3]="Regular tweet";
		$row[5]=preg_replace("/[\r\n]+/"," ",$row[5]);
		$row[17]=preg_replace("/[\r\n]+/"," ",$row[17]);
		$row[5]=str_replace("\"","'",$row[5]);
		$row[17]=str_replace("\"","'",$row[17]);
                $row[7]=preg_replace("/\s+/","|",$row[7]);
                $row[13]=preg_replace("/\s+/","|",$row[13]);
		fputcsv($fp, $row);
		$ind++;
              }
fclose($fp);
     echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."top_tweets_".$toplimit.".csv'>file ($table"."_"."top_tweets_".$toplimit.".csv)</a><br>\n";
    }
//die();
///////////////////////
///////////////////////
/* Get all user records to compare to */

$query = "SELECT LOWER(user_screen_name) FROM users_".$table." WHERE user_screen_name is not null";
            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");
            $valid_users=array();
            while ($row = $result->fetch_array())
              {
		$valid_users[$row[0]]=1;
	      }
/****************/
//print_r($valid_users);// exit;

	echo "Kumu: Doing $table <br>\nCreating connection for responses...";
	$t="user_mentions_".$table; 

	$query= "SELECT LOWER($t.user_screen_name), LOWER($t.in_response_to_user_screen_name),$t.in_response_to_tweet,$table.is_retweet,".
		"$t.tweet_datetime,$table.tweet_permalink_path,$table.user_verified,$table.has_image,$table.has_video,".
		"$table.has_link,$table.media_link,$table.expanded_links,$table.source,$table.location_name,$table.country,".
		"$table.tweet_language,$table.raw_text,$table.hashtags,$table.user_mentions,$table.retweets,$table.favorites,$t.tweet_id ".
		"FROM $t,$table WHERE $t.in_response_to_user_screen_name is not null and $t.user_screen_name is not null ".
                "AND $table.is_protected_or_deleted is null and $table.date_time is not null ".
		"and $t.tweet_id=$table.tweet_id order by $table.retweets DESC";

            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");

            $first_line=array("From","To","Type","Date","Link","From_Verified_User","Is_Image",
			      "Is_Video","Is_Link","Media_Link","Other_Links","Source","Location",
		   	      "Language","Content","Hashtags","Mentions","Retweets","Favorites","Tweet_ID"); 
	    $all_responses=array(); $all_users=array();
	    $ind=0; $indx=0;
$fp=fopen("tmp/kumu/$table"."_"."responses.csv",'w');
fputcsv($fp, $first_line);
            while ($row = $result->fetch_array())
              {
                $row[0]=ltrim($row[0],'@'); $row[1]=ltrim($row[1],'@');
		if (!isset($valid_users[$row[0]])) { /*echo "Skipping (${row[0]})...";*/ continue; }
		if (!isset($valid_users[$row[1]])) { /*echo "Skipping (${row[1]})...";*/ continue; }
//echo "\nGot in!\n";
		$ind++;
		if ($row[3]) { $row[2]="Retreet"; } elseif ($row[2]) { $row[2]="Reply to tweet"; } else $row[2]="Reply to tweeter";
                if ($row[13] || $row[14])  { $row[13]=$row[13].", ".$row[14]; $row[13]=trim($row[13]); }

                $row[5]=preg_replace("/[\r\n]+/"," ",$row[5]);
                $row[16]=preg_replace("/[\r\n]+/"," ",$row[16]);
		$row[5]=str_replace("\"","'",$row[5]);
		$row[16]=str_replace("\"","'",$row[16]);
                $row[18]=preg_replace("/\s+/","|",$row[18]);
                $line=array(); $ind2=0;
                for ($k=0; $k<22; $k++)
                   {
                      if ($k==14 || $k==3) continue;
                      $line[$ind2]=$row[$k];
		      $ind2++;
                   }
		$all_responses[$row[21]]=1;
		if (!isset($all_users[$row[0]])) $all_users[$row[0]]=1;
		if (!isset($all_users[$row[1]])) $all_users[$row[1]]=1;
//echo implode(",",$line)."\n";
fputcsv($fp, $line);
		$indx++;
		if ($indx>=$max_kumu_size) break;
              }
fclose($fp);
            echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."responses.csv'>file ($table"."_"."responses.csv)</a><br>\n";

//die("done\n");

echo "Kumu: DONE\n\nCreating connection for mentions...";

        $query= "SELECT LOWER($t.user_screen_name),$t.mention1,$t.mention2,$t.mention3,$t.mention4,$t.mention5,$t.mention6,$t.mention7,".
		"$t.mention8,$t.mention9,$t.mention10,".
		"$t.tweet_datetime,$table.is_retweet,$table.tweet_permalink_path,$table.user_verified,$table.has_image,$table.has_video,".
                "$table.has_link,$table.media_link,$table.expanded_links,$table.source,$table.location_name,$table.country,".
                "$table.tweet_language,$table.raw_text,$table.hashtags,$table.user_mentions,$table.retweets,$table.favorites,$t.tweet_id ".
                "FROM $t,$table WHERE $t.mention1>'' and $t.user_screen_name is not null and $t.tweet_id=$table.tweet_id ".
                "AND $table.is_protected_or_deleted is null and $table.date_time is not null ".
		"order by $table.retweets DESC";

//echo "\n$query\n";

            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");
            $first_line=array("From","To","Type","Date","Position","Link","From_Verified_User","Is_Image","Is_Video",
			      "Is_Link","Media_Link","Other_Links","Source","Location","Language","Content","Hashtags",
			      "Mentions","Retweets","Favorites","Tweet_ID");
	    $all_mentions=array();
	    $indx=0;
	    $fp=fopen("tmp/kumu/$table"."_"."mentions.csv",'w');
	    fputcsv($fp, $first_line);
            while ($row = $result->fetch_array())
              {
		$row[0]=ltrim($row[0],'@'); 
		$row[1]=ltrim($row[1],'@');
		if (!isset($valid_users[$row[0]])) continue; 
	        if (!isset($all_responses[$row[29]])) 
		   {
			$mention="mention only"; 
			if (!isset($all_mentions[$row[29]])) $all_mentions[$row[29]]=1;
		   } 
		else 
		   {
		     $mention="response and mention";
		   }
                if ($row[21] || $row[22]){ $row[21]=$row[21].", ".$row[22]; $row[21]=trim($row[21]); }
                if ($row[25]) $row[25]=preg_replace("/\s+/","|",$row[25]);
                if ($row[26]) $row[26]=preg_replace("/\s+/","|",$row[26]);
                $row[24]=preg_replace("/[\r\n]+/"," ",$row[24]);
		$row[24]=str_replace("\"","'",$row[24]);
                if (!isset($all_users[$row[0]])) $all_users[$row[0]]=1;
                $ind=5; $line=array();
                $line[0]=$row[0]; $line[1]=$row[1]; $line[2]=$mention; $line[3]=$row[11]; $line[4]=1;
                for ($k=13; $k<30; $k++) { if ($k!=22) { $line[$ind]=$row[$k]; $ind++; } }
                if (isset($valid_users[$row[1]]))
                  {
                   if (!isset($all_users[$row[1]])) $all_users[$row[1]]=1;
		   fputcsv($fp, $line);
		  }
		for($i=2; $i<10; $i++)
		  {
		    if (!$row[$i]) break;
                    $row[$i]=ltrim($row[$i],'@');
		    if (!isset($valid_users[$row[$i]])) continue; 
                    if (!isset($all_users[$row[$i]])) $all_users[$row[$i]]=1;;
                    $line[0]=$row[0]; $line[1]=$row[$i]; $line[2]="mention only"; $line[3]=$row[11]; $line[4]=$i;
		    fputcsv($fp, $line);
		  }
		$indx++;
                if ($indx>=$max_kumu_size) break;
	     }
fclose($fp);
            echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."mentions.csv'>file ($table"."_"."mentions.csv)</a><br>\n";

//echo "\n---\nResponse tweets"; print_r($all_responses); 
//echo "\n---\nMentions tweets:\n"; print_r($all_mentions);
//echo "\n---\nUsers:\n"; print_r($all_users);

        echo "\nKumu: Creating elements for users...";
        $first_line=array("Label","Image","User Verified","Link","Bio","Language","Location","Tweets","Followers",
			  "Following","Favorites","Lists","Created Date","Profile Page");
        $fp=fopen("tmp/kumu/$table"."_"."users.csv",'w');
        fputcsv($fp, $first_line);

        $query= "SELECT LOWER(user_screen_name),user_image_url,user_verified,user_url,user_bio,user_lang,user_location,user_tweets,".
		"user_followers,user_following,user_favorites,user_lists,user_created FROM users_".$table;;

            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n"; }
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");
            while ($row = $result->fetch_array(MYSQLI_NUM))
              {
                if (!isset($all_users[$row[0]])) continue;
		$row[13]="https://twitter.com/".$row[0];
                $row[4]=preg_replace("/[\r\n]+/"," ",$row[4]);
		$row[4]=str_replace("\"","'",$row[4]);

		fputcsv($fp, $row);
              }
fclose($fp);
            echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."users.csv'>file ($table"."_"."users.csv)</a><br>\n";
/********************************************************/
/*
echo "\nMappr: Creating top 1000 & 2000 nodes for Mappr (with duplicates)...";
$first_line=array("id","full_name","Is_user_verified","user_location","user_language","user_bio","user_image_url","tweets_total","retweets_total",
                  "mentions","hashtags","media_urls","other_urls","sources","profile_url");
$fp1=fopen("mappr/mappr_".$table."_"."top_1000_all.csv",'w');
$fp2=fopen("mappr/mappr_".$table."_"."top_2000_all.csv",'w');
fputcsv($fp1, $first_line);
fputcsv($fp2, $first_line);

$link->query("SET SESSION group_concat_max_len = 1000000");

$query= "select LOWER(user_screen_name), user_name, user_verified, user_location, user_lang, user_bio, user_image_url, count(tweet_id) as tweets_total, sum(retweets) as retweets_total, group_concat(user_mentions SEPARATOR ' ') as mentions, group_concat(hashtags SEPARATOR ' ') as hashtags,group_concat(media_link SEPARATOR ' ') as media_links, group_concat(expanded_links SEPARATOR ' ') as other_links, group_concat(source SEPARATOR '|') as sources from $table group by user_screen_name order by sum(retweets) desc";

    if ($result = $link->query($query))
        {
          if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
          $total=$result->num_rows;
        }
    else die("Error in query: ". $link->error.": $query");
    $i=0;
    while ($row = $result->fetch_assoc())
      {
        
$row['mentions']=preg_replace("/\s+/"," ",$row['mentions']);
$row['mentions']=str_replace(" ","|",trim($row['mentions']));
$row['hashtags']=preg_replace("/\s+/"," ",$row['hashtags']);
$row['hashtags']=str_replace(" ","|",trim($row['hashtags']));

if ($row['media_links'])
   {
        $row['media_links']=preg_replace("/\s+/"," ",$row['media_links']);
//echo "ml:\n".$row['media_links']."\n";
        $row['media_links']=str_replace(' ','"},{"url":"',trim($row['media_links']));
        $row['media_links']='[{"url":"'.$row['media_links'].'"}]';
//echo "ml:\n".$row['media_links']."\n";
   }
if ($row['other_links'])
   {
        $row['other_links']=preg_replace("/\s+/"," ",$row['other_links']);
//echo "ol:\n".$row['other_links']."\n";
        $row['other_links']=str_replace(' ','"},{"url":"',trim($row['other_links']));
        $row['other_links']='[{"url":"'.$row['other_links'].'"}]';
//echo "ol:\n".$row['other_links']."\n";
   }
$row['sources']=preg_replace("/\s+/","_",$row['sources']);
$row['sources']=str_replace(" ","|",trim($row['sources']));
$row['user_image_url']=str_replace("_normal.jpg","_400x400.jpg",$row['user_image_url']);
$row['user_bio']=str_replace("@","[at]",$row['user_bio']);
$row['user_bio']=str_replace("|",",",$row['user_bio']);
$row['tweets_total']=log($row['tweets_total']);
$row['retweets_total']=log($row['retweets_total']);

$row[13]=$row['user_screen_name'];
//print_r($row); exit;
if ($i<=1000) fputcsv($fp1, $row);
fputcsv($fp2,$row);
$i++;
if ($i==2000) break;
}
fclose($fp1); fclose($fp2);
echo "Mappr: Saved CSV <a href='mappr/mappr_".$table."_"."top_2000_full.csv'>file ($table"."_"."top_2000_full.csv)</a><br>\n";
*/
/***************************************************************************/
/*
echo "\nMappr: Creating top 2000 & 5000 nodes for Mappr (without duplicates)...";
$first_line=array("id","full_name","Is_user_verified","user_location","user_language","user_bio","user_image_url","tweets_total","retweets_total",
          "mentions","hashtags","media_urls","other_urls","sources","profile_url");
$fp1=fopen("mappr/mappr_".$table."_"."top_2000.csv",'w');
$fp2=fopen("mappr/mappr_".$table."_"."top_5000.csv",'w');
fputcsv($fp1, $first_line);
fputcsv($fp2, $first_line);

$link->query("SET SESSION group_concat_max_len = 1000000");

$query= "select user_screen_name, user_name, user_verified, user_location, user_lang, user_bio, user_image_url, count(tweet_id) as tweets_total, sum(retweets) as retweets_total, group_concat(distinct user_mentions SEPARATOR ' ') as mentions, group_concat(distinct hashtags SEPARATOR ' ') as hashtags,group_concat(distinct media_link SEPARATOR ' ') as media_links, group_concat(distinct expanded_links SEPARATOR ' ') as other_links, group_concat(distinct source SEPARATOR ' ') as sources from $table group by user_screen_name order by sum(retweets) desc";

    if ($result = $link->query($query))
        {
          if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
          $total=$result->num_rows;
        }
    else die("Error in query: ". $link->error.": $query");
    $i=0;
    while ($row = $result->fetch_assoc())
      {
        $row['mentions']=preg_replace("/\s+/"," ",$row['mentions']);
        $row['mentions']=str_replace(" ","|",trim($row['mentions']));
        $row['hashtags']=preg_replace("/\s+/"," ",$row['hashtags']);
        $row['hashtags']=str_replace(" ","|",trim($row['hashtags']));

        if ($row['media_links'])
           {
                $row['media_links']=preg_replace("/\s+/"," ",$row['media_links']);
//echo "ml:\n".$row['media_links']."\n";
                $row['media_links']=str_replace(' ','"},{"url":"',trim($row['media_links']));
                $row['media_links']='[{"url":"'.$row['media_links'].'"}]';
//echo "ml:\n".$row['media_links']."\n";
           }
        if ($row['other_links'])
           {
                $row['other_links']=preg_replace("/\s+/"," ",$row['other_links']);
//echo "ol:\n".$row['other_links']."\n";
                $row['other_links']=str_replace(' ','"},{"url":"',trim($row['other_links']));
                $row['other_links']='[{"url":"'.$row['other_links'].'"}]';
//echo "ol:\n".$row['other_links']."\n";
           }
        $row['sources']=preg_replace("/\s+/","_",$row['sources']);
        $row['sources']=str_replace(" ","|",trim($row['sources']));
        $row['user_image_url']=str_replace("_normal.jpg","_400x400.jpg",$row['user_image_url']);
        $row[13]=$row['user_screen_name'];
        $row['user_bio']=str_replace("@","[at]",$row['user_bio']);
        $row['user_bio']=str_replace("|",",",$row['user_bio']);
        $row['tweets_total']=log($row['tweets_total']);
        if ($row['retweets_total']>0) $row['retweets_total']=log($row['retweets_total']);
        
                if ($i<=2000) fputcsv($fp1, $row);
                fputcsv($fp2,$row);
                $i++;
                if ($i==5000) break;
              }
        fclose($fp1); fclose($fp2);
        echo "Mappr: Saved CSV <a href='mappr/mappr_".$table."_"."top_2000.csv'>file ($table"."_"."top_2000.csv)</a><br>\n";
        echo "Mappr: Saved CSV <a href='mappr/mappr_".$table."_"."top_5000.csv'>file ($table"."_"."top_5000.csv)</a><br>\n";
*/
/************************************************/
 }

function update_cases_table($mode)
      {
        global $table; global $link;
	if ($mode=="started") { echo "Recorded starting!\n"; $add_compl=",last_process_completed='0000-00-00 00:00:00'"; }
	else {  $add_compl=""; }
        $query="update cases set last_process_"."$mode=NOW()$add_compl,status='$mode' where id='$table'";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
      }

function getapi_record($postfields)
      {
        global $twitter_api_settings; global $last_setting;
    //    print_r($postfields); exit;
        $twitter = new TwitterAPIExchange($twitter_api_settings[$last_setting]);
//        echo "-\n"; print_r($postfields); echo "-\n";
    //exit;
        $response=$twitter->setPostfields($postfields)->buildOauth("https://api.twitter.com/1.1/statuses/lookup.json", "POST")->performRequest();
        $record=json_decode($response);
        $error=$record->errors;
        $error=$error[0];
        if($error)
          {
            if ($error->code==88)
              {
                  echo "\nError 88: Rate exceeded, waiting for 5 minutes to continue with $table\n";
                  for ($k=4; $k>=0; $k--)
                   {
                     echo ($k)." minutes remaining...\n";
                     sleep(60);
                   }
                  $last_setting=$last_setting+1;
                  if ($last_setting>sizeof($twitter_api_settings)-1) $last_setting=0;
                  $response=getapi_record($postfields);
              }
            else
            {
              echo "Error: code: ".$error->code.", message: ".$error->message."\n";
              exit();
            }
          }
    //    echo $response; exit;
        return $response;
      }

function GetRealURL( $url )
      {
         $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_USERAGENT      => "spider",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
         );
          $ch      = curl_init( $url );
          curl_setopt_array( $ch, $options );
          $content = curl_exec( $ch );
          $err     = curl_errno( $ch );
          $errmsg  = curl_error( $ch );
          $header  = curl_getinfo( $ch );
          curl_close( $ch );
    //      print_r($header);
          if ($header['header_size'])
            return $header['url']." ".$header['content_type'];
          return "";
       }

function url_get_contents($url) {
    global $failed_proxy;
    if (!function_exists('curl_init')) die('CURL is not installed!');
    $header = array(
    'accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
//    'accept-language:en-US,en;q=0.8,sv;q=0.6,ar;q=0.4',
//    'user-agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36');
    $ch = curl_init();
/*    if (!$failed_proxy)  
	{  
	   curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:9050");
    	   curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
	}
*/
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    sleep(1);
    $output = curl_exec($ch);
    if(curl_errno($ch))
     {
      if (!$failed_proxy)
	{
	   $failed_proxy=1;
	   echo "\nProxy connection failed... Trying without proxy\n";
           curl_close($ch);
           url_get_contents($url);
	}
     }
    return $output;
}

function put_in_database($tweet)
{
//  print_r($tweet); exit;
  global $i; global $mode; global $mysql_db; global $table; global $verbose;
  global $added; global $skipped; global $link;
//die("\n[$i]\n");
  $query = "$mode `$mysql_db`.`$table` (`index_on_page`,`tweet_id`) VALUES ($i, "."'".$tweet['tweet_id']."')";
       if (!($result = $link->query($query))) die("Error in query: ". $link->error.": $query\n");
       elseif ($verbose==1)
        {
          if ($link->affected_rows==1)
          {
            echo "added\n";
            $added++;
          }
          elseif ($link->affected_rows==0)
          {
            echo "skipped\n";
            $skipped++;
          }
        }
//        exit;
  }

  function get_last_line($file)
  {
    $line = '';
    if (!file_exists($file)) { echo "File ($file) doesn't exist\n"; return ""; }
    $f = fopen($file, 'r');
    $cursor = -1;
    fseek($f, $cursor, SEEK_END);
    $char = fgetc($f);
    while ($char === "\n" || $char === "\r")
      {
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
      }
    while ($char !== false && $char !== "\n" && $char !== "\r")
      {
        $line = $char . $line;
        fseek($f, $cursor--, SEEK_END);
        $char = fgetc($f);
     }
    $line=trim($line);
    return $line;
  }

function del_last_line($file,$record_exists)
{
  $lines = file($file);
  $last = sizeof($lines) - 1 ;
  unset($lines[$last]);
  // write the new data to the file
  $fp = fopen($file, 'w');
  $temp=implode('', $lines);
  $tmp=preg_replace("/no more tweets\n/","",$tmp);
  fwrite($fp, $temp);
  fclose($fp);
//  die("seeking ($record_exists) in $file \n--\n$temp\n--\n) sp:".strpos($temp,$record_exists));
//  exit;
  if (strpos($temp,$record_exists)===false) return 1;
  else return -1;
}
  function note($line)
  {
    global $log; global $log_file;
    if ($log)
      file_put_contents($log_file,$line,FILE_APPEND);
    else
      {
        echo $line;
        file_put_contents($log_file,$line,FILE_APPEND);
      }
  }


  function extract_and_store_data2($u)
  {
    global $i;
//    if ($u) { print_r($u); exit; }
      $user=array();
      if ($u->id) $user['user_id']=$u->id;
      if ($u->screen_name) $user['user_screen_name']=$u->screen_name;
      if ($u->name) $user['user_name']=$u->name;
      if ($u->location) $user['user_location']=$u->location;
      if ($u->followers_count===0 || $u->followers_count) $user['user_followers']=$u->followers_count;
      if ($u->following_count===0 || $u->following_count) $user['user_following']=$u->following_count;
      if ($u->friends_count===0 || $u->friends_count) $user['user_following']=$u->friends_count;
      if ($u->protected) $user['user_protected']=$u->protected;
      else  $user['user_protected']=0;
      if ($u->listed_count===0 || $u->listed_count) $user['user_lists']=$u->listed_count;
      if (preg_match("/([A-Za-z]+) ([\d]+) ([\d]+\:[\d]+\:[\d]+) \+0000 ([\d]+)/si",$u->created_at,$cr))
        {
          $datetime=$cr[2]." ".$cr[1]." ".$cr[4]." ".$cr[3];
          $user['user_created']=date('Y-m-d H:i:s',strtotime($datetime));
        }
      if ($u->favourites_count===0 || $u->favourites_count) $user['user_favorites']=$u->favourites_count;
      if ($u->utc_offset) $user['user_utc_offset']=$u->utc_offset;
      if ($u->time_zone) $user['user_timezone']=$u->time_zone;
      if ($u->geo_enabled) $user['user_geo_enabled']=1; else $user['user_geo_enabled']=0;
      if ($u->verified) $user['user_verified']=$u->verified; else $user['user_verified']=0;
      if ($u->statuses_count===0 || $u->statuses_count) $user['user_tweets']=$u->statuses_count;
      if ($u->lang) $user['user_lang']=$u->lang;
      if ($u->description) $user['user_bio']=$u->description;
      if ($u->profile_image_https) $user['user_image_url']=$u->profile_image_https;
      if ($u->entities)
       {
         $en=$u->entities;
         if ($en->url)
          {
            $urls=$en->url;
            if (sizeof($urls)>0)
            {
              foreach($urls as $url1)
               {
                 foreach ($url1 as $url2)
                   {
                    $user['user_url']=$user['user_url']." ".$url2->expanded_url;
                   }
               }
              $user['user_url']=trim($user['user_url']);
            }
          }
       }
      if (!$user['user_url'] && $u->url) { $user['user_url']=$u->url; }
      if ($u->withheld_in_countries) $user['user_withheld_in_countries']=$u->withheld_in_countries;
      if ($u->withheld_scope) $user['user_withheld_scope']=$u->withheld_scope;
      /******resume twitter data******/
      if ($u)
       {
  //      print_r($user); exit;
        put_in_database2($user);
       }
      else
        {
          echo "No user found: ".$user['user_screen_name']."\n";
          sleep(2);
        }
      }

  function put_in_database2($user)
    {
      global $table; global $i; //global $tweet_updated_rows; global $user_updated_rows;
      global $link;

      $insert_part="INSERT INTO `users_".$table."` ";
      $update_part = "ON DUPLICATE KEY UPDATE \n";
      $query="`user_name`='".$link->real_escape_string($user['user_name'])."'";
      $fields=array_keys($user);
      $names="`user_id`";
      $values="'".$user['user_id']."'";
      foreach ($fields as $field)
       {
         if ($field!='user_name' && $field!='user_id')
           $query="$query, \n`$field`='".$link->real_escape_string($user[$field])."'";
         if ($field!='user_id')
           {
             $names="$names, `$field`";
             $values="$values, '".$link->real_escape_string($user[$field])."'";
           }
       }
      $query="$insert_part ($names) values ($values) $update_part $query;";
  //       echo "\nuser query:\n\n$query\n"; exit;
     $result = $link->query($query);
     if (!$result) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
  //   if ($link->affected_rows>$a_rows) $user_updated_rows++;
  //   echo "\n---\n".$tweet_updated_rows." ".$user_updated_rows." (".$link->affected_rows.")\n---\n";
    }

  function getapi_record2($postfields)
    {
      global $records; global $twitter_api_settings; global $link;
      global $last_setting;
  //    print_r($postfields);
      $twitter = new TwitterAPIExchange($twitter_api_settings[$last_setting]);
  //    echo "-\n"; print_r($postfields); echo "-\n";
  //exit;
      $response=$twitter->setPostfields($postfields)->buildOauth("https://api.twitter.com/1.1/users/lookup.json", "POST")->performRequest();
      $record=json_decode($response);
      $error=$record->errors;
      $error=$error[0];
      if($error)
        {
  //echo "error"; exit;
          if ($error->code==88)
            {
                echo "\nError 88: Rate exceeded, waiting for 15 minutes to continue with $table\n";
                for ($k=15; $k>=0; $k--)
                 {
                   echo ($k)." minutes remaining...\n";
                   sleep(62);
                 }
                $response=getapi_record2($postfields);
            }
          elseif ($error->code==17)
           {
              $screen_names=explode(",",$postfields['screen_name']);
  //            print_r($screen_names); exit;
              foreach ($screen_names as $sn)
                {
  //                print_r($sn); exit;
  //                echo "https://twitter.com/".$sn."\n"; continue;
  //                echo file_get_contents("https://twitter.com/".$sn); exit;
  //echo get_http_response_code("https://twitter.com/".$sn); exit;
                  $header=get_headers("https://twitter.com/".$sn);
  //print_r($header);
                  if (strpos($header[0]," 404 ")>0)
                    {
                      $query="UPDATE `users_"."$table` SET `is_deleted`='1' where user_screen_name='".$sn."'";
                      $result3 = $link->query($query);
                      if (!$result3) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
                      echo $sn.": The account has been deleted and marked as such in DB\n";
                    }
                  elseif ($header[7]=="location: https://twitter.com/account/suspended")
                    {
                      $query="UPDATE `users_"."$table` SET `is_suspended`='1' where user_screen_name='".$sn."'";
                      $result3 = $link->query($query);
                      if (!$result3) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
                      echo $sn.": The account has been suspended and marked as such in DB\n";
                    }
                }
  //exit;
            }
          else
           {
            echo "Error: code: ".$error->code.", message: ".$error->message."\n";
            exit();
           }
        }
  //    echo $response; exit;
      return $response;
    }

function draw_network($table)
  {
    global $link;
    $maximum_strength=5;
    $minimum_strength=0;
    $limit=10;

  //$minimum_followers=500;

    update_kumu_files($table);
    echo "\nstart drawing\n";

echo "START DRAWING<br>\n";
            connect_mysql();
            $qry= "SELECT user_id,user_screen_name,user_name,user_followers,user_verified FROM users_".$table." "; /*,user_image_url,user_location*/
            $condition="WHERE user_screen_name is not null" ;
//            if ($minimum_followers) $condition=$condition." and user_followers>=$minimum_followers";

            $query = "$qry $condition order by user_followers desc";

//echo "($query)\n";
            if ($result = $link->query($query))
                {
                  if (!$result->num_rows) echo "No results in the database matched your query.<br>\n";
                  $total=$result->num_rows;
                }
            else die("Error in query: ". $link->error.": $query");
/*
            if ($total>300000) $minimum_strength=3;
            if ($total>200000) $minimum_strength=2;
            if ($total>100000) $minimum_strength=1;
*/
            $nodes=""; $i=0; $all_nodes=array();
            while ($row = $result->fetch_array())
              {
        //        if ($row[4]==1) $color="#FC0404"; else $color="#81BEF7";
//                if ($minimum_followers) { if ($row[3]<$minimum_followers) continue; }
//echo "$i) working on ${row[0]}<br>\n";
                $tmpnode=strtolower($link->real_escape_string($row[1]));
                $nodes=$nodes."g.nodes.push({ id: '${row[0]}', label: '$tmpnode', x: Math.random(), y: Math.random(), size: ".($row[3]).", color: 'FF8000' });\n";
                $all_nodes1[$row[0]]=$tmpnode;
//                array_push($all_nodes,$row[0]);
                $i++;
        //              if ($i==10) break;
              }
echo "\n\nSTEP 1 (users) DONE\n\n";
        //echo "done1"; exit;
//print_r($all_nodes1); exit;
              $total_nodes=$i;
              $qry= "select user_id,user_screen_name,in_response_to_user_id,count(tweet_id) from user_mentions_".$table;
                  $condition="WHERE in_response_to_user_screen_name is not null";
                  $query = "$qry $condition group by concat(user_id, ' ', in_response_to_user_id) order by count(tweet_id) desc";
//echo "\n--\n$qry\n--\n";
              if ($result = $link->query($query))
                {
                  if (!$result->num_rows) echo "No results in the database matched your query.<br>\n";
                  $total=$result->num_rows;
                }
              else die("Error in query: ". $link->error.": $query");
              if ($result->num_rows)
               {
//echo $result->num_rows." DB Records found\n";
                $all_nodes=array_keys($all_nodes1);
                $header="source,target,value\n";
                $edges=array(); $edges[0]=$header; $edges[1]=$header; $edges[2]=$header;
                $edges[3]=$header; $edges[4]=$header; $edges[5]=$header;
                $connected_nodes=array();
                $ii=0;
                while ($row = $result->fetch_array())
                 {
                  $ii++;
                  for ($i=$maximum_strength; $i>$minimum_strength; $i--)
                    {
                      if ($maximum_strength) { if ($row[3]<$i) continue; }
                      if (!in_array($row[0],$all_nodes)) continue;
                      if (!in_array($row[2],$all_nodes)) continue;
                      $edges[$i]=$edges[$i].$all_nodes1[$row[0]].",".$all_nodes1[$row[2]].",".$row[3]."\n";
                    }
//                  echo "- $ii) working on ${row[0]} (i:$i)<br>\n";
                 }
                for ($i=$maximum_strength; $i>$minimum_strength; $i--)
                  {
                    file_put_contents("tmp/network/$table"."_"."$i.csv",$edges[$i]);

                    echo "Saved CSV <a href='tmp/network/$table"."_"."$i.csv'>file ($table"."_"."$i.csv)</a>";
                  }
                }
echo "\n\nSTEP 2 (replies) DONE\n\n";

              $qry= "select user_screen_name,mention1,mention2,mention3,mention4,mention5,
                     mention6,mention7,mention8,mention9,mention10 from user_mentions_".$table;
                  $condition="WHERE mention1 is not null";
                  $query = "$qry $condition";
              if ($result = $link->query($query))
                {
                  if (!$result->num_rows) echo "No results in the database matched your query.<br>\n";
                  $total=$result->num_rows;
                }
              else die("Error in query: ". $link->error.": $query");
              if ($result->num_rows)
               {
                 $header="source,target,value\n";
                 $edges=array(); $edges[0]=$header; $edges[1]=$header; $edges[2]=$header;
                 $edges[3]=$header; $edges[4]=$header; $edges[5]=$header;
                 $connected_nodes=array();
                 $ii=0;
//echo "\n$query\n<br>";
//print_r($all_nodes1);
                 while ($row = $result->fetch_array())
                  {
                   for ($kk=1; $kk<=10; $kk++)
                    {
                      $row[$kk]=ltrim($row[$kk],'@');
                      if (!in_array($row[0],$all_nodes1)) continue;
                      if (!in_array($row[$kk],$all_nodes1)) continue;
                      if (!$edges[$row[0].",".$row[$kk]]) $edges[$row[0].",".$row[$kk]]=0;
                      $edges[$row[0].",".$row[$kk]]++;
//echo "Adding: ".$row[0].",".$row[$kk]."\n<br>";
                    }
                  }
//print_r($edges); exit;
                $edge_arr=array();
                for ($i=$maximum_strength; $i>$minimum_strength; $i--) { $edge_arr[$i]=$header; }
                $edges_keys=array_keys($edges);
                foreach ($edges_keys as $edg)
                 {
                  for ($i=$maximum_strength; $i>$minimum_strength; $i--)
                     { if ($edges[$edg]>$i) $edge_arr[$i]=$edge_arr[$i].$edg.",".$edges[$edg]."\n"; }
                 }
//print_r($edges_arr); exit;
                for ($i=$maximum_strength; $i>$minimum_strength; $i--)
                 {
                    if (!$edge_arr[$i]) { echo "No $i-level connections\n<br>"; continue; }
                    file_put_contents("tmp/network/$table"."_mentions_"."$i.csv",$edge_arr[$i]);
                    echo "Saved CSV <a href='tmp/network/$table"."_mentions_"."$i.csv'>file ($table"."_mentions_"."$i.csv)</a>";
                 }
               }
echo "\n\nSTEP 3 (mentions) DONE\n\n";
echo "\n\nALL DONE\n\n";
update_cases_table("completed");
array_map('unlink', glob("tmp/cache/$table*.tab"));
array_map('unlink', glob("tmp/cache/$table*.htm*"));
array_map('unlink', glob("tmp/cache/$table?*-hashcloud*.*"));
  }
function startswith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

?>

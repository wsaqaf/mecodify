<?php
error_reporting(E_ALL);
require_once("configurations.php");
require_once("cloud.php");

ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');

if (!isset($argv[1])) $argv[1]=null;

if (!$argv[1]) die("Missing case...\n\n");

$table=$argv[1];
$from=$argv[2];
$to=$argv[3];
$include_referenced=$argv[4];
$starting_point=$argv[5];
$top_limit=array(1000,5000,10000);

$verbose=1;
$overwrite=0;
$use_api=0;
$global_step=0;
$last_tweet_id="";
$max_list=1000000;
$max_per_page=100;
$global_step_limit=100;
$start_from=1;
$track_stream=1;
$list_count=1;
$started=false;
$step=0;
$failed_proxy=0;
$hash_cloud="";
$next_token="start";
$since_id="";
$until_id="";

$added_users_list=array();
$added_tweets_list=array();

$limit_remaining=300;
$limit_reset=1000000000;
$full_header="";
$fresh_start=true;

$retweet_keys=array("clear_text","raw_text","has_image","has_video","media_link","has_link","urls","expanded_links","context_annotations","tweet_language","hashtags","user_mentions");

array_map('unlink', glob("tmp/cache/$table*.tab"));
array_map('unlink', glob("tmp/cache/$table*.htm*"));
$keywords=rawurlencode($cases[$table]['query']);

$mode="INSERT IGNORE"; if ($overwrite) $mode="REPLACE";

if ($mode=="log") $log=1; else $log=0;
if ($starting_point=="purge") $resume=0; else $resume=1;
$log=0;

$start_time=$cases[$table]['from'];
if ($start_time=="0000-00-00 00:00:00") $start_time="";
else $start_time = str_replace(" ", "T",$cases[$table]['from']).".00Z";

$end_time=$cases[$table]['to'];
if ($end_time=="0000-00-00 00:00:00") $end_time="";
else $end_time = str_replace(" ", "T",$cases[$table]['to']).".00Z";

$include_retweets=$cases[$table]['include_retweets'];

if (!$argv[4]) $include_referenced=$cases[$table]['top_only'];

$dates=array();
$c=0;

if (!$include_retweets) { $keywords=($keywords)."%20-is:retweet%20-is:quote"; }

get_tweet_ids($table,$keywords);
tweeter_data($table);

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
file_put_contents("tmp/cache/$table-hashcloud.html","<html><meta http-equiv='content-type' content='text/html; charset=utf-8' />\n$hash_cloud</html>\n");
$cloud = new PTagCloud(100);
$cloud->addTagsFromText($hash_cloud);
$cloud->setWidth("900px");
$temp=$link->real_escape_string($cloud->emitCloud());
$query= "UPDATE cases SET hashtag_cloud='$temp' where id='$table'";
if ($result = $link->query($query)) echo "updated $table\n";
else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; exit; }

$link->close();

function get_tweet_ids($table,$keywords)
  {
    global $link; global $mode; global $mysql_db; global $i; global $step2; global $max_tweets_per_case;
    global $global_step;  global $oldest_tweet_id; global $last_tweet_id;  global $max_list;
    global $list_count; global $start_from; global $added; global $skipped; global $include_referenced;
    global $global_step_limit; global $max_per_page; global $count_total; global $next_token;

    $tweets_done=0;
    $count_total=0;

    $pg=0;
    while ($next_token)
     {
        if ($next_token=="start") $next_token="";

        note("Starting page $pg\n");
        $processed=get_all_fields($table,$keywords);
        if ($count_total>=$max_tweets_per_case)
          {
            note("Number of tweets exceeds total per case ($max_tweets_per_case), exiting...\n");
            break;
          }
        $pg++;
     }
    if (!$tweets_done)
      {
          note("No more tweets found, exiting...\n");
      }
    note("Processed total of $tweets_done tweets\n");
  }

function get_all_fields($table,$getfield)
  {
    global $link; global $mysql_db; global $j; global $twitter_api_settings; global $last_setting;
    global $oldest_tweet_id; global $last_tweet_id; global $count_total;
    global $next_token; global $max_tweets_per_case;

    $last_setting=rand(0,sizeof($twitter_api_settings)-1);

    $regex = '$\b(https?|ftp|file)://[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|]$i';

    $recs=getapi_record($getfield);

    $records=$recs->data;

    if (!sizeof($records))
      {
        note("no more tweets\n");
        return 0;
      }

    $i=0;

    $oldest_tweet_id="";
    foreach($records as $record)
      {
        if (!$oldest_tweet_id) $oldest_tweet_id=$record->id;
        if (not_blank($record->id))
          {
            note("Doing #: $i with id [".$record->id."]\n");
          }
        else { note("Tweet object id missing!\n"); continue; }

        extract_and_store_data($record,$recs,true,0);
        $i++;
        $count_total++;
        $last_tweet_id=$record->id;
      }
    note("\nProcessed $i records ($oldest_tweet_id - $last_tweet_id) in $table\n");// for ($tweet_updated_rows tweets, $user_updated_rows users updated)\n";
    if (not_blank($recs->meta->next_token)) $next_token="&next_token=".$recs->meta->next_token;
    else $next_token="";

    return $i;
  }

function add_user($u)
 {
      global $added_users_list;

      if (in_array($u->id, $added_users_list)) return;

      $user=array();
      if ($u->id) $user['user_id']=$u->id;
      if ($u->username) $user['user_screen_name']=$u->username;
      if ($u->name) $user['user_name']=$u->name;
      if ($u->location) $user['user_location']=$u->location;
      if (not_blank($u->public_metrics->followers_count)) $user['user_followers']=$u->public_metrics->followers_count;
      if (not_blank($u->public_metrics->following_count)) $user['user_following']=$u->public_metrics->following_count;
      if (not_blank($u->public_metrics->tweet_count)) $user['user_tweets']=$u->public_metrics->tweet_count;
      if (not_blank($u->public_metrics->listed_count)) $user['user_lists']=$u->public_metrics->listed_count;
      if ($u->protected) $user['user_protected']=$u->protected; else  $user['user_protected']=0;
      $user['user_created']=str_replace("T"," ",substr($u->created_at,0,19));
      if ($u->geo_enabled) $user['user_geo_enabled']=1; else $user['user_geo_enabled']=0;
      if ($u->verified) $user['user_verified']=$u->verified; else $user['user_verified']=0;
      if ($u->description) $user['user_bio']=$u->description;
      if ($u->profile_image_url) $user['user_image_url']=str_replace("_400x400.jpg","_200x200.jpg", str_replace("_normal.jpg","_200x200.jpg",$u->profile_image_url));
      if ($u->entities)
       {
         if ($u->entities->url->urls)
          {
            if (sizeof($u->entities->url->urls)>0)
            {
              foreach($u->entities->url->urls as $url)
               {
                    $user['user_url']=$user['user_url']." ".$url->expanded_url;
               }
              $user['user_url']=trim($user['user_url']);
            }
          }
       }
      if (!not_blank($user['user_url']))
        {
          if (not_blank($u->url)) $user['user_url']=$u->url;
          else $user['user_url']="https://twitter.com/".$user['user_screen_name'];
        }
      /******resume twitter data******/
      if ($u)
        {
          put_user_in_database($user);
          $added_users_list[]=$u->id;
        }
      else
        {
          echo "No user found: ".$user['user_screen_name']."\n";
          sleep(2);
        }
}

function tweeter_data($table)
 {
    update_response_mentions();
    draw_network($table);
 }

function init_tw()
 {
    $tmp=array();
    $vars=array("user_verified", "is_retweet", "is_quote", "is_reply", "is_referenced", "is_message", "has_video", "retweets", "mentions_of_tweeter", "possibly_sensitive", "withheld_copyright", "is_protected_or_deleted");
    foreach ($var as $v)
      {
        $tmp[$v]=0;
      }
    return $tmp;
 }

function not_blank($var)
  {
    if (isset($var))
      {
        if ($var)
          {
            return true;
          }
        else return false;
      }
    return false;
  }

function extract_and_store_data($tweet,$parent,$save_to_db,$is_referenced)
  {
    global $table; global $regex; global $cases; global $link; global $include_referenced; global $retweet_keys;
    global $hash_cloud; global $from; global $to; global $added_tweets; global $include_retweets;

    if (in_array($tweet->id, $added_tweets_list)) return;

    if (startsWith($tweet->text,"RT @") && !$include_retweets) return;

    $tw=init_tw();
    $user=array();
    $tw['tweet_id']=$tweet->id;
    $tw['is_referenced']=$is_referenced;
    if (not_blank($tweet->created_at))
      {
        $tw['date_time']=str_replace("T"," ",substr($tweet->created_at,0,19));
        $tw['tweet_date']=date('Y-m-d',strtotime($tw['date_time']));
      }
    if (not_blank($tweet->source))
      {
        $tw['full_source']=$tweet->source;
        $tw['source']=preg_replace('/.+>([^<>]+?)<.+/','$1',$tweet->source);
      }
    if (not_blank($tweet->text)) $tw['raw_text']=$tweet->text;
    if (not_blank($tweet->lang)) $tw['tweet_language']=$tweet->lang;
    if (not_blank($tweet->author_id)) $tw['user_id']=$tweet->author_id;
    if (not_blank($tweet->conversation_id)) $tw['conversation_id']=$tweet->conversation_id;
    if (not_blank($tweet->context_annotations)) $tw['context_annotations']=$tweet->context_annotations;
    if (not_blank($tweet->possibly_sensitive)) $tw['possibly_sensitive']=$tweet->possibly_sensitive;
    if (not_blank($tweet->in_reply_to_user_id)) $tw['in_reply_to_user']=$tweet->in_reply_to_user_id;
    if (not_blank($tweet->public_metrics->retweet_count)) $tw['retweets']=$tweet->public_metrics->retweet_count;
    if (not_blank($tweet->public_metrics->quote_count)) $tw['quotes']=($tweet->public_metrics->quote_count);
    if (not_blank($tweet->public_metrics->like_count)) $tw['favorites']=$tweet->public_metrics->like_count;
    if (not_blank($tweet->public_metrics->reply_count)) $tw['replies']=$tweet->public_metrics->reply_count;

/******geo data of tweet (if any)******/
    if (not_blank($tweet->geo))
      {
    		if (sizeof($parent->includes->places)>0)
          {
      		 foreach($parent->includes->places as $p)
      		  {
      		    if ($tweet->geo->place_id==$p->id)
          			{
    		           if ($p->country) $tw['country']=$p->country."(".$p->country_code.")";
    		           if ($p->fullname) $tw['location_fullname']=$p->full_name;
    		           if ($p->name) $tw['location_name']=$p->name;
    		           if ($p->place_type) $tw['location_type']=$p->place_type;
                   break;
          			}
            }
          }
      }

    if (not_blank($tweet->attachments->media_keys))
     {
       foreach($tweet->attachments->media_keys as $media_key)
        {
    		 foreach($parent->includes->media as $med)
    		  {
    		    if ($media_key==$med->media_key)
        			{
                 if ($med->type=="photo" || $med->type=="animated_gif") $tw['has_image']=1;
                 elseif ($med->type=="video") $tw['has_video']=1;
                 if (strpos($tw['media_link'],$med->preview_image_url)===false) $tw['media_link']=$tw['media_link']." ".$med->preview_image_url;
                 if (strpos($tw['media_link'],$med->url)===false) $tw['media_link']=$tw['media_link']." ".$med->url;
        			}
          }
          $tw['media_link']=trim($tw['media_link']);
        }
     }

/******resume twitter data******/
    if (not_blank($tweet->entities))
     {
       $en=$tweet->entities;
        if (not_blank($en->hashtags))
           {
             foreach($en->hashtags as $h)
               {
                  $tw['hashtags']=$tw['hashtags']." #".strtolower($h->tag);
               }
             $tw['hashtags']=trim($tw['hashtags']);
             $hash_cloud=$hash_cloud." ".$tw['hashtags'];
           }
        if (not_blank($en->mentions))
          {
            foreach($en->mentions as $men)
               {
                $tw['user_mentions']=$tw['user_mentions']." @".strtolower($men->username);
               }
               $tw['user_mentions']=trim($tw['user_mentions']);
           }
        if (not_blank($en->urls))
          {
            $tw['has_link']=1;
            foreach($en->urls as $ur)
              {
                if (not_blank($ur->unwound_url)) $ur->expanded_url=$ur->unwound_url;
                if (strpos($tw['links'],$ur->url)===true || strpos($tw['expanded_links'],$ur->expanded_url)===true) continue;
                if (strpos($tw['links']=$tw['links'],$ur->url)===false) $tw['links']=$tw['links']." ".$ur->url;
                if (strpos($tw['expanded_links'],$ur->expanded_url)===false) $tw['expanded_links']=$tw['expanded_links']." ".$ur->expanded_url;
                if (not_blank($ur->title))
                  {
                    if (not_blank($ur->description))
                      {
                        $tw['raw_text']=$tw['raw_text']." --- ".$tw['expanded_links']." [".$ur->title." ".$ur->description."]---";
                      }
                  }
                if (not_blank($ur->images))
                  {
                    foreach ($ur->images as $img)
                      {
                         $tw['has_image']=1;
                         if (not_blank($img->url))
                          {
                            if (!(substr($img->url, 0, strlen("https://pbs.twimg.com/news_img")) === "https://pbs.twimg.com/news_img"
                                && substr($img->url, -strlen("name=150x150")) === "name=150x150"))
                                {
                                  if (strpos($tw['media_link'],$img->url)===false) $tw['media_link']=$tw['media_link']." ".$img->url;
                                }
                          }
                      }
                  }
              }
              $tw['links']=trim($tw['links']);
              $tw['expanded_links']=trim($tw['expanded_links']);
              $tw['media_link']=trim($tw['media_link']);
          }
      }
    if (not_blank($tweet->withheld))
     {
      if (not_blank($tweet->withheld->copyright)) $tw['withheld_copyright']=$tweet->withheld->copyright;
      if (not_blank($tweet->withheld->country_codes)) $tw['withheld_in_countries']=$tweet->withheld->country_codes;
     }

    if (not_blank($tw['links'])) $tw['links']=trim($tw['links']);
    if (not_blank($tw['expanded_links'])) $tw['expanded_links']=trim($tw['expanded_links']);

    $tw['clear_text']=strip_tags($tw['raw_text']);
    if (strpos($tw['clear_text'],"@")===0) $tw['is_message']=1; else $tw['is_message']=0;

    /******user data******/

    if (not_blank($parent->includes->users))
      {
        foreach ($parent->includes->users as $subt)
          {
            if ($subt->id == $tw['user_id'])
               {
                 $tw['user_screen_name']=$subt->username;
                 $tw['user_name']=$subt->name;
                 $tw['user_location']=$subt->location;
                 $tw['user_bio']=$subt->description;
                 $tw['user_image_url']=str_replace("_400x400.jpg","_200x200.jpg", str_replace("_normal.jpg","_200x200.jpg",$subt->profile_image_url));
                 $tw['user_verified']=$subt->verified;
                 $tw['tweet_permalink_path']="https://twitter.com/".$subt->username."/status/".$tweet->id;
                 add_user($subt);
               }
            else
              {
                  add_user($subt);
              }
          }
      }

    if (not_blank($tweet->referenced_tweets))
     {
       foreach ($tweet->referenced_tweets as $rtw)
        {
		      switch($rtw->type)
            {
      			  case "retweeted":
      				$tw['is_retweet']=1;
              $tw['retweeted_tweet_id']=$rtw->id;
              $tw['retweets']=0;
              if (not_blank($parent->includes->tweets))
                {
          				foreach ($parent->includes->tweets as $subt)
          				  {
          				    if ($subt->id == $rtw->id)
              					{
              					  $tmp_tw=extract_and_store_data($subt,$parent,$include_referenced,1);
                          foreach ($retweet_keys as $rk) { $tw[$rk]=$tmp_tw[$rk]; }
                          $tw['retweeted_user_id']=$tmp_tw['user_id'];
                          $tw['clear_text']="RT ".$tw['clear_text'];
                          $tw['raw_text']="RT ".$tw['raw_text'];
              					  break;
              					}
                      else extract_and_store_data($subt,$parent,$include_referenced,1);
          				  }
                }
              else
                {
                  die("Included tweets missing, exiting...\n");
                }
      				continue 2;
              case "quoted":
                $tw['is_quote']=1;
                $tw['quoted_tweet_id']=$rtw->id;
                foreach ($parent->includes->tweets as $subt)
                  {
                    if ($subt->id == $rtw->id)
                        {
                          $tmp_tw=extract_and_store_data($subt,$parent,$include_referenced,1);
                          $tw['retweeted_user_id']=$tmp_tw['user_id'];
                          break;
                        }
                  }
              continue 2;
              case "replied_to":
                $tw['is_reply']=1;
                $tw['in_reply_to_tweet']=$rtw->id;
                foreach ($parent->includes->tweets as $subt)
                  {
                    if ($subt->id == $rtw->id)
                        {
                          $tmp_tw=extract_and_store_data($subt,$parent,$include_referenced,1);
                          $tw['in_reply_to_user']=$tmp_tw['user_id'];
                          break;
                        }
                  }
              continue 2;
            }
         }
     }


    if ($save_to_db)
      {
        put_tweet_in_database($tw);
        $added_tweets_list[]=$tweet->id;
      }
    return $tw;
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

function put_tweet_in_database($tweet)
  {
    global $table; global $i;
    global $link; global $fix_utf8;
    if ($tweet)
     {
        $query = "REPLACE INTO `$table` ";
        $fields=array_keys($tweet); $names=""; $values="";
        foreach ($fields as $field)
         {
            $names=$names."$field,";
            $values=$values."'".$link->real_escape_string($tweet[$field])."',";
         }
        $names=rtrim($names,","); $values=rtrim($values,",");
        $query="$query ($names) VALUES($values)";


       $result = $link->query($query);
       if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
      }
 }
function update_response_mentions()
      {
        global $table; global $link; global $mysql_db;
        $all_m="user_all_mentions_"."$table"; $u_m="user_mentions_".$table;
        echo "Adding replies, replies to tweeter and mentions of tweeter data to table ...<br>\n";

        $query="UPDATE `users_".$table."` SET `users_".$table."`.`not_in_search_results`=1 ".
                "WHERE NOT EXISTS (SELECT 1 FROM `".$table."` WHERE `".$table."`.`user_screen_name`=`users_".$table."`.`user_screen_name`)";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="CREATE TABLE IF NOT EXISTS $all_m like 1_empty_all_mentions";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="TRUNCATE TABLE $all_m";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

/*** Add reply data (to tweets and to tweeter) ***/
        $query="INSERT INTO $all_m (tweet_id,replies) (SELECT $table.in_reply_to_tweet,count($table.tweet_id) FROM $table WHERE $table.is_protected_or_deleted is null and $table.date_time is not null AND $table.in_reply_to_tweet is not null group by $table.in_reply_to_tweet order by count($table.tweet_id) desc)";
      	$result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

      	$query="UPDATE IGNORE $all_m,$table SET $all_m.user_screen_name = LOWER($table.user_screen_name), $all_m.user_id = $table.user_id  WHERE $all_m.tweet_id = $table.tweet_id";
      	$result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

      	$query="UPDATE $all_m,$table SET $all_m.responses_to_tweeter=(SELECT count($table.tweet_id) FROM $table WHERE $table.in_reply_to_user is not null AND $table.is_protected_or_deleted is null and $table.date_time is not null AND $all_m.user_id=$table.in_reply_to_user group by $table.in_reply_to_user) WHERE $all_m.user_id=$table.in_reply_to_user";
      	$result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="CREATE TABLE IF NOT EXISTS $u_m LIKE 1_empty_user_mentions";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="TRUNCATE TABLE $u_m";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="DROP FUNCTION IF EXISTS SPLIT_STRING";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="CREATE FUNCTION SPLIT_STRING(str VARCHAR(255), delim VARCHAR(12), pos INT) RETURNS VARCHAR(255) RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos), LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1), delim, '')";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

/*** Add mention data (upto 20 mentions per tweet) ***/

        $mentions="mention1"; $mentions2="SPLIT_STRING($table.user_mentions, ' ', 1)";
        for ($i=2; $i<=20; $i++) { $mentions=$mentions.", mention$i"; $mentions2=$mentions2.",SPLIT_STRING($table.user_mentions, ' ', $i)"; }

        $query="INSERT INTO $u_m(tweet_id,user_id,user_screen_name, $mentions) select $table.tweet_id, $table.user_id, LOWER($table.user_screen_name), $mentions2 ".
        "from $table where $table.user_mentions is not null or ($table.in_reply_to_tweet is not null and $table.in_reply_to_user is not null AND $table.is_protected_or_deleted is null and $table.date_time is not null)";

        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        for ($i=1; $i<=20; $i++)
          {
            $query="INSERT INTO `$all_m` (user_screen_name,mention$i) (SELECT SUBSTR($u_m.mention$i,2),count($u_m.tweet_id) AS counts FROM $u_m WHERE $u_m.mention$i<>'' group by $u_m.mention$i order by count($u_m.tweet_id) desc) on duplicate key update $all_m.mention$i=VALUES(mention$i)";
            $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
          }
        $mentions="sum(mention1)";
        for ($i=2; $i<=20; $i++) { $mentions=$mentions."+sum(mention$i)"; }
        $query="UPDATE $all_m r JOIN (SELECT user_screen_name,$mentions as mt FROM $all_m group by user_screen_name) u ON r.user_screen_name=u.user_screen_name SET r.mentions_of_tweeter=u.mt";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$all_m set $table.replies=$all_m.replies where $table.tweet_id=$all_m.tweet_id and $all_m.tweet_id is not null";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$all_m set $table.responses_to_tweeter=$all_m.responses_to_tweeter where $table.user_id=$all_m.user_id";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

        $query="update $table,$all_m set $table.mentions_of_tweeter=$all_m.mentions_of_tweeter where LOWER($table.user_screen_name)=LOWER($all_m.user_screen_name)";
        $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

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

        echo "\nDone with updating user mentions and replies...\n";
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
          LOWER($table.user_screen_name) as screen_name,
          $table.user_image_url,
          '' as profile_link,
          '' as tweet_type,
          $table.date_time,
          $table.raw_text,
          $table.tweet_permalink_path,
          $table.hashtags,
          $table.tweet_language,
          $table.source,
          $table.retweets,
          $table.quotes,
          $table.favorites,
          $table.replies,
          LOWER($table.user_mentions) as user_mentions,
          $table.user_name,
          $table.user_location,
          $table.user_lang,
          $table.user_bio,
          $table.user_verified,
          $table.in_reply_to_user,
          $table.is_retweet,
          $table.is_quote,
          $table.is_reply,
          $table.has_image,
          $table.has_video,
          $table.has_link,
          $table.location_name,
          $table.country
          FROM $table WHERE $table.is_protected_or_deleted is null and $table.date_time is not null ORDER BY retweets DESC";

        	$first_line=array("Label","Image","Profile Link","Type","Date","Tweet Text","Tweet Link","Tags","Tweet Language","Source",
        	"Retweets","Favorites","Quotes","Replies","User Mentions","User Full Name","User Location","User Language","User Bio",
        	"User Verified","In Reply to User","Is a Retweet","Has an Image","Has a Video","Has a Link","Media Link",
        	"Other Links","Tweeted From Location","Tweeted from Country");

          if ($result = $link->query($query))
              {
                if (!$result->num_rows) { echo "No rows in the database matched your query.<br>\n";  }
                $total=$result->num_rows;
              }
          else die("Error in query: ". $link->error.": $query");

          $fp=fopen("tmp/kumu/$table"."_"."top_tweets_".$toplimit.".csv",'w');
          fputcsv($fp, $first_line);
        	$ind=0;
          while ($row = $result->fetch_assoc())
            {
          		if ($ind==$toplimit) break;
          		$row['profile_link']="https://twitter.com/".$row['screen_name'];
          		if ($row['is_retweet']) $row['tweet_type']="Retweet";
              elseif ($row['is_reply']) $row['tweet_type']="Tweet with reply";
          		elseif ($row['has_image']) $row['tweet_type']="Tweet with image";
          		elseif ($row['has_video']) $row['tweet_type']="Tweet with video";
          		elseif ($row['has_link']) $row['tweet_type']="Tweet with link";
          		else $row['tweet_type']="Regular tweet";
          		$row['raw_text']=preg_replace("/[\r\n]+/"," ",$row['raw_text']);
              $row['raw_text']=str_replace("\"","'",$row['raw_text']);
          		$row['bio']=preg_replace("/[\r\n]+/"," ",$row['bio']);
          		$row['bio']=str_replace("\"","'",$row['bio']);
              $row['hashtags']=preg_replace("/\s+/","|",$row['hashtags']);
              $row['user_mentions']=preg_replace("/\s+/","|",$row['user_mentions']);
          		fputcsv($fp, $row);
          		$ind++;
            }
         fclose($fp);
         echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."top_tweets_".$toplimit.".csv'>file ($table"."_"."top_tweets_".$toplimit.".csv)</a><br>\n";
        }

      $query = "SELECT LOWER(user_screen_name) FROM users_".$table." WHERE user_screen_name is not null";
      if ($result = $link->query($query))
        {
          if (!$result->num_rows) { echo "No users in the database matched your query.<br>\n";  }
          $total=$result->num_rows;
        }
      else die("Error in query: ". $link->error.": $query");
      $valid_users=array();
      while ($row = $result->fetch_array())
        {
      		$valid_users[$row[0]]=1;
	      }

    	echo "Kumu: Doing $table <br>\nCreating connection for replies...";
    	$t="user_mentions_".$table;

    	$query= "SELECT LOWER($t.user_screen_name) as screen_name,LOWER($t.in_response_to_user_screen_name) as response_screen_name,".
      "'' as tweet_type,$t.in_response_to_tweet,$table.is_retweet,".
  		"$table.is_quote,$table.is_reply,$t.tweet_datetime,$table.tweet_permalink_path,$table.user_verified,$table.has_image,$table.has_video,".
  		"$table.has_link,$table.media_link,$table.expanded_links,$table.source,$table.location_name,$table.country,".
  		"$table.tweet_language,$table.raw_text,$table.hashtags,$table.user_mentions,$table.retweets,$table.quotes,$table.replies,$table.favorites,$t.tweet_id ".
  		"FROM $t,$table WHERE $t.in_response_to_user_screen_name is not null and $t.user_screen_name is not null ".
  		"AND $table.is_protected_or_deleted is null and $table.date_time is not null ".
  		"and $t.tweet_id=$table.tweet_id order by $table.retweets DESC";

      if ($result = $link->query($query))
          {
            if (!$result->num_rows) { echo "No data rows in the database matched your query.<br>\n";  }
            $total=$result->num_rows;
          }
      else die("Error in query: ". $link->error.": $query");

      $first_line=array("From","To","Type","Date","Link","From_Verified_User","Is_Image",
      "Is_Video","Is_Link","Media_Link","Other_Links","Source","Location",
	    "Language","Content","Tags","Mentions","Retweets","Quotes","Replies","Favorites","Tweet_ID");
	    $all_responses=array(); $all_users=array();
	    $indx=0;
      $fp=fopen("tmp/kumu/$table"."_responses.csv",'w');
      fputcsv($fp, $first_line);
      while ($row = $result->fetch_assoc())
        {
          $new_row=array(); foreach ($first_line as $item) { $new_row[$item]=""; }
          $new_row['From']=ltrim($row['screen_name'],'@');
          $new_row['To']=ltrim($row['response_screen_name'],'@');
      		if (!not_blank($valid_users[$row['screen_name']])) { /*echo "Skipping (${row[0]})...";*/ continue; }
      		if (!not_blank($valid_users[$row['response_screen_name']])) { /*echo "Skipping (${row[1]})...";*/ continue; }
      		if ($row['is_retweet']) { $new_row['Type']="Retweet"; }
          elseif ($row['is_quote']) { $new_row['Type']="Quote of a tweet"; }
          elseif ($row['is_reply']) { $new_row['Type']="Reply to tweet"; }
          else $new_row['Type']="Regular tweet";
          if ($row['location_name'] || $row['country'])
            { $new_row['Location']=trim($row['location_name'].", ".$row['country']); }
          $new_row['Content']=str_replace("\"","'",preg_replace("/[\r\n]+/"," ",$row['raw_text']));
          if ($row['hashtags']) $new_row['Tags']=preg_replace("/\s+/","|",$row['hashtags']);
          if ($row['user_mentions']) $new_row['Mentions']=preg_replace("/\s+/","|",$row['user_mentions']);
      		$all_responses[$row['tweet_id']]=1;
      		if (!not_blank($all_users[$row['screen_name']])) $all_users[$row['screen_name']]=1;
      		if (!not_blank($all_users[$row['response_screen_name']])) $all_users[$row['response_screen_name']]=1;

          $new_row['Date']=$row['tweet_datetime'];
          $new_row['Link']=$row['tweet_permalink_path'];
          $new_row['From_Verified_User']=$row['user_verified'];
          $new_row['Is_Image']=$row['has_image'];
          $new_row['Is_Video']=$row['has_video'];
          $new_row['Is_Link']=$row['has_link'];
          $new_row['Media_Link']=$row['media_link'];
          $new_row['Other_Links']=$row['expanded_links'];
          $new_row['Source']=$row['source'];
          $new_row['Location']=$row['location_name'];
          $new_row['Language']=$row['tweet_language'];
          $new_row['Tags']=$row['hashtags'];
          $new_row['Retweets']=$row['retweets'];
          $new_row['Quotes']=$row['quotes'];
          $new_row['Replies']=$row['replies'];
          $new_row['Favorites']=$row['favorites'];
          $new_row['Tweet_ID']=$row['tweet_id'];

          fputcsv($fp, $new_row);
      		$indx++;
      		if ($indx>=$max_kumu_size) break;
        }

      fclose($fp);
      echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."responses.csv'>file ($table"."_"."responses.csv)</a><br>\n";

      $mentions="mention1";
      for ($i=2; $i<=20; $i++) { $mentions=$mentions.",$t.mention$i"; }

      $query= "SELECT LOWER($t.user_screen_name) as screen_name,$mentions,$t.tweet_datetime,$table.is_retweet,$table.is_quote,".
  		"$table.is_reply,$table.tweet_permalink_path,$table.user_verified,$table.has_image,$table.has_video,".
      "$table.has_link,$table.media_link,$table.expanded_links,$table.source,$table.location_name,$table.country,".
      "$table.tweet_language,$table.raw_text,$table.hashtags,$table.user_mentions,$table.retweets,$table.quotes,$table.replies,$table.favorites,$t.tweet_id ".
      "FROM $t,$table WHERE $t.mention1>'' and $t.user_screen_name is not null and $t.tweet_id=$table.tweet_id ".
  		"AND $table.is_protected_or_deleted is null and $table.date_time is not null ".
  		"order by $table.retweets DESC";

      if ($result = $link->query($query))
          {
            if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
            $total=$result->num_rows;
          }
      else die("Error in query: ". $link->error.": $query");
      $first_line=array("From","To","Type","Date","Position","Link","From_Verified_User","Is_Image","Is_Video",
      "Is_Link","Media_Link","Other_Links","Source","Location","Language","Content","Tags",
      "Mentions","Retweets","Quotes","Replies","Favorites","Tweet_ID");
	    $all_mentions=array();
	    $indx=0;
	    $fp=fopen("tmp/kumu/$table"."_"."mentions.csv",'w');
	    fputcsv($fp, $first_line);
      while ($row = $result->fetch_assoc())
        {
          $new_row=array(); foreach ($first_line as $item) { $new_row[$item]=""; }
          $new_row['From']=ltrim($row['screen_name'],'@');
      		$new_row['To']=ltrim($row['mention1'],'@');
      		if (!not_blank($valid_users[$row['screen_name']])) continue;
	        if (!not_blank($all_responses[$row['tweet']]))
      		   {
          			$mention="mention only";
          			if (!not_blank($all_mentions[$row['tweet']])) $all_mentions[$row[$row['tweet']]]=1;
      		   }
      		else
      		   {
      		     $mention="response and mention";
      		   }
          if ($row['location_name'] || $row['country']) { $new_row['Location']=trim($row['location_name'].", ".$row['country']); }
          if ($row['hashtags']) $new_row['Tags']=preg_replace("/\s+/","|",$row['hashtags']);
          if ($row['user_mentions']) $new_row['Mentions']=preg_replace("/\s+/","|",$row['user_mentions']);
          $new_row['Content']=str_replace("\"","'",preg_replace("/[\r\n]+/"," ",$row['raw_text']));
          if (!not_blank($all_users[$row['screen_name']])) $all_users[$row['screen_name']]=1;

          $new_row['Position']=1;
          $new_row['Date']=$row['tweet_datetime'];
          $new_row['Link']=$row['tweet_permalink_path'];
          $new_row['From_Verified_User']=$row['user_verified'];
          $new_row['Is_Image']=$row['has_image'];
          $new_row['Is_Video']=$row['has_video'];
          $new_row['Is_Link']=$row['has_link'];
          $new_row['Media_Link']=$row['media_link'];
          $new_row['Other_Links']=$row['expanded_links'];
          $new_row['Source']=$row['source'];
          $new_row['Location']=$row['location_name'];
          $new_row['Language']=$row['tweet_language'];
          $new_row['Retweets']=$row['retweets'];
          $new_row['Quotes']=$row['quotes'];
          $new_row['Replies']=$row['replies'];
          $new_row['Favorites']=$row['favorites'];
          $new_row['Tweet_ID']=$row['tweet_id'];

          if (not_blank($valid_users[$row['mention1']]))
            {
               if (!not_blank($all_users[$row['mention1']])) $all_users[$row['mention1']]=1;
        		   fputcsv($fp, $new_row);
      		  }
      		for($i=2; $i<=20; $i++)
      		  {
              $m_i="mention".(string)$i;
      		    if (!$row[$m_i]) break;
              $row[$m_i]=ltrim($row[$m_i],'@');
      		    if (!not_blank($valid_users[$row[$m_i]])) continue;
              if (!not_blank($all_users[$row[$m_i]])) $all_users[$row[$m_i]]=1;
              $new_row['To']=$row[$m_i];
              $new_row['Type']="mention only";
              $new_row['Position']=$i;
      		    fputcsv($fp, $new_row);
      		  }
      		$indx++;
          if ($indx>=$max_kumu_size) break;
  	     }
      fclose($fp);
      echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."mentions.csv'>file ($table"."_"."mentions.csv)</a><br>\n";

      echo "\nKumu: Creating elements for users...";
      $first_line=array("Label","Image","User Verified","Link","Bio","Language","Location","Tweets","Followers",
		  "Following","Favorites","Lists","Created Date","Profile Page");
      $fp=fopen("tmp/kumu/$table"."_"."users.csv",'w');
      fputcsv($fp, $first_line);

      $query= "SELECT LOWER(user_screen_name),user_image_url,user_verified,user_url,user_bio,user_lang,user_location,user_tweets,user_followers,user_following,user_favorites,user_lists,user_created FROM users_".$table;

      if ($result = $link->query($query))
          {
            if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
            $total=$result->num_rows;
          }
      else die("Error in query: ". $link->error.": $query");

      while ($row = $result->fetch_array(MYSQLI_NUM))
        {
          if (!not_blank($all_users[$row[0]])) continue;
      		$row[13]="https://twitter.com/".$row[0];
          $row[4]=preg_replace("/[\r\n]+/"," ",$row[4]);
  		    $row[4]=str_replace("\"","'",$row[4]);
      		fputcsv($fp, $row);
        }
     fclose($fp);
     echo "Kumu: Saved CSV <a href='tmp/kumu/$table"."_"."users.csv'>file ($table"."_"."users.csv)</a><br>\n";
}

function update_cases_table($mode)
      {
        global $table; global $link;
        if ($mode=="started") { echo "Recorded starting!\n"; $add_compl=",last_process_completed='0000-00-00 00:00:00'"; }
        else { echo "Recorded completed!\n"; $add_compl=""; }
        $query="update cases set last_process_"."$mode=NOW()$add_compl,status='$mode' where id='$table'";
        $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
      }

function cUrlGetData($url)
 {
   global $twitter_api_settings; global $limit_remaining; global $limit_reset; global $full_header;
#sandbox token
	$token="Bearer ".$twitter_api_settings['bearer'];

#academic token
#    	$token="Bearer AAAAAAAAAAAAAAAAAAAAAKGfgAAAAAAADOYTT8XoXgcQ4e48zcr%2FYnoZv6g%3DokDOzVRPl58vp48OzxBakZQ83LnULG2WlG1kLoVAKsPfn6Q09l";

  $ch = curl_init( $url );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	   'Content-Type: application/json',
	   'Authorization: '.$token
     ));

  curl_setopt($ch, CURLOPT_HEADER, 1);

  $response = curl_exec( $ch );

  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $full_header = substr($response, 0, $header_size);
  $data = substr($response, $header_size);

  $matches = array();
  preg_match('/x-rate-limit-remaining: ([0-9]+)[\s]+x-rate-limit-reset: (.+)/', $full_header, $matches);

  $limit_remaining=$matches[1];
  $limit_reset=$matches[2];

  $err     = curl_errno( $ch );
  $errmsg  = curl_error( $ch );
  $info  = curl_getinfo( $ch );

	if ($err) {
    	echo 'Error:' . curl_error($ch);
	}

	curl_close($ch);

	return $data;
}


function complete_url($qry)
 {
   global $start_time; global $end_time; global $max_per_page;
   global $since_id; global $until_id; global $next_token;
   global $twitter_api_settings; global $table; global $link; global $fresh_start;

	$fields="tweet.fields=created_at,author_id,public_metrics,entities,geo,in_reply_to_user_id,lang,referenced_tweets,attachments,context_annotations,source,conversation_id,withheld,possibly_sensitive&place.fields=contained_within,country,country_code,full_name,geo,id,name,place_type&user.fields=created_at,public_metrics,description,entities,id,location,name,pinned_tweet_id,profile_image_url,protected,url,username,verified,withheld&media.fields=duration_ms,height,media_key,preview_image_url,public_metrics,type,url,width&expansions=author_id,referenced_tweets.id,in_reply_to_user_id,attachments.media_keys,geo.place_id,entities.mentions.username,referenced_tweets.id.author_id";

  $query="SELECT status FROM cases where id='$table'";
  $result=$link->query($query); if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
  $status = $result->fetch_assoc();

  if ($fresh_start)
    {
      if ($status['status']=="expanded_right")
        {
          $query= "SELECT tweet_id,date_time from $table WHERE NOT is_referenced order by tweet_id DESC";
          if ($result = $link->query($query)) { if (!($result->num_rows)) $oldest_tweet_id=""; }
          else die("Error in query: ". $link->error.": $query");
          $row = $result->fetch_assoc();

          if ($row)
            {
              $newest_tweet_id=$row['tweet_id'];
              $newest_tweet_time=$row['date_time'];

              if ($end_time) $et=str_replace(".00Z","",str_replace("T"," ",$end_time));
              else { $et = new DateTime(gmdate("Y-m-d H:i:s")); $et=$et->format('Y-m-d H:i:s'); }

              if ($newest_tweet_time<$et)
                {
                  $start_time=str_replace(" ","T",$newest_tweet_time).".00Z";
                  note("Continuing to get tweets posted after $newest_tweet_id at ".$newest_tweet_time."\n");
                }
            }
        }
      elseif ($status['status']=="expanded_left" || $status['status']!="completed")
          {
            $query= "SELECT tweet_id,date_time from $table WHERE NOT is_referenced order by tweet_id ASC";
            if ($result = $link->query($query)) { if (!($result->num_rows)) $oldest_tweet_id=""; }
            else die("Error in query: ". $link->error.": $query");
            $row = $result->fetch_assoc();

            if ($row)
              {
                $oldest_tweet_id=$row['tweet_id'];
                $oldest_tweet_time=$row['date_time'];

                if ($start_time) $st=str_replace(".00Z","",str_replace("T"," ",$start_time));
                else { $st = new DateTime(gmdate("Y-m-d H:i:s")); $st->modify('-7 day'); $st=$st->format('Y-m-d H:i:s'); }
                if ($oldest_tweet_time>$st)
                  {
                      $end_time=str_replace(" ","T",$oldest_tweet_time).".00Z";
                      note("Getting tweets before ".$oldest_tweet_time."\n");
                  }
              }
          }
      update_cases_table("started");
      $fresh_start=false;
    }

  if (!$twitter_api_settings['is_premium']) $search_mode="recent";
  else $search_mode="all";

  $period="";
  if ($start_time) $period="&start_time=$start_time";
  if ($end_time) $period=$period."&end_time=$end_time";
  $url="https://api.twitter.com/2/tweets/search/$search_mode?query=$qry&max_results=$max_per_page$next_token$until_id$since_id$period&$fields";

  echo "\nURL:\n----\n$url\n----\n";
	return $url;
 }

function getapi_record($getfield)
  {
    global $twitter_api_settings; global $last_setting;
    global $limit_remaining; global $limit_reset; global $full_header;

    if ($limit_remaining==0)
      {
          echo "Rate exceeded, resuming in ".(string)($limit_reset-time())." seconds\n";
          sleep($limit_reset-time());
      }

    $url=complete_url($getfield);

    $response = cUrlGetData($url);
    $record=json_decode($response);

    if (not_blank($record->status))
      {
        echo "Status error getapi_record: \n";
        note("\n---Header-----\n$full_header\n-------\n");
        print_r($record);
        node("1errors: "); var_dump($response);
        return "";
      }

    if (not_blank($record->errors))
      {
        echo "Error getapi_record: \n";
        var_dump($record->errors);
        foreach ($record->errors as $error)
            {
              if ($error->parameters->query[0]) { echo "Query: ".$error->parameters->query[0]."\n"; }
              echo $error->title."\n";
              echo $error->message."\n";
              echo $error->detail."\n";
            }
      }

    if (not_blank($record->meta->result_count))
      {
        if ($record->meta->result_count===0)
          {
            echo "The rearch returned no results.";
            return;
          }
        else
          {
            echo "The rearch returned ".$record->meta->result_count." results.\n";
            return $record;
          }
      }
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
      if ($header['header_size'])
        return $header['url']." ".$header['content_type'];
      return "";
   }

function url_get_contents($url)
  {
    global $failed_proxy;
    if (!function_exists('curl_init')) die('CURL is not installed!');
    $header = array(
    'accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
    $ch = curl_init();
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

 function note($line)
    {
          echo $line;
    }

function put_user_in_database($user)
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
     $result = $link->query($query);
     if (!$result) die("Invalid query: " . $link->sqlstate. "<hr>$query<hr>");
    }

function draw_network($table)
  {
    global $link;
    $maximum_strength=5;
    $minimum_strength=0;
    $limit=10;

    update_kumu_files($table);
    echo "\nstart drawing\n";

    echo "START DRAWING<br>\n";
    connect_mysql();
    $qry= "SELECT user_id,user_screen_name,user_name,user_followers,user_verified FROM users_".$table." "; /*,user_image_url,user_location*/
    $condition="WHERE user_screen_name is not null" ;

    $query = "$qry $condition order by user_followers desc";

    if ($result = $link->query($query))
        {
          if (!$result->num_rows) echo "No results in the database matched your query.<br>\n";
          $total=$result->num_rows;
        }
    else die("Error in query: ". $link->error.": $query");

    $nodes=""; $i=0; $all_nodes=array();
    while ($row = $result->fetch_array())
      {
        $tmpnode=strtolower($link->real_escape_string($row[1]));
        $nodes=$nodes."g.nodes.push({ id: '${row[0]}', label: '$tmpnode', x: Math.random(), y: Math.random(), size: ".($row[3]).", color: 'FF8000' });\n";
        $all_nodes1[$row[0]]=$tmpnode;
        $i++;
      }
echo "\n\nSTEP 1 (users) DONE\n\n";

    $total_nodes=$i;
    $qry= "select user_id,user_screen_name,in_response_to_user_id,count(tweet_id) from user_mentions_".$table;
    $condition="WHERE in_response_to_user_screen_name is not null";
    $query = "$qry $condition group by concat(user_id, ' ', in_response_to_user_id) order by count(tweet_id) desc";
    if ($result = $link->query($query))
      {
        if (!$result->num_rows) echo "No results in the database matched your query.<br>\n";
        $total=$result->num_rows;
      }
    else die("Error in query: ". $link->error.": $query");
    if ($result->num_rows)
     {
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
       }
      for ($i=$maximum_strength; $i>$minimum_strength; $i--)
        {
          file_put_contents("tmp/network/$table"."_"."$i.csv",$edges[$i]);

          echo "Saved CSV <a href='tmp/network/$table"."_"."$i.csv'>file ($table"."_"."$i.csv)</a>";
        }
      }
      echo "\n\nSTEP 2 (replies) DONE\n\n";

      $mentions="mention1";
      for ($i=2; $i<=20; $i++) { $mentions=$mentions.",mention$i"; }

      $qry= "select user_screen_name,$mentions from user_mentions_".$table;
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

         while ($row = $result->fetch_array())
          {
           for ($kk=1; $kk<=10; $kk++)
            {
              $row[$kk]=ltrim($row[$kk],'@');
              if (!in_array($row[0],$all_nodes1)) continue;
              if (!in_array($row[$kk],$all_nodes1)) continue;
              if (!$edges[$row[0].",".$row[$kk]]) $edges[$row[0].",".$row[$kk]]=0;
              $edges[$row[0].",".$row[$kk]]++;
            }
          }
        $edge_arr=array();
        for ($i=$maximum_strength; $i>$minimum_strength; $i--) { $edge_arr[$i]=$header; }
        $edges_keys=array_keys($edges);
        foreach ($edges_keys as $edg)
         {
          for ($i=$maximum_strength; $i>$minimum_strength; $i--)
             { if ($edges[$edg]>$i) $edge_arr[$i]=$edge_arr[$i].$edg.",".$edges[$edg]."\n"; }
         }
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
  }

function startswith($haystack, $needle) {
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

?>

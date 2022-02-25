<?php
error_reporting(0);
require_once("configurations.php");
$debug=0;

ini_set('max_execution_time', 3000);
ini_set('memory_limit', '1024M');

date_default_timezone_set('America/New_York');

if (!$argv[2] && !$_SESSION[basename(__DIR__)])
  {
    die("<b>You are logged out. Please <a href='index.php?id=tweeters'>Return to the main page</a> to log in again.</b><br><hr>");
  }

$overview=$_GET['overview'];
$table=$_GET['table'];
$user_screen_name=$_GET['user'];
$rank=$_GET['i'];
$responses=$_GET['responses2'];
$mentions=$_GET['mentions'];
$level=$_GET['level'];
if ($_GET['limit']) $limit=$_GET['limit'];
else $limit=10;

$maximum_strength=5;
$minimum_strength=0;

$params=array("followers","retweets","responses","responders","mention","all_mentions","quotes","tweets");

if ($responses)
  {
    if ($table)
      {
        if (file_exists("tmp/network/$table"."_"."$level.csv"))
          {
            $size=filesize("tmp/network/$table"."_"."$level.csv");
            if ($size>10000) { $w=(6-$level)*1000; $h=(7-$level)*1000; }
            else { $w=1800; $h=1200; }
            $graph_data=file_get_contents("templates/template-nodes.html");
            $graph_data=str_replace("<!--w-->",$w,$graph_data);
            $graph_data=str_replace("<!--h-->",$h,$graph_data);
            $graph_data=str_replace("<!--table-->",$table."_"."$level",$graph_data);
            $graph_data=str_replace("<!--case-->",$table,$graph_data);
            echo "$graph_data";
          }
        else echo "The results don't have enough connections to form network graphs. Try fetching more records...";
      }
  }
if ($mentions)
  {
    if ($table)
      {
        if (file_exists("tmp/network/$table"."_mentions_"."$level.csv"))
          {
            $size=filesize("tmp/network/$table"."_mentions_"."$level.csv");
            if ($size>10000) { $w=(6-$level)*1000; $h=(7-$level)*1000; }
            else { $w=1800; $h=1200; }
            $graph_data=file_get_contents("templates/template-nodes.html");
            $graph_data=str_replace("<!--w-->",$w,$graph_data);
            $graph_data=str_replace("<!--h-->",$h,$graph_data);
            $graph_data=str_replace("<!--table-->",$table."_mentions_"."$level",$graph_data);
            $graph_data=str_replace("<!--case-->",$table,$graph_data);
            echo "$graph_data";
          }
        else echo "The results don't have enough connections to form network graphs. Try fetching more records...";
      }
  }
elseif ($overview)
    {
      foreach ($params as $p)
        {
          if ($_GET[$p]) get_top($p,$limit);
        }
    }
elseif ($user_screen_name) show_profile($rank,$table,$user_screen_name);

function show_profile($rank,$table,$user_screen_name)
  {
    global $link; global $cases;

    $qry= "SELECT users_".$table.".user_id,users_".$table.".user_name,users_".$table.".user_image_url,users_".$table.".user_lang,users_".$table.".user_location,users_".$table.".user_timezone,users_".$table.".user_tweets,".
    "users_".$table.".user_followers,users_".$table.".user_following,users_".$table.".user_favorites,users_".$table.".user_lists,users_".$table.".user_verified,users_".$table.".user_bio,users_".$table.".user_created,count($table.tweet_id) as a,".
    "sum($table.retweets) as b,sum($table.replies) as c,sum($table.quotes) as d, $table.mentions_of_tweeter as d2,sum($table.favorites) as e FROM $table, users_".$table." ";
    $condition="WHERE users_".$table.".user_screen_name='".$user_screen_name."' AND $table.user_screen_name='".$user_screen_name."'" ;
    $query="$qry $condition";
    if ($result = $link->query($query))
          {
            if (!$result->num_rows) die("No results in the database matched your query.<br>\n");
            $total=$result->num_rows;
          }
        else die("Error in query: ". $link->error.": $query");

   $row = $result->fetch_assoc();
   echo "<table style='font-size:10pt; width:600px; background-color:#FFFFFF; border:1px; margin-left:30px'><tr><td colspan=2><h1><center>$rank</center></h1></td></tr>";

   if (!$row['a'])
    {
     $qry= "SELECT users_".$table.".user_id,users_".$table.".user_name,users_".$table.".user_image_url,users_".$table.".user_lang,users_".$table.".user_location,users_".$table.".user_timezone,users_".$table.".user_tweets,".
     "users_".$table.".user_followers,users_".$table.".user_following,users_".$table.".user_favorites,users_".$table.".user_lists,users_".$table.".user_verified,users_".$table.".user_bio,users_".$table.".user_created FROM users_".$table." ";

     $condition="WHERE users_".$table.".user_screen_name='".$user_screen_name."'" ;
     $query="$qry $condition";
     if ($result = $link->query($query))
           {
             if (!$result->num_rows) die("No results in the database matched your query.<br>\n");
             $total=$result->num_rows;
           }
         else die("Error in query: ". $link->error.": $query");
         $row = $result->fetch_assoc();
         if ($row['user_name']) $row['a']=0;
     }
   if ($row['a']===0 || $row['a'])
    {
        echo "<tr><td><img src='".str_replace("_normal.","_200x200.",$row['user_image_url'])."' alt='$user_screen_name profile image' style='display: inline-block;' width=200></td><td>\n";
        if ($row['user_verified']) echo "<img src='images/twitter-verified.jpg' width=100 alt='$user_screen_name verified' style='display: inline-block;'>\n";
        echo "<a href='https://www.twitter.com/$user_screen_name' target=_blank><b>${row['user_name']} ($user_screen_name)</b></a></td></tr>\n";
        if ($row['user_bio']) echo "<tr><td>Bio</td><td><b>${row['user_bio']}</b></td</tr>\n";
        if ($row['user_location']) echo "<tr><td>Based in <b></td><td></b>${row['user_location']}</b> <i>(as indicated in profile)</i></td></tr>";
        if ($row['user_timezone']) echo "<tr><td>Timezone: </td><td><b>${row['user_timezone']}</b></td></tr>";
        echo "</td></tr><tr><td>Joined Twitter:</td><td><b>${row['user_created']}</b></td><tr><td>Contributed</td><td><b>".number_format($row['user_tweets'])." tweets</b></td></tr>\n";
        echo "<tr><td>Followers</td><td><b>".number_format($row['user_followers'])."</b></td></tr><tr><td>Following</td><td><b>".number_format($row['user_following'])."</b></td></tr>\n";
        echo "<tr><td>Lists</td><td><b>".number_format($row['user_lists'])."</b></td></td>\n";
        echo "</table><br>In connection to this case only <b> (".$cases[$table]['name'].")";
        echo "<table style='font-size:10pt; width:600px; background-color:#FFFFFF; border:1px; margin-left:30px'>";
        if ($row['a']>0)
          {
            echo "<tr><td>Tweets</td><td><b>".number_format($row['a'])."</b></td></tr>";
            echo "<tr><td>Retweets by others</td><td><b>".number_format($row['b'])."</b></td></tr>\n";
            echo "<tr><td>Responses from others</td><td><b>".number_format($row['c'])."</b></td></tr>\n";
            echo "<tr><td>Mentions by others</td><td><b>".number_format($row['d2'])."</b></td></tr>\n";
            echo "<tr><td>Quotes by others</td><td><b>".number_format($row['d'])."</b></td></tr>\n";
            echo "<tr><td>Favorites by others</td><td><b>".number_format($row['e'])."</b></td></tr>\n";
            echo "<tr><td colspan=2><center><b><a href='index.php?id=tweets&table=$table&user_screen_name=$user_screen_name&load=1' target=_blank>See the tweets</a></b></center></td></tr>\n";
          }
        else
          {
            echo "<tr style='background-color:#ECF0F0'><td colspan=2><br><center>@$user_screen_name has no tweets in the database.<br><br>You can <a href='https://twitter.com/$user_screen_name' target=_blank>go to the twitter profile page</a> directly instead.</a></center><br></td></tr>";
          }
    }
   else
    {
  	 echo "<tr style='background-color:#ECF0F0'><td colspan=2><br><center>@$user_screen_name has no tweets in the database.<br><br>You can <a href='https://twitter.com/$user_screen_name' target=_blank>go to the twitter profile page</a> directly instead.</a></center><br></td></tr>";
    }
   echo "</table></center>\n";
}

function get_top($type,$limit)
    {
    global $debug; global $admin_email;
    global $table; global $link; global $cases;
    $started=0;
    if ($_GET['location'])
      {
        $_GET['location']=trim($_GET['location']);
	$tmp=preg_split('/[\s,]+/',$_GET['location'], -1, PREG_SPLIT_NO_EMPTY);
        $c=""; $started=false;
        if ($type=="followers" || $type=="all_mentions") $t="users_".$table; elseif ($type=="quotes") $t="k1"; else $t=$table;
        $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
        foreach ($tmp as $k)
          {
            $k=strtolower($k);
            if (!$started) $c="AND ((LOWER($t.user_location) like '%".$k."%' OR LOWER($t.user_timezone) like '%".$k."%')";
            else $c=" OR (LOWER($t.user_location) like '%".$k."%' OR LOWER($t.user_timezone) like '%".$k."%')";
          }
          $condition=$condition." $c) ";
      }

      if ($_GET['bio'])
        {
          $_GET['bio']=trim($_GET['bio']);
	  $tmp=preg_split('/[\s,]+/',$_GET['bio'], -1, PREG_SPLIT_NO_EMPTY);
          $tmp=explode(",",$_GET['bio']);
          $c=""; $started=false;
          if ($type=="followers" || $type=="all_mentions") $t="users_".$table; elseif ($type=="quotes") $t="k1"; else $t=$table;
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
          foreach ($tmp as $k)
            {
              $k=strtolower($k);
              if (!$started) $c="AND (LOWER($t.user_bio) like '%".$k."%' ";
              else $c=" OR LOWER($t.user_bio) like '%".$k."%' ";
            }
            $condition=$condition." $c) ";
        }
/* to be implemented
      if ($_GET['language'])
        {
          $languages=array("af"=>"Afrikaans","sq"=>"Albanian","ar-dz"=>"Arabic(Algeria)","ar-bh"=>"Arabic(Bahrain)","ar-eg"=>"Arabic(Egypt)","ar-iq"=>"Arabic(Iraq)","ar-jo"=>"Arabic(Jordan)","ar-kw"=>"Arabic(Kuwait)","ar-lb"=>"Arabic(Lebanon)","ar-ly"=>"Arabic(libya)","ar-ma"=>"Arabic(Morocco)","ar-om"=>"Arabic(Oman)",
          "ar-qa"=>"Arabic(Qatar)","ar-sa"=>"Arabic(Saudi Arabia)","ar-sy"=>"Arabic(Syria)","ar-tn"=>"Arabic(Tunisia)","ar-ae"=>"Arabic(U.A.E.)","ar-ye"=>"Arabic(Yemen)","ar"=>"Arabic","hy"=>"Armenian","as"=>"Assamese","az"=>"Azeri(Cyrillic)","az"=>"Azeri(Latin)","eu"=>"Basque",
          "be"=>"Belarusian","bn"=>"Bengali","bg"=>"Bulgarian","ca"=>"Catalan","zh-cn"=>"Chinese(China)","zh-hk"=>"Chinese(Hong Kong SAR)","zh-mo"=>"Chinese(Macau SAR)","zh-sg"=>"Chinese(Singapore)","zh-tw"=>"Chinese(Taiwan)","zh"=>"Chinese","hr"=>"Croatian",
          "cs"=>"Chech","da"=>"Danish","div"=>"Divehi","nl-be"=>"Dutch(Belgium)","nl"=>"Dutch(Netherlands)","en-au"=>"English(Australia)","en-bz"=>"English(Belize)","en-ca"=>"English(Canada)","en-ie"=>"English(Ireland)","en-jm"=>"English(Jamaica)","en-nz"=>"English(New Zealand)",
          "en-ph"=>"English(Philippines)","en-za"=>"English(South Africa)","en-tt"=>"English(Trinidad)","en-gb"=>"English(United Kingdom)","en-us"=>"English(United States)","en-zw"=>"English(Zimbabwe)","en"=>"English","et"=>"Estonian","fo"=>"Faeroese","fa"=>"Farsi","fi"=>"Finnish",
          "fr-be"=>"French(Belgium)","fr-ca"=>"French(Canada)","fr"=>"French(France)","fr-lu"=>"French(Luxembourg)","fr-mc"=>"French(Monaco)","fr-ch"=>"French(Switzerland)","mk"=>"FYRO Macedonian","gd"=>"Gaelic","ka"=>"Georgian","de-at"=>"German(Austria)","de"=>"German(Germany)","de-li"=>"German(Liechtenstein)",
          "de-lu"=>"German(lexumbourg)","de-ch"=>"German(Switzerland)","el"=>"Greek","gu"=>"Gujarati","he"=>"Hebrew","hi"=>"Hindi","hu"=>"Hungarian","is"=>"Icelandic","id"=>"Indonesian","it"=>"Italian(Italy)","it-ch"=>"Italian(Switzerland)","ja"=>"Japanese",
          "kn"=>"Kannada","kk"=>"Kazakh","kok"=>"Konkani","ko"=>"Korean","kz"=>"Kyrgyz","lv"=>"Latvian","lt"=>"Lithuanian","ms"=>"Malay(Brunei)","ms"=>"Malay(Malaysia)","ml"=>"Malayalam","mt"=>"Maltese","mr"=>"Marathi",
          "mn"=>"Mongolian(Cyrillic)","ne"=>"Nepali(India)","nb-no"=>"Norwegian(Bokmal)","no"=>"Norwegian(Bokmal)","nn-no"=>"Norwegian(Nynorsk)","or"=>"Oriya","pl"=>"Polish","pt-br"=>"Portuguese(Brazil)","pt"=>"Portuguese(Portugal)","pa"=>"Punjabi","rm"=>"Rhaeto-Romanic","ro-md"=>"Romanian(Moldova)",
          "ro"=>"Romanian","ru-md"=>"Russian(Moldova)","ru"=>"Russian","sa"=>"Sanskrit","sr"=>"Serbian(Cyrillic)","sr"=>"Serbian(Latin)","sk"=>"Slovak","ls"=>"Slovenian","sb"=>"Sorbian","es-ar"=>"Spanish(Argentina)","es-bo"=>"Spanish(Bolivia)","es-cl"=>"Spanish(Chile)",
          "es-co"=>"Spanish(Colombia)","es-cr"=>"Spanish(Costa Rica)","es-do"=>"Spanish(Dominican Republic)","es-ec"=>"Spanish(Ecuador)","es-sv"=>"Spanish(El Salvador)","es-gt"=>"Spanish(Guatemala)","es-hn"=>"Spanish(Honduras)","es"=>"Spanish(International Sort)","es-mx"=>"Spanish(Mexico)",
          "es-ni"=>"Spanish(Nicaragua)","es-pa"=>"Spanish(Panama)","es-py"=>"Spanish(Paraguay)","es-pe"=>"Spanish(Peru)","es-pr"=>"Spanish(Puerto Rico)","es"=>"Spanish(Traditional Sort)","es-us"=>"Spanish(United States)","es-uy"=>"Spanish(Uruguay)","es-ve"=>"Spanish(Venezuela)","sx"=>"Sutu","sw"=>"Swahili",
          "sv-fi"=>"Swedish(Finland)","sv"=>"Swedish","syr"=>"Syriac","ta"=>"Tamil","tt"=>"Tatar","te"=>"Telugu","th"=>"Thai","ts"=>"Tsonga","tn"=>"Tswana","tr"=>"Turkish","uk"=>"Ukrainian","ur"=>"Urdu",
          "uz"=>"Uzbek(Cyrillic)","uz"=>"Uzbek(Latin)","vi"=>"Vietnamese","xh"=>"Xhosa","yi"=>"Yiddish","zu"=>"Zulu");

          $_GET['languages']=trim($_GET['languages']);
	  $tmp=preg_split('/[\s,]+/',$_GET['languages'], -1, PREG_SPLIT_NO_EMPTY);
          $c=""; $started=false;
          $lang_keys=array_keys($languages);
          if ($type=="followers" || $type=="all_mentions") $t="users_".$table.".user_lang"; elseif ($type=="quotes") $t="k2.user_lang"; else $t="$table.user_lang";

          foreach ($tmp as $k)
            {
              $k2=strtolower($k);
              if (!in_array($k2,$lang_keys))
                {
                  $k=ucwords($k);
                  foreach ($lang_keys as $l)
                    if ($k==$languages[$l]) { $k=$l; break; }
                }
              if (!$started) $c="AND ($t ='$k' ";
              else $c=" OR $t ='$k' ";
            }
          $condition=$condition." $c) ";
        }
*/
    $condition=preg_replace('/^\s*AND /','WHERE ',$condition);
    if ($type=="followers")
        {
          $title="Top $limit followed tweeters for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total number of followers as of November 2015";
          $yaxis="followers";
          $name="Top followed tweeters";
          if ($_GET['export'])
            $qry= "SELECT user_screen_name,user_name AS full_name,user_followers AS followers, user_verified, user_location, user_bio,CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM users_".$table." ";
	        else
            $qry= "SELECT user_screen_name,user_followers AS followers FROM users_".$table." ";
          if (substr($condition,0,5)==="WHERE") $condition=$condition." AND not_in_search_results IS NULL ";
          else $condition="WHERE not_in_search_results IS NULL ";
          $query = "$qry $condition order by user_followers desc";
        }
    elseif ($type=="retweets")
        {
          $title="Top retweeted tweeters for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total number of retweets";
          $yaxis="retweets";
          $name="Top retweeted tweeters";
          if ($_GET['export'])
            $qry= "SELECT user_screen_name,user_name AS full_name,user_screen_name,sum($table.retweets) AS retweets, user_verified, user_location, user_bio,CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM $table";
          else
            $qry= "SELECT $table.user_screen_name,sum($table.retweets) AS retweets FROM $table ";
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
          $condition="WHERE (is_retweet<>1) ".$condition." ";
          $query = "$qry $condition group by $table.user_screen_name order by sum($table.retweets) desc";
        }
    elseif ($type=="responses")
        {
          $title="Top tweeters who responded to others for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total number of responses sent";
          $yaxis="responses sent";
          $name="Top responding tweeters";
          if ($_GET['export'])
            $qry= "SELECT user_screen_name,user_name AS full_name,count(in_reply_to_user) AS replies,user_verified, user_location, user_bio,CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM $table ";
          else
            $qry= "SELECT user_screen_name,count(in_reply_to_user) AS replies FROM $table ";
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
          $condition="WHERE in_reply_to_user is not null ".$condition;
          $query = "$qry $condition group by user_screen_name order by count(in_reply_to_user) desc";
        }
    elseif ($type=="responders")
        {
          $title="Top tweeters with highest responses from others for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total total number of responses received";
          $yaxis="responses received";
          $name="Top responded to tweeters ";
          if ($_GET['export'])
	         $qry= "SELECT user_screen_name,user_name AS full_name,
responses_to_tweeter AS repliers,mentions_of_tweeter,user_verified,user_location,user_bio,CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM $table";
          else
            $qry= "SELECT user_screen_name,responses_to_tweeter FROM $table";
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
          $condition="WHERE responses_to_tweeter>0 ".$condition;
          $query = "$qry $condition group by user_screen_name order by responses_to_tweeter DESC";
        }
    elseif ($type=="mention")
        {
          $title="Top tweeters (active) with highest number of mentions from others for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total total number of  mentions";
          $yaxis="number of mentions";
          $name="Top mentioned tweeters (active)";
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
	        $condition="WHERE mentions_of_tweeter>0".$condition;
          if ($_GET['export'])
            $qry= "SELECT distinct user_screen_name, user_name AS full_name, COUNT(tweet_id) AS total_tweets, SUM(retweets) AS total_retweets, SUM(replies) AS total_tweet_replies, responses_to_tweeter, mentions_of_tweeter, user_verified, user_location, user_bio, CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM $table";
          else
            $qry= "SELECT distinct user_screen_name,mentions_of_tweeter FROM $table";
          $query = "$qry $condition order by mentions_of_tweeter DESC";
      }
    elseif ($type=="all_mentions")
        {
          $title="Top accounts (all) with highest number of mentions from others for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total total number of  mentions";
          $yaxis="number of mentions";
          $name="Top mentioned tweeters (all)";
          if ($condition) { $condition=$condition." AND users_".$table.".user_screen_name=user_all_mentions_".$table.".user_screen_name"; }
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
	        $condition="WHERE user_all_mentions_"."$table.mentions_of_tweeter>0 ".$condition;
      	  if ($_GET['export'])
      	    $qry="SELECT k1.user_screen_name as user_screen_name,k2.user_name AS full_name,k1.mentions_of_tweeter AS mentions_of_tweeter,k2.user_verified as user_verified,k2.user_location as user_location,k2.user_bio as user_bio,CONCAT('https://twitter.com/',k1.user_screen_name) AS user_twitter_page FROM user_all_mentions_".$table." k1 left outer join users_".$table." k2 on (k2.user_screen_name=k1.user_screen_name AND k1.mentions_of_tweeter>0 $condition)";
      	  else
            $qry="SELECT user_all_mentions_"."$table.user_screen_name,user_all_mentions_"."$table.mentions_of_tweeter FROM user_all_mentions_"."$table, users_".$table;
          $query = "$qry $condition group by user_all_mentions_"."$table.user_screen_name order by user_all_mentions_"."$table.mentions_of_tweeter DESC";
      }
    elseif ($type=="quotes")
        {
          $title="Top tweeters with highest number of quoted tweets for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
          $subtitle="Total total number of quoted tweets";
          $yaxis="quoted tweets";
          $name="Top tweeters with quoted tweets";
          if ($_GET['export'])
            $qry= "SELECT k2.user_screen_name,k2.user_name AS full_name,count(k1.quoted_tweet_id) AS quoted_tweets,k1.user_verified,k1.user_location,k1.user_bio,CONCAT('https://
twitter.com/',k1.user_screen_name) AS user_twitter_page FROM $table k1 inner join $table k2 on k2.tweet_id=k1.quoted_tweet_id ";
          else
            $qry= "SELECT  k2.user_screen_name,count(k1.quoted_tweet_id) AS quoted_tweets FROM $table k1 inner join $table k2 on k2.tweet_id=k1.quoted_tweet_id ";
          $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
          $condition="where k1.quoted_tweet_id is not null ".$condition;
          $query = "$qry $condition group by k2.user_screen_name order by count(k1.quoted_tweet_id) desc";
        }
    elseif ($type=="tweets")
          {
            $title="Top $limit tweeting tweeters for for ".$cases[$table]['name']." (".$cases[$table]['query'].")";
            $subtitle="Total number of tweets ".get_period($table);
            $yaxis="tweets";
            $name="Top tweeters";
	    if ($_GET['export'])
              $qry= "SELECT user_screen_name,user_name AS full_name,count(tweet_id) AS tweets,user_verified,user_location, user_bio,CONCAT('https://twitter.com/',user_screen_name) AS user_twitter_page  FROM $table ";
            else
              $qry= "SELECT user_screen_name,count(tweet_id) AS tweets FROM $table ";
            $condition=preg_replace("/\s*WHERE/i"," AND ",$condition);
            $condition="WHERE is_protected_or_deleted is null ".$condition;
            $query = "$qry $condition group by user_screen_name order by count(tweet_id) desc";
          }

      if ($debug && $_SESSION[basename(__DIR__).'email']==$admin_email) echo "(".$query.")";

      connect_mysql();

       if ($_GET['export'])
         {
 	         if ($result = $link->query($query))
        	    {
       		       if (!$result->num_rows) die("No results in the database matched your query.<br>\n");
	               $total=$result->num_rows;
        	    }
    	      else die("Error in query: ". $link->error.": $query");
               header( 'Content-Type: text/csv; charset=utf-8' );
               header( 'Content-Disposition: attachment;filename=tweeters_'.$table.'_'.$type.'.csv');
               $fp = fopen('php://output', 'w');

              if($row = $result->fetch_assoc())
                {
                  fputcsv($fp, array_keys($row));
                  $result->data_seek(0);
                }

	            while ($row = $result->fetch_assoc())
                {
                 fputcsv($fp, $row);
	              }
	         fclose($fp);
	         exit;
	       }

        if ($result = $link->query($query))
               {
                  if (!$result->num_rows) die("No results in the database matched your query.<br>\n");
                  $total=$result->num_rows;
               }
        else die("Error in query: ". $link->error.": $query");

        $data=""; $i=$limit; $j=1;
        $user_names=array();

        while ($row = $result->fetch_array())
          {
           if ($row[0])
	    {
	      $data=$data."{name:'${row[0]}', y:".$row[1].", case:'$table', rank:'$j', sec:'$type', drilldown:null},\n";
              array_push($user_names,$row[0]);
	    }
            if ($limit) { if ($i==1) break; }
            $i--; $j++;
          }

      $graph_data=file_get_contents("js/tweeter-graph.js");
      $graph_data=str_replace("<!--type-->",$type,$graph_data);
      $graph_data=str_replace("<!--title-->",addslashes($title)." from a total of ".number_format($total),$graph_data);
      $graph_data=str_replace("<!--subtitle-->",addslashes($subtitle),$graph_data);
      $graph_data=str_replace("<!--yaxis-->",$yaxis,$graph_data);
      $graph_data=str_replace("<!--name-->",$name,$graph_data);
      $graph_data=str_replace("<!--data-->",$data,$graph_data);
      $graph_data=str_replace("<!--drilldowns-->",$drilldowns,$graph_data);
      $graph_data=$graph_data."<span id='chartcontainer$type'></span>";

      echo $graph_data."<span id='$type'></span>";

      if ($limit>sizeof($user_names)) $limit=sizeof($user_names);

      echo "<br><center><a href='".$_SERVER['REQUEST_URI']."&export=1'>Export to CSV file (full list of <b>$total</b> tweeters)</a></center><br>";

      for ($i=1; $i<=$limit; $i++)
        {
          echo "<br><a name='#$type$i'></a>\n";
          show_profile($i,$table,$user_names[$i-1]);
          echo "<br>";
        }
  }


  function get_period($table)
  {
    global $cases;
    return $cases[$table]['from']." - ".$cases[$table]['to'];
  }
  function get_days($table)
  {
    global $cases;
    $datediff = strtotime($cases[$table]['to']) - strtotime($cases[$table]['from']);
    return floor($datediff/(60*60*24));
  }


?>

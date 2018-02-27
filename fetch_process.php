<?php
$lifetime=60000;
session_set_cookie_params($lifetime);
session_start();
if (!$_SESSION[basename(__DIR__)]) die("You need to login first by going to the <a href='index.php'>Main Page</a>\n");
date_default_timezone_set('UTC');

$table=$_GET['id'];

require_once("configurations.php");

$query="SELECT * FROM `cases` WHERE `id`='$table'";
if ($result = $link->query($query))
      {
        $row = $result->fetch_assoc();
        $date_created=$row['date_created'];
        $last_process_started=$row['last_process_started'];
        $last_process_updated=$row['last_process_updated'];
        $last_process_completed=$row['last_process_completed'];
        $platform=$row['platform'];
        $search_method=$row['search_method'];
	$pstatus=$row['status']; 
     }
    if ($platform==1)
     {
       if ($search_method==0)
         {
           $search_meth="api_search";
         }
      elseif ($search_method==1)
         {
           $search_meth="api_stream";
         }
      elseif ($search_method==2)
         {
           $search_meth="web_search";
         }
      $cmd='php '.$search_meth.'.php '.$_GET['id'].' >> tmp/log/'.$_GET['id'].'-'.$search_meth.'.log &';
     }

//die("(s:".$last_process_started.",u:".$last_process_updated.",c:".$row['last_process_completed'].")"); 

if (!$_GET['progress'] && $table && !$_GET['stop'] && !$_GET['overlimit'])
  {
    kill_process(0);
      $query="update cases set status='$status',last_process_started='".gmdate("Y-m-d H:i:s")."', last_process_updated='".gmdate("Y-m-d H:i:s")."' where id='${_GET['id']}'";
      $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");

//die($cmd);

    shell_exec($cmd);
    echo "<HTML><HEAD><meta http-equiv=\"refresh\" content=\"0; URL='fetch_process.php?progress=1&id=".$_GET['id']."'\">";
    echo "</HEAD><BODY>The platform has just started the data extraction and database population process.<br>";
    echo "You can always track progress <a href='fetch_process.php?id=".$_GET['id']."&progress=1'>here</a> or from the case profile page.</BODY></HTML>";
    exit; 
 }
elseif ($_GET['progress'] && $table)
  {
    $query="SELECT tweet_id AS num_rows FROM `$mysql_db`.`$table` WHERE `is_protected_or_deleted` IS NULL";
    if ($result = $link->query($query)) $step1=$result->num_rows;
    else die("Error in query: ". $link->error.": $query\n");

    $query="SELECT date_time AS num_rows FROM `$mysql_db`.`$table` WHERE `date_time` IS NOT NULL";
    if ($result = $link->query($query)) $step2=$result->num_rows;

if (!$_GET['overlimit'])
  {
   if ($pstatus=="overlimit")
     {
      kill_process(0);
      $cmd='php '.$search_meth.'.php '.$_GET['id'].' step4 >> tmp/log/'.$_GET['id'].'-'.$search_meth.'.log &';
//die($cmd);
      shell_exec($cmd);
      $refresh=" <meta http-equiv=\"refresh\" content=\"10; URL='fetch_process.php?progress=1&id=$table&overlimit=1'\">";
     }
    else { $refresh=" <meta http-equiv=\"refresh\" content=\"10; URL='fetch_process.php?progress=1&id=$table'\">"; }
  }
    $html="</HEAD><BODY>Progress with extracting and saving data for case ($table)<br><br>";
    $period_covered='NA';
    $pstatus2=process_status($table);
//echo "($pstatus)";
    if ($pstatus2)
          {
	    $status="<img src='images/in_progress.gif'> <font color=orange>In progress - <a href='fetch_process.php?id=$table&stop=1'>Stop</a></font>"; 
	    $html="<HTML><HEAD> $refresh $html";
	    update_status("In progress");
          }
    else
          {
	    if ($last_process_started!='0000-00-00 00:00:00' && $last_process_completed!='0000-00-00 00:00:00')
		{
 		 if ($pstatus=="overlimit") 
		   { $status="<font color=green>Limit Exceeded</font>"; }
		 else
		   { $status="<font color=green>Completed (<a href='fetch_process.php?id=$table'>Process again</a>)</font>"; }
		}
	    elseif ($last_process_started!='0000-00-00 00:00:00' && $last_process_completed=='0000-00-00 00:00:00')
		{
            	  $status="<font color=red>Stopped (<a href='fetch_process.php?id=$table'>Resume Now</a>)</font>";
		}
	    else
		{
		  $status="Not initiated (<a href='fetch_process.php?id=$table'>Initiate Now</a>)";
		}
          }
    if ($step2)
      {
        $query="SELECT date_time FROM `$mysql_db`.`$table` WHERE `date_time` IS NOT NULL ORDER BY date_time LIMIT 1";
        if ($result = $link->query($query))
          {
            $row=$result->fetch_assoc();
            $period_covered=$row['date_time'];
          }
        $query="SELECT date_time FROM `$mysql_db`.`$table` WHERE `date_time` IS NOT NULL ORDER BY date_time DESC LIMIT 1";
        if ($result = $link->query($query))
          {
            $row=$result->fetch_assoc();
            $period_covered.=" - ".$row['date_time'];
          }
      }
  }
  elseif ($_GET['stop'] && $_GET['id'])
    {
      kill_process(1);
      $query="update cases set last_process_completed='0000-00-00 00:00:00',status='interrupted' where id='$table'";
      $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
     exit; 
    }
  if (!$last_process_started) $last_process_started='N/A';
  if (!$last_process_updated) $last_process_updated='N/A';
  if ($_GET['overlimit'])
                    {
                      $status="<font color=green>Limit Reached</font>";
                      $html.="<hr><big><b>Limit reached!</b></big><br><br>";
                      $html.="Your demo case contains $step2 tweets, which is beyond the allowed limit of <b>".$max_tweets_per_case."</b> tweets per case.<br>";
                      $html.="<br>You can now <a href='".$website_url."' target=_blank>go back to the main page</a> to view your created demo case.<br>";
                      $html.="<br><br>We have to enforce a limit to reduce the burden on our server, which is only meant for demonstration purposes.<br>";
                      $html.="You can download Mecodify from <a href='https://github.com/wsaqaf/mecodify' target=_blank>GitHub</a> and install it on your server if you wish to create larger cases.";
                      $html.="If you need help, feel free to contact us by email on <a href='mailto:admin@mecodify.org'>admin@mecodify.org</a></BODY></HTML>";
                    }
  echo $html;
  echo "<table border=1><tr><td>Created</td><td>Status</td><td>Last process started</td><td>Last activity</td><td>Period covered</td><td>Records fetched</td><td>Detailed records fetched</td></tr>";
    echo "<tr><td>$date_created</td><td>$status</td><td>$last_process_started</td><td>$last_process_updated</td><td>$period_covered</td><td>$step1</td><td>$step2</td></tr></table>";
    echo "<br><br>You can always review the results of the extraction on <a href='$website_url'>the main page</a></BODY></HTML>";

  function time_elapsed_string($ptime)
  {
      $etime = time() - $ptime;

      if ($etime < 1)
      {
          return '0 seconds';
      }

      $a = array( 365 * 24 * 60 * 60  =>  'year',
                   30 * 24 * 60 * 60  =>  'month',
                        24 * 60 * 60  =>  'day',
                             60 * 60  =>  'hour',
                                  60  =>  'minute',
                                   1  =>  'second'
                  );
      $a_plural = array( 'year'   => 'years',
                         'month'  => 'months',
                         'day'    => 'days',
                         'hour'   => 'hours',
                         'minute' => 'minutes',
                         'second' => 'seconds'
                  );

      foreach ($a as $secs => $str)
      {
          $d = $etime / $secs;
          if ($d >= 1)
          {
              $r = round($d);
              return $r . ' ' . ($r > 1 ? $a_plural[$str] : $str) . ' ago';
          }
      }
  }

function kill_process($verbose)
  {
    global $search_meth; global $step2;
    $match=$search_meth.'.php '.$_GET['id'];
    $match = escapeshellarg($match)."\$";
    $str="ps x|grep $match|grep -v grep|awk '{print $1}'";
//echo "($search_meth:$str)";
//exit;
    $ret=shell_exec($str);
    if($ret && $verbose) echo "Process found, trying to stop it!<br>\n";
    system('kill '. $ret, $k);
    $ret=shell_exec($str);
    if(!$ret) {
//    if(posix_kill($ret,SIGKILL)) {
            update_status('Stopped');
	    if ($step2)
   	        echo "<HTML><HEAD><meta http-equiv=\"refresh\" content=\"0; URL='fetch_process.php?progress=1&id=".$_GET['id']."&overlimit=1'\">";
	    else
                echo "<HTML><HEAD><meta http-equiv=\"refresh\" content=\"0; URL='fetch_process.php?progress=1&id=".$_GET['id']."'\">";
    	    echo "</HEAD><BODY></BODY></HTML>";
    } else {
         echo "The process does not seem to be running<br> <a href='fetch_process.php?id=".$_GET['id']."&progress=1'>Go back</a> \n"; 
    }
  }
function process_status($table)
  {
    global $search_meth;
    $running=0;
    $match=$search_meth.'.php '.$table;
    $match = escapeshellarg($match)."\$";
    $str="ps x|grep $match|grep -v grep|awk '{print $1}'";
//echo "(str:$str ".shell_exec($str).")";
//echo "(".shell_exec('ps -aux').")";
//die();
    exec($str, $output, $ret);
    if($ret && $verbose) echo 'Error: Could not check the process. Contact admin!<br>\n';
    while(list(,$t) = each($output))
     {
        if(preg_match('/^([0-9]+)/', $t, $r)) { $running=1; }
     }
    if ($running) return 1;
    return 0;
  }

function update_status($status)
    {
      global $link;
      $query="update cases set status='$status',last_process_updated='".gmdate("Y-m-d H:i:s")."',last_process_completed='0000-00-00 00:00:00' where id='${_GET['id']}'"; 
      $result=$link->query($query);if (!$result) die("Invalid query: " . $link->sqlstate. "\n$query\n");
    }
?>

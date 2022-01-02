<?php
if ($_GET['check_updates'])
  {
        echo "Checking updates...<hr>";   
        check_ver();
        exit();
  }

include_once('configurations.php');
if (!$_GET['id']) $id="tweets";
else $id=$_GET['id'];
$table=$_GET['table'];
$load=$_GET['load'];
$user_screen_name=$_GET['user_screen_name'];
$template=file_get_contents("templates/template_"."$id.html");
if ($_SESSION[basename(__DIR__)])
  {
   $template=str_replace('<!--cases-->',get_cases_db($_GET['table']),$template);
   $template=str_replace('<!--url-->',$website_url,$template);
   $template=str_replace('<!--title-->',$website_title,$template);
  }
if ($load && $user_screen_name)
  {
    if (!$_SESSION[basename(__DIR__)]) die("You need to login first by going to the <a href='index.php'>Main Page</a>");
    $template=str_replace("//auto load","$(document).ready(function() { go_to_user('$user_screen_name'); });",$template);
  }
else  $template=str_replace('//auto load','',$template);

echo $template;

function get_cases_db($case)
  {
      global $link; global $allow_new_cases;

      $cond="";

      $query= "SELECT * from cases $cond order by date_created";
      if ($result = $link->query($query))
        {
          if (!$result->num_rows)
            {
              $output="<font size=-1 color=red>There are no cases available.</font>";
              if ($allow_new_cases) $output=$output."<br><a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
              return $output;
            }
          $total=$result->num_rows;
        }
      else die("Error in query: ". $link->error.": $query");

      $cnt=1; $is_yours1=0;
      $list="<small>Select a case from below:</small><br><select id='case' style='color:black; background-color:white' onchange='if (typeof(this.selectedIndex)!=undefined) showkumu();'>".
"<option value='' style='color:black; background-color:white'>---------------</option>\n";
      while ($row = $result->fetch_assoc())
        {
	  if ($case==$row['id']) $sel="SELECTED"; else $sel="";
          $is_yours=isyours($row['creator'],$_SESSION[basename(__DIR__).'email']);
          if ($is_yours=="*") $is_yours1=1; else $is_yours1=0;
	  if (!$row['private'] || $is_yours1)
            { $list.="<option value='${row['id']}' id='${row['id']}' style='color:blacki; background-color:white' $sel>${row['name']}<sup>$is_yours</sup>"; }
          $cnt++;
        }
      $list.="</select><br><i><font size=-1><a href='#' onclick=javascript:case_proc('more_info');>More info about the selected case</a></font></i><br>";
      if ($allow_new_cases) $list.="<br><a href='#' onclick=case_proc('add_case');><div style='text-align:center'>Add a new case </a></div><br>";
      else $list.="<br>";
      return $list;
  }

function isyours($creator,$email)
    {
	global $admin_email;
      if ($creator==$email || $email==$admin_email) return "*";
      return "";
    }
function check_ver()
 {
   $this_ver=trim(file_get_contents("./ver.no"));
   $latest_ver=trim(file_get_contents("https://mecodify.org/get_ver.php"));
   if ($latest_ver>$this_ver)
    {
       echo "<br><small>Your version ($this_ver) is out-of-date. Please go to the <a href='https://github.com/wsaqaf/mecodify' target=_blank>Github repo</a> to clone the latest repo (version $latest_ver)</small><br>";
    }
   else echo "<br><br>Your version ($this_ver) is up-to-date!";
 }

?>

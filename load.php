<?php
check_ver();
include_once('configurations.php');
if (!$_GET['id']) $id="tweets";
else $id=$_GET['id'];
$table=$_GET['table'];
$load=$_GET['load'];
$user_screen_name=$_GET['user_screen_name'];
$template=file_get_contents("templates/template_"."$id.html");
//echo "templates/template_"."$id.html";
$template=str_replace('<!--cases-->',get_cases_db($_GET['table']),$template);
$template=str_replace('<!--url-->',$website_url,$template);
$template=str_replace('<!--title-->',$website_title,$template);

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
              return "<font size=-1 color=red>There are no cases available.</font>";
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
            { $list.="<option value='${row['id']}' id='${row['id']}' style='color:blacki; background-color:white' $sel>".tops($row['top_only'])."${row['name']}<sup>$is_yours</sup>"; }
          $cnt++;
        }
      $list.="</select><br><i><font size=-1><a href='#' onclick=javascript:case_proc('more_info');>More info about the selected case</a></font></i><br>";
      if ($allow_new_cases) $list.="<br><a href='#' onclick=case_proc('add_case');><div style='text-align:center'>Add a new case </a></div><br>";
      else $list.="<br>";
//          if ($is_yours1) $list.="<br> &nbsp; &nbsp; &nbsp; <font color=yellow>*<i><font size=-1> A case you created</i></font></font>";
      return $list;
  }

function tops($top_only)
 {
   if ($top_only) return "";
   return "+ ";
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
   $latest_ver=trim(file_get_contents("http://mecodify.org/get_ver.php"));
   if ($latest_ver>$this_ver)
    {
       echo "<small><small>Your version ($this_ver) is out-of-date. Please <a href='https://github.com/wsaqaf/mecodify/releases' target=_blank>update</a> to version $latest_ver</small></small><br>";
    }
 }

?>

<?php
error_reporting(E_ERROR);

require_once("configurations.php");

use phpmailer\PHPMailer\PHPMailer;
use phpmailer\PHPMailer\SMTP;
use phpmailer\PHPMailer\Exception;

$add_demo_details="";
$sign_up="<a href='#' onclick=case_proc('signup'); >Sign up</a>";

if ($demo_config['enabled']) 
  { 
        $add_demo_details="Use email: <b>${demo_config['email']}</b> and password <b>${demo_config['pw']}</b> to try out the demo.<br>";
	$sign_up="";
  }

$login_str = <<<END
     <font size=+1><b>Login to your account:</b></font><br><br>$add_demo_details
     <form id='login'><table>
     <tr><td style='border: none !important;'>Email</td><td style='border: none !important;'><input type='text' id='email' name='email' ></td><td style='border: none !important;'><span class='email'></span></td></tr>
     <tr><td style='border: none !important;'>Password</td><td style='border: none !important;'> <input type='password' id='password'  name='password' ></td><td style='border: none !important;'></td></tr>
     <tr><td style='border: none !important;'></td><td style='border: none !important;'><input type='button' value='Login' onclick=case_proc('login'); > $sign_up 
</td></tr></table><br>
     </form>
     <script>
     document.getElementById('password')
       .addEventListener('keyup', function(event) {
         if (event.code === 'Enter') {
           event.preventDefault();
           case_proc('login');
         }
       });
    </script>
END;

$submit_case_form = <<<END
<font size=+1>Add a new case</font><br>
<form id='add_case'><table>
<span class='tip'></span>
<tr><td style="border: none !important;"> ID<sup><font color=red>*</font></sup></td><td style="border: none !important;"> <input maxlength=20 type='text' size=20 id='case_id'>  <img src='images/info.png'  onclick=showtip('id'); > (maximum 20 characters)</td></tr>
<tr><td style="border: none !important;">Name<sup><font color=red>*</font></sup></td><td style="border: none !important;">  <input  maxlength=100 type='text' size=35 id='case_name'> <img src='images/info.png'  onclick=showtip('name');> (maximum 100 characters)</td></tr>
<tr><td style="border: none !important;">Platform<sup><font color=red>*</font></sup></td><td style="border: none !important;"> <select id='case_platform'><option value=1 id='twitter' selected>Twitter</option><option value=2 id='facebook' disabled>Facebook</option><option value=3 id='YouTube' disabled>Youtube</option></select> <img src='images/info.png'  onclick=showtip('platform'); ></td></tr>
<tr><td style="border: none !important;">Include retweets</td><td style="border: none !important;"> <input type='checkbox' id='case_include_retweets'> If checked, retweets matching the criteria will be included. <img src='images/info.png' onclick=showtip('include_retweets'); ></td></tr>
<tr><td style="border: none !important;">Include referenced tweets</td><td style="border: none !important;"> <input type='checkbox' id='case_top_only'> If checked, tweets referenced by matched tweets will be included (even if they are outside the specified search period).<img src='images/info.png'  onclick=showtip('top_only'); ></td></tr>
<tr><td style="border: none !important;">Case search query<sup><font color=red>*</font></sup></td><td style="border: none !important;"> <input type='search' size=50 id='case_query'> <img src='images/info.png'  onclick=showtip('query'); ></td></tr>
<tr><td style="border: none !important;">From (<small>e.g. 2016-12-20 23:55:30</small>) in UTC</td><td style="border: none !important;">  <input maxlength=20  onfocusout=ValidateDateTime('case_from') id='case_from'><span class='case_from'></span><img src='images/info.png'  onclick=showtip('from'); ></td></tr>
<tr><td style="border: none !important;">To (<small>e.g. 2016-12-31 23:55:30</small>) in UTC</td><td style="border: none !important;">  <input type=20 onfocusout=ValidateDateTime('case_to') id='case_to'><span class='case_to'></span><img src='images/info.png' onclick=showtip('to'); ></td></tr>
<tr><td style="border: none !important;">Details</td><td style="border: none !important;"><textarea  rows='5' cols='50' id='case_details'></textarea> <img src='images/info.png'  onclick=showtip('details'); ></td></tr>
<tr><td style="border: none !important;">URL</td><td style="border: none !important;"><input type='text' size=50 id='case_details_url'>  <img src='images/info.png'  onclick=showtip('details_url'); ></td></tr>
<tr><td style="border: none !important;">Flags</td><td style="border: none !important;"><textarea  rows='5' cols='50' id='case_flags'></textarea> <img src='images/info.png'  onclick=showtip('flags'); ></td></tr>
<tr><td style="border: none !important;">Privacy: <select id='case_private'><option value="1">Private</option><option value="0">Public</option></select> <img src='images/info.png'  onclick=showtip('private'); ></td></tr></table>
<input type='hidden' id='email' value='${_SESSION[basename(__DIR__).'email']}'>
<input type='button' value='Submit case' onclick=case_proc('submit_case');>
</form>
<br><b>NOTE:</b> This platform has a limit of <b>($max_tweets_per_case)</b> tweets per case.
END;

$create_account_form = <<<END
<font size=+1><b>Sign up for an account:</b></font><br><br>
<form id='login'><table><tr><td style='border: none !important;'>
Full Name<sup><font color=red>*</font></sup> </td><td style='border: none !important;'> <input type='text' id='name' size=40 name='name'><span class='name'></span></td></tr><td style='border: none !important;'>
Title</td><td style='border: none !important;'> <input type='text' id='title' size=40 name='title'></td></tr><td style='border: none !important;'>
Institution</td><td style='border: none !important;'> <input type='text' id='institution' size=40 name='institution'></td></tr><td style='border: none !important;'>
Country</td><td style='border: none !important;'> <input type='text' id='country' size=40 name='country'></td></tr><td style='border: none !important;'>
Email<sup><font color=red>*</font></sup></td><td style='border: none !important;'> <input type='text' id='email' size=40 name='email'><span class='email'></span></td></tr><td style='border: none !important;'>
Password<sup><font color=red>*</font></sup></td><td style='border: none !important;'> <input type='password' id='password' size=40 name='password'><span class='password'></span></td></tr><td style='border: none !important;'>
Verify password<sup><font color=red>*</font></sup></td><td style='border: none !important;'> <input type='password' id='password2' size=40 name='password2'><span class='password2'></span></td></tr><td style='border: none !important;'>
<td colspan=2 style='border: none !important;'><input type='checkbox' id='terms' name='terms' > I agree to <a href='terms.htm' target=_blank>the terms and conditions of use</a><sup><font color=red>*</font></sup><span class='terms'></span></td></tr><td style='border: none !important;'>
<td colspan=2 style='border: none !important;'><input type='button' value='Signup' onclick=case_proc('create_account'); ></td></tr></table>
</form>
<sup><font color=red>*</font></sup> Required field.
END;

if ($_GET['action']=='toggle_login')
  {
    echo toggle_login($_GET['preserve']);
    exit;
  }

if ($_SESSION[basename(__DIR__)])
   {
      if ($_GET['action']=='add_case')
         {
            echo $submit_case_form;
            exit;
         }
      elseif ($_GET['action']=='forgot' || $_GET['action']=='signup')
        {
            die("You are already logged in!");
            exit;
        }
      elseif ($_GET['action']=='login')
        {
	  clear_cases();
	  if ($allow_new_cases)
          	echo "<a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
	  else echo "Select one of the cases to visualise.";
          exit;
        }
      elseif ($_GET['action']=='list_cases')
        {
          echo get_from_db($_SESSION[basename(__DIR__).'email']);
          clear_cases();
          if ($allow_new_cases)
                echo "<a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
          else echo "Select one of the cases to visualise.";
          exit;
        }
      elseif ($_GET['action']=='more_info')
        {
          connect_mysql();
          echo get_from_db('',0,$_GET['case']);
          exit;
        }
      elseif ($_GET['action']=='edit_case' && $_GET['case_id'])
         {
           connect_mysql();
           echo edit_from_db($_GET['email'],0,$_GET['case_id']);
           exit;
         }
     elseif ($_GET['action']=='delete_case' && $_GET['case_id'])
        {
          connect_mysql();
          echo del_from_db($_GET['email'],$_GET['case_id']);
          exit;
        }
    elseif ($_GET['action']=='empty_case' && $_GET['case_id'])
       {
         connect_mysql();
         echo empty_from_db($_GET['email'],$_GET['case_id']);
         exit;
       }
    elseif ($_GET['action']=='edit_profile')
        {
            connect_mysql();
            echo edit_profile($_SESSION[basename(__DIR__).'email']);
            exit;
        }
    elseif ($_GET['action']=='delete_account' && $_GET['email'])
       {
         connect_mysql();
         echo del_account($_GET['email']);
         $_SESSION[basename(__DIR__)]=false;
         echo $login_str;
         exit;
       }
     elseif ($_GET['action']=='toggle_access' && $_GET['case_id'])
       {
         connect_mysql();
         echo toggle_from_db($_SESSION[basename(__DIR__).'email'],0,$_GET['case_id']);
         exit;
       }
     elseif ($_GET['action']=='logout')
       {
         $_SESSION[basename(__DIR__)]=false;
         echo $login_str;
         exit;
       }
     elseif ($_GET['action']=="tip")
       {
         $tips=array("id"=>"A unique alphanumeric code to be associated with this case (e.g., westgate2013, ombudsman2015, SanBernardin2015)",
                     "name"=>"A brief understandable name for the case that is 5 words maximum (e.g., San Bernardin 2015 Terrorist Attack)",
                     "platform"=>"The platform that you wish to search. Currently only Twitter is fully supported. There is a plan to add other sources (e.g., YouTube, Facebook) in the future.",
                     "query"=>"The search query used to identify relevant results (e.g., #SONA2015 OR #SONA OR \"SONA 2015\" OR \"2015 State of the Union Address\"). Note: As of API 2.0, AND is not needed. You can just have the words you want to appear in the same tweet separated by space. <br>Click <a href='https://developer.twitter.com/en/docs/twitter-api/tweets/search/integrate/build-a-query' target=_blank>here</a> for tips. If you need help to construct a proper query, contact $admin_email.",
                     "incude_retweets"=>"If checked, retweets and quoted tweets matching the criteria will also be included. If unchecked, only original tweets will be fetched.",
                     "top_only"=>"If checked, respondes to matched tweets will be excluded. If unchecked, it will take much longer to fetch and process the additional tweets.",
                     "from"=>"The start date and time formatted as YYYY-MM-DD HH:MM:SS (inclusive) of the search. If no value is provided, the start date will default to the one week before the 'to' value",
                     "to"=>"The end date and time of the search formatted as YYYY-MM-DD HH:MM:SS. If no value is provided, the end and time will default to now",
                     "details"=>"This is a helpful field to provide a background about this particular case and why you think it is important to study",
                     "details_url"=>"This is an optional link to a story, wikipedia entry, or any source you think would be helpful to provide more background about the case",
                     "flags"=>"This is a JSON set of flagged times and days where you want to mark on the timeline. The below example shows how three flags can be added. You can insert as many flags as you want but please follow the given format:<br><hr>
{\"flags\": [{
<br>		\"date_and_time\": \"YYYY-MM-DD HH:MM:SS\",
<br>		\"title\": \"title1\",
<br>		\"description\": \"description1\"
<br>	},
<br>	{
<br>                \"date_and_time\": \"YYYY-MM-DD HH:MM:SS\",
<br>                \"title\": \"title2\",
<br>                \"description\": \"description2\"
<br>	},
<br>	{
<br>		\"date_and_time\": \"YYYY-MM-DD HH:MM:SS\",
<br>		\"title\": \"title3\",
<br>		\"description\": \"description3\"
<br>	}]}<hr><b>Note:</b> Before submitting any flags field, please validate it using a JSON validator such as <a href='http://jsonlint.com/' target=_blank>jsonlint.com</a>.<br><br>",
                  "private"=>"Sometimes, you may want to keep the data private only to you. It is recommended that you keep it public though so that others would benefit.");

         echo "</td></tr><tr><td colspan=2><font size=-1><font color=blue><i>".$tips[$_GET['field']]."</i></font></font></td></tr>";
         exit;
       }
     elseif ($_POST['action']=="submit_case")
         {
           connect_mysql();
           echo submit_case("");
           exit;
         }
     elseif ($_POST['action']=="resubmit_case")
         {
           connect_mysql();
           echo submit_case("replace");
           exit;
         }
     elseif ($_POST['action']=='update_account')
          {
            connect_mysql();
            echo create_account("update");
            toggle_login($_GET['preserve']);
            exit;
         }
     else
         {
	   clear_cases();
          if ($allow_new_cases)
                echo "<a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
          else echo "Select one of the cases to visualise.";
           exit;
         }
  }
elseif (!empty($_POST) && $_POST['action']=='login')
      {
         $email = empty($_POST['email']) ? null : $_POST['email'];
         $password = empty($_POST['password']) ? null : $_POST['password'];
         if (correct_credentials($email,$password))
          {
      	     if ( ! session_id() ) @ session_start();
      	     $_SESSION[basename(__DIR__)] = true;
             $_SESSION[basename(__DIR__).'email']=$_POST['email'];
             clear_cases();
             if ($allow_new_cases)
                  echo "<a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
             else echo "Select one of the cases to visualise.";
             exit;
          }
        else
          {
             echo "Incorrect email or password. Please try again.<br><br> $login_str";
         }
     }
elseif (!empty($_POST) && $_POST['action']=='create_account')
     {
       connect_mysql();
       echo create_account("");
       exit;
    }
elseif ($_GET['action']=='signup')
    {
        echo $create_account_form;
        exit;
    }
elseif ($_GET['action']=='forgot' && !$_GET['email'])
    {
         echo $login_str;
         exit;
    }
/* to be implemented
elseif ($_GET['action']=='forgot' && $_GET['email'])
    {
      $pw=get_pw($_GET['email']);
      send_mail($_GET['email'],'','',$pw);
      exit;
    }
*/
elseif ($_GET['verification_str'] && $_GET['email'])
    {
      connect_mysql();
      verify_code($_GET['verification_str'] && $_GET['email']);
      exit;
    }
else
    {
         echo $login_str;
         exit;
    }
/*
else
    {
      connect_mysql();
      echo get_from_db('');
      exit;
    }
*/

function isValidTime(string $from, string $to): bool
{
    global $twitter_api_settings;

    if ($from)
      {
        $fromDate = DateTime::createFromFormat('Y-m-d H:i:s', $from);
        if (!($fromDate && $fromDate->format('Y-m-d H:i:s') == $from))
          {
            die("Format for 'from' value is invalid. It has to be of the format YYYY-MM-DD HH:MM:SS, e.g. 2016-12-20 23:55:30 \n");
            return false;
          }
        }

    if ($to)
      {
        $toDate = DateTime::createFromFormat('Y-m-d H:i:s', $to);
        if (!($toDate && $toDate->format('Y-m-d H:i:s') == $to))
          {
            die("Format for 'to' value is invalid. It has to be of the format YYYY-MM-DD HH:MM:SS, e.g. 2016-12-20 23:55:30 \n");
            return false;
          }
      }

      if ($to && $from)
        {
          if ($toDate<=$fromDate)
            {
              echo "End time cannot be less or equal to start time<br>\n";
              return false;
            }
        }

      if ($from && !$twitter_api_settings['is_premium'])
        {
          $last_week = new DateTime(gmdate("Y-m-d H:i:s"));
          $last_week->modify('-7 day');
          if ($fromDate->format("Y-m-d H:i:s") < $last_week->format("Y-m-d H:i:s"))
            {
                echo "You can only have a start date some time within the last week in a non-premium account!\n<br>\n";
                echo "In this case, the earliest you can have is <b>".$last_week->format('Y-m-d H:i:s')."</b><br>\n";
                echo "Alternatively, you can leave the fields blank to have the API assign the allowed period.";
                return false;
            }
        }
    return true;
}

function submit_case($replace)
    {
        global $admin_email; global $link; global $login_str;

        if (!isValidTime($_POST['case_from'],$_POST['case_to'])) die();

        $query= "SELECT id,from_date,to_date from cases where id='${_POST['case_id']}'";
        if ($result = $link->query($query))
          {
            if ($result->num_rows && !$replace) die("There is already a case with the same id (${_POST['case_id']}).<br> Please delete the old case if it is yours or choose another id.<br>");
            $row=$result->fetch_assoc();
          }
        else die("Error in query: ". $link->error.": $query");
       if ($replace)
        {
          $new_status="";
          if ($_POST['case_from'] || $_POST['case_to'])
            {
              if ($_POST['case_from'] && ($row['from_date']>$_POST['case_from']) && $row['to_date']==$_POST['case_to']) { $new_status=" status='expanded_left',"; }
              elseif (($row['to_date']<$_POST['case_to'] || !$_POST['case_to']) && $row['from_date']==$_POST['case_from']) { $new_status=" status='expanded_right',"; }
              elseif ($_POST['case_from'] && $row['from_date']>$_POST['case_from'] && $row['to_date']<$_POST['case_to']){ die("You cannot change both start and end dates values at once. Start with updating one (either start or end) and run the process. Once finished, you can expand by changing the other value."); }
              elseif ($row['from_date']<$_POST['case_from'] || ($row['to_date']>$_POST['case_to'] && $_POST['case_to'])
                  || (!$row['to_date'] && $_POST['case_to']) || (!$row['from_date'] && $_POST['case_from']))
                  {
                    die("You cannot make the period shorter, but can only expand it. Start a new case with the shorter time period instead.");
                  }
            }
          $query="UPDATE cases set name='".$link->real_escape_string($_POST['case_name'])."', ".
          "include_retweets='${_POST['case_include_retweets']}', top_only='${_POST['case_top_only']}', from_date='${_POST['case_from']}', to_date='${_POST['case_to']}', details='".
          $link->real_escape_string($_POST['case_details'])."',$new_status details_url='".$link->real_escape_string($_POST['case_details_url'])."', flags='".$link->real_escape_string($_POST['case_flags'])."', private='${_POST['case_private']}' WHERE id='${_POST['case_id']}'";
          $returned="Your case has now been updated successfully.<br><br><a href='fetch_process.php?id=".$_POST['case_id']."' target=_blank>Click here</a> to use the new settings to populate the database in the background. <br><br>The process may take a while depending on your query and amount of data to be populated.<br><br>It will continue until all the results are fetched or when the maximum number of retreived (one million) record is reached. <br><br>You will receive an email once the process is completed.";
        }
       else
        {
          $query="CREATE TABLE ${_POST['case_id']} LIKE ".$_POST['case_platform']."_empty_case";
          if (!($result = $link->query($query))) die("Could not create new table. Please contact admin! <a href='#' onclick=javascript:case_proc('add_case');>Try again</a> Error in query: ". $link->error.": $query");
echo "Creating users table ...<br>\n";

          $query="CREATE TABLE users_"."${_POST['case_id']} LIKE ".$_POST['case_platform']."_empty_users";
          if (!($result = $link->query($query))) die("Could not create new users table. Please contact admin! <a href='#' onclick=javascript:case_proc('add_case');>Try again</a>");

          $query="CREATE TABLE user_mentions_"."${_POST['case_id']} LIKE ".$_POST['case_platform']."_empty_user_mentions";
          if (!($result = $link->query($query))) die("Could not create new user_mentions table. Please contact admin! <a href='#' onclick=javascript:case_proc('add_case');>Try again</a>");

          if ($_POST['case_from']=="0000-00-00 00:00:00") $_POST['case_from']="";
          if ($_POST['case_to']=="0000-00-00 00:00:00") $_POST['case_to']="";

          $_POST['case_query']=preg_replace_callback('/([A-Z]+\:)/', 'lower', $_POST['case_query']);
          $_POST['case_query']=str_replace(" AND "," ",$_POST['case_query']);

          $query="INSERT INTO cases (id, name, creator, platform, include_retweets, top_only, query, from_date, to_date, details, details_url, flags, private) values ".
          "('${_POST['case_id']}', '".$link->real_escape_string($_POST['case_name'])."', '${_POST['email']}', '${_POST['case_platform']}', '${_POST['case_include_retweets']}', '${_POST['case_top_only']}', '".$link->real_escape_string($_POST['case_query'])."', '${_POST['case_from']}', '${_POST['case_to']}', '".$link->real_escape_string($_POST['case_details'])."', '".$link->real_escape_string($_POST['case_details_url'])."', '".$link->real_escape_string($_POST['case_flags'])."', '${_POST['case_private']}')";
        }
       if (!($result = $link->query($query))) die("Could not insert new case with query ($query). Please contact admin! <a href='#' onclick=javascript:case_proc('add_case');>Try again</a>");
        $_SESSION[basename(__DIR__).'created']=$_POST['case_id'];
        echo '<script type="text/javascript"> location.reload(); </script>';
        email_admin(lst($_POST),"");
       exit();
    }

function lower($matches) { return strtolower($matches[1]); }

function toggle_login($preserve)
  {
    global $allow_new_cases;
    $case_sec="";
    if ($_GET['login'])
      {
        if (!$_SESSION[basename(__DIR__)]) return "<li class=''><center><a href='#' onclick=javascript:case_proc('');>Login</a></center></li>";
        else return "<li class=''><center><a href='#' onclick=case_proc('logout');>Logout</a> - <font size=-2><a href='#' onclick=javascript:case_proc('edit_profile');>Profile</a></font></center></li><hr>";
      }
        $case_sec=get_from_db('',1);
        if ($case_sec=="<font size=-1 color=red>There are no cases available.</font>")
          {
            clear_cases();
          if ($allow_new_cases)
                echo "<a href='#' onclick=case_proc('add_case');> Add a new case</a> ";
          else echo "Select one of the cases to visualise.";
          }
       return $case_sec;
  }

function get_from_db($email,$menu,$case)
  {
      global $link; global $login_str; global $admin_email; global $allow_new_cases;
if (!$case) { echo "Please select a case first"; return; }
      $cond="";

      if ($email)
        {
          $condition="where creator='$email'";
          if ($case) $condition.=" AND id='$case' $cond";
        }
      elseif ($case) $condition="where id='$case' $cond";
      else $condition="";
      $query= "SELECT * from cases $condition";
      $output="";
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
      if ($menu) $list="<select id='case'>\n";
      while ($row = $result->fetch_assoc())
        {
          if (!$menu)
            {
              $updated="<td style='border: none !important;'>In progress (started: ${row['last_process_started']})</td>";
              if ($row['last_process_completed'])
		{
			$updated="<td style='border: none !important;'>${row['last_process_completed']}";
		}
              elseif (!$row['last_process_completed']) $updated="<td style='border: none !important;'>Not completed <a href='fetch_process.php?id=$case&progress=1' target=_blank>See progress</a></td>";
              if ($_SESSION[basename(__DIR__).'email']==$row['creator'] || $_SESSION[basename(__DIR__).'email']==$admin_email)
                {
                  $action="<tr><td style='border: none !important;'>Action</td>";
                  $public="<td style='border: none !important;'><a href='#' onclick=javascript:case_proc('toggle_access','${row['id']}');>".ispublic($row['private'])."</a> </td></tr>";
                  $action.="<td style='border: none !important;'>";
                  $action.="<a href='#' onclick=javascript:case_proc('edit_case','${row['id']}','${row['creator']}');> Edit</a> (<a href='fetch_process.php?id=${row['id']}&progress=1' target=_blank>more info</a>)";
                  $action.="</td></tr>";
                }
              else $public="<td style='border: none !important;'>".ispublic($row['private'])."</td><td style='border: none !important;'></td></tr>";

      	      if (!$case) $list.="\n<br><b>$cnt</b>";
              if ($row['from_date']=="0000-00-00 00:00:00") $row['from_date']="";
              if ($row['to_date']=="0000-00-00 00:00:00") $row['to_date']="";

              $list.="<br><table><tr><td style='border: none !important;'>Platform</td><td style='border: none !important;'>".platform($row['platform'])."</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important; width:150px'>ID</td><td style='border: none !important;'><b>${row['id']}</b></td></tr>".
              "<tr><td style='border: none !important;'>Name</td><td style='border: none !important;'>${row['name']}</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Platform</td><td style='border: none !important;'>".platform($row['platform'])."</td></tr>".
              "<tr><td style='border: none !important;'>Include retweets?</td><td style='border: none !important;'>".top_only($row['include_retweets'])."</td></tr>".
              "<tr><td style='border: none !important;'>Include referenced tweets?</td><td style='border: none !important;'>".top_only($row['top_only'])."</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Search query</td><td style='border: none !important;'>${row['query']}</td></tr>".
              "<tr><td style='border: none !important;'>From</td><td style='border: none !important;'>${row['from_date']}</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>To</td><td style='border: none !important;'>${row['to_date']}</td>".
              "<tr><td style='border: none !important;'>Created by</td><td style='border: none !important;'>${row['creator']}</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Created date</td><td style='border: none !important;'>${row['date_created']}</td></tr>".
              "<tr><td style='border: none !important;'>Is public?</td><td style='border: none !important;'>".ispublic($row['private'])."</td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Details</td><td style='border: none !important;'>${row['details']}</td></tr>".
              "<tr><td style='border: none !important;'>URL reference</td><td style='border: none !important;'><a href='${row['details_url']}' target=_blank>${row['details_url']}</a></td></tr>".
              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Flags</td><td style='border: none !important;'>${row['flags']}</td></tr>".

              "<tr style='background-color:#f2f2f2'><td style='border: none !important;'>Last update</td>$updated</tr>$action</table><br>\n";
            }
          else
            {
              $is_yours=isyours($row['creator'],$_SESSION[basename(__DIR__).'email']);
              if ($is_yours=="*") $is_yours1=1;
              $list.="<option value='${row['id']}' id='${row['id']}'>${row['name']}<sup>$is_yours</sup>";
            }
          $cnt++;
        }
      if ($menu)
        {
          $list.="</select><br><i><font size=-1><a href='#' onclick=javascript:case_proc('more_info');>More info about the selected case</a></font></i><br><br><a href='#' onclick=case_proc('add_case');>";
          if ($allow_new_cases)
                $list.="<div style='text-align:center'>Add a new case </a></div><br>";
          else $list.="<div style='text-align:center'>Select one of the cases to visualise.</div><br>";
        }
      return $list;
  }

function edit_from_db($email,$menu,$case)
  {
      global $link; global $submit_case_form; global $platforms; global $allow_new_cases;

      if ($email)
        {
          $condition="where creator='$email'";
          if ($case) $condition.=" AND id='$case' ";
        }
      elseif ($case) $condition="where id='$case' ";
      else $condition="";
      $query= "SELECT * from cases $condition";
      $output="";
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
      $template=$submit_case_form;
      $row = $result->fetch_assoc();
      if ($row['from_date']=="0000-00-00 00:00:00") $row['from_date']="";
      if ($row['to_date']=="0000-00-00 00:00:00") $row['to_date']="";
      $template=str_replace("Add a new case","Edit case",$template);
      $template=str_replace("'case_id'>","'case_id' value='${row['id']}' readonly><i> <font size=-2>The id cannot be changed. You can add a new case with a new id</font></i>",$template);
      $template=str_replace("'case_name'>","'case_name' value='".htmlspecialchars($row['name'])."'>",$template);
      $template=str_replace("'case_query'>","'case_query' style='background-color:#f2f2f2' value='".htmlspecialchars($row['query'])."' readonly><br><i> <font size=-2>The query and search category cannot be changed. You can add a new case with a different settings</font></i>",$template);
      $template=str_replace("id='case_from'>","id='case_from' value='${row['from_date']}'>",$template);
      $template=str_replace("id='case_to'>","id='case_to' value='${row['to_date']}'>",$template);
      $template=str_replace("'case_details'>","'case_details'>".htmlentities($row['details']),$template);
      $template=str_replace("'case_flags'>","'case_flags'>".htmlentities($row['flags']),$template);
      $template=str_replace("'case_details_url'>","'case_details_url' value='".htmlentities($row['details_url'])."'>",$template);
      if (ischecked($row['private']))
      	{
      	  $template=str_replace('<option value="1">Private</option><option value="0">Public</option></select>','<option value="1" selected>Private</option><option value="0">Public</option></select>',$template);
      	}
      else
      	{
      	  $template=str_replace('<option value="1">Private</option><option value="0">Public</option></select>','<option value="1">Private</option><option value="0" selected>Public</option></select>',$template);
      	}
      $template=str_replace("'case_private'>","'case_private' ".ischecked($row['private']).">",$template);
      $template=str_replace("'case_include_retweets'>","'case_include_retweets' ".ischecked($row['include_retweets']).">",$template);
      $template=str_replace("'case_top_only'>","'case_top_only' ".ischecked($row['top_only']).">",$template);
      $template=str_replace("value='Submit case' onclick=case_proc('submit_case');>","value='Save changes' onclick=case_proc('resubmit_case');>",$template);
      $template.="<br><br><center><big><a href='#' onclick=javascript:case_proc('empty_case','${row['id']}','${row['creator']}');> Empty data from the case</a> ";
      $template.=" - <a href='#' onclick=javascript:case_proc('delete_case','${row['id']}','${row['creator']}');> Delete from database</a></big></center><br>";
      return $template;
  }

function edit_profile($email)
  {
      global $link; global $create_account_form; global $demo_config;
      if ($demo_config['enabled'] && $_SESSION[basename(__DIR__).'email']==$demo_config['email'])
	{ echo "Editing DEMO profile is not permitted"; return; }
      if ($email)
        {
          $condition="where email='$email'";
        }
      else $condition="";
      $query= "SELECT * from members $condition";
      $output="";
      if ($result = $link->query($query))
        {
          if (!$result->num_rows)
            {
              return "<font size=-1 color=red>There are no users with that email available.</font>";
            }
          $total=$result->num_rows;
        }
      else die("Error in query: ". $link->error.": $query");
      $template=$create_account_form;
      $row = $result->fetch_assoc();
      $template=str_replace("'name'>","'name' value='".htmlspecialchars($row['name'])."'>",$template);
      $template=str_replace("'title'>","'title' value='".htmlspecialchars($row['title'])."'>",$template);
      $template=str_replace("'institution'>","'institution' value='".htmlspecialchars($row['institution'])."'>",$template);
      $template=str_replace("'country'>","'country' value='".htmlspecialchars($row['country'])."'>",$template);
      $template=str_replace("name='email'>","name='email' value='${row['email']}' readonly><i> <font size=-2>Your email cannot be changed. You can delete this account and create another with the new one if you wish </font></i>",$template);
      $template=str_replace("value='Signup' onclick=case_proc('create_account'); >","value='Update profile' onclick=case_proc('update_account');>",$template);
      $template.="<br><hr><a href='#' onclick=javascript:case_proc('delete_account','${row['email']}'> Delete account</a><br><br>";
      return $template;
  }

function isyours($creator,$email)
  {
    if ($creator==$email) return "*";
    return "";
  }

function ischecked($private)
  {
    if (!$private) return "";
    return "checked";
  }

function ispublic($private)
  {
    if ($private==1) return "No";
    return "Yes";
  }

function platform($platform)
  {
    global $platforms;
    if ($platform) return $platforms[$platform];
    return "";
  }

function top_only($top_only) { if ($top_only) return "Yes"; return "No"; }

function correct_credentials($email,$password)
    {
        global $link;
	      if (!preg_match('/^[a-zA-Z0-9\@\.\-\_]+$/', $email) OR !preg_match('/^[a-zA-Z0-9\@\!\.\-\_]+$/', $password)) return false;
        $hash=get_pw($email);
        if (password_verify($password, $hash)) return true;
        return false;
    }

function del_from_db($creator,$case_id)
    {
        global $link; global $admin_email; 

        $mask = "$case_id"."*.*";
        array_map('unlink', glob("tmp/network/$mask"));
        array_map('unlink', glob("tmp/kumu/$mask"));
        array_map('unlink', glob("tmp/log/$case_id/$mask"));
        unlink("tmp/log/$mask.log");
        rmdir("tmp/log/$case_id");
	echo "Files deleted... Attempting to delete database entries..<br>\n";
        if ($creator!=$_SESSION[basename(__DIR__).'email'] && $_SESSION[basename(__DIR__).'email']!=$admin_email)
		return $_SESSION[basename(__DIR__).'email']." is not the creator ($creator): Permission denied";
        $query= "DELETE from cases where id='$case_id' AND creator='$creator'";
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help.");
        $query= "DROP TABLE IF EXISTS $case_id";
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help.");
        $query= "DROP table IF EXISTS users_".$case_id;
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help.");
        $query= "DROP table IF EXISTS user_mentions_".$case_id;
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help.");
        $query= "DROP table IF EXISTS user_all_mentions_".$case_id;
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help.");
	$_SESSION[basename(__DIR__).'deleted']=$case_id;
	echo '<script type="text/javascript"> location.reload(); </script>';
    }

function empty_if_exists($table)
  {
    global $mysql_db; global $link; global $admin_email;
    $query="SHOW TABLES LIKE '$table'";
    $result = $link->query($query);
    if ($result->num_rows > 0) return "TRUNCATE $table";
    return "SELECT 1";
  }

function empty_from_db($creator,$case_id)
    {
        global $link; global $admin_email;

        $mask = "$case_id"."*.*";
        array_map('unlink', glob("tmp/network/$mask"));
        array_map('unlink', glob("tmp/kumu/$mask"));
        array_map('unlink', glob("tmp/cache/$mask"));
        array_map('unlink', glob("tmp/log/$case_id/$mask"));
        unlink("tmp/log/$mask.log");
        rmdir("tmp/log/$case_id");
	      echo "Files deleted... Attempting to empty database ..<br>\n";
        if ($creator!=$_SESSION[basename(__DIR__).'email'] && $_SESSION[basename(__DIR__).'email']!=$admin_email)
		        return $_SESSION[basename(__DIR__).'email']." is not the creator ($creator): Permission denied";
        $query= "UPDATE cases SET status='',hashtag_cloud='' where id='$case_id' AND creator='$creator'";
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help. (q:$query)");
        $query= empty_if_exists($case_id);
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help. (q:$query)");
        $query= empty_if_exists("users_".$case_id);
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help. (q:$query)");
        $query= empty_if_exists("user_mentions_".$case_id);
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help. (q:$query)");
        $query= empty_if_exists("user_all_mentions_".$case_id);
        if (!$link->query($query)) die("Error in query: ". $link->error.": Contact admin $admin_email for help. (q:$query)");
      	$_SESSION[basename(__DIR__).'emptied']=$case_id;
      	echo '<script type="text/javascript"> location.reload(); </script>';
    }

function del_account($creator)
    {
        global $link; global $admin_email; global $demo_config;
        if ($creator!=$_SESSION[basename(__DIR__).'email'] && $_SESSION[basename(__DIR__).'email']!=$admin_email) return "Permission denied";
      if ($demo_config['enabled'] && $_SESSION[basename(__DIR__).'email']==$demo_config['email'])
        { echo "Deleting DEMO profile is not permitted"; return; }

        $query= "DELETE from members where email='$creator'";
        if ($result = $link->query($query))
          {
            return "Account ($creator) deleted!";
          }
        else die("Error in query: ". $link->error.": $query");
    }

function toggle_from_db($creator,$case_id)
    {
        global $link;
        $query= "UPDATE cases set private = not private where id='$case_id' AND creator='$creator'";
        if ($result = $link->query($query))
          {
            if (!$result->num_rows) return "";
            $total=$result->num_rows;
          }
        else die("Error in query: ". $link->error.": $query");
        return "1";
    }

function get_pw($email)
    {
        global $link;
        $query= "SELECT password from members where email='$email'";
        if ($result = $link->query($query))
          {
            if (!$result->num_rows) die("There is no account with email ($email). You can create a new account or email <a href='mailto:$admin_email'>$admin_email</a> for support.");
          }
        else die("Error in query: ". $link->error.": $query");
        $row=$result->fetch_assoc();
        return $row['password'];
    }

function verify_code($email,$code)
    {
        global $link;
        $query= "UPDATE members set verified=1 where email='$email' and verification_str='$code'";
        if ($result = $link->query($query))
          {
            if (!$result->num_rows) die("The code could not be verified. Please try again or email <a href='mailto:$admin_email'>$admin_email</a> for support.");
          }
        else die("Error in query: ". $link->error.": $query");
        echo "Verification successful. Please <a href='http://localhost'>proceed to the main page</a> to sign into your new account.<br>";
        echo "Redirecting in 5 seconds";
        sleep(5);
        header('http://localhost/');
    }

function create_account($replace)
    {
        global $link; global $login_str;
        $query= "SELECT email from members where email='${_POST['email']}' AND verified=1";
        if ($result = $link->query($query))
          {
            if ($result->num_rows && !$replace) die("There is already an account under (${_POST['email']}).<br>Login using your credentials below or email <a href='mailto:$admin_email'>$admin_email</a> for support.<br><br>$login_str");
          }
        else die("Error in query: ". $link->error.": $query");
       $code=md5(uniqid($_POST['email'], true));
       $hash=password_hash($_POST['password'], PASSWORD_DEFAULT);
       if ($replace)
        {
         $query="UPDATE members SET name='${_POST['name']}', password='$hash', title='${_POST['title']}', ".
         "institution='${_POST['institution']}', country='${_POST['country']}' WHERE email='${_POST['email']}' ";
         $returned= "You have now updated your profile successfully.";
        }
       else
       {
         $query="INSERT INTO members (email, name, password, title, institution, country, verified, verification_str) values ".
         "('${_POST['email']}', '${_POST['name']}', '$hash', '${_POST['title']}', '${_POST['institution']}', '${_POST['country']}', 1, '$code')";
         $returned= "You have now signed up. Proceed to log in with your email and chosen password below:<br>$login_str";
       }
       if (!($result = $link->query($query))) die("Could not update the database. Please contact admin! Error in query: ". $link->error.": $query");
	email_admin("",lst($_POST));
        return $returned;
    }

function email_admin($case,$user)
   {
    global $admin_email; global $admin_name; global $website_title; global $website_url;
    global $smtp_host; global $smtp_user; global $smtp_port; global $smtp_secure; global $smtp_pw;

    if (!$smtp_host || !$smtp_port) return;

    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host     = $smtp_host; // SMTP servers
    $mail->SMTPSecure = $smtp_secure;
    $mail->SMTPAuth = true;     // turn on SMTP authentication
    $mail->Username = $smtp_user;
    $mail->Port     = $smtp_port;
    $mail->Password = $smtp_pw;

    $mail->From = $_SESSION[basename(__DIR__).'email'];
    $mail->FromName = $admin_name;
    $mail->SetFrom($_SESSION[basename(__DIR__).'email'],$_SESSION[basename(__DIR__).'email']);
    $mail->addAddress($admin_email, $admin_name);     // Add a recipient

    $mail->WordWrap = 50;                                 // Set word wrap to 50 characters
    $mail->isHTML(false);                                  // Set email format to HTML
    $mail->Timeout=100;

    if ($case)
	{
	    $mail->Subject = "New case added to $website_title";
	    $mail->Body    = "A new case was added to $website_title at $website_url with the below details:\n\n".
	    		     "Case details: $case\n\n--\n\nAdded By account: ${_SESSION[basename(__DIR__).'email']}\n";
	}
    else
	{
	    $mail->Subject = "New user registered on $website_title";
	    $mail->Body    = "Account details: \n\n$user\n\n";
	}
    if(!$mail->send())
       {
        echo 'Mailer Error: ' . $mail->ErrorInfo;
        return false;
       }
   }

function lst($array)
  {
    foreach($array as $key=>$value) { if ($key!='password') $list .="$key: $value\n"; }
    return "$list\n";
  }

function clear_cases()
 {
    if ($_SESSION[basename(__DIR__).'deleted'])
         {
           echo "Case <b>${_SESSION[basename(__DIR__).'deleted']}</b> deleted successfully.<br><hr><br>";
           $_SESSION[basename(__DIR__).'deleted']="";
         }
    if ($_SESSION[basename(__DIR__).'emptied'])
        {
          echo "Case <b>${_SESSION[basename(__DIR__).'emptied']}</b> emptied successfully.<br><hr><br>";
          $_SESSION[basename(__DIR__).'emptied']="";
        }
    if ($_SESSION[basename(__DIR__).'created'])
        {
          echo "Your new case has just been added successfull <font color=red><b>but is not yet populated</b></font>.<br><br><a href='fetch_process.php?id=".$_SESSION[basename(__DIR__).'created']."' target=_blank>Click here</a> to start populating the database in the background.<br><br>The process may take a while depending on your query and amount of data to be populated.<br><br>The process will continue until all the results are fetched or when the maximum number (half a million records) is fetched. <br><hr><br>";
          $_SESSION[basename(__DIR__).'created']="";
        }
 }
?>

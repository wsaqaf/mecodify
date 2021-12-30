<?php

$lifetime=6000;
if(!isset($_SESSION)){
  session_set_cookie_params($lifetime);
  session_start();
}

$enable_new_accounts=1; // set to 0 to disable new accounts (signup)
$allow_new_cases=1; //allow adding new cases (can be set when you wish to prevent altering the DB
$max_tweets_per_case=500000; //maximum tweets per case (applies only to API Search & can be exceeded by 100 records max)
$is_demo=false; //set to true if you would like to have this installation as a demo (allows login with demo@mecodify.org)

$website_url="http://127.0.0.1"; //e.g., https://mecodify.org . Don't end with '/'
$website_title="My Mecodify";

$admin_email=""; //Recommended to create one as the super user (should be the first email to sign up)
$admin_name="";

$mysql_db="Mecodify";
$mysql_server = "localhost";
$mysql_user = "root";
$mysql_pw = "";

$smtp_host=""; //If you wish to receive notifications when users sign up or cases created/edited
$smtp_secure=""; //can be "ssl" or "tls"
$smtp_port="";
$smtp_user="";
$smtp_pw="";

$twitter_api_settings=array(
   "bearer" => "", //here you enter the bearer code (usually starting with 'AAAA')
   "is_premium" => true //here you indicate if the account is free (sandbox) or premium
 );


$platforms=array('1'=>'Twitter'); //Facebook and Youtube and other sources to be added in the future

$website_title=$website_title." (powered by Mecodify v".trim(file_get_contents("ver.no")).")";

$website_url=rtrim($website_url,"/");
connect_mysql();

get_cases();

function connect_mysql()
  {
      global $link;
    global $mysql_db; global $mysql_server; global $mysql_user; global $mysql_pw;

    $link = new mysqli($mysql_server, $mysql_user, $mysql_pw);

    if (!mysqli_select_db($link, $mysql_db)) {
        if(mysqli_query($link, "CREATE DATABASE $mysql_db")){
            echo "DB Successfully created";
            mysqli_select_db($link, $mysql_db);
        }
        else {
                echo "Failed to create DB. Exiting...";
                echo "Error: Unable to connect to MySQL." . PHP_EOL;
                echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
                echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
                exit;
            }
        }

      $link->set_charset("utf8mb4");
      $link->query("SET sql_mode=''");
      $link->query("SET time_zone='+0:00'");
      if (!$link) die ("Can't use $mysql_db : " . $link->error);

      $result=$link->query("SHOW TABLES LIKE 'members'");
      if (!$result->num_rows)
	{
        $sql = file_get_contents('templates/template_tables.sql');
        $sql=str_replace("<DBS>",$mysql_db,$sql);
        $sql=str_replace("<USR>",$mysql_user,$sql);
        $sql=str_replace("<SRVR>",$mysql_server,$sql);
        $query_array = explode(';', $sql);
        $ii = 0;
        if( $link->multi_query( $sql ) )
          {
            do {
                $link->next_result();
                $ii++;
            }
            while( $link->more_results() );
          }

        if( $link->errno )
          {
            die(
                '<h1>ERROR</h1>
                Query #' . ( $ii + 1 ) . ' of <b>template_tables.sql</b>:<br /><br />
                <pre>' . $query_array[ $ii ] . '</pre><br /><br />
                <span style="color:red;">' . $link->error . '</span>'
            );
          }

	}
  }

function get_cases()
  {
    global $cases; global $link; global $cond;

    $query= "SELECT * from cases $cond";

    $result = $link->query($query);

    $cases=array();

    while ($row = $result->fetch_assoc())
      {
        $cases[$row['id']]['name']=$row['name'];
        $cases[$row['id']]['query']=$row['query'];
        $cases[$row['id']]['keywords']=$row['query'];
        $cases[$row['id']]['from']=$row['from_date'];
        $cases[$row['id']]['to']=$row['to_date'];
        $cases[$row['id']]['include_retweets']=$row['include_retweets'];
        $cases[$row['id']]['top_only']=$row['top_only'];
        $cases[$row['id']]['details']=$row['details'];
        $cases[$row['id']]['creator']=$row['creator'];
      }
  }

?>

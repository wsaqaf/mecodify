<?php

require_once("configurations.php");

connect_mysql();

  $query= "SELECT * from cases where id='${_GET['case']}'";
  if ($result = $link->query($query))
    {
      if (!($result->num_rows)) die("There is no case with id (${_GET['id']}).<br> Please delete the old case if it is yours or choose another id.<br>");
    }
  else die("Error in query: ". $link->error.": $query");

$row = $result->fetch_assoc();
$cases[$_GET['case']]=$row;

if ($_GET['case'] && $_GET['q']=="topic") echo $row["name"];
elseif ($_GET['case'] && $_GET['q']=="period")
  {
      echo "Valid period ranges for ${_GET['case']}:<br>".
           "From:".$row["from_date"]." To:".$row["to_date"]."</b>";
  }
elseif ($_GET['case'])
  {
        echo "Name: ${row['name']}<br>";
        echo "Query: ${row['query']}<br>";
  }
?>

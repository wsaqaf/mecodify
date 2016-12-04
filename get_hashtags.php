<?php
error_reporting(E_ALL);
require("configurations.php");

$cases=array(
/*'ISIS',
'ChrChristensen2',
'EFF2015',
'egypt1',
'egypt2',
'Farage2015',
'FeelTheBernNH',
'FeelTheBernNH_All',
'FeesMustFall2015',
'fromUKIP20151wk',
'ICT4D',
'Kas14',
'kenya1',
'kenya2',
'Macchiarini_scandal',
'Malema2014',
'MasperoTweets',
'refugeecrisis',
'serbia1',
'serbia2',
'Sida',
'Sida2016',
'Sisi',
'Sisi_all',
'SONA2016',
'southafrica1',
'southafrica2',
'southafrica3',
'UgandaElections',
'UKIP2015take2',
'UKIP2015Walid',
'UKIP2015Walid2',
'USK2014',
'Yemen'
*/
);
$cases=array("southafrica1_bkup");


foreach ($cases as $table)
 {
echo "Doing $table...";
$query= "SELECT hashtags FROM $table where hashtags is not null AND date_time>='2015-02-12 15:00:00' AND date_time<='2015-02-12 21:00:00'";
    if ($result = $link->query($query))
        {
          if (!$result->num_rows) { echo "No results in the database matched your query.<br>\n";  }
          $total=$result->num_rows;
        }
    else { echo "Error in query: ". $link->error.": $query... Skipping\n\n"; continue; } 

while ($row=$result->fetch_assoc())
  {
     $hashtag=$hashtag." ".$row['hashtags'];
  }
  file_put_contents("cloud.txt",$hashtag);
  echo "Done!\n";
 }
?>

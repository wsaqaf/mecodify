<?php
//This file is only for debugging purposes
// Start Session
session_start();
// Show banner
echo '<b>Session Support Checker</b><hr />';
// Check if the page has been reloaded
if(!isset($_GET['reload']) OR $_GET['reload'] != 'true') {
   // Set the message
   $_SESSION['MESSAGE'] = 'Session support enabled!<br />';
   // Give user link to check
   echo '<a href="?reload=true">Click HERE</a> to check for PHP Session Support.<br />';
} else {
   // Check if the message has been carried on in the reload
   if(isset($_SESSION['MESSAGE'])) {
      echo $_SESSION['MESSAGE'];
   } else {
      echo 'Sorry, it appears session support is not enabled, or you PHP version is to old. <a href="?reload=false">Click HERE</a> to go back.<br />';
   }
}
?>

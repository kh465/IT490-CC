#!/usr/bin/php
<?php
$con = mysqli_connect("100.80.61.121","keven","12345","GC_USERS_DB", "3306");

// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  exit();
}
?> 

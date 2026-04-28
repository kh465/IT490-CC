#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once __DIR__ . ('/logging/logger.php');


function doBooking ($user_id, $booking_type)
{
$con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
// Check connection
if (mysqli_connect_errno()){
	echo "Failed to connect to MYSqL: " . mysqli_connect_error();
	sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
	exit();
}
   else {
	sendRemoteLog("Successful mySQL connection!", "INFO");
   	echo "Successfully connected to mysql database";

}

$stmt = $con-> prepare("SELECT id FROM users WHERE  id = ?");
$stmt0->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

}

function doRegister ($username, $password)
{
 
$con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
// Check connection
if (mysqli_connect_errno()) {
	echo "Failed to connect to MYSqL: " . mysqli_connect_error();
	sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
	exit(); 
}
  else {
	echo "Succesfully connected to mysql database";
	sendRemoteLog("Successful mySQL connection!", "INFO");
}
  
$stmt = $con-> prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
	echo  "\nUser already exists!";
	sendRemoteLog("User already exists!", "WARN");
	return false;
}

$stmt = $con->prepare("INSERT INTO users (id, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password);

if ($stmt->execute()) {
	echo "\nregistered!";
	sendRemoteLog("User registered!", "INFO");
	return true;
} else  {
	echo "\nregistration failed!" ;
	sendRemoteLog("Registration failed!", "WARN");
	return false;
}
}

function doLogin($username,$password)
{
$con = mysqli_connect("127.0.0.1", "keven" ,"12345", "GC_USERS_DB");

// Check connection
if (mysqli_connect_errno()) {
   echo "Failed to connect to MYSqL: " . mysqli_connect_error();
   sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
   exit();
}
else {
   echo "Successfully connected to mysql database";
   sendRemoteLog("Successful mySQL connection!", "INFO");
}

$stmt = $con->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result(); 

if($result->num_rows == 0) {
	echo "\nno user";
	sendRemoteLog("No user?", "WARN");
	return false;
}



if ($row = $result->fetch_assoc()) {
	$dbpword = $row['password_hash'];
	if ($dbpword == $password) {
		echo "\ntrue!";
		return true;
	}
	
		
	else {
		echo "\nfalse!";
		return false;
	}
}
    // lookup username in database
    // check password
    // $login = new loginDB();
    //return $login->validateLogin($username,$password);
    //return true;
    //return true if not valid
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "login":
      return doLogin($request['username'],$request['password']);
    case "book":
      return doBooking($request['user_id'],$request['booking_type']);
    case "register":
      return doRegister($request['username'],$request['password']);
	
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>


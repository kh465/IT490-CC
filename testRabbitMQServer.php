#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');

function doLogin($username,$password)
{
$con = mysqli_connect("127.0.0.1", "keven" ,"12345", "GC_USERS_DB");

// Check connection
if (mysqli_connect_errno()) {
   echo "Failed to connect to MYSqL: " . mysqli_connect_error();
   exit();
}
else {
   echo "Successfully connected to mysql database";
}

$stmt = $con->prepare("SELECT password_hash FROM users WHERE id = ?");
$uname = $username;
$pword = $password;
$stmt->bind_param("s", $uname);
echo $stmt;
$stmt->execute();
$stmt->bind_result($dbpword);
echo $dbpword;
if ($pword == $dbpword) {
	return true;
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
    case "validate_session":
      return doValidate($request['sessionId']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>


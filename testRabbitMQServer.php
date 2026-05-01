#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once __DIR__ . ('/logging/logger.php');

function doLogDeploy($env, $release, $commit, $status, $decidedBy, $notes) {
$con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to MySQL: " . mysqli_connect_error(), "FAIL");
        return false;
    }

    $decidedAt = ($status === 'pending') ? null : date('Y-m-d H:i:s');

    $stmt = $con->prepare("INSERT INTO deployments(environment, release_name, commit_hash, status, decided_by, notes, decided_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $env, $release, $commit, $status, $decidedBy, $notes, $decidedAt);

    if ($stmt->execute()) {
        sendRemoteLog("Deploy logged: $release on $env as $status", "INFO");
        return true;
    } else {
        sendRemoteLog("Deploy log failed: " . $stmt->error, "FAIL");
        return false;
    }
}

function doSetStatus($release, $status, $decidedBy, $notes) {
$con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to MySQL: " . mysqli_connect_error(), "FAIL");
        return false;
    }

    $stmt = $con->prepare("UPDATE deployments SET status = ?, decided_by = ?, notes = ?, decided_at = NOW() WHERE release_name = ? AND status = 'pending'");
    $stmt->bind_param("ssss", $status, $decidedBy, $notes, $release);

    if ($stmt->execute()) {
        sendRemoteLog("Status set: $release → $status", "INFO");
        return true;
    } else {
        sendRemoteLog("Set status failed: " . $stmt->error, "FAIL");
        return false;
    }
}

function doLogRollback($env, $release, $decidedBy, $notes) {
$con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to MySQL: " . mysqli_connect_error(), "FAIL");
        return false;
    }

    $stmt = $con->prepare("INSERT INTO deployments(environment, release_name, commit_hash, status, decided_by, notes, decided_at) VALUES (?, ?, '', 'rolled_back', ?, ?, NOW())");
    $stmt->bind_param("ssss", $env, $release, $decidedBy, $notes);

    if ($stmt->execute()) {
        sendRemoteLog("Rollback logged: $release on $env", "INFO");
        return true;
    } else {
        sendRemoteLog("Rollback log failed: " . $stmt->error, "FAIL");
        return false;
    }
}

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
$stmt->bind_param("s", $username);
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

$password = password_hash($password, PASSWORD_DEFAULT);
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
	$dbword = $row['password_hash'];
	if (password_verify($password, $dbword)) {
		echo "\ntrue!";
                sendRemoteLog("Login successful for user: " . $username, "INFO");
		return true;
        } else { 
                echo  "\nfalse!";
                sendRemoteLog("Invalid password for user: " . $username, "WARN");
                return false;
	}
}

return false; // prevent any null bugs

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
    case "log_deploy":
      return doLogDeploy($request['env'],$request['release'],$request['commit'],$request['status'],$request['decided_by'] ?? null,$request['notes'] ?? null);
    case "set_status":
      return doSetStatus($request['release'],$request['status'],$request['decided_by'] ?? null,$request['notes'] ?? null);
    case "log_rollback":
      return doLogRollback($request['env'],$request['release'],$request['decided_by'] ?? null,$request['notes'] ?? null);
	
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>


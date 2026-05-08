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
        sendRemoteLog("Status set: $release -> $status", "INFO");
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

return false; // used only when there are not any matching users in DB

    // General logic flow:
    // lookup username in database
    // check the password to see if it matches
    // $login = new loginDB(); function allows the user to login
    //return $login->validateLogin($username,$password); vlidation stage
    //return true; (user logged in aka valid user)
    // false means the user is not in DB
}


function doSaveBooking($username, $session_key, $event_title, $event_description,
                       $event_date, $event_address, $event_url, $event_thumbnail, $venue_name)
{
 $con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to mySQL database: " . mysqli_connect_error(), "FAIL");
        return ['status'=> 'error', 'message' => 'Database connection failed'];
    }

    if (empty($username) || empty($event_title)) {
        
           return ['status' => 'error', 'message'  => 'Missing username or event title'];
    }

    $now = time();
    $booking_type = 'activity';
    $status = 'saved';
    $currency = 'USD';

    $stmt = $con->prepare(
        "INSERT INTO terrifictravel_bookings
         (event_title, username, booking_type, status, currency,
          notes, event_address, event_description, event_url, event_thumbnail, venue_name,
          created_at_epoch, updated_at_epoch)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        
            sendRemoteLog("save_booking prepare failed: " . $con->error, "FAIL");
            return ['status'=> 'error', 'message' => 'Prepare failed'];
    }

    $stmt->bind_param("sssssssssssii",
        $event_title, $username, $booking_type, $status, $currency,
        $event_date, $event_address, $event_description, $event_url, $event_thumbnail, $venue_name,
        $now, $now
    );

    if ($stmt->execute()) {
        sendRemoteLog("Booking saved: $event_title for $username", "INFO");
        return ['status' => 'success', 'message' => 'Booking saved'];
    } else {
        if ($stmt->errno === 1062) {
            sendRemoteLog("Duplicate booking: $event_title", "WARN");
            return ['status' => 'error', 'message' => 'This activity is already in your bookings'];
        }
        sendRemoteLog("save_booking failed: " . $stmt->error, "FAIL");
        return ['status' => 'error', 'message' => 'Could not save booking'];
    }
}

function doGetBookings($username, $session_key)
{
    $con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
        return ['status' => 'error', 'message' => 'Database connection failed'];
    }

    if (empty($username)) {
        return ['status' => 'error', 'message' => 'Missing username'];
    }

    // alias notes back to event_date so the frontend keeps working unchanged
 $stmt = $con->prepare(
        "SELECT event_title,
               COALESCE(notes, '') AS event_date,
               COALESCE(venue_name, '') AS venue_name,
               COALESCE(event_address, '') AS event_address,
               COALESCE(event_description, '') AS event_description,
               COALESCE(event_url, '') AS event_url,
               COALESCE(event_thumbnail, '') AS event_thumbnail
         FROM terrifictravel_bookings
         WHERE username = ?
         ORDER BY created_at_epoch DESC"
 );

 if (!$stmt) {
        sendRemoteLog("get_bookings prepare failed: " . $con->error, "FAIL");
        return ['status' => 'error', 'message' => 'Prepare failed'];
 }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    sendRemoteLog("get_bookings: " . count($bookings) . " rows for $username", "INFO");
    return ['status' => 'success', 'bookings' => $bookings];
}

function doSaveReview($username, $session_key, $event_title, $rating, $review_text)
{
    $con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
        return ['status'  => 'error', 'message'  => ' Connection failed'];
    }

    if (empty($username) || empty($event_title)) {
        return ['status'=> 'error', 'message' => 'Missing username or event title, please insert'];
    }
    if ($rating < 1 || $rating > 5) {
        return ['status' => "error", 'message' => 'Rating must be between 1 and 5, review again'];
    }

    // verify the booking actually exists for this user before reviewing it
    $check = $con->prepare(
        "SELECT event_title FROM terrifictravel_bookings
         WHERE username = ? AND event_title = ? LIMIT 1"
    );
    $check->bind_param("ss", $username, $event_title);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        return ['status'  => 'error', 'message' => 'No matching booking found for this event'];
    }

    $now = time();

    // upsert function present if the user just does a mistake it re updates instead of crashing/error
    $stmt = $con->prepare(
        "INSERT INTO terrifictravel_reviews
         (username, event_title, rating, review_text, created_at_epoch)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            review_text = VALUES(review_text),
            created_at_epoch = VALUES(created_at_epoch)"
    );

    if (!$stmt) {
        sendRemoteLog("save_review prepare failed: " . $con->error, "FAIL");
        return ['status' => 'error', 'message' => 'Prepare failed'];
    }

    $stmt->bind_param("ssisi", $username, $event_title, $rating, $review_text, $now);

    if ($stmt->execute()) {
        sendRemoteLog("Review saved: $event_title by $username ($rating/5)", "INFO");
        return ['status'  => 'success', 'message' => 'Review saved'];
    } else {
        sendRemoteLog("save_review failed: " . $stmt->error, "FAIL");
        return ['status' => 'error', 'message'  => 'Could not save review'];
    }
}

function doGetReviews($username, $session_key)
{
    $con = mysqli_connect("127.0.0.1", "keven", "12345", "GC_USERS_DB");
    if (mysqli_connect_errno()) {
        sendRemoteLog("Failed to connect to mySQL: " . mysqli_connect_error(), "FAIL");
        return ['status'  => 'error', 'message' => 'Database connection failed'];
    }

    if (empty($username)) {
        return ['status' => 'error', 'message' => 'Missing username'];
    }

    $stmt = $con->prepare(
        "SELECT event_title, rating, review_text, created_at_epoch
         FROM terrifictravel_reviews
         WHERE username = ?
         ORDER BY created_at_epoch DESC"
    );

    if (!$stmt) {
        sendRemoteLog("get_reviews prepare failed: " . $con->error, "FAIL");
        return ['status' => 'error', 'message' => 'Prepare failed'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $row['created_at'] = !empty($row['created_at_epoch'])
            ? date('Y-m-d H:i', (int)$row['created_at_epoch'])
         : '';
        $reviews[] = $row;
    }

    sendRemoteLog("get_reviews: " . count($reviews) . " rows for $username", "INFO");
    return ['status' => 'success', 'reviews' => $reviews];
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
    case "save_booking":
      return doSaveBooking($request['username'] ?? '', $request['session_key'] ?? '', $request['event_title'] ?? '', $request['event_description'] ?? '', $request['event_date'] ?? '', $request['event_address'] ?? '', $request['event_url'] ?? '', $request['event_thumbnail'] ?? '', $request['venue_name'] ?? '');
    case "get_bookings":
      return doGetBookings($request['username'] ?? '', $request['session_key'] ?? '');
    case "save_review":
      return doSaveReview($request['username'] ?? '', $request['session_key'] ?? '', $request['event_title'] ?? '', (int)($request['rating'] ?? 0), $request['review_text'] ?? '');
    case "get_reviews":
      return doGetReviews($request['username'] ?? '', $request['session_key'] ?? '');
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>
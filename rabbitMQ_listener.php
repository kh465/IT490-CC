<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

//  Establish the connection right now through rabbitmq sample code
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

//  DECLARE QUEUE
$channel->queue_declare('auth_queue', false, true, false, false);

// CONNECT TO DATABASE
// This is used to verify if the user exists
try {
    $pdo = new PDO("mysql:host=localhost;dbname=GC_USERS_DB", "root", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Server: Connected to Database.\n";
} catch (PDOException $e) {
    die("Server: Could not connect to DB. " . $e->getMessage());
}

echo "Server: Waiting for requests...\n";

// defines the callback or logic
$callback = function ($msg) use ($pdo, $channel) {
    echo "Server: Received Request.\n";
    
    $data = json_decode($msg->body, true);
    $response = [];

    // login code
    if ($data['type'] === 'login') {
        echo "Server: Processing Login for " . $data['username'] . "\n";

        // see if the user exists inside of the database
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // boolean check to see if password and usernmae match
        if ($user && password_verify($data['password'], $user['password_hash'])) {
            
            echo "Server: Password Verified. Logging in.\n";

            // Create a session key for the user
            $session_key = bin2hex(random_bytes(32));

            // Store the session in DB (Optional, but good for security)
            $stmt = $pdo->prepare("INSERT INTO session_token (user_id, session_key) VALUES (?, ?)");
            $stmt->execute([$user['id'], $session_key]);

            // returns true for successful login
            $response = [
                "status" => "success",  
                "session_key" => $session_key
            ];

        } else {
            echo "Server: Verification Failed.\n";
            
            // RETurn false or failure to login
            $response = [
                "status" => "failure", 
                "message" => "Invalid credentials"
            ];
        }
    }
    
    // works if the person is registering for the first time right now. 
    elseif ($data['type'] === 'register') {
        echo "Server: Processing Registration for " . $data['username'] . "\n";
        
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$data['username'], $hash]);
            $response = ["status" => "success"];
            echo "Server: User Registered.\n";
        } catch (Exception $e) {
            $response = ["status" => "failure", "message" => "User already exists"];
            echo "Server: Registration Failed (User exists).\n";
        }
    }

     // checks the sessions or validates it
    elseif ($data['type'] === 'validate_session') {
        echo "Server: Validating session for " . $data['username'] . "\n";
        
        // Check if the session_key exists AND belongs to the correct user
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM session_token s
            JOIN users u ON s.user_id = u.id
            WHERE u.username = ? AND s.session_key = ?
        ");
        $stmt->execute([$data['username'], $data['session_key']]);
        $valid_session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($valid_session) {
            echo "Server: Session is valid.\n";
            $response = ["status" => "success"];
        } else {
            echo "Server: Session is INVALID or spoofed.\n";
            $response = ["status" => "failure"];
        }
    }

    // code here sends the response back
    $reply = new AMQPMessage(
        json_encode($response),
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply, '', $msg->get('reply_to'));
    $msg->ack();
};

$channel->basic_consume('auth_queue', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
?>

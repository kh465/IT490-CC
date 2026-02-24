<?php
require_once __DIR__ . '/vendor/autoload.php'; // need to create vendor code later

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('RABBITMQ_IP', 5672, 'guest', 'guest'); // sample  connection replace later with actual config
$channel = $connection->channel();

$channel->queue_declare('auth_queue', false, true, false, false); // new queue

$pdo = new PDO("mysql:host=localhost;dbname=GC_USERS_DB", "root", "password");

$callback = function ($msg) use ($pdo, $channel) {

    $data = json_decode($msg->body, true); // get json
    $response = [];

    if ($data['type'] === 'register') {

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$data['username'], $hash]);
            $response = ["status" => "success"];
        } catch (Exception $e) {
            $response = ["status" => "failure", "message" => "User already exists"];
        }

    } elseif ($data['type'] === 'login') {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['password'], $user['password_hash'])) {

            $session_key = bin2hex(random_bytes(32)); // create session key

            $stmt = $pdo->prepare("INSERT INTO session_token (user_id, session_key) VALUES (?, ?)");
            $stmt->execute([$user['id'], $session_key]);

            $response = [
                "status" => "success",
                "session_key" => $session_key
            ];

        } else {
            $response = ["status" => "failure"];
        }
    }

    $reply = new AMQPMessage(
        json_encode($response),
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply, '', $msg->get('reply_to'));
    $msg->ack();
};

$channel->basic_consume('auth_queue', '', false, false, false, false, $callback);

while ($channel->is_consuming()) { // listener/consumer
    $channel->wait();
}

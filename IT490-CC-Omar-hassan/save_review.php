<?php
session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('host.ini');

$err = '';
$successmes = '';
$bookings = [];
$reviews = [];

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    $err = "Please login first";
    exit();
}

$client = null;
try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
} catch (Exception $e) {
    $err = "Error: Could not connect to the server.";
}

if ($client) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        $eventName = $_POST['event_name'];
        $rating = (int)$_POST['rating'];
        $ratingDes = $_POST['description'];

        if ($rating < 1 || $rating > 5) {
            $err = "Please select a rating between 1 and 5.";
        } elseif (empty($eventName)) {
            $err = "Name of event not specified.";
        } else {
            $request = [
                'type'=> 'save_review',
                'username' => $_SESSION['username'],
                'session_key' => $_SESSION['session_key'],
                'event_title'=> $eventName,
                'rating' => $rating,
                'review_text' => $ratingDes,
            ];

            $response = $client->send_request($request);


            if (isset($response['status'])) {
                $successmes = "Review has been saved for: " . htmlspecialchars($eventName);
            } else {
                $err = $response['message'] ?? "Could not save review.";
            }
        }
    }

    $request = [
        'type' => 'get_bookings',
        'username' => $_SESSION['username'],
        'session_key' => $_SESSION['session_key'],
    ];
  }
?>
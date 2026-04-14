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

    $request = [
        'type'  => 'get_reviews',
        'username' => $_SESSION['username'],
        'session_key' => $_SESSION['session_key'],
    ];

    $response = $client->send_request($request);

    if (isset($response['status'])) {
        $reviews = $response['reviews'];
    }
}

?>
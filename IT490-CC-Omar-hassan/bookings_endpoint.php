<?php // This is the get bookings logic moved over to help make things more async and load  faster
session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('host.ini');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$bookings = [];
$err = '';

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = [
        'type' => 'get_booking',
        'username' => $_SESSION['username'],
        'session_key' =>  $_SESSION['session_key']
    ];


    $response = $client->send_request($request);

    if (isset($response['status'])) {
        $bookings = $response['bookings'];
    } else {
        $err = $response['message'] ?? "You haven't booked any activities yet";
    }
} catch (Exception $e) {
    $err = "Error: Booking server is currently unreachable.";
}
?>
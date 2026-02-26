<?php
session_start();
require 'rpc_client.php'; // Import the class

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rpc = new AuthRPC(); // You were missing this line!

    $response = $rpc->call([
        "type" => "register",
        "username" => $_POST['username'],
        "password" => $_POST['password']
    ]);

    if ($response['status'] === 'success') {
        echo "Registration successful! <a href='login.php'>Login here</a>";
    } else {
        echo "Registration failed: " . ($response['message'] ?? 'Unknown error');
    }
}
?>
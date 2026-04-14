<?php
session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('host.ini');

$error = "";
$success = "";

// If already logged in, redirect
if (isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['register'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $confirm_password = trim($_POST['confirm_password']);

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields."; // checks if user left any fields empty
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {

        try {
            $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

            $request = [];
            $request['type'] = "register";
            $request['username'] = $username;
            $request['password'] = $password;
            $request['message'] = "registration attempt";

            $response = $client->send_request($request);

            if ($response) {
                $success = "Registration successful! You may now log in.";
            } else {
                $error = $response['message'] ?? "Registration failed.";
            }

        } catch (Exception $e) {
            $error = "The authentication server is currently unreachable. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>

<nav class="navbar">
    <div class="logo"><a href="index.php">Game Central</a></div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <?php if (isset($_SESSION["username"])): ?>
            <li>Logged in as: <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></li>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="login-container">
    <h1>Register</h1>

    <form action="register.php" method="POST">

        <?php if ($error): ?>
            <p style="color: red; font-weight: bold;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: green; font-weight: bold;">
                <?php echo htmlspecialchars($success); ?>
            </p>
        <?php endif; ?>

        <label for="username">Username:</label>
        <input type="text" name="username" placeholder="Choose a Username" required>

        <label for="password">Password:</label>
        <input type="password" name="password" placeholder="Create a Password" required>

        <label for="confirm_password">Confirm Password:</label>
        <input type="password" name="confirm_password" placeholder="Confirm your Password" required>

        <input type="submit" value="Register" name="register">
    </form>

    <p style="text-align:center; margin-top:20px;">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>

</body>
</html>
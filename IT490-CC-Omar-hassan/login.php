<?php
session_start();
require 'rpc_client.php';

$error = "";
$success = "";


if (isset($_GET['error']) && $_GET['error'] === 'invalid_session') {
    $error = "Your session has expired. Please log in again.";
}

if(isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if(empty($username) || empty($password)) {
        $error = "Please enter both your Username and Password.";
    } else {
    
        try {
            $client = new rabbitMQClient("$username", "$password");

            $request = array();
            $request['type'] = "login";
            $request['username'] = "$username";
            $request['password'] = "$password";
            $request["message"] = "login attempt";

            $response = $client->send_request($request);

            if($response['status'] === "true") { // logs the user in and sends them to main page
                $_SESSION["username"] = $username;
                $_SESSION["session_key"] = $response['session_key'];
                header("Location: index.php");
                exit();
            } else {

                $error = "Invalid Username or Password.";
            }

        } catch (Exception $e) {// for any other possible error this gets thrown
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
    <title>Login Page</title>
    <link rel="stylesheet" type ="text/css" href = "styles.css">
</head>
<body>
    <nav class="navbar">
        <div class = "logo"><a href=index.php>Game Central</a></div>
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
        <h1>Login</h1>

        <form action = "login.php" method = "POST">
            <?php if($error): ?>
            <p style="color: red; font-weight: bold;"><?php echo $error; ?></p>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <p style="color: red; font-weight: bold;">
            <?php echo htmlspecialchars($success); ?>
            </p>
            <?php endif; ?>
    

            <label for="username">Username:</label>
            <input type = "text" name ="username" placeholder="Please enter your Username">

            <label for="password">Password:</label>
            <input type = "password" name ="password" placeholder="Please enter your Password">

            <input type="submit" value ="Login" name = "login" >
        </form>
    </div>
</body>
</html>
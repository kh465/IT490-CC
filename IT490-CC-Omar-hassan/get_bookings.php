<?php
session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('host.ini');

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$bookings = [];
$err = '';

$rabbitmq_down = false;
ini_set('default_socket_timeout', 5);

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
    $rabbitmq_down = true; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings – Hotel HotSpot</title>
    <link rel = "stylesheet" type = "text/css" href = "styles.css">
    <style>
        .booking-card {
            border: 1px solid #ddd;
            padding: 16px;
            margin-bottom: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .booking-card img { border-radius: 4px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class ="logo"> <a href = "index.php"> Hotel HotSpot</a></div>
        <ul>
            <li><a href = "index.php">Home</a></li>
            <?php if (isset($_SESSION['username'])): ?>
            <li>Logged in as: <strong> <?php echo htmlspecialchars($_SESSION['username']); ?> </strong></li>
            <li><a href= "logout.php">Logout</a></li>
            <?php else: ?>
            <li><a href = "login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class = "main-container">
        <h1>My Saved Bookings</h1>

        <?php if ($rabbitmq_down): ?>
        <p style="color:red; font-weight:bold;">Our booking service is currently having some issues, please refresh</p>
        <?php elseif ($err): ?>
            <p style = "color: orange; font-weight: bold;"><?php echo htmlspecialchars($err); ?></p>
            <p><a href ="search_results.php">Back to search results</a></p>
        <?php elseif (empty($bookings)): ?>
            <p>You don't have any bookings. <a href="search_results.php">Start searching here.</a></p>
        <?php else: ?>
            <p>Showing your <strong><?php echo count($bookings); ?></strong> saved activities.</p>

            <?php foreach ($bookings as $book):
                $title= htmlspecialchars($book['event_title']);
                $date = htmlspecialchars($book['event_date']);
                $venue = htmlspecialchars($book['venue_name']);
                $addr= htmlspecialchars($book['event_address']);
                $desc =htmlspecialchars($book['event_description']);
                $thumb = htmlspecialchars($book['event_thumbnail']);
                $url = htmlspecialchars($book['event_url']);
            ?>
                <div class="booking-card">
                    <?php if ($thumb): ?>
                        <img src="<?php echo $thumb; ?>" alt="<?php echo $title; ?>"
                             style="width:100%; max-width:300px; height:auto; display:block; margin-bottom:10px;">
                    <?php endif; ?>

                    <h3><?php echo $title; ?></h3>

                    <ul style ="list-style:none; padding:0; margin-bottom:12px;">
                        <?php if ($date): ?>
                            <li><strong>Details:</strong> <?php echo $date; ?></li>
                        <?php endif; ?>

                        <?php if ($venue): ?>
                            <li><strong>Categories:</strong> <?php echo $venue; ?></li>
                        <?php endif; ?>

                        <?php if ($addr): ?>
                            <li>Location:<?php echo $addr; ?></li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($desc): ?>
                        <p style ="font-size:0.9em; color:#555;"><?php echo substr($desc, 0, 150) . '...'; ?></p>
                    <?php endif; ?>

                    <?php if ($url): ?>
                        <a href = "<?php echo $url; ?>" target="_blank" rel="noopener noreferrer"
                           style = "display:inline-block; margin-top:10px; padding:8px 12px; background:#007BFF; color:#fff; text-decoration:none; border-radius:4px;">Book Externally</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
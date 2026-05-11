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
$rabbitmq_down = false;

ini_set('default_socket_timeout', 5);

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$client = null;
try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
} catch (Exception $e) {
    $rabbitmq_down = true;
}

if ($client) {

    // Handle review submission first if this is a POST request or something
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        $eventName = trim($_POST['event_name'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        $ratingDes = trim($_POST['description'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $err = "Please select a rating between 1 and 5.";
        } elseif (empty($eventName)) {
            $err = "Name of event not specified.";
        } else {
            $request = [
                'type' => 'save_review',
                'username' => $_SESSION['username'],
                'session_key' => $_SESSION['session_key'],
                'event_title' => $eventName,
                'rating' => $rating,
                'review_text' => $ratingDes,
            ];

            try {
                $response = $client->send_request($request);
                if (isset($response['status']) && $response['status'] === 'success') {
                    $successmes = "Review has been saved for: " . htmlspecialchars($eventName);
                }   else {
                   
                    $err = $response['message'] ?? "Could not save review.";
                }
            } catch (Exception $e) {
                $rabbitmq_down = true;
            }
        }
    }

    // Always fetch bookings (for the dropdown) on every page load
    try {
        $request = [
            'type' => 'get_bookings',
            'username'=> $_SESSION['username'],
            'session_key' => $_SESSION['session_key'],
        ];
        $response = $client->send_request($request);

        if (isset($response['status']) && $response['status'] === 'success') {
            $bookings = $response['bookings'] ?? [];
        }
    } catch (Exception $e) {
        $rabbitmq_down = true;
    }

    // Always fetch existing reviews for the user on every page load
    try {
        $request = [
            'type' => 'get_reviews',
            'username' => $_SESSION['username'],
            'session_key' => $_SESSION['session_key'],
        ];
        $response = $client->send_request($request);

        if (isset($response['status']) && $response['status'] === 'success') {
            $reviews = $response['reviews'] ?? [];
        }
    } catch (Exception $e) {
        $rabbitmq_down = true;
    }
}

function renderStars($rating) {

    $rating = (int)$rating;
    $stars = '';

    for ($i = 1; $i <= 5; $i++) {
        $stars .= ($i <= $rating) ? '&#9733;' : '&#9734;';
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang ="en">
<head>
    <meta charset="UTF-8">
    <meta name = "viewport" content ="width=device-width, initial-scale=1.0">
    <title>Review Bookings – Hotel HotSpot</title>
    <link rel = "stylesheet" type = "text/css" href = "styles.css">
    <style>
        .star-row {
            display: inline-block;
            font-size: 26px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .star-row span {
            color: #ccc;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo"><a href="index.php">Hotel HotSpot</a></div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <?php if (isset($_SESSION['username'])): ?>
        <li>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></li>
        <li><a href = "logout.php">Logout</a></li>
        <li><a href= "get_bookings.php">See your bookings</a></li>
        <?php else: ?>
        <li><a href = "login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class= "main-container">
    <h1>Review and Rate Your Bookings</h1>

    

    <?php if ($err): ?>
        <p style = "color: red; font-weight: bold;"><?php echo htmlspecialchars($err); ?></p>
    <?php endif; ?>

    <?php if ($successmes): ?>
        <p style = "color: green; font-weight: bold;"><?php echo $successmes; ?></p>
    <?php endif; ?>

    <?php if (!empty($bookings)): ?>

    <form method = "POST" action="review.php">
        <h2>Leave a Review</h2>

        <label for = "event_name">Select a  Booking:</label><br>
        <select name = "event_name" id ="event_name" required style="padding: 6px; margin-bottom: 10px;">
            <option value = "">Choose one of the bookings from down below</option>
            <?php foreach ($bookings as $book): ?>
                <option value = "<?php echo htmlspecialchars($book['event_title']); ?>">
                    <?php echo htmlspecialchars($book['event_title']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>Rating (1-5):</label><br>
        <div class="star-row" id="star-container">

            <span data-value = "1">&#9734;</span>
            <span data-value= "2">&#9734;</span>

            <span data-value= "3">&#9734;</span>
            <span data-value = "4">&#9734;</span>

            <span data-value ="5">&#9734;</span>

        </div>
        <input type= "hidden" name = "rating" id = "rating_input">
        <br>

        <label for =  "description">Review Description (optional): </label><br>
        <textarea name = "description" id="description" rows="4"
                  style  ="width: 60%; max-width: 400px; padding: 6px; margin-bottom: 10px;"
                  placeholder="Write your thoughts, or tell us what you think"></textarea><br>

        <input type= "submit" name = "submit_review" value = "Submit Review" style = "padding: 8px 16px;">
    </form>
    <?php else: ?>
        <p>No bookings to review yet. <a href="search_results.php">Find something you'd like</a></p>
    <?php endif; ?>

    <?php if (!empty($reviews)): ?>
        <h2 style="margin-top: 40px;">Your Past Reviews and Events</h2>
        <?php foreach ($reviews as $review): ?>
            <div style="border: 1px solid #ddd; padding: 14px; margin-bottom: 16px; border-radius: 8px; background: #f9f9f9;">
                <h3><?php echo htmlspecialchars($review['event_title']); ?></h3>
                <p><?php echo renderStars((int)$review['rating']); ?>
                   <strong><?php echo (int)$review['rating']; ?>/5</strong></p>
                <?php if (!empty($review['review_text'])): ?>
                    <p style="color: #555;"><?php echo htmlspecialchars($review['review_text']); ?></p>
                <?php endif; ?>

                <?php if (!empty($review['created_at'])): ?>
                    <small style="color: #aaa;">Reviewed on: <?php echo htmlspecialchars($review['created_at']); ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
     let  stars = document.querySelectorAll("#star-container span");
    let  ratingInput = document.getElementById("rating_input");
    let  container = document.getElementById("star-container");
    let selected = 0;

    for (let  i = 0; i < stars.length; i++) {
        stars[i].addEventListener("mouseover", function() {
            fillStars(this.getAttribute("data-value"));
        });

        stars[i].addEventListener("click", function() {
            selected = this.getAttribute("data-value");
             ratingInput.value = selected;
            //  console.log("clicked star: " + selected);
            fillStars(selected);
        });
    }

    container.addEventListener("mouseout", function() {
        fillStars(selected);
    });

    function fillStars(val) {
        for (let  s = 0; s < stars.length; s++) {
        
            let starVal = stars[s].getAttribute("data-value");
            if (starVal <= val) {
    
                stars[s].innerHTML = '&#9733;';
                stars[s].style.color = '#FFD700';

            } else {

                stars[s].innerHTML = '&#9734;';
                stars[s].style.color  = '#ccc';

            }
        }
    }
});

</script>

</body>
</html>
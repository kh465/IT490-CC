<?php
// Copyright 2026 oh826
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     https://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

session_start();
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('host.ini');


// --- Auth ---
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/*
try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = array();
    $request['type'] = "validate_session";
    $request['username'] = $_SESSION['username'];
    $request['session_key'] = $_SESSION['session_key'];
    $response = $client->send_request($request);
    if(!$response || $response['status'] !== 'success') {
        session_destroy();
        header("Location: login.php?error=invalid_session");
        exit();
    }
} catch (Exception $e) {
    $authWarning = "Warning: Session validation unavailable.";
} // checks if the session is still valid

*/

// store here for now might need config file later
define('AMADEUS_CLIENT_ID',     'CzkQYGg81I4Q4WEUfsd49jxRG6NL9Srl');
define('AMADEUS_CLIENT_SECRET', '4jGYNBNGPtLeF2LC');
define('AMADEUS_BASE_URL',      'https://test.api.amadeus.com');


function getAmadeusToken(): string {
    if (
        isset($_SESSION['amadeus_token'], $_SESSION['amadeus_token_expiry']) &&
        $_SESSION['amadeus_token_expiry'] > time()
    ) {
        return $_SESSION['amadeus_token'];
    }

    $ch = curl_init(AMADEUS_BASE_URL . '/v1/security/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([

            'grant_type'   => 'client_credentials',
            'client_id'=> AMADEUS_CLIENT_ID,
             'client_secret' => AMADEUS_CLIENT_SECRET,
    ]));

    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

     $raw = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $curlErr = curl_error($ch);
      curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        throw new Exception("Amadeus token request failed");
    }

    $tokenData = json_decode($raw, true);
    if (empty($tokenData['access_token'])) {
        throw new Exception("Amadeus token response missing access_token.");
    }

    $expiresIn = isset($tokenData['expires_in']) ? (int)$tokenData['expires_in'] : 1799; //Checks how long/ valid the session is
    $expiresIn = $expiresIn - 60;

    $_SESSION['amadeus_token'] = $tokenData['access_token'];

    $_SESSION['amadeus_token_expiry'] = time() + $expiresIn;

    return $_SESSION['amadeus_token'];
}

// --- Input validation ---
$actId = trim($_GET['act_id'] ?? '');
$lat = filter_var($_GET['lat'] ?? '', FILTER_VALIDATE_FLOAT) ?: 40.7357;
$lng  = filter_var($_GET['lng'] ?? '', FILTER_VALIDATE_FLOAT) ?: -74.1724;

if (empty($actId) || !preg_match('/^[a-zA-Z0-9\-]+$/', $actId)) {
    header("Location: index.php");
    exit();
}

// --- Fetch activity detail ---
$activity = [];
$fetchError = '';

try {
    $token = getAmadeusToken();

    $ch = curl_init(AMADEUS_BASE_URL . '/v1/shopping/activities/' . urlencode($actId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlErr = curl_error($ch);
    curl_close($ch);


    if ($curlErr) {
        $fetchError = "Error: Amadeus request failed.";
    } 
     elseif ($httpCode === 404) {
        $fetchError = "Error: Activity not found.";

    } elseif ($httpCode !== 200) {
        $fetchError = "Error: Amadeus returned HTTP $httpCode.";

    } else {
        $responseData = json_decode($raw, true);
        $activity = $responseData['data'] ?? [];
        
        if (empty($activity) || json_last_error() !== JSON_ERROR_NONE) {
            $fetchError = "Error: Could not parse activity data.";
            $activity = [];
        }
    }

} catch (Exception $e) {
    $fetchError = "Error: Travel data service unavailable.";
}


$bookingSuccess = false;
$bookingError = '';
$alreadyBooked = false;

if (!empty($activity) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_activity'])) {

    $heroPhoto = !empty($activity['pictures']) ? $activity['pictures'][0] : '';

    $priceStr = '';
    if (!empty($activity['price']['amount'])) {
        $priceStr = ($activity['price']['currencyCode'] ?? 'USD') . ' '
            . number_format((float)$activity['price']['amount'], 2);
    }

    $parts = [];
    if($priceStr) $parts[] = "From $priceStr";
    if(isset($activity['minimumDuration']) && formatDuration($activity['minimumDuration'])) {
     $parts[] = formatDuration($activity['minimumDuration']);
    }
    $eventDate = implode(' · ', $parts);

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

        $request = [
            'type'  => 'save_booking',
            'username' => $_SESSION['username'],
            'session_key'=> $_SESSION['session_key'],
            'event_title' => $activity['name'] ?? 'Unnamed Activity',
            'event_description' => $activity['description'] ?? $activity['shortDescription'],
            'event_date' => $eventDate,
            'event_address' => buildGeoString($activity),
            'event_url' => $activity['bookingLink'],
            'event_thumbnail'=> $heroPhoto,
            'venue_name' => implode(', ', $activity['categories'] ?? []),
        ];

        $response = $client->send_request($request);

        if ($response) {
            $bookingSuccess = true;
        } 
        else {
            $bookingError = $response['message'] ?? "Error, event already booked or unavailiable";
        }

    } catch (Exception $e) {
        $bookingError = "Error: Booking server unreachable.";
    }
}

// helpers with updated logic 
function formatDuration($iso): string {
    if (!$iso) return '';
    
    $time_part = explode('T', $iso)[1];

    if(strpos($time_part, 'H') !== false) {
    $hours   = (int)explode('H', $time_part)[0];
    $after_h = explode('H', $time_part)[1];
    }    else {
        $after_h = $time_part;
    }

    if(strpos($after_h, 'M') !== false) {
     $minutes = (int)explode('M', $after_h)[0];
    }

    if(!empty($hours) && !empty($minutes)) return "{$hours}h {$minutes}m";

    if(!empty($hours))   return "{$hours}h";
    if(!empty($minutes)) return "{$minutes}m";

    return '';
}


function buildGeoString(array $activity): string {
    $geo = $activity['geoCode'] ?? [];
    if (empty($geo['latitude']) || empty($geo['longitude'])) return '';
    return round((float)$geo['latitude'], 5) . ', ' . round((float)$geo['longitude'], 5);
}

function amadeusRatingStars(string $rating): string {
    $val  = (float)$rating;
    $filled = (int) round($val);
    $empty  = 5 - $filled;
    return str_repeat('&#9733;', max(0, $filled)) . str_repeat('&#9734;', max(0, $empty));
}

// --- Display values ---
if (!empty($activity)) {
    $name = ($activity['name']  ?? 'Unnamed Activity');
    $shortDesc = ($activity['shortDescription']);
    $description  = ($activity['description']);
    $rating = $activity['rating'] ?? null;
    $bookingLink = $activity['bookingLink'];
    $cats = $activity['categories'] ;
    $pictures = $activity['pictures']  ;
    $duration = formatDuration($activity['minimumDuration']);

    $priceAmount = !empty($activity['price']['amount'])       ? (float)$activity['price']['amount'] : null;
    $priceCurrency = !empty($activity['price']['currencyCode']) ? $activity['price']['currencyCode'] : 'USD';

    $geoLat = $activity['geoCode']['latitude'] ?? null;
    $geoLng = $activity['geoCode']['longitude']   ?? null;

    $heroPhoto = !empty($pictures) ? htmlspecialchars($pictures[0]) : '';
    $galleryPhotos = array_slice($pictures, 1, 5);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($activity) ? $name : 'Activity Details'; ?> – Game Central</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo"><a href="index.php">Game Central</a></div>
        <ul>
            <li><a href="index.php">Home</a></li>
            <?php if (isset($_SESSION['username'])): ?>
            <li>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></li>
            <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="main-container">

        <?php if (isset($authWarning)): ?>
            <p style="color: orange; font-weight: bold;"><?php echo htmlspecialchars($authWarning); ?></p>
        <?php endif; ?>

        <p><a href="javascript:history.back()">&larr; Back to results</a></p>

        <?php if ($fetchError): ?>
            <h2>Could not load activity</h2>
            <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($fetchError); ?></p>
            <p><a href="javascript:history.back()">&larr; Go Back</a></p>

        <?php elseif (!empty($activity)): ?>

            <?php if ($heroPhoto): ?>
                <img src="<?php echo $heroPhoto; ?>"
                     alt="<?php echo $name; ?>"
                     style="width: 100%; max-width: 600px; height: auto; display: block; margin-bottom: 10px;"
                     onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                <p style="display:none;">[no image]</p>
            <?php else: ?>
                <p>[no image]</p>
            <?php endif; ?>

            <?php if (!empty($galleryPhotos)): ?>
                <div style="margin-bottom: 16px;">
                    <?php foreach ($galleryPhotos as $photo): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>"
                             alt="<?php echo $name; ?>"
                             style="height: 80px; width: auto; margin-right: 4px;"
                             onerror="this.style.display='none';">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h1><?php echo $name; ?></h1>

            <?php if (!empty($cats)): ?>
                <p><strong>Category:</strong> <?php echo htmlspecialchars(implode(', ', $cats)); ?></p>
            <?php endif; ?>

            <ul style="list-style: none; padding: 0; margin-bottom: 16px;">
                <?php if ($priceAmount !== null): ?>
                    <li><strong>Price:</strong> From <?php echo htmlspecialchars($priceCurrency); ?> <?php echo number_format($priceAmount, 2); ?></li>
                <?php endif; ?>

                <?php if ($rating !== null): ?>
                    <li><strong>Rating:</strong> <?php echo amadeusRatingStars((string)$rating); ?> <?php echo htmlspecialchars($rating); ?> / 5</li>
                <?php endif; ?>

                <?php if ($duration): ?>
                    <li><strong>Duration:</strong> <?php echo htmlspecialchars($duration); ?></li>
                <?php endif; ?>
                
                <?php if ($geoLat && $geoLng): ?>
                    <li>
                        <strong>Location:</strong>
                        <?php echo round($geoLat, 5); ?>, <?php echo round($geoLng, 5); ?>
                        &mdash;
                        <a href="https://www.google.com/maps?q=<?php echo $geoLat; ?>,<?php echo $geoLng; ?>"
                           target="_blank" rel="noopener noreferrer">View on Google Maps</a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($shortDesc): ?>
                <p><em><?php echo $shortDesc; ?></em></p>
            <?php endif; ?>

            <?php if ($description): ?>
                <p><?php echo($description); ?></p>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <form method="POST"
                      action="event_detail.php?act_id=<?php echo urlencode($actId); ?>&lat=<?php echo urlencode($lat); ?>&lng=<?php echo urlencode($lng); ?>">
                    <input type="submit"
                           name="save_activity"
                           value="<?php
                               if ($bookingSuccess)    echo 'Saved';
                               elseif ($alreadyBooked) echo 'Already Booked';
                               else                    echo 'Book this activity/destination';
                           ?>"
                           <?php echo ($bookingSuccess || $alreadyBooked) ? 'disabled' : ''; ?>
                           style="padding: 8px 16px;">
                </form>

                <?php if ($bookingLink): ?>
                    <p style="margin-top: 10px;">
                        <a href="<?php echo htmlspecialchars($bookingLink); ?>"
                           target="_blank" rel="noopener noreferrer">Book Externally</a>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($bookingSuccess): ?>
                <p style="color: green; font-weight: bold;"><strong><?php echo $name; ?></strong> saved to your account.</p>

            <?php elseif ($alreadyBooked): ?>
                <p style="color: orange; font-weight: bold;">This activity is already in your saved list.</p>

            <?php elseif ($bookingError): ?>
                <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($bookingError); ?></p>

            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>
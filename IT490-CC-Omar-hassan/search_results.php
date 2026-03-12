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
}
    */

//  Move credentials to .env or config file at least according to kehoe for later
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

    'grant_type' => 'client_credentials',
    'client_id' => AMADEUS_CLIENT_ID,
    'client_secret' => AMADEUS_CLIENT_SECRET,
    ]));

    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $raw = curl_exec($ch); // curl logic
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        throw new Exception("Search request failed try again later");
    }

    $tokenData = json_decode($raw, true);
    if (empty($tokenData['access_token'])) {
        throw new Exception("Amadeus token response missing access_token.");
    }

    $expiresIn = isset($tokenData['expires_in']) ? (int)$tokenData['expires_in'] : 1799;
    $expiresIn = $expiresIn - 60;

    $_SESSION['amadeus_token'] = $tokenData['access_token'];
    $_SESSION['amadeus_token_expiry'] = time() + $expiresIn;

    return $_SESSION['amadeus_token'];
}


$query = trim($_GET['query'] ?? '');

$lat = filter_var($_GET['lat'] ?? '', FILTER_VALIDATE_FLOAT);

$lng = filter_var($_GET['lng'] ?? '', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false) { // defaults back to newark if location getting failed
        $lat =  40.7357;
         $lng = -74.1724;
}

if (empty($query)) { // send user back to main page
    header("Location: index.php");
    exit();
}


$results = [];
$fetchError  ='';
$allActivities  = [];

try {
    $token = getAmadeusToken();

    $params = http_build_query([
        'latitude' => round($lat, 6),
        'longitude' => round($lng, 6),
        'radius' => 20,
    ]);

    $ch = curl_init(AMADEUS_BASE_URL . '/v1/shopping/activities?' . $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // the api sometimes hangs so this is long enoguh for no error messages 
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
        $fetchError = "Error: Search/Amadeus request failed.";
    } 
    elseif ($httpCode !== 200) {
        $fetchError = "Error: Amadeus returned HTTP $httpCode.";
    } 
    else {
        $data = json_decode($raw, true);
        $allActivities = $data['data'] ?? [];

        if (empty($allActivities)) {
            $fetchError = "No activities or destinations found near your location.";
        } else {
            $queryLower  = strtolower($query);

            $filtered = array_filter($allActivities, function ($activity) use ($queryLower) {

                $searchableText  = strtolower(implode(' ', [
                    $activity['name']  ?? '',
                    $activity['shortDescription'] ?? '',
                    implode(' ', $activity['categories'] ?? []),
                ]));

                return str_contains($searchableText, $queryLower);
            });

            $pool = !empty($filtered) ? $filtered : $allActivities;
            $results = array_slice(array_values($pool), 0, 3);

            if (empty($results)) {
                $fetchError = "No activities found for <strong>" . htmlspecialchars($query) . "</strong> near your location.";
            }
        }
    }

} catch (Exception $e) {
    $fetchError = "Error: Travel data service unavailable.";
}

// Helpers 
function amadeusPrice(array $activity): string {
    $price = $activity['price'] ?? null;
    if (!$price || empty($price['amount'])) return ''; // simple error check

      $amount = number_format((float)$price['amount'], 2);

      $currency = $price['currencyCode'] ?? 'USD';
    
    return "$currency $amount";
}

function formatDuration(?string $iso): string {
    if (!$iso) return '';
    
    $hours = 0;
    $minutes = 0;
    
    // takes out hours if present if not move to minutes
     $hourPos = strpos($iso, 'H');

    if ($hourPos !== false) {
      	  $hours = (int) substr($iso, strpos($iso, 'T') + 1, $hourPos - strpos($iso, 'T') - 1);
    }
    
    // if there are no hours then minutes are assumed to exist and are taken out
    $minPos = strpos($iso, 'M');
    if ($minPos !== false) {

      	  $start = $hourPos !== false ? $hourPos + 1 : strpos($iso, 'T') + 1;
        	    $minutes = (int) substr($iso, $start, $minPos - $start);

    }
    
    if ($hours && $minutes) return "{$hours}h {$minutes}m";

    if ($hours) return "{$hours}h";

    if ($minutes) return "{$minutes}m";
    return '';

}

function amadeusRatingStars(string $rating): string {
    $val= (float)$rating;
    $filled = (int) round($val);
    $empty = 5 - $filled;
    return str_repeat('&#9733;', max(0, $filled)) . str_repeat('&#9734;', max(0, $empty));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results for "<?php echo htmlspecialchars($query); ?>" – Game Central</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo"><a href="index.php">Game Central</a></div>
        <ul>
            <li><a href ="index.php">Home</a></li>
            <?php if (isset($_SESSION['username'])): ?>
            <li>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></li>
            <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="main-container">

        <div class="search-container" style="margin-bottom: 20px;">
            <form action="search_results.php" method="GET">
                <input type ="text"
                       name="query"
                       value ="<?php echo htmlspecialchars($query); ?>"
                       placeholder="Search activities and events near you..."
                       required
                       style ="padding: 8px; width: 60%; max-width: 400px;">
                <input type ="hidden" name="lat" value="<?php echo htmlspecialchars($lat); ?>">
                <input type ="hidden" name="lng" value="<?php echo htmlspecialchars($lng); ?>">

                <input type="submit" value="Search" style="padding: 8px 16px;">
            </form>
        </div>

        <?php if (isset($authWarning)): ?>
            <p style = "color: orange; font-weight: bold;"><?php echo htmlspecialchars($authWarning); ?></p>
        <?php endif; ?>

        <p>
            Showing travel activities within 20km of your  location,
            filtered for <strong>"<?php echo htmlspecialchars($query); ?>"</strong>.
            <?php if (!empty($filtered) && count($filtered) < count($allActivities)): ?>
                <?php echo count($filtered); ?> match<?php echo count($filtered) !== 1 ? 'es' : ''; ?> found
                &mdash; showing top <?php echo count($results); ?>.
            <?php elseif (empty($filtered) && !empty($allActivities)): ?>
                No exact keyword match &mdash; showing closest nearby activities instead.
            <?php endif; ?> 
        </p>

        <?php if ($fetchError): ?>
            <p style = "color: red; font-weight: bold;"><?php echo $fetchError; ?></p>

        <?php elseif (!empty($results)): ?>
            <p>Showing <strong><?php echo count($results); ?></strong> result<?php echo count($results) !== 1 ? 's' : ''; ?></p>

            <?php foreach ($results as $activity):
                $actId = $activity['id'];
                $name    = htmlspecialchars($activity['name'] ?? 'Unnamed Activity');

                $shortDesc = htmlspecialchars($activity['shortDescription']);
                $rating = $activity['rating'];

                $duration = formatDuration($activity['minimumDuration']);
                $priceStr  = amadeusPrice($activity);

                $cats = $activity['categories'];
                $pictures = $activity['pictures'];

                $thumb = !empty($pictures) ? htmlspecialchars($pictures[0]) : '';
                $detailUrl = "event_detail.php"
                    . "?act_id=" . urlencode($actId)
                    . "&lat=" . urlencode($lat)
                    . "&lng=" . urlencode($lng);
            ?>
            
            <div style="margin-bottom: 30px; border-bottom: 1px solid #ccc; padding-bottom: 20px;">

                <?php if ($thumb): ?>
                    <img src="<?php echo $thumb; ?>"
                         alt="<?php echo $name; ?>"
                         style="width: 100%; max-width: 400px; height: auto; display: block; margin-bottom: 10px;"
                         onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                    <p style="display:none;">[no image]</p>

                <?php else: ?>
                    <p>[no image]</p>
                <?php endif; ?>

                <h3><a href = "<?php echo $detailUrl; ?>"><?php echo $name; ?></a></h3>

                <?php if (!empty($cats)): ?>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars(implode(', ', $cats)); ?></p>
                <?php endif; ?>

                <?php if ($shortDesc): ?>
                    <p><?php echo $shortDesc; ?></p>
                <?php endif; ?>

                <ul style = "list-style: none; padding: 0; margin: 8px 0;">

                    <?php if ($rating !== null): ?>
                        <li><strong>Rating:</strong> <?php echo amadeusRatingStars((string)$rating); ?> <?php echo htmlspecialchars($rating); ?>/5</li>
                    <?php endif; ?>
                    
                    <?php if ($duration): ?>
                        <li><strong>Duration:</strong> <?php echo htmlspecialchars($duration); ?></li>
                    <?php endif; ?>

                    <?php if ($priceStr): ?>
                        <li><strong>Price:</strong> From <?php echo htmlspecialchars($priceStr); ?></li>
                    <?php endif; ?>
                </ul>

                <a href ="<?php echo $detailUrl; ?>">View Details &amp; Save</a>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</body>
</html>
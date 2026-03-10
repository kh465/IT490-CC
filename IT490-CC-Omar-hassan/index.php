<!--
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
-->

<?php
session_start();

if(!isset($_SESSION["username"])){
    header("Location:login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    
    <script src="https://cdn.maptiler.com/maptiler-sdk-js/v3.10.2/maptiler-sdk.umd.min.js"></script>
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v3.10.2/maptiler-sdk.css" rel="stylesheet" />
    
    <style>
        #map {
            width: 100%;
            height: 500px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        /* Geo status badge shown beneath the search bar */
        #geo-status {
            font-size: 0.8rem;
            margin-top: 6px;
            height: 18px; /* Reserve space so layout doesn't jump */
        }
        .geo-ok      { color: #27ae60; }
        .geo-default { color: #e67e22; }
        .geo-loading { color: #888; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><a href="#">Game Central</a></div>
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

    <div class="main-container">
        <h1>Home</h1>
        
        <div class="search-container" style="margin-bottom: 6px;">
            
            <form id="search-form" action="search_results.php" method="GET">
                <input type="text"
                       name="query"
                       placeholder="Search places, events, restaurants..."
                       required
                       style="padding: 8px; width: 60%; max-width: 400px;">

                <input type="hidden" id="lat-input" name="lat" value="40.7357">
                <input type="hidden" id="lng-input" name="lng" value="-74.1724">

                <input type="submit" value="Search" style="padding: 8px 16px;">
            </form>
        </div>

        
        <div id="geo-status" class="geo-loading">Tracking your favorite destinations…</div>

        <p>See your favourite destinations/events below</p>

        <div id="map"></div>
    </div>

    <script>
        
        // TODO: move API key to .env per Kehoe's advice or professional development
        maptilersdk.config.apiKey = 'SrrbV3S3FnYJS3gcSWTk';

        // Default location is newark,NJ
        const DEFAULT_LNG = -74.1724;
        const DEFAULT_LAT =  40.7357;

        const map = new maptilersdk.Map({
            container: 'map',
            style: maptilersdk.MapStyle.STREETS,
            center: [DEFAULT_LNG, DEFAULT_LAT],
            zoom: 14
        });

        // THis holds a reference to the user marker so we can move it later when showing th user thier optional locations
        const userMarker = new maptilersdk.Marker({ color: "#FF0000" })
            .setLngLat([DEFAULT_LNG, DEFAULT_LAT])
            .addTo(map);

        // geolocation logic 
        const geoStatus  = document.getElementById('geo-status');
        const latInput   = document.getElementById('lat-input');
        const lngInput   = document.getElementById('lng-input');

        function onGeoSuccess(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            // Feed the real coordinates into the hidden form fields
            latInput.value = lat;
            lngInput.value = lng;

            // Move the map and marker to the user's actual position
            map.flyTo({ center: [lng, lat], zoom: 14 });
            userMarker.setLngLat([lng, lat]);

            geoStatus.textContent  = 'Using your current location for searches';
            geoStatus.className    = 'geo-ok';
        }

        function onGeoError(err) {
            // Silently fall back to Newark if any error occurs
            geoStatus.textContent = ' Desistination unavailable defaulting to Newark, NJ';
            geoStatus.className   = 'geo-default';
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(onGeoSuccess, onGeoError, {
                timeout:            8000,
                maximumAge:         60000, 
                enableHighAccuracy: false   
            });
        } else {
            geoStatus.textContent = 'Geolocation not supported';
            geoStatus.className   = 'geo-default';
        }
    </script>
</body>
</html>
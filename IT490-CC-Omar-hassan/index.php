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
        /* map styling for visual represention*/
        #map {
            width: 100%;
            height: 500px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-top: 20px;
            margin-bottom: 20px;
        }
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
        
        <div class="search-container" style="margin-bottom: 20px;">
            <form action="search_results.php" method="GET">
                <input type="text" name="query" placeholder="Search..." required style="padding: 8px; width: 60%; max-width: 400px;">
                <input type="submit" value="Search" style="padding: 8px 16px;">
            </form>
        </div>
        <p>See your favoritie desitinations/events below </p>

        <div id="map"></div>

    </div>

    <script> // javascript logic
        // hard-coded api key might need to move to .env based on kehoe's advice
        maptilersdk.config.apiKey = 'SrrbV3S3FnYJS3gcSWTk';

        // Creat the map
        const map = new maptilersdk.Map({
            container: 'map', // Connects to the map id mentioned above
            style: maptilersdk.MapStyle.STREETS,
            center: [-74.1724, 40.7357], // centered on newark
            zoom: 15
        });

        // little pin feature to show wehrre user is at or cucrent location. 
        new maptilersdk.Marker({color: "#FF0000"})
            .setLngLat([-74.1724, 40.7357]) // newark
            .addTo(map);
    </script>
</body>
</html>
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

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Rabbitmq connection
$connection = new AMQPStreamConnection('100.116.159.74', 5672, 'test', 'test');
$channel = $connection->channel();

$channel->queue_declare('auth_queue', false, true, false, false);

// Connect to the database using pdo
try {
    $pdo = new PDO("mysql:host=localhost;dbname=GC_USERS_DB", "root", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Server: Connected to Database.\n";
} catch (PDOException $e) {

    die("Server: Could not connect to DB. " . $e->getMessage());
}

echo "Server: Waiting for requests...\n";


function verifySession(PDO $pdo, string $username, string $sessionKey): bool {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM session_token s
        JOIN users u ON s.user_id = u.id
        WHERE u.username = ? AND s.session_key = ?
    ");
    $stmt->execute([$username, $sessionKey]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}


$callback = function (AMQPMessage $msg) use ($pdo, $channel) {
    echo "Server: Received Request.\n";

    $data = json_decode($msg->body, true);
    $response = [];

    // Login logic
    if ($data['type'] === 'login') {
        echo "Server: Processing Login for " . $data['username'] . "\n";

        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");// query from the database to find user

        $stmt->execute([$data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            echo "Server: Password Verified, logging the user in.\n";

            $session_key = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO session_token (user_id, session_key) VALUES (?, ?)");
            $stmt->execute([$user['id'], $session_key]);

            $response = [

                'status' => 'true',
                'session_key' => $session_key,

            ];
        } else {
            echo "Server: Verification Failed.\n";
            $response = [

                'status' => 'false',
                'message' => 'Invalid credentials',

            ];
        }

    
    } elseif ($data['type'] === 'register') {// registration logic/case
        echo "Server: Processing Registration for " . $data['username'] . "\n";

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$data['username'], $hash]);

            $response = ['status' => 'success'];
            echo "Server: User Registered.\n";
        } catch (Exception $e) { // if user exists return error message

            $response = ['status' => 'failure', 'message' => 'User already exists'];
            echo "Server: Registration Failed (User exists).\n";

        }

    
    } elseif ($data['type'] === 'validate_session') {
        echo "Server: Validating session for " . $data['username'] . "\n";

        if (verifySession($pdo, $data['username'], $data['session_key'])) {

            echo "Server: Session is valid.\n";
            $response = ['status' => 'success'];

        } else {

            echo "Server: Session is INVALID or spoofed.\n";
            $response = ['status' => 'failure'];
        }

    } elseif ($data['type'] === 'save_booking') {
        echo "Server: Processing booking for " . $data['username'] . "\n";

        // Always re-verify the session before writing anything to the DB and causing issues
        if (!verifySession($pdo, $data['username'], $data['session_key'])) {

            echo "Server: Booking rejected — invalid session.\n";
            $response = ['status' => 'failure', 'message' => 'Invalid session'];

        } else {
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response = ['status' => 'failure', 'message' => 'User not found'];
            } else {
                $userId = $user['id'];

                // Check for a duplicate aka same user and same booking
                $stmt = $pdo->prepare("
                    SELECT id FROM bookings
                    WHERE user_id = ? AND event_title = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId, $data['event_title']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    echo "Server: Booking already made \n";
                    $response = ['status' => 'duplicate', 'message' => 'Event already saved'];
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO bookings
                                (user_id, event_title, event_description,
                                 event_date, event_address, event_url,
                                 event_thumbnail, venue_name, booked_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId,
                            $data['event_title'],
                            $data['event_description'],
                            $data['event_date'],
                            $data['event_address'],
                            $data['event_url'],
                            $data['event_thumbnail'],
                            $data['venue_name'],
                            time(), 
                        ]);
                        echo "Server: Booking saved successfully.\n";
                        $response = ['status' => 'success'];
                    } catch (PDOException $e) {

                        echo "Server: Booking insert failed — " . $e->getMessage() . "\n";
                        $response = ['status' => 'failure', 'message' => 'Database error'];

                    }
                }
            }
        }

    // Get's bookings logic from database still needs some work
    } elseif ($data['type'] === 'get_bookings') {
        echo "Server: Fetching bookings for " . $data['username'] . "\n";
        if (!verifySession($pdo, $data['username'], $data['session_key'])) {
            echo "Server: get_bookings query rejected, invalid session.\n";
            $response = ['status' => 'failure', 'message' => 'Invalid session, Please Log in '];
        } else {
            $stmt = $pdo->prepare("
                SELECT b.id, b.event_title, b.event_description,
                       b.event_date, b.event_address, b.event_url,
                       b.event_thumbnail, b.venue_name,
                       b.booked_at

                FROM bookings b
                JOIN users u ON b.user_id = u.id

                WHERE u.username = ?
                ORDER BY b.booked_at DESC
            ");
            
            $stmt->execute([$data['username']]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "Server: Returning " . count($bookings) . " bookings.\n";
            $response = [
                'status'   => 'success',
                'bookings' => $bookings,
            ];
        }


        } elseif ($data['type'] === 'save_review') {
        echo "Server: Processing review for " . $data['username'] . "\n";

        if (!verifySession($pdo, $data['username'], $data['session_key'])) {
            echo "Server: Review rejected, invalid session.\n";
            $response = ['status' => 'failure', 'message' => 'Invalid session'];
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response = ['status' => 'failure', 'message' => 'User not found'];
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO reviews (user_id, event_title, rating, review_text, created_at)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            rating = VALUES(rating),
                            review_text = VALUES(review_text),
                            created_at = VALUES(created_at)
                    ");
                    $stmt->execute([
                        $user['id'],
                        $data['event_title'],
                        (int)$data['rating'],
                        $data['review_text'] ?? '',
                        time(),
                    ]);
                    echo "Server: Review saved.\n";
                    $response = ['status' => 'success'];
                } catch (PDOException $e) {
                    echo "Server: Review insert failed - " . $e->getMessage() . "\n";
                    $response = ['status' => 'failure', 'message' => 'Database error'];
                }
            }
        }

    } elseif ($data['type'] === 'get_reviews') {
        echo "Server: Fetching reviews for " . $data['username'] . "\n";

        if (!verifySession($pdo, $data['username'], $data['session_key'])) {
            echo "Server: get_reviews rejected, invalid session.\n";
            $response = ['status' => 'failure', 'message' => 'Invalid session'];
        } else {
            $stmt = $pdo->prepare("
                SELECT r.id, r.event_title, r.rating, r.review_text, r.created_at
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE u.username = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$data['username']]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "Server: Returning " . count($reviews) . " reviews.\n";
            $response = [
                'status' => 'success',
                'reviews' => $reviews,
            ];
        }

    
    } else {
        echo "Server: Unknown request type: " . ($data['type'] ?? 'null') . "\n";
        $response = ['status' => 'failure', 'message' => 'Unknown request type'];
    }

    // sends the replay back to teh queue
    $reply = new AMQPMessage(
        json_encode($response),
        ['correlation_id' => $msg->get('correlation_id')]
    );
    $channel->basic_publish($reply, '', $msg->get('reply_to'));
    $msg->ack();
};

$channel->basic_consume('auth_queue', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
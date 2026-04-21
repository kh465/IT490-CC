<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function sendRemoteLog($messageText, $severity = 'info') {

    $connection = new AMQPStreamConnection('100.116.159.74', 5672, 'newadmin', 'password', 'testHost');
    $channel = $connection->channel();

    $channel->exchange_declare('log_exchange', 'fanout', false, false, false);

    $queue = 'logs_' . gethostname();
    $channel->queue_declare($queue, false, false, false, false);

    $channel->queue_bind($queue, 'log_exchange');

    $data = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'severity'  => $severity,
        'message'   => $messageText . PHP_EOL
    ]);

    $msg = new AMQPMessage($data);
    $channel->basic_publish($msg, 'log_exchange', '');

    $channel->close();
    $connection->close();
}
?>

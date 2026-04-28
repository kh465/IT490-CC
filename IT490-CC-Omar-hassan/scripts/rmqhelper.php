#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


function publishDeployEvent(string $action, array $payload): void {
	$host = '100.116.159.74';
	$port = 5672;
	$user = 'newadmin';
	$pass = 'password';
	$vhost = 'testHost';

	$conn = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
	$channel = $conn->channel();

	$channel->queue_declare('testQueue', false, true, false, false);

	$body = json_encode(['action' => $action, 'payload' => $payload]);
	$msg = new AMQPMessage($body, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
	$channel->basic_publish($msg, '', 'testQueue2');

	$channel->close();
	$conn->close();

}

<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AuthRPC {

    private $connection;
    private $channel;
    private $callback_queue;
    private $response;
    private $corr_id;

    public function __construct() {
        $this->connection = new AMQPStreamConnection('RABBITMQ_IP', 5672, 'guest', 'guest');
        $this->channel = $this->connection->channel();

        list($this->callback_queue,,) = $this->channel->queue_declare("", false, false, true, false);

        $this->channel->basic_consume(
            $this->callback_queue,
            '',
            false,
            true,
            false,
            false,
            [$this, 'onResponse']
        );
    }

    public function onResponse($rep) {
        if ($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    public function call($data) {

        $this->response = null;
        $this->corr_id = uniqid();

        $msg = new AMQPMessage(
            json_encode($data),
            [
                'correlation_id' => $this->corr_id,
                'reply_to' => $this->callback_queue
            ]
        );

        $this->channel->basic_publish($msg, '', 'auth_queue');

        while (!$this->response) {
            $this->channel->wait();
        }

        return json_decode($this->response, true);
    }
}
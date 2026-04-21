#!/bin/bash

SERVER="100.116.159.74"
VHOST="testHost"
USER="newadmin"
PASS="password"
EXCHANGE="log_exchange"
QUEUE="logs_$(hostname)"

echo "Starting consumer on $(hostname)..."

#declare exchange and queue separately
amqp-publish \
  --server=$SERVER --vhost=$VHOST \
  --username=$USER --password=$PASS \
  --exchange=$EXCHANGE \
  --routing-key="" \
  --body=""

amqp-declare-queue \
  --server=$SERVER --vhost=$VHOST \
  --username=$USER --password=$PASS \
  --queue=$QUEUE

#message consumer
amqp-consume \
  --server=$SERVER \
  --vhost=$VHOST \
  --username=$USER \
  --password=$PASS \
  --queue=$QUEUE \
  cat >> /var/log/distributed_apps.log

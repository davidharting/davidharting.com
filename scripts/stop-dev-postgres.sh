#!/bin/bash
#
# Stop and remove the development Postgres container
#

CONTAINER_NAME="davidharting-dev-postgres"

if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    docker rm -f "$CONTAINER_NAME" > /dev/null 2>&1
    echo "Stopped and removed $CONTAINER_NAME"
else
    echo "Container $CONTAINER_NAME not running"
fi

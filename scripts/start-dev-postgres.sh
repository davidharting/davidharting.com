#!/bin/bash
#
# Start a Postgres container for local development
# Outputs the assigned port to stdout and waits for the database to be ready
#

set -e

CONTAINER_NAME="davidharting-dev-postgres"
POSTGRES_USER="${DB_USERNAME:-root}"
POSTGRES_PASSWORD="${DB_PASSWORD:-password}"
POSTGRES_DB="${DB_DATABASE:-laravel}"

# Stop and remove existing container if it exists
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    docker rm -f "$CONTAINER_NAME" > /dev/null 2>&1
fi

# Start Postgres container with random port
docker run -d \
    --name "$CONTAINER_NAME" \
    -e POSTGRES_USER="$POSTGRES_USER" \
    -e POSTGRES_PASSWORD="$POSTGRES_PASSWORD" \
    -e POSTGRES_DB="$POSTGRES_DB" \
    -P \
    postgres:17.2 > /dev/null

# Get the assigned port
PORT=$(docker port "$CONTAINER_NAME" 5432 | cut -d: -f2)

echo "$PORT"

# Wait for Postgres to be ready
echo "Waiting for Postgres to be ready on port $PORT..." >&2
for i in {1..30}; do
    if docker exec "$CONTAINER_NAME" pg_isready -U "$POSTGRES_USER" > /dev/null 2>&1; then
        echo "Postgres is ready!" >&2
        exit 0
    fi
    sleep 1
done

echo "Timeout waiting for Postgres to be ready" >&2
exit 1

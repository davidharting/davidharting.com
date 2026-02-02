#!/bin/bash
#
# Start a Postgres container for local development
# Writes the assigned port to .dev-db-port and waits for the database to be ready
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PORT_FILE="$PROJECT_ROOT/.dev-db-port"

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

# Get the assigned port and write to file
PORT=$(docker port "$CONTAINER_NAME" 5432 | cut -d: -f2)
echo "$PORT" > "$PORT_FILE"
echo "Postgres starting on port $PORT (saved to .dev-db-port)"

# Wait for Postgres to be ready
echo "Waiting for Postgres to be ready..."
for i in {1..30}; do
    if docker exec "$CONTAINER_NAME" pg_isready -U "$POSTGRES_USER" > /dev/null 2>&1; then
        echo "Postgres is ready!"
        exit 0
    fi
    sleep 1
done

echo "Timeout waiting for Postgres to be ready"
exit 1

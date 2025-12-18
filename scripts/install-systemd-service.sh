#!/bin/bash
set -e

echo "Installing davidharting-com systemd service..."

# Auto-detect current user and repository directory
INSTALL_USER=$(whoami)
INSTALL_HOME=$(eval echo ~$INSTALL_USER)
REPO_DIR="$INSTALL_HOME/repos/davidharting.com"

# Verify we're in the right directory
if [ ! -f "$REPO_DIR/docker-compose.yml" ]; then
    echo "Error: Could not find docker-compose.yml at $REPO_DIR"
    echo "Please ensure the repository is cloned to $REPO_DIR"
    exit 1
fi

# Generate service file from template
TEMPLATE_FILE="$REPO_DIR/systemd/davidharting-com.service.template"
if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "Error: Could not find template file at $TEMPLATE_FILE"
    exit 1
fi

echo "Generating service file for user: $INSTALL_USER"
echo "Working directory: $REPO_DIR"

sed -e "s|USER_PLACEHOLDER|$INSTALL_USER|g" \
    -e "s|WORKING_DIR_PLACEHOLDER|$REPO_DIR|g" \
    "$TEMPLATE_FILE" > /tmp/davidharting-com.service

# Install the service
echo "Installing service to /etc/systemd/system/..."
sudo cp /tmp/davidharting-com.service /etc/systemd/system/davidharting-com.service

# Reload systemd
echo "Reloading systemd daemon..."
sudo systemctl daemon-reload

# Enable service for auto-start
echo "Enabling service for auto-start on boot..."
sudo systemctl enable davidharting-com

echo ""
echo "✓ Service installed successfully!"
echo ""
echo "You can now manage the service with:"
echo "  sudo systemctl start davidharting-com      # Start the application"
echo "  sudo systemctl stop davidharting-com       # Stop the application"
echo "  sudo systemctl restart davidharting-com    # Restart the application"
echo "  sudo systemctl status davidharting-com     # Check service status"
echo ""
echo "The service will automatically start on system boot."

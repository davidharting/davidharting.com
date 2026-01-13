# Production Deployment

This document describes how to set up and deploy davidharting.com in production.

## Architecture

- **CI/CD**: GitHub Actions builds Docker images on every push to `main` and pushes to `ghcr.io/davidharting/davidharting.com:latest`
- **Hosting**: Digital Ocean droplet running Ubuntu with Docker
- **Auto-start**: systemd service manages application lifecycle and auto-starts on boot
- **Deployment**: Manual deployment via `task prod:deploy` command

## Initial Setup

### Prerequisites

- Digital Ocean droplet with Docker and Docker Compose installed
- Repository cloned to `/home/$USER/repos/davidharting.com`
- All secrets files in `/home/$USER/repos/davidharting.com/secrets/`
- SSH access configured with environment variables:
  - `SSH_HOST`: Server hostname/IP
  - `SSH_USER`: SSH username
  - `SSH_PORT`: SSH port

### Install systemd Service

The systemd service ensures the application automatically starts when the server boots.

1. SSH into the production server:
   ```bash
   task prod:ssh:shell
   ```

2. Run the installation script:
   ```bash
   cd /home/$USER/repos/davidharting.com
   ./scripts/install-systemd-service.sh
   ```

   This script will:
   - Generate the systemd service file with your username and paths
   - Install it to `/etc/systemd/system/davidharting-com.service`
   - Enable auto-start on boot
   - Start the service (or restart if already running)
   - Display service status

3. Verify containers are running (optional - script shows status):
   ```bash
   docker compose ps
   ```

## Deployment Workflow

### How Deployments Work

1. **Build**: Push code to `main` branch → GitHub Actions builds and pushes Docker image
2. **Deploy**: Run `task prod:deploy` from local machine
3. **What happens**:
   - SSH into production server
   - Pull latest Docker images with `docker compose pull`
   - Restart the systemd service with `sudo systemctl restart davidharting-com`
   - Service stops all containers, then starts them with new images
   - Migrations run automatically before web/worker/cron start (via `depends_on`)

### Deploy Latest Version

From your local machine:

```bash
task prod:deploy
```

This command:
1. Pulls the latest images from GitHub Container Registry
2. Restarts the systemd service (which stops and starts all containers)
3. Brief downtime during restart (~5-30 seconds)

### Monitor Deployment

Check service status:
```bash
task prod:ssh:exec -- sudo systemctl status davidharting-com
```

Check container status:
```bash
task prod:ssh:exec -- docker compose ps
```

View logs:
```bash
# All services
task prod:ssh:exec -- docker compose logs --tail=100 -f

# Specific service
task prod:ssh:exec -- docker compose logs web --tail=100 -f
```

## Service Management

The systemd service automatically manages all Docker Compose containers.

### Common Commands

Run these on the production server:

```bash
# Start application
sudo systemctl start davidharting-com

# Stop application
sudo systemctl stop davidharting-com

# Restart application (used during deployments)
sudo systemctl restart davidharting-com

# Check service status
sudo systemctl status davidharting-com

# View service logs
sudo journalctl -u davidharting-com -f

# Check if auto-start is enabled
sudo systemctl is-enabled davidharting-com
```

### What the Service Does

- **On start**: Runs `docker compose up -d` to start all containers
- **On stop**: Runs `docker compose down` to gracefully stop and remove containers
- **On boot**: Automatically starts after Docker is ready
- **On crash**: Does NOT auto-restart (use `docker-compose.yml` restart policies for container-level restarts)

## Container Restart Policies

Each container in `docker-compose.yml` has `restart: unless-stopped`, which means:

- ✅ Container restarts if it crashes
- ✅ Container restarts if Docker daemon restarts
- ❌ Container does NOT start after being manually stopped
- ✅ systemd service handles starting containers on boot

## Troubleshooting

### Service won't start

```bash
# Check service status
sudo systemctl status davidharting-com

# View detailed logs
sudo journalctl -u davidharting-com -n 50

# Check Docker daemon
sudo systemctl status docker

# Check if containers are running
docker compose ps
```

### Application not responding after deployment

```bash
# Check container health
docker compose ps

# View application logs
docker compose logs web --tail=100

# Check migrations completed
docker compose logs migrations

# Restart service
sudo systemctl restart davidharting-com
```

### Containers not stopping cleanly

```bash
# Manual cleanup
docker compose down

# Force remove if stuck
docker compose down --remove-orphans

# Restart service
sudo systemctl restart davidharting-com
```

## Rollback

To rollback to a previous version:

1. Find the commit SHA of the working version in GitHub
2. On production server, update docker-compose.yml to use that SHA:
   ```yaml
   image: ghcr.io/davidharting/davidharting.com:<commit-sha>
   ```
3. Restart the service:
   ```bash
   sudo systemctl restart davidharting-com
   ```

## Updating the systemd Service

If you need to modify the systemd service (e.g., change paths, add environment variables):

1. Update `systemd/davidharting-com.service.template` in the repository
2. Push changes to `main`
3. Pull changes on production server
4. Re-run the install script:
   ```bash
   cd /home/$USER/repos/davidharting.com
   git pull
   ./scripts/install-systemd-service.sh
   ```

The script will overwrite the existing service file and reload systemd.

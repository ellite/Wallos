# Wallos justfile - Main development commands
# Usage: just <command>
# Image configuration (DRY)

image := "wallos"
tag := "latest"
image_tag := image + ":" + tag

# Default command to show available commands
_list:
    @just --list

# Build the Docker image
build:
    @echo "Building Wallos Docker image..."
    docker build -t {{ image_tag }} .

# Start the Docker services
start:
    @echo "Starting Wallos Docker services..."
    docker compose up -d

# Start development mode with bind mounts (no rebuild needed)
dev:
    @echo "Starting Wallos in development mode with bind mounts..."
    docker compose -f docker-compose.yaml -f docker-compose.dev.yaml up -d

# Stop the Docker services
stop:
    @echo "Stopping Wallos Docker services..."
    docker compose down

# Stop development mode
dev-stop:
    @echo "Stopping Wallos development mode..."
    docker compose -f docker-compose.yaml -f docker-compose.dev.yaml down

# Restart the Docker services
restart:
    @echo "Restarting Wallos Docker services..."
    docker compose restart

# View running containers and logs
logs:
    @echo "Showing Wallos container logs..."
    docker compose logs -f

# View container status
status:
    @echo "Container status:"
    docker compose ps

# Open Wallos in the default browser
open:
    @echo "Opening Wallos in browser..."
    open http://localhost:8282

# Run Superlinter on the codebase
superlint:
    @echo "Running Superlinter on Wallos codebase..."
    docker run --rm \
        -e RUN_LOCAL=true \
        -v $(pwd):/tmp/lint \
        -w /tmp/lint \
        github/super-linter:latest

# Clean up Docker resources
clean:
    @echo "Removing local image {{ image_tag }}..."
    docker image rm -f {{ image_tag }} || true

# Rebuild: build, stop, and start
rebuild: build stop start
    @echo "Rebuild completed!"

# Full reset: stop, clean, rebuild, and start
reset: stop clean build start
    @echo "Full reset completed!"

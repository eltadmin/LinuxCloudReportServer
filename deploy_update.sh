#!/bin/bash
# Script to deploy updates to the server

echo "Deploying updates to the Linux Cloud Report Server..."

# Make sure we're in the correct directory
cd $(dirname "$0")

# Add changes to git
echo "Adding changes to git..."
git add server/tcp_server.py change_log.md

# Commit changes
echo "Committing changes..."
git commit -m "Fixed missing constants in tcp_server.py"

# Push changes to remote
echo "Pushing changes to remote..."
git push

echo "Changes pushed to repository."
echo "To update the server, run the following commands on the server:"
echo "  git pull"
echo "  docker-compose down"
echo "  docker-compose up -d"
echo "  docker-compose logs -f"
echo ""
echo "Done." 
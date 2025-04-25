#!/bin/bash
# Docker entrypoint script for Cloud Report Server

echo "===================================="
echo "Cloud Report Server Startup"
echo "===================================="

# Check permissions on directories
echo "Checking directory permissions..."
mkdir -p /app/logs
mkdir -p /app/updates
chmod 777 /app/logs /app/updates

# Check configuration
if [ ! -f "/app/config/server.ini" ]; then
    echo "Error: Configuration file not found: /app/config/server.ini"
    echo "Please make sure you have mounted the config directory correctly."
    exit 1
fi

# Check Python modules
echo "Checking Python modules..."
python -c "
try:
    import Crypto.Cipher.AES
    print('Crypto module imported successfully')
except ImportError:
    try:
        import Cryptodome.Cipher.AES
        import sys
        import Cryptodome as Crypto
        sys.modules['Crypto'] = Crypto
        print('Cryptodome module imported successfully and aliased as Crypto')
    except ImportError as e:
        print(f'Failed to import crypto modules: {e}')
        exit(1)
"

if [ $? -ne 0 ]; then
    echo "Error: Failed to import required Python modules"
    exit 1
fi

echo "Starting Cloud Report Server..."
exec python /app/server.py 
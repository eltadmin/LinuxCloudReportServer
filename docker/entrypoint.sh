#!/bin/bash
# Docker entrypoint script for Linux Cloud Report Server
# Checks and updates registration key if needed

set -e

CONFIG_DIR="/app/config"
CONFIG_FILE="${CONFIG_DIR}/server.ini"
REGISTRATION_FILE="${CONFIG_DIR}/registration.ini"

# Create necessary directories
mkdir -p /app/logs
mkdir -p /app/updates

echo "Checking server registration..."

# Function to update server.ini with registration information
update_registration() {
    local serial=$1
    local key=$2
    
    echo "Updating registration information in server.ini"
    
    # Check if server.ini exists
    if [ ! -f "$CONFIG_FILE" ]; then
        echo "ERROR: Configuration file $CONFIG_FILE not found!"
        exit 1
    fi
    
    # Create a temporary file
    local temp_file=$(mktemp)
    
    # Process the file
    local in_reg_section=0
    local reg_section_updated=0
    
    while IFS= read -r line; do
        # Check if we're entering the registration section
        if [[ "$line" == "[REGISTRATION INFO]" ]]; then
            in_reg_section=1
            echo "$line" >> "$temp_file"
            echo "SERIAL NUMBER=$serial" >> "$temp_file"
            echo "KEY=$key" >> "$temp_file"
            reg_section_updated=1
            # Skip the next two lines (old serial and key)
            read -r line
            read -r line
            continue
        fi
        
        # If we're in the registration section and we encounter a new section or EOF,
        # and we haven't updated the registration yet, add it
        if [[ $in_reg_section -eq 1 && ($line == \[* || -z "$line") && $reg_section_updated -eq 0 ]]; then
            echo "SERIAL NUMBER=$serial" >> "$temp_file"
            echo "KEY=$key" >> "$temp_file"
            echo "" >> "$temp_file"
            reg_section_updated=1
        fi
        
        # Check if we're leaving the registration section
        if [[ $in_reg_section -eq 1 && $line == \[* ]]; then
            in_reg_section=0
        fi
        
        # Write the current line to the temp file
        echo "$line" >> "$temp_file"
    done < "$CONFIG_FILE"
    
    # If we reached the end of the file and are still in the registration section
    # and haven't updated the registration, add it now
    if [[ $in_reg_section -eq 1 && $reg_section_updated -eq 0 ]]; then
        echo "SERIAL NUMBER=$serial" >> "$temp_file"
        echo "KEY=$key" >> "$temp_file"
    fi
    
    # If we never found or updated the registration section, add it to the end
    if [[ $reg_section_updated -eq 0 && $in_reg_section -eq 0 ]]; then
        echo "" >> "$temp_file"
        echo "[REGISTRATION INFO]" >> "$temp_file"
        echo "SERIAL NUMBER=$serial" >> "$temp_file"
        echo "KEY=$key" >> "$temp_file"
    fi
    
    # Replace the original file with the temporary file
    mv "$temp_file" "$CONFIG_FILE"
    
    echo "Registration info updated in $CONFIG_FILE"
}

# Check for environment variable override for serial number
if [ -n "$SERVER_SERIAL" ]; then
    echo "Using server serial from environment: $SERVER_SERIAL"
    SERIAL="$SERVER_SERIAL"
else
    # Try to get hardware serial number
    echo "Detecting hardware serial number..."
    
    # Try to get disk device
    DISK_DEVICE=$(lsblk -no NAME,TYPE | grep disk | head -n 1 | awk '{print $1}')
    
    if [ -n "$DISK_DEVICE" ]; then
        # Try to get disk serial
        DISK_INFO=$(udevadm info --query=property "/dev/$DISK_DEVICE" 2>/dev/null || echo "")
        SERIAL=$(echo "$DISK_INFO" | grep ID_SERIAL= | cut -d= -f2)
    fi
    
    # Fallback to machine-id
    if [ -z "$SERIAL" ] && [ -f "/etc/machine-id" ]; then
        SERIAL=$(cat /etc/machine-id)
    fi
    
    # Final fallback to hostname + random
    if [ -z "$SERIAL" ]; then
        HOSTNAME=$(hostname)
        RANDOM_PART=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 8 | head -n 1)
        SERIAL="${HOSTNAME}-${RANDOM_PART}"
    fi
    
    echo "Detected serial: $SERIAL"
fi

# Check if we should generate a new key
GENERATE_KEY=0

# Check if registration key is provided via environment
if [ -n "$SERVER_KEY" ]; then
    echo "Using registration key from environment"
    KEY="$SERVER_KEY"
elif [ -f "$REGISTRATION_FILE" ]; then
    # Read from registration.ini if it exists
    echo "Reading registration from $REGISTRATION_FILE"
    TEMP_SERIAL=$(grep "SERIAL NUMBER=" "$REGISTRATION_FILE" | cut -d= -f2)
    TEMP_KEY=$(grep "KEY=" "$REGISTRATION_FILE" | cut -d= -f2)
    
    if [ -n "$TEMP_SERIAL" ] && [ -n "$TEMP_KEY" ]; then
        SERIAL="$TEMP_SERIAL"
        KEY="$TEMP_KEY"
    else
        GENERATE_KEY=1
    fi
else
    # Check existing server.ini
    if [ -f "$CONFIG_FILE" ]; then
        echo "Checking existing configuration in $CONFIG_FILE"
        TEMP_SERIAL=$(grep "SERIAL NUMBER=" "$CONFIG_FILE" | cut -d= -f2)
        TEMP_KEY=$(grep "KEY=" "$CONFIG_FILE" | cut -d= -f2)
        
        if [ -n "$TEMP_SERIAL" ] && [ -n "$TEMP_KEY" ] && [ "$TEMP_SERIAL" == "$SERIAL" ]; then
            echo "Using existing key for serial $SERIAL"
            KEY="$TEMP_KEY"
        else
            GENERATE_KEY=1
        fi
    else
        GENERATE_KEY=1
    fi
fi

# Generate key if needed
if [ "$GENERATE_KEY" -eq 1 ]; then
    echo "Generating new registration key for serial $SERIAL"
    python /app/src/key_generator.py --serial "$SERIAL" --output "$REGISTRATION_FILE"
    
    # Read the generated key
    if [ -f "$REGISTRATION_FILE" ]; then
        KEY=$(grep "KEY=" "$REGISTRATION_FILE" | cut -d= -f2)
        echo "Key generated: $KEY"
    else
        echo "ERROR: Failed to generate key!"
        exit 1
    fi
fi

# Update server.ini with the registration information
update_registration "$SERIAL" "$KEY"

# Run the server
echo "Starting Cloud Report Server..."
exec python /app/src/server.py 
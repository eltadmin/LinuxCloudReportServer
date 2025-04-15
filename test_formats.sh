#!/bin/bash
# Script to test different INIT response formats

echo "Testing different INIT response formats..."

# Save the current directory
CURRENT_DIR=$(pwd)
cd "$CURRENT_DIR"

# Format descriptions
FORMAT_DESCRIPTIONS=(
  "1: Standard Delphi format with CRLF - LEN=8\r\nKEY=ABCDEFGH"
  "2: TIdReply direct format - 200 LEN=8 KEY=ABCDEFGH\r\n"
  "3: With numeric code and text - 200 OK\r\nLEN=8\r\nKEY=ABCDEFGH\r\n"
  "4: Just key-value pairs with CRLF ending - LEN=8\r\nKEY=ABCDEFGH\r\n"
  "5: Just key-value pairs with single LF - LEN=8\nKEY=ABCDEFGH\n"
  "6: Just the key - ABCDEFGH"
  "7: Delphi TStringList.SaveToStream binary format"
  "8: Binary format with length-prefixed strings"
)

# Define test function
test_format() {
  format_num=$1
  echo ""
  echo "Testing format ${format_num}: ${FORMAT_DESCRIPTIONS[$((format_num-1))]}"
  echo "--------------------------------"
  
  # Set the environment variable
  export INIT_RESPONSE_FORMAT=$format_num
  
  # Stop and start the server
  echo "Stopping any running server..."
  docker-compose down 2>/dev/null
  
  echo "Starting server with format $format_num..."
  docker-compose up -d report_server
  
  # Wait for the server to start
  echo "Waiting for server to start..."
  sleep 5
  
  # Show logs to view the format
  echo "Server logs:"
  docker-compose logs report_server | tail -n 20
  
  echo "--------------------------------"
}

# Test each format
for i in {1..8}; do
  test_format $i
done

# Restore default format
echo ""
echo "Testing complete. Setting format back to default (1)..."
export INIT_RESPONSE_FORMAT=1
docker-compose down 2>/dev/null
docker-compose up -d report_server

echo "Done!" 
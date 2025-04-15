#!/bin/bash
# Script to test the INIT response format compatibility with Delphi clients

echo "====== Testing INIT response format ======"
echo "1. Setting INIT_RESPONSE_FORMAT to 14 (Delphi compatible)"
export INIT_RESPONSE_FORMAT=14

echo "2. Restarting the server"
docker-compose down
docker-compose up -d

echo "3. Waiting for server to start (10 seconds)"
sleep 10

echo "4. Running validation test"
python3 test_format_validation.py localhost 8016

status=$?
if [ $status -eq 0 ]; then
    echo
    echo "===== SUCCESS: INIT format validation passed! ====="
    echo "The server is correctly configured to communicate with Delphi clients."
else
    echo
    echo "===== ERROR: INIT format validation failed! ====="
    echo "The server is not correctly responding in a format Delphi clients can understand."
    echo "Please check the server logs for details."
fi

exit $status 
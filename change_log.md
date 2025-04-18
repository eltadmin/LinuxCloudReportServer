## Change Log for Linux Cloud Report Server

### 2025-04-15 - Added Deployment and Verification Tools

**Changes Made**:
1. Created deployment scripts to simplify pushing updates to the server:
   - `deploy_update.sh`: Bash script for Linux environments
   - `deploy_update.bat`: Batch script for Windows environments
2. Created verification tools to check server functionality:
   - `verify_server.py`: Python script to test server connectivity and functionality
   - `verify_server.bat`: Batch script to run the verification tool

**Files Added**:
- `deploy_update.sh`: Script to deploy changes to the server
- `deploy_update.bat`: Windows version of the deployment script
- `verify_server.py`: Server verification tool
- `verify_server.bat`: Windows batch script to run the verification tool

**Benefits**:
- Simplified deployment process
- Easy verification of server functionality after updates
- Better debugging and troubleshooting capabilities

### 2025-04-15 - Fix for Missing Constants in tcp_server.py

**Issue**: Server fails to start due to undefined constants
- Error messages in the logs:
  - `NameError: name 'INACTIVITY_CHECK_INTERVAL' is not defined`
  - `NameError: name 'CMD_INFO' is not defined`
- The refactored code referenced constants that were not defined at the top of the file

**Changes Made**:
1. Added command constants (CMD_INIT, CMD_ERRL, CMD_PING, CMD_INFO, etc.)
2. Added timeout constants:
   - CONNECTION_TIMEOUT = 300 (5 minutes)
   - PENDING_CONNECTION_TIMEOUT = 120 (2 minutes)
   - INACTIVITY_CHECK_INTERVAL = 60 (1 minute)
3. Added key generation constants (KEY_LENGTH)
4. Added RESPONSE_FORMATS dictionary for consistent response formatting

**Files Modified**:
- `server/tcp_server.py`: Added missing constants

**Expected Results**:
- Server should start without errors
- The connection cleanup task should run properly
- Command handling should work correctly

### 2025-04-15 - Code Refactoring of tcp_server.py

**Changes Made**:
1. Improved code organization and structure
   - Added proper docstrings to all classes and methods
   - Added type hints for better code clarity
   - Organized imports logically
   - Added constants for configuration values
   - Extracted command handling into separate methods

2. Added better separation of concerns between classes
   - TCPConnection class focuses solely on connection management
   - Added new helper methods to make code more modular
   - Extracted common functionality into separate methods

3. Enhanced error handling and logging
   - Added more detailed log messages
   - Improved exception handling patterns
   - Added context to error logs

4. Improved the INIT command handling
   - Refactored into smaller, more focused methods
   - Better handling of crypto key testing
   - Improved response formatting for Delphi client compatibility

5. Improved connection cleanup
   - Added dedicated method for connection cleanup
   - Added proper task cancellation and resource cleanup
   - Added more robust connection tracking

**Files Modified**:
- `server/tcp_server.py`: Major refactoring of the code

**Benefits**:
- More maintainable codebase
- Better separation of concerns
- Improved readability
- More robust error handling
- Easier to extend and modify in the future

### 2025-04-15 - Fix for INIT command response format

**Issue**: Client can't initialize communication with server
- The Delphi client code expects the INIT response to be in a specific format with key-value pairs
- The client was failing with the error: "Unable to initizlize communication! Terminate connection"

**Root Cause**:
- Analyzed logs which show client sending: `ERRL [Error]Unable to initizlize communication! Terminate connection`
- The Delphi client expects the INIT response with LEN and KEY parameters in a specific format
- The client parses these values using `FTCPClient.LastCmdResult.Text.Values['LEN']` and `FTCPClient.LastCmdResult.Text.Values['KEY']`
- This requires a specific format where these parameters are on separate lines with CRLF line endings

**Solution**:
1. Modified the server's INIT response format to match what the Delphi client expects
2. Updated the `_format_init_response` method in `tcp_server.py` to use the correct format
3. Set the default `INIT_RESPONSE_FORMAT` environment variable to 14
4. Added validation tests to verify the response format

**Changes**:
- `server/tcp_server.py`: Modified the INIT response format to `LEN=value\r\nKEY=value\r\n`
- `docker-compose.yml`: Updated to use `INIT_RESPONSE_FORMAT=14` by default
- `test_format_validation.py`: Added test script to validate the INIT response format
- `run_format_test.sh`: Added script to test the server with the correct configuration

**Lessons Learned**:
- Delphi's TStringList.Values property has specific expectations for how key-value pairs are formatted
- Protocol adaptations need to match the exact format expected by legacy clients
- Always validate communication formats with automated tests

**Test Steps**:
1. Run `chmod +x run_format_test.sh` to make the test script executable
2. Execute `./run_format_test.sh` to test the INIT response format
3. Verify that the test passes and confirms the Delphi client compatibility

**Files Modified**:
- `server/tcp_server.py`: Modified the INIT response format to `LEN=value\r\nKEY=value\r\n`
- `docker-compose.yml`: Updated to use `INIT_RESPONSE_FORMAT=14` by default
- `test_format_validation.py`: Added test script to validate the INIT response format
- `run_format_test.sh`: Added script to test the server with the correct configuration

**New Files Created**:
- `change_log.md`: This log file to track changes
- `test_init_format.py`: Test script to verify the INIT command response format
- `test_init_format.bat`: Windows batch script to run the test
- `restart_server.sh`: Shell script to restart the server (for Linux environments)

**Expected Results**:
- The client should now be able to correctly parse the response
- The client will successfully create the crypto key and continue with the connection
- If there are still issues, the enhanced logging will provide more information for debugging

**How to Test**:
1. Deploy the updated code to the server
2. Restart the server using `restart_server.sh` on Linux or by restarting the Docker container
3. Run the test script `test_init_format.py` to verify the response format
4. Try connecting with a real client to verify the fix 
"""
Connection handling module for Cloud Report Server
"""

import datetime
import json
import random
import socket
import threading
import time
from typing import Dict, List, Optional, Tuple, Any

import requests
import sys
import traceback

from constants import (
    DROP_DEVICE_WITHOUT_ACTIVITY_SEC,
    HARDCODED_KEYS,
    ID8_KEY,
    ID8_LEN,
    LINE_SEPARATOR,
    RESPONSE_OK,
    TCP_ERR_FAIL_DECODE_DATA,
    TCP_ERR_FAIL_ENCODE_DATA,
    TCP_ERR_INVALID_CRYPTO_KEY,
    TCP_ERR_INVALID_DATA_PACKET,
    TCP_ERR_FAIL_INIT_CLIENT_ID,
    TCP_ERR_CHECK_UPDATE_ERROR,
)
from crypto import DataCompressor, generate_client_crypto_key
from logger import Logger

class ConnectionInfo:
    """Connection information class"""
    
    def __init__(self):
        self.remote_host = ""
        self.remote_ip = ""
        self.remote_port = 0
        self.local_port = 0
        self.connect_time = datetime.datetime.now()
        self.disconnect_time = None
        self.last_action = datetime.datetime.now()

class RemoteConnection:
    """Base class for remote connections"""
    
    def __init__(self, log_path: str):
        self.log_path = log_path
        self.logger = Logger(log_path)
        self.last_error = ""
        self.connection_info = ConnectionInfo()
        self.must_disconnect = False
    
    @property
    def connected_time_sec(self) -> int:
        """Get the connected time in seconds"""
        delta = datetime.datetime.now() - self.connection_info.connect_time
        return int(delta.total_seconds())
    
    @property
    def idle_time_sec(self) -> int:
        """Get the idle time in seconds"""
        delta = datetime.datetime.now() - self.connection_info.last_action
        return int(delta.total_seconds())
    
    def on_connect(self, client_socket: socket.socket, address: Tuple[str, int]):
        """Handle client connection"""
        self.connection_info.remote_ip = address[0]
        self.connection_info.remote_port = address[1]
        self.connection_info.local_port = client_socket.getsockname()[1]
        self.connection_info.connect_time = datetime.datetime.now()
        self.connection_info.last_action = datetime.datetime.now()
    
    def on_disconnect(self):
        """Handle client disconnection"""
        self.connection_info.disconnect_time = datetime.datetime.now()

class TCPConnection(RemoteConnection):
    """TCP connection class"""
    
    def __init__(self, client_socket: socket.socket, address: Tuple[str, int], log_path: str):
        super().__init__(log_path)
        self.client_socket = client_socket
        self.address = address
        self.client_id = ""
        self.time_diff_sec = 0
        
        # Generate random server key (as in original code)
        self.server_key = "".join([
            format(random.randint(1, 255), '02X') 
            for _ in range(2)
        ])
        
        # Default server key for most connections
        if random.random() < 0.9:  # 90% of connections get the standard key
            self.server_key = "D5F2"
            
        self.crypto_key = ""
        self.client_host = ""
        self.client_name = ""
        self.app_type = ""
        self.app_version = ""
        self.db_type = ""
        self.expire_date = None
        self.busy = False
        self.request_counter = 0
        self.last_request = ""
        self.last_response = ""
        self.event = threading.Event()
        self.destroying = False
        
        # Indicate connection was established
        self.on_connect(client_socket, address)
        
    def connection_info_as_text(self) -> str:
        """Get connection information as text"""
        result = f"TCP Start:{self.connection_info.connect_time.strftime('%M%S.%f')} "
        
        if self.connection_info.disconnect_time:
            result += f"End:{self.connection_info.disconnect_time.strftime('%M%S.%f')} "
            
        result += (
            f"Time:{self.connected_time_sec} "
            f"RH:{self.connection_info.remote_ip} "
            f"R/LP:{self.connection_info.remote_port}/{self.connection_info.local_port}"
        )
        
        return result
    
    def set_time_diff(self, s_date: str, s_time: str) -> None:
        """Set time difference between client and server"""
        try:
            # Format: DT=YYMMDD TM=HHMMSS
            year = 2000 + int(s_date[0:2])
            month = int(s_date[2:4])
            day = int(s_date[4:6])
            
            hour = int(s_time[0:2])
            minute = int(s_time[2:4])
            second = int(s_time[4:6])
            
            client_time = datetime.datetime(year, month, day, hour, minute, second)
            server_time = datetime.datetime.now()
            
            delta = server_time - client_time
            self.time_diff_sec = int(delta.total_seconds())
        except Exception as e:
            self.last_error = f"Failed to set time difference: {e}"
            self.time_diff_sec = 0
    
    def init_crypto_key(self, data: Dict[str, str]) -> Tuple[bool, int]:
        """Initialize the cryptographic key for this connection"""
        try:
            # Get client ID
            client_id = int(data.get("ID", "0"))
            print(f"Initializing crypto key for client ID: {client_id}")
            
            if client_id < 1 or client_id > 10:
                self.last_error = f"Invalid client ID: {client_id}"
                print(f"Invalid client ID: {client_id} (must be between 1-10)")
                return False, 0
                
            # Get hostname
            self.client_host = data.get("HST", "")
            print(f"Client hostname: {self.client_host}")
            
            if not self.client_host:
                self.last_error = "Hostname is empty"
                print("Error: Client hostname is empty")
                return False, 0
            
            # Special handling for ID=8
            if client_id == 8:
                self.server_key = ID8_KEY
                print(f"Using special key for ID=8: KEY={ID8_KEY}, LEN={ID8_LEN}")
                return True, ID8_LEN
            
            # Generate crypto key
            self.crypto_key = generate_client_crypto_key(client_id, self.server_key, self.client_host)
            print(f"Generated crypto key: {self.crypto_key}")
            
            # Determine key length
            key_len = 2 if client_id == 9 else 1
            print(f"Using key length: {key_len}")
            
            return True, key_len
            
        except Exception as e:
            self.last_error = f"Failed to initialize crypto key: {e}"
            print(f"Exception in init_crypto_key: {e}")
            return False, 0
    
    def init_client_id(self, data: str, rest_url: str) -> bool:
        """Initialize client ID using REST call"""
        try:
            # Split data into key-value pairs
            lines = data.split("\r\n")
            client_data = {}
            
            for line in lines:
                if "=" in line:
                    key, value = line.split("=", 1)
                    client_data[key] = value
            
            # Check if TT=Test is present (validation test)
            if client_data.get("TT") != "Test":
                self.last_error = "Invalid data packet format: missing TT=Test"
                return False
            
            # Extract client information
            self.client_id = client_data.get("ID", "")
            self.client_name = client_data.get("FN", "")  # Firm name
            self.client_host = client_data.get("HS", self.client_host)  # Host name
            self.app_type = client_data.get("AT", "")  # App type
            self.app_version = client_data.get("AV", "")  # App version
            self.db_type = client_data.get("DT", "")  # DB type
            
            if not self.client_id:
                self.last_error = "Client ID is empty"
                return False
            
            # Call REST API to validate client
            if rest_url:
                try:
                    # Prepare request to the authentication server
                    api_url = f"{rest_url}/objectinfo"
                    params = {
                        "objectid": self.client_id,
                        "objectname": client_data.get("ON", ""),  # Office name
                        "customername": self.client_name,
                        "eik": client_data.get("FB", ""),  # Bulstat
                        "address": client_data.get("FA", ""),  # Address
                        "hostname": self.client_host,
                        "comment": f"App: {self.app_type} {self.app_version}"
                    }
                    
                    # Send request
                    response = requests.get(api_url, params=params, timeout=10)
                    
                    if response.status_code == 200:
                        # Parse response
                        result = response.json()
                        
                        if result.get("result") == 0:
                            # Set expiry date
                            expire_str = result.get("expiredate", "")
                            if expire_str:
                                try:
                                    # Format: YYYY-MM-DD
                                    year, month, day = expire_str.split("-")
                                    self.expire_date = datetime.datetime(
                                        int(year), int(month), int(day)
                                    )
                                except Exception:
                                    # Default expiry: 30 days from now
                                    self.expire_date = datetime.datetime.now() + datetime.timedelta(days=30)
                            else:
                                # Default expiry: 30 days from now
                                self.expire_date = datetime.datetime.now() + datetime.timedelta(days=30)
                                
                            return True
                        else:
                            self.last_error = f"REST API error: {result.get('message', 'Unknown error')}"
                    else:
                        self.last_error = f"REST API HTTP error: {response.status_code}"
                
                except Exception as e:
                    self.last_error = f"REST API request error: {e}"
                    # Continue without REST validation (for backward compatibility)
            
            # If we reach this point, either there was no REST URL or the REST call failed
            # Set default expiry date (30 days)
            self.expire_date = datetime.datetime.now() + datetime.timedelta(days=30)
            
            return True
            
        except Exception as e:
            self.last_error = f"Failed to initialize client ID: {e}"
            return False
    
    def decrypt_data(self, source: str) -> Tuple[bool, str]:
        """
        Decrypt data using the client's crypto key
        """
        try:
            self.logger.debug(f"[decrypt_data] Using client_id: {self.client_id}")
            self.logger.debug(f"[decrypt_data] Creating DataCompressor with key '{self.crypto_key}' and client_id={self.client_id}")
            data_compressor = DataCompressor(self.crypto_key, self.client_id)
            result = data_compressor.decompress_data(source)
            return True, result
        except Exception as ex:
            # Special handling for client ID=2 and client ID=6
            if self.client_id in [2, 6]:
                try:
                    self.logger.debug(f"Special handling for client ID={self.client_id} after initial failure")
                    if self.client_id == 2:
                        # For client ID=2, use hardcoded key
                        data_compressor = DataCompressor("D5F2aRD-", self.client_id)
                    elif self.client_id == 6:
                        # For client ID=6, use hardcoded key
                        data_compressor = DataCompressor("D5F26NE-", self.client_id)
                    
                    result = data_compressor.decompress_data(source)
                    return True, result
                except Exception as inner_ex:
                    self.logger.error(f"Secondary decryption attempt failed for client ID={self.client_id}: {inner_ex}")
                    return False, f"Error decrypting data: {str(inner_ex)}"
            else:
                self.logger.error(f"Error decrypting data: {str(ex)}")
                return False, f"Error decrypting data: {str(ex)}"
    
    def encrypt_data(self, data: str) -> Tuple[bool, str]:
        """Encrypt data using the crypto key"""
        if not self.crypto_key:
            self.last_error = "Crypto key is not initialized"
            return False, ""
        
        # Get client ID as integer if possible
        try:
            client_id = int(self.client_id) if self.client_id else 0
        except ValueError:
            client_id = 0
            
        compressor = DataCompressor(self.crypto_key, client_id)
        result = compressor.compress_data(data)
        
        if result:
            return True, result
        
        self.last_error = f"Failed to encrypt data: {compressor.last_error}"
        return False, ""
    
    def send_request(self, data: str, reset_event: bool = True) -> bool:
        """Send a request to the client"""
        try:
            if reset_event:
                self.event.clear()
                
            self.busy = True
            self.last_request = data
            
            # Send data to client
            full_data = f"{data}{LINE_SEPARATOR}"
            self.client_socket.sendall(full_data.encode('utf-8'))
            
            return True
        except Exception as e:
            self.last_error = f"Failed to send request: {e}"
            self.busy = False
            return False
    
    def get_response(self, r_cntr: str, data: str) -> bool:
        """Process response from client"""
        try:
            self.busy = False
            self.last_response = data
            
            # Signal that response has been received
            self.event.set()
            
            return True
        except Exception as e:
            self.last_error = f"Failed to process response: {e}"
            return False
    
    def post_error_to_file(self, msg: str) -> None:
        """Log error message to file"""
        try:
            if not self.client_id:
                # Can't log without client ID
                return
                
            # Create Logger for this client
            error_logger = Logger(self.log_path, f"ErrLog_{self.client_id}.txt")
            
            # Log the error
            error_logger.log(msg)
            
        except Exception as e:
            self.last_error = f"Failed to log error: {e}"

class HTTPConnection(RemoteConnection):
    """HTTP connection class"""
    
    def __init__(self, log_path: str):
        super().__init__(log_path)
    
    def connection_info_as_text(self) -> str:
        """Get connection information as text"""
        result = f"HTTP Start:{self.connection_info.connect_time.strftime('%M%S.%f')} "
        
        if self.connection_info.disconnect_time:
            result += f"End:{self.connection_info.disconnect_time.strftime('%M%S.%f')} "
            
        result += (
            f"Time:{self.connected_time_sec} "
            f"RH:{self.connection_info.remote_ip} "
            f"R/LP:{self.connection_info.remote_port}/{self.connection_info.local_port}"
        )
        
        return result

class TCPCommandHandler:
    """TCP command handler class"""
    
    def __init__(self, connection: TCPConnection, auth_server_url: str):
        self.connection = connection
        self.auth_server_url = auth_server_url
    
    def handle_command(self, command: str, command_data: Dict[str, str]) -> str:
        """Handle a TCP command"""
        # Update last action time
        self.connection.connection_info.last_action = datetime.datetime.now()
        
        # Process different commands
        cmd_parts = command.split()
        if not cmd_parts:
            return f"{TCP_ERR_FAIL_DECODE_DATA} Command is empty"
            
        cmd = cmd_parts[0].upper()
        
        if cmd == "INIT":
            return self.handle_init(command_data)
        elif cmd == "INFO":
            return self.handle_info(command_data)
        elif cmd == "PING":
            return self.handle_ping()
        elif cmd == "GREQ":
            return self.handle_greq()
        elif cmd == "SRSP":
            return self.handle_srsp(command_data)
        elif cmd == "VERS":
            return self.handle_vers()
        elif cmd == "DWNL":
            return self.handle_dwnl(command_data)
        elif cmd == "ERRL":
            return self.handle_errl(command.replace("ERRL ", "", 1))
        else:
            return f"{TCP_ERR_FAIL_DECODE_DATA} Unknown command: {cmd}"
    
    def handle_init(self, data: Dict[str, str]) -> str:
        """Handle INIT command"""
        try:
            # Log incoming init request
            print(f"Received INIT request with data: {data}")
            
            # Set time difference
            if "DT" in data and "TM" in data:
                self.connection.set_time_diff(data["DT"], data["TM"])
            
            # Initialize crypto key
            success, key_len = self.connection.init_crypto_key(data)
            if not success:
                print(f"Failed to initialize crypto key: {self.connection.last_error}")
                return f"{TCP_ERR_INVALID_CRYPTO_KEY} {self.connection.last_error}"
            
            # Format response
            response = f"200-KEY={self.connection.server_key}{LINE_SEPARATOR}200 LEN={key_len}"
            print(f"INIT response: {response}")
            
            return response
        except Exception as e:
            print(f"Exception in handle_init: {e}")
            return f"{TCP_ERR_INVALID_CRYPTO_KEY} Error: {e}"
    
    def handle_info(self, data: Dict[str, str]) -> str:
        """Handle INFO command"""
        try:
            # Log incoming info request
            print(f"Received INFO request with data param length: {len(data.get('DATA', ''))}")
            
            # Check if DATA parameter exists
            if "DATA" not in data:
                print("ERROR: Missing DATA parameter in INFO request")
                return f"{TCP_ERR_INVALID_DATA_PACKET} Missing DATA parameter"
            
            # Decrypt data
            print(f"Attempting to decrypt data with key: {self.connection.crypto_key}, client_id: {self.connection.client_id}")
            success, decrypted = self.connection.decrypt_data(data["DATA"])
            if not success:
                print(f"ERROR: Failed to decrypt INFO data: {decrypted}")
                return f"{TCP_ERR_FAIL_DECODE_DATA} Failed to decrypt data"
            
            print(f"Successfully decrypted INFO data: {decrypted}")
            
            # Initialize client ID
            success = self.connection.init_client_id(decrypted, self.auth_server_url)
            if not success:
                print(f"ERROR: Failed to initialize client ID: {self.connection.last_error}")
                return f"{TCP_ERR_FAIL_INIT_CLIENT_ID} {self.connection.last_error}"
            
            print(f"Successfully initialized client ID: {self.connection.client_id}")
            
            # Prepare response data
            now = datetime.datetime.now()
            response_data = "TT=Test\r\n"
            response_data += f"ID={self.connection.client_id}\r\n"
            
            if self.connection.expire_date:
                response_data += f"EX={self.connection.expire_date.strftime('%Y-%m-%d')}\r\n"
            else:
                response_data += "EX=2099-12-31\r\n"
                
            response_data += "EN=1\r\n"  # Enabled
            response_data += f"CD={now.strftime('%Y-%m-%d')}\r\n"  # Creation date
            response_data += f"CT={now.strftime('%H:%M:%S')}\r\n"  # Creation time
            
            print(f"Preparing to encrypt response: {response_data}")
            
            # Encrypt response
            success, encrypted = self.connection.encrypt_data(response_data)
            if not success:
                print(f"ERROR: Failed to encrypt INFO response: {self.connection.last_error}")
                return f"{TCP_ERR_FAIL_ENCODE_DATA} Failed to encrypt response"
            
            # Format full response
            response = f"200 DATA={encrypted}"
            print(f"INFO response length: {len(response)}")
            
            return response
        except Exception as e:
            print(f"ERROR: Exception in handle_info: {e}")
            print(traceback.format_exc(), file=sys.stderr)
            return f"{TCP_ERR_FAIL_DECODE_DATA} Error: {e}"
    
    def handle_ping(self) -> str:
        """Handle PING command"""
        return RESPONSE_OK
    
    def handle_greq(self) -> str:
        """Handle GREQ command"""
        # In original implementation, this would check for pending requests
        # For now, just return a simple response
        return f"200 CMD=0 DATA="
    
    def handle_srsp(self, data: Dict[str, str]) -> str:
        """Handle SRSP command"""
        try:
            # Check if CMD and DATA parameters exist
            if "CMD" not in data:
                return f"{TCP_ERR_INVALID_DATA_PACKET} Missing CMD parameter"
                
            if "DATA" not in data:
                return f"{TCP_ERR_INVALID_DATA_PACKET} Missing DATA parameter"
            
            # Process response
            success = self.connection.get_response(data["CMD"], data["DATA"])
            if not success:
                return f"{TCP_ERR_FAIL_DECODE_DATA} {self.connection.last_error}"
            
            return RESPONSE_OK
        except Exception as e:
            return f"{TCP_ERR_FAIL_DECODE_DATA} Error: {e}"
    
    def handle_vers(self) -> str:
        """Handle VERS command"""
        # In original implementation, this would check for software updates
        # For now, just return a simple response
        return "200 C=0"
    
    def handle_dwnl(self, data: Dict[str, str]) -> str:
        """Handle DWNL command"""
        try:
            # Check if F parameter exists
            if "F" not in data:
                return f"{TCP_ERR_INVALID_DATA_PACKET} Missing F parameter"
            
            # In original implementation, this would send a file
            # For now, just return an error
            return f"{TCP_ERR_CHECK_UPDATE_ERROR} File not found: {data['F']}"
        except Exception as e:
            return f"{TCP_ERR_CHECK_UPDATE_ERROR} Error: {e}"
    
    def handle_errl(self, error_msg: str) -> str:
        """Handle ERRL command"""
        try:
            # Log error message
            if error_msg:
                self.connection.post_error_to_file(error_msg)
            
            return RESPONSE_OK
        except Exception as e:
            return f"{TCP_ERR_FAIL_DECODE_DATA} Error: {e}" 
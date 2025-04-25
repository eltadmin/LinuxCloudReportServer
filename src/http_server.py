"""
HTTP server implementation for Cloud Report Server
"""

import json
import sys
import threading
import traceback
from typing import Dict, List, Optional, Tuple, Any, Callable

from flask import Flask, request, Response, jsonify
from werkzeug.serving import make_server

from constants import (
    HTTP_ERR_CLIENT_IS_OFFLINE,
    HTTP_ERR_CLIENT_IS_BUSY,
    HTTP_ERR_LOGIN_INCORRECT,
    HTTP_ERR_MISSING_CLIENT_ID,
    HTTP_ERR_MISSING_LOGIN_INFO,
)
from logger import Logger

class HttpServer:
    """HTTP server implementation using Flask"""
    
    def __init__(
        self,
        host: str,
        port: int,
        log_path: str,
        logins: Dict[str, str],
        get_client_func: Callable[[str], Any],
        get_client_list_func: Callable[[], List[Dict[str, str]]],
    ):
        """
        Initialize the HTTP server
        
        Args:
            host: Host to bind to
            port: Port to bind to
            log_path: Path to log files
            logins: Dictionary of username -> password for HTTP authentication
            get_client_func: Function to get a client by ID
            get_client_list_func: Function to get list of all clients
        """
        self.host = host
        self.port = port
        self.logger = Logger(log_path)
        self.logins = logins
        self.get_client = get_client_func
        self.get_client_list = get_client_list_func
        
        try:
            # Create Flask app
            self.app = Flask(__name__)
            
            # Configure Flask logging
            self.app.logger.handlers = []  # Remove default handlers
            
            # Set up error handling
            self.app.errorhandler(404)(self.handle_404)
            self.app.errorhandler(500)(self.handle_500)
            
            # Register routes
            self.register_routes()
            
            # Server object
            self.server = None
            self.server_thread = None
            self.running = False
            
        except Exception as e:
            error_msg = f"Error initializing HTTP server: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            raise
    
    def handle_404(self, e):
        """Handle 404 errors"""
        self.logger.log(f"404 Error: {request.path}")
        return self._error_response(404, f"Resource not found: {request.path}")
    
    def handle_500(self, e):
        """Handle 500 errors"""
        error_msg = f"500 Server Error: {str(e)}"
        self.logger.log(error_msg)
        print(error_msg, file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        return self._error_response(500, "Internal server error")
    
    def register_routes(self) -> None:
        """Register Flask routes"""
        
        # Authentication decorator
        def auth_required(f):
            def decorated(*args, **kwargs):
                try:
                    auth = request.authorization
                    if not auth:
                        self.logger.log(f"Missing auth - IP: {request.remote_addr}, Path: {request.path}")
                        return self._error_response(HTTP_ERR_MISSING_LOGIN_INFO, "HTTP authorization missing! Access denied!")
                    
                    if auth.username not in self.logins or self.logins[auth.username] != auth.password:
                        self.logger.log(f"Auth failed - User: {auth.username}, IP: {request.remote_addr}, Path: {request.path}")
                        return self._error_response(HTTP_ERR_LOGIN_INCORRECT, "HTTP authorization fail! Access denied!")
                    
                    return f(*args, **kwargs)
                except Exception as e:
                    error_msg = f"Error in auth_required: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    return self._error_response(500, "Internal server error during authentication")
            
            # Set function name and docstring
            decorated.__name__ = f.__name__
            decorated.__doc__ = f.__doc__
            
            return decorated
        
        # Register routes
        
        # Report endpoint
        @self.app.route('/report/<report_name>', methods=['GET', 'POST'])
        @auth_required
        def report(report_name):
            try:
                self.logger.log(f"Report request: {report_name}, Method: {request.method}, IP: {request.remote_addr}")
                
                # Check if client ID is provided
                client_id = request.args.get('id')
                if not client_id:
                    self.logger.log("Missing client ID parameter")
                    return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "Missing client ID parameter")
                
                # Get request data
                data = request.get_data(as_text=True)
                if not data:
                    self.logger.log("Empty request data")
                    return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "[TCPC][SendRequest]Data is empty!")
                
                # Get client
                client = self.get_client(client_id)
                if not client:
                    self.logger.log(f"Client with ID {client_id} is offline")
                    return self._error_response(HTTP_ERR_CLIENT_IS_OFFLINE, f"Client with ID {client_id} is offline")
                
                # Check if client is busy
                if client.busy:
                    self.logger.log(f"Client with ID {client_id} is busy")
                    return self._error_response(HTTP_ERR_CLIENT_IS_BUSY, f"Client with ID {client_id} is busy")
                
                # Send request to client and wait for response
                client.request_counter += 1
                request_id = str(client.request_counter)
                
                self.logger.log(f"Sending request to client {client_id}, request ID: {request_id}")
                
                # Format for event wait - encode client request
                if not client.send_request(f"200 CMD={request_id} DATA={data}"):
                    self.logger.log(f"Failed to send request to client {client_id}")
                    return self._error_response(HTTP_ERR_CLIENT_IS_BUSY, f"Failed to send request to client {client_id}")
                
                # Wait for response (with timeout)
                if not client.event.wait(timeout=60):
                    self.logger.log(f"Client with ID {client_id} did not respond in time")
                    client.busy = False  # Reset busy flag
                    return self._error_response(HTTP_ERR_CLIENT_IS_BUSY, f"Client with ID {client_id} did not respond in time")
                
                self.logger.log(f"Received response from client {client_id}")
                
                # Return response - need to escape the curly braces in f-string
                response_json = f'{{"ResultCode":0,"ResultMessage":"OK",{client.last_response[1:]}}}'
                
                return Response(
                    response=response_json,
                    status=200,
                    mimetype='application/json'
                )
                
            except Exception as e:
                error_msg = f"Error in report endpoint: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                return self._error_response(500, f"Internal server error: {str(e)}")
        
        # Client list endpoint
        @self.app.route('/server/clientlist', methods=['GET'])
        @auth_required
        def client_list():
            try:
                self.logger.log(f"Client list request - IP: {request.remote_addr}")
                clients = self.get_client_list()
                result = {
                    "ResultCode": 0,
                    "ResultMessage": "OK",
                    "Clients": clients
                }
                return jsonify(result)
            except Exception as e:
                error_msg = f"Error in client_list endpoint: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                return self._error_response(500, f"Internal server error: {str(e)}")
        
        # Client status endpoint
        @self.app.route('/server/clientstat', methods=['GET'])
        @auth_required
        def client_stat():
            try:
                # Check if client ID is provided
                client_id = request.args.get('id')
                if not client_id:
                    self.logger.log("Missing client ID parameter in clientstat")
                    return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "Missing client ID parameter")
                
                self.logger.log(f"Client status request - ID: {client_id}, IP: {request.remote_addr}")
                
                # Get client
                client = self.get_client(client_id)
                if not client:
                    self.logger.log(f"Client with ID {client_id} is offline")
                    return self._error_response(HTTP_ERR_CLIENT_IS_OFFLINE, f"Client with ID {client_id} is offline")
                
                # Format client info
                result = {
                    "ResultCode": 0,
                    "ResultMessage": "OK",
                    "Client": {
                        "Id": client.client_id,
                        "Host": client.client_host,
                        "Conn": client.connection_info.connect_time.strftime("%Y-%m-%d %H:%M:%S"),
                        "Act": client.connection_info.last_action.strftime("%Y-%m-%d %H:%M:%S"),
                        "Name": client.client_name,
                        "AppType": client.app_type,
                        "AppVersion": client.app_version,
                        "Idle": client.idle_time_sec
                    }
                }
                
                return jsonify(result)
            except Exception as e:
                error_msg = f"Error in client_stat endpoint: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                return self._error_response(500, f"Internal server error: {str(e)}")
    
    def _error_response(self, code: int, message: str) -> Response:
        """
        Create an error response
        
        Args:
            code: Error code
            message: Error message
            
        Returns:
            Flask Response object
        """
        result = {
            "ResultCode": code,
            "ResultMessage": message
        }
        
        return jsonify(result)
    
    def start(self) -> None:
        """Start the HTTP server in a separate thread"""
        if self.running:
            return
        
        try:
            self.running = True
            
            # Create server
            self.server = make_server(self.host, self.port, self.app)
            
            # Start server in a thread
            def run_server():
                try:
                    self.logger.log(f"Starting HTTP server on {self.host}:{self.port}")
                    self.server.serve_forever()
                except Exception as e:
                    if self.running:  # Only log if we didn't stop the server intentionally
                        error_msg = f"HTTP server error: {e}"
                        self.logger.log(error_msg)
                        print(error_msg, file=sys.stderr)
                        print(traceback.format_exc(), file=sys.stderr)
            
            self.server_thread = threading.Thread(target=run_server)
            self.server_thread.daemon = True
            self.server_thread.start()
            
            self.logger.log(f"HTTP server started on {self.host}:{self.port}")
            
        except Exception as e:
            self.running = False
            error_msg = f"Failed to start HTTP server: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            raise
    
    def stop(self) -> None:
        """Stop the HTTP server"""
        if not self.running:
            return
        
        try:
            self.running = False
            
            # Shutdown the server
            if self.server:
                self.server.shutdown()
                self.server = None
            
            self.logger.log("HTTP server stopped")
            
        except Exception as e:
            error_msg = f"Error stopping HTTP server: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr) 
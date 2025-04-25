"""
HTTP server implementation for Cloud Report Server
"""

import json
import threading
from typing import Dict, List, Optional, Tuple, Any, Callable

from flask import Flask, request, Response, jsonify

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
        
        # Create Flask app
        self.app = Flask(__name__)
        
        # Register routes
        self.register_routes()
        
        # Server thread
        self.server_thread = None
        self.running = False
    
    def register_routes(self) -> None:
        """Register Flask routes"""
        
        # Authentication decorator
        def auth_required(f):
            def decorated(*args, **kwargs):
                auth = request.authorization
                if not auth:
                    return self._error_response(HTTP_ERR_MISSING_LOGIN_INFO, "HTTP authorization missing! Access denied!")
                
                if auth.username not in self.logins or self.logins[auth.username] != auth.password:
                    return self._error_response(HTTP_ERR_LOGIN_INCORRECT, "HTTP authorization fail! Access denied!")
                
                return f(*args, **kwargs)
            
            # Set function name and docstring
            decorated.__name__ = f.__name__
            decorated.__doc__ = f.__doc__
            
            return decorated
        
        # Register routes
        
        # Report endpoint
        @self.app.route('/report/<report_name>', methods=['GET', 'POST'])
        @auth_required
        def report(report_name):
            # Check if client ID is provided
            client_id = request.args.get('id')
            if not client_id:
                return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "Missing client ID parameter")
            
            # Get request data
            data = request.get_data(as_text=True)
            if not data:
                return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "[TCPC][SendRequest]Data is empty!")
            
            # Get client
            client = self.get_client(client_id)
            if not client:
                return self._error_response(HTTP_ERR_CLIENT_IS_OFFLINE, f"Client with ID {client_id} is offline")
            
            # Check if client is busy
            if client.busy:
                return self._error_response(HTTP_ERR_CLIENT_IS_BUSY, f"Client with ID {client_id} is busy")
            
            # Send request to client and wait for response
            client.request_counter += 1
            request_id = str(client.request_counter)
            
            # Format for event wait - encode client request
            client.send_request(f"200 CMD={request_id} DATA={data}")
            
            # Wait for response (with timeout)
            if not client.event.wait(timeout=60):
                return self._error_response(HTTP_ERR_CLIENT_IS_BUSY, f"Client with ID {client_id} did not respond in time")
            
            # Return response - need to escape the curly braces in f-string
            response_json = f'{{"ResultCode":0,"ResultMessage":"OK",{client.last_response[1:]}}}'
            
            return Response(
                response=response_json,
                status=200,
                mimetype='application/json'
            )
        
        # Client list endpoint
        @self.app.route('/server/clientlist', methods=['GET'])
        @auth_required
        def client_list():
            clients = self.get_client_list()
            result = {
                "ResultCode": 0,
                "ResultMessage": "OK",
                "Clients": clients
            }
            return jsonify(result)
        
        # Client status endpoint
        @self.app.route('/server/clientstat', methods=['GET'])
        @auth_required
        def client_stat():
            # Check if client ID is provided
            client_id = request.args.get('id')
            if not client_id:
                return self._error_response(HTTP_ERR_MISSING_CLIENT_ID, "Missing client ID parameter")
            
            # Get client
            client = self.get_client(client_id)
            if not client:
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
                    "Name": client.client_name
                }
            }
            
            return jsonify(result)
    
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
        
        self.running = True
        
        def run_server():
            self.app.run(
                host=self.host,
                port=self.port,
                debug=False,
                use_reloader=False,
                threaded=True
            )
        
        self.server_thread = threading.Thread(target=run_server)
        self.server_thread.daemon = True
        self.server_thread.start()
        
        self.logger.log(f"HTTP server started on {self.host}:{self.port}")
    
    def stop(self) -> None:
        """Stop the HTTP server"""
        if not self.running:
            return
        
        self.running = False
        
        # Server thread will terminate when the main thread exits
        self.logger.log("HTTP server stopped") 
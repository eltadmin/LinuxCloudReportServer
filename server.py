import socketserver
import configparser
import logging
import threading

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# --- Configuration Loading ---
config = configparser.ConfigParser()
# Use a relative path assuming the script runs from LinuxCloudReportServer
config_path = 'config.ini' 
try:
    config.read(config_path)
    if not config.sections():
        logging.error(f"Configuration file '{config_path}' not found or empty.")
        exit(1)

    # TCP Settings
    tcp_host = config.get('SRV_1_TCP', 'TCP_IPInterface', fallback='0.0.0.0')
    tcp_port = config.getint('SRV_1_TCP', 'TCP_Port', fallback=8016)

    # Auth Server Settings (example of using another setting)
    rest_url = config.get('SRV_1_AUTHSERVER', 'REST_URL', fallback=None)
    if not rest_url:
        logging.warning("REST_URL not found in configuration.")
        
    # Logging setting
    trace_log_enabled = config.getboolean('SRV_1_COMMON', 'TraceLogEnabled', fallback=False)
    if trace_log_enabled:
        logging.getLogger().setLevel(logging.DEBUG) # Or configure file logging etc.
        logging.debug("Trace logging enabled.")
    else:
        logging.debug("Trace logging disabled.")

except configparser.Error as e:
    logging.error(f"Error reading configuration file '{config_path}': {e}")
    exit(1)
except ValueError as e:
    logging.error(f"Configuration error: Invalid value - {e}")
    exit(1)

# --- TCP Request Handler ---
class TCPRequestHandler(socketserver.BaseRequestHandler):
    """
    The request handler class for our server.

    It is instantiated once per connection to the server, and must
    override the handle() method to implement communication to the
    client.
    """
    def handle(self):
        client_address = self.client_address
        logging.info(f"Connection established from {client_address}")
        
        # --- !!! Protocol Implementation Needed Here !!! ---
        # This is where the specific logic for handling data from the client
        # needs to be implemented based on the Delphi application's protocol.
        # You will need to analyze the Delphi code to understand what data
        # is expected, how it's formatted, and what responses to send.
        
        # Example: Reading data (adjust buffer size as needed)
        try:
            while True:
                data = self.request.recv(1024) # Read up to 1024 bytes
                if not data:
                    logging.info(f"Connection closed by {client_address}")
                    break
                
                # Process the received data (replace with actual protocol logic)
                logging.debug(f"Received from {client_address}: {data.hex()} (hex) / {data.decode(errors='ignore')} (decoded)")
                
                # Send a response (replace with actual protocol logic)
                # response = b"ACK" 
                # self.request.sendall(response)
                # logging.debug(f"Sent to {client_address}: {response}")

        except ConnectionResetError:
             logging.warning(f"Connection reset by {client_address}")
        except Exception as e:
            logging.error(f"Error handling client {client_address}: {e}")
        finally:
            logging.info(f"Closing connection from {client_address}")
            # self.request.close() # socketserver usually handles this

# --- Main Server Logic ---
if __name__ == "__main__":
    logging.info(f"Starting TCP server on {tcp_host}:{tcp_port}")
    logging.info(f"Using REST API at: {rest_url}")

    # Use ThreadingMixIn for handling multiple clients concurrently
    class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
        pass

    # Allow address reuse immediately after server termination
    ThreadedTCPServer.allow_reuse_address = True 

    try:
        # Create the server, binding to host and port
        server = ThreadedTCPServer((tcp_host, tcp_port), TCPRequestHandler)

        # Activate the server; this will keep running until interrupted
        server_thread = threading.Thread(target=server.serve_forever)
        # Exit the server thread when the main thread terminates
        server_thread.daemon = True 
        server_thread.start()

        logging.info(f"Server loop running in thread: {server_thread.name}")
        
        # Keep the main thread alive (otherwise the daemon thread might exit prematurely)
        # You might want a more sophisticated shutdown mechanism here
        while True:
             threading.Event().wait(1) # Wait indefinitely, checking every second

    except OSError as e:
        logging.error(f"Could not bind to {tcp_host}:{tcp_port}. Error: {e}")
    except KeyboardInterrupt:
        logging.info("Server shutdown requested.")
    finally:
        if 'server' in locals() and server:
            logging.info("Shutting down server...")
            server.shutdown() # Stop the serve_forever loop
            server.server_close() # Close the server socket
            logging.info("Server shut down successfully.") 
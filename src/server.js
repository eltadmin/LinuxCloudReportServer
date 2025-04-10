/**
 * EBO Cloud Report Server
 * Linux-compatible version
 */

const fs = require('fs-extra');
const path = require('path');
const ReportServer = require('./reportServer');

// Constants
const SERVER_NAME = 'EBO Cloud Report Server';
const VERSION = '1.0.0';
const INI_FILENAME = 'eboCloudReportServer.ini';
const INI_SECTION = 'SRV_1_';
const HTTP_DEFAULT_PORT = 8080;
const TCP_DEFAULT_PORT = 8016;

console.log(`${SERVER_NAME} v${VERSION}`);
console.log('Starting server...');

// Ensure log directory exists
const logPath = path.join(process.cwd(), 'logs');
fs.ensureDirSync(logPath);

// Create the server instance
let server = null;

try {
  server = new ReportServer(
    path.join(process.cwd(), INI_FILENAME),
    INI_SECTION,
    logPath,
    HTTP_DEFAULT_PORT,
    TCP_DEFAULT_PORT
  );
  
  // Set up event handlers
  server.onError = (server, connection, errorMessage) => {
    console.error(`Error: ${errorMessage}`);
  };
  
  server.onConnect = (server, connection) => {
    console.log(`New connection from ${connection.connectionInfo.remoteIP}:${connection.connectionInfo.remotePort}`);
  };
  
  server.onDisconnect = (server, connection) => {
    console.log(`Disconnected: ${connection.connectionInfo.remoteIP}:${connection.connectionInfo.remotePort}`);
  };
  
  server.onCommand = (server, connection, command) => {
    console.log(`Command from ${connection.connectionInfo.remoteIP}:${connection.connectionInfo.remotePort}: ${command}`);
  };
  
  console.log(`Server started successfully on:`);
  console.log(`- HTTP: ${server.set_Http_Interface}:${server.set_Http_Port}`);
  console.log(`- TCP: ${server.set_Tcp_Interface}:${server.set_Tcp_Port}`);
  console.log(`- REST API URL: ${server.set_AuthServerUrl}`);
  
  // Handle termination signals
  process.on('SIGINT', () => {
    console.log('Received SIGINT. Shutting down server...');
    if (server) {
      server.destroy();
      server = null;
    }
    process.exit(0);
  });
  
  process.on('SIGTERM', () => {
    console.log('Received SIGTERM. Shutting down server...');
    if (server) {
      server.destroy();
      server = null;
    }
    process.exit(0);
  });
  
} catch (error) {
  console.error(`Failed to start server: ${error.message}`);
  process.exit(1);
} 
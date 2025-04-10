/**
 * EBO Cloud Report Server
 * Linux-compatible version
 */

const fs = require('fs-extra');
const path = require('path');
const net = require('net');
const http = require('http');
const express = require('express');
const ini = require('ini');
const mysql = require('mysql2/promise');

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

// Database configuration
const dbConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USER || 'dreports',
  password: process.env.DB_PASSWORD || 'ftUk58_HoRs3sAzz8jk',
  database: process.env.DB_NAME || 'dreports',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Global variables
let dbPool = null;
let settings = {};
let tcpServer = null;
let httpServer = null;
let activeConnections = new Map();

// Load configuration from INI file
function loadConfig() {
  try {
    if (!fs.existsSync(INI_FILENAME)) {
      // Create default INI file if it doesn't exist
      const defaultConfig = {
        [`${INI_SECTION}COMMON`]: {
          TraceLogEnabled: false,
          UpdateFolder: 'Updates'
        },
        [`${INI_SECTION}HTTP`]: {
          HTTP_IPInterface: '0.0.0.0',
          HTTP_Port: HTTP_DEFAULT_PORT
        },
        [`${INI_SECTION}TCP`]: {
          TCP_IPInterface: '0.0.0.0',
          TCP_Port: TCP_DEFAULT_PORT
        },
        [`${INI_SECTION}AUTHSERVER`]: {
          REST_URL: 'http://localhost/dreport/api.php'
        },
        [`${INI_SECTION}HTTPLOGINS`]: {
          user: 'pass$123'
        }
      };
      fs.writeFileSync(INI_FILENAME, ini.stringify(defaultConfig));
    }

    // Read configuration
    const config = ini.parse(fs.readFileSync(INI_FILENAME, 'utf-8'));
    
    settings = {
      traceLogEnabled: config[`${INI_SECTION}COMMON`]?.TraceLogEnabled === 'true' || 
                       config[`${INI_SECTION}COMMON`]?.TraceLogEnabled === '1' || 
                       config[`${INI_SECTION}COMMON`]?.TraceLogEnabled === true || 
                       false,
      updatePath: config[`${INI_SECTION}COMMON`]?.UpdateFolder || 'Updates',
      httpInterface: config[`${INI_SECTION}HTTP`]?.HTTP_IPInterface || '0.0.0.0',
      httpPort: parseInt(config[`${INI_SECTION}HTTP`]?.HTTP_Port, 10) || HTTP_DEFAULT_PORT,
      tcpInterface: config[`${INI_SECTION}TCP`]?.TCP_IPInterface || '0.0.0.0',
      tcpPort: parseInt(config[`${INI_SECTION}TCP`]?.TCP_Port, 10) || TCP_DEFAULT_PORT,
      authServerUrl: config[`${INI_SECTION}AUTHSERVER`]?.REST_URL || 'http://localhost/dreport/api.php',
      logins: config[`${INI_SECTION}HTTPLOGINS`] || { user: 'pass$123' }
    };

    // Ensure update directory exists
    if (settings.updatePath) {
      fs.ensureDirSync(settings.updatePath);
    }

    console.log('Configuration loaded');
    return true;
  } catch (error) {
    console.error(`Error loading configuration: ${error.message}`);
    return false;
  }
}

// Initialize database connection
async function initDatabase() {
  try {
    console.log(`Connecting to database at ${dbConfig.host}`);
    dbPool = mysql.createPool(dbConfig);
    
    // Test connection
    const connection = await dbPool.getConnection();
    console.log('Database connection established successfully');
    connection.release();
    return true;
  } catch (error) {
    console.error(`Failed to initialize database: ${error.message}`);
    return false;
  }
}

// Log an event to the database
async function logEvent(opertype, operid, description) {
  if (!dbPool) return false;
  
  try {
    await dbPool.execute(
      'INSERT INTO t_statistics(s_opertype, s_operid, s_description) VALUES (?, ?, ?)',
      [opertype, operid || '0', description]
    );
    return true;
  } catch (error) {
    console.error(`Error logging event: ${error.message}`);
    return false;
  }
}

// Handle TCP client commands
function handleTcpCommand(socket, data) {
  const clientAddress = `${socket.remoteAddress}:${socket.remotePort}`;
  const command = data.toString().trim();
  console.log(`Received command from ${clientAddress}: ${command}`);
  
  const clientId = socket.clientId || '0';
  logEvent(1, clientId, `Command: ${command}`);
  
  // Parse command
  const parts = command.split(' ');
  const cmd = parts[0].toUpperCase();
  
  try {
    switch(cmd) {
      case 'INIT':
        handleInitCommand(socket, parts);
        break;
      case 'INFO':
        handleInfoCommand(socket);
        break;
      case 'PING':
        socket.write('OK 200 PONG\r\n');
        break;
      case 'AUTH':
        handleAuthCommand(socket, parts);
        break;
      case 'SEND':
        handleSendCommand(socket, parts, data);
        break;
      case 'UPDATE':
        handleUpdateCommand(socket, parts);
        break;
      case 'GET':
        handleGetCommand(socket, parts);
        break;
      case 'EXIT':
        socket.end('OK 200 Goodbye\r\n');
        break;
      default:
        socket.write('ERROR 400 Unknown command\r\n');
    }
  } catch (error) {
    console.error(`Error processing command: ${error.message}`);
    socket.write(`ERROR 500 Server error: ${error.message}\r\n`);
    logEvent(5, clientId, `Error: ${error.message}`);
  }
}

// Handle INIT command
function handleInitCommand(socket, parts) {
  socket.initialized = true;
  socket.write('OK 200 Initialization successful\r\n');
}

// Handle INFO command
function handleInfoCommand(socket) {
  const info = {
    name: SERVER_NAME,
    version: VERSION,
    uptime: process.uptime(),
    connections: activeConnections.size
  };
  socket.write(`OK 200 ${JSON.stringify(info)}\r\n`);
}

// Handle AUTH command
async function handleAuthCommand(socket, parts) {
  if (parts.length < 4) {
    socket.write('ERROR 400 Invalid AUTH command format\r\n');
    return;
  }
  
  const deviceId = parts[1];
  const objectId = parts[2];
  const password = parts[3];
  
  try {
    const [rows] = await dbPool.execute(
      'SELECT * FROM t_devices WHERE d_deviceid = ? AND d_objectid = ?',
      [deviceId, objectId]
    );
    
    if (rows.length === 0) {
      socket.write('ERROR 403 Device not found\r\n');
      return;
    }
    
    const device = rows[0];
    if (device.d_objectpswd !== password) {
      socket.write('ERROR 403 Invalid password\r\n');
      return;
    }
    
    // Authentication successful
    socket.clientId = objectId;
    socket.deviceId = deviceId;
    socket.authenticated = true;
    
    socket.write('OK 200 Authentication successful\r\n');
    logEvent(0, objectId, `Device ${deviceId} authenticated`);
  } catch (error) {
    console.error(`Authentication error: ${error.message}`);
    socket.write('ERROR 500 Authentication error\r\n');
  }
}

// Handle SEND command
function handleSendCommand(socket, parts, data) {
  if (!socket.authenticated) {
    socket.write('ERROR 401 Authentication required\r\n');
    return;
  }
  
  // Process the data to be sent
  socket.write('OK 200 Data received\r\n');
}

// Handle UPDATE command
function handleUpdateCommand(socket, parts) {
  if (!socket.authenticated) {
    socket.write('ERROR 401 Authentication required\r\n');
    return;
  }
  
  socket.write('OK 200 Update processed\r\n');
}

// Handle GET command
function handleGetCommand(socket, parts) {
  if (!socket.authenticated) {
    socket.write('ERROR 401 Authentication required\r\n');
    return;
  }
  
  socket.write('OK 200 Data retrieved\r\n');
}

// Start TCP server
function startTcpServer() {
  tcpServer = net.createServer((socket) => {
    const clientAddress = `${socket.remoteAddress}:${socket.remotePort}`;
    console.log(`New TCP connection from ${clientAddress}`);
    
    socket.id = Date.now() + Math.random().toString(36).substring(2, 15);
    socket.initialized = false;
    socket.authenticated = false;
    socket.clientId = null;
    socket.deviceId = null;
    
    activeConnections.set(socket.id, socket);
    
    socket.on('data', (data) => {
      handleTcpCommand(socket, data);
    });
    
    socket.on('error', (error) => {
      console.error(`Socket error from ${clientAddress}: ${error.message}`);
      logEvent(5, socket.clientId, `Socket error: ${error.message}`);
    });
    
    socket.on('close', () => {
      console.log(`Connection closed from ${clientAddress}`);
      activeConnections.delete(socket.id);
    });
    
    // Welcome message
    socket.write(`${SERVER_NAME} v${VERSION} ready\r\n`);
  });
  
  tcpServer.on('error', (error) => {
    console.error(`TCP server error: ${error.message}`);
  });
  
  tcpServer.listen(settings.tcpPort, settings.tcpInterface, () => {
    console.log(`TCP server listening on ${settings.tcpInterface}:${settings.tcpPort}`);
  });
}

// Start HTTP server
function startHttpServer() {
  const app = express();
  
  // Middleware
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));
  
  // Health check endpoint
  app.get('/health', (req, res) => {
    res.status(200).send('OK');
  });
  
  // Status endpoint
  app.get('/status', (req, res) => {
    res.json({
      name: SERVER_NAME,
      version: VERSION,
      uptime: process.uptime(),
      connections: activeConnections.size
    });
  });
  
  // Start the HTTP server
  httpServer = app.listen(settings.httpPort, settings.httpInterface, () => {
    console.log(`HTTP server listening on ${settings.httpInterface}:${settings.httpPort}`);
  });
  
  httpServer.on('error', (error) => {
    console.error(`HTTP server error: ${error.message}`);
  });
}

// Graceful shutdown function
function shutdown() {
  console.log('Shutting down server...');
  
  // Close all active connections
  for (const [id, socket] of activeConnections) {
    try {
      socket.end('Server shutting down\r\n');
    } catch (error) {
      console.error(`Error closing socket: ${error.message}`);
    }
  }
  
  // Close TCP server
  if (tcpServer) {
    tcpServer.close(() => {
      console.log('TCP server closed');
    });
  }
  
  // Close HTTP server
  if (httpServer) {
    httpServer.close(() => {
      console.log('HTTP server closed');
    });
  }
  
  // Close database connection
  if (dbPool) {
    dbPool.end().then(() => {
      console.log('Database connection closed');
    }).catch((error) => {
      console.error(`Error closing database connection: ${error.message}`);
    });
  }
}

// Handle process termination
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

// Main function to start everything
async function main() {
  // Load configuration
  if (!loadConfig()) {
    console.error('Failed to load configuration. Exiting.');
    process.exit(1);
  }
  
  // Initialize database
  if (!await initDatabase()) {
    console.error('Failed to initialize database. Exiting.');
    process.exit(1);
  }
  
  // Start TCP server
  startTcpServer();
  
  // Start HTTP server
  startHttpServer();
  
  console.log(`${SERVER_NAME} started successfully`);
}

// Run the main function
main().catch(error => {
  console.error(`Fatal error: ${error.message}`);
  process.exit(1);
}); 
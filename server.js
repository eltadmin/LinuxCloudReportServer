/**
 * Cloud Report Server - Linux Version
 * Main server file that initializes both HTTP and TCP servers
 */

const express = require('express');
const net = require('net');
const fs = require('fs');
const path = require('path');
const ini = require('ini');
const winston = require('winston');
const cors = require('cors');
const bodyParser = require('body-parser');
const cookieParser = require('cookie-parser');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const dotenv = require('dotenv');
const fetch = (...args) => import('node-fetch').then(({default: fetch}) => fetch(...args));

// Load environment variables
dotenv.config();

const { createReportServer } = require('./reportServerUnit');
const { initLogger } = require('./utils/logger');
const { loadConfig } = require('./utils/config');
const constants = require('./utils/constants');
const { monitorRequest, metricsEndpoint } = require('./middleware/monitoring');
const { sequelize, testConnection } = require('./config/database');

// Global variables
let config;
let logger;
let httpServer;
let tcpServer;

// Initialize server
async function initServer() {
  try {
    // Setup logging
    const logPath = path.join(__dirname, 'logs');
    if (!fs.existsSync(logPath)) {
      fs.mkdirSync(logPath, { recursive: true });
    }
    
    logger = initLogger(logPath);
    logger.info('Cloud Report Server starting...');
    
    // Load configuration
    const configPath = path.join(__dirname, 'config', 'server.ini');
    config = loadConfig(configPath);
    
    // Test database connection
    logger.info('Testing database connection...');
    const dbConnected = await testConnection();
    
    if (!dbConnected) {
      logger.error('Failed to connect to the database');
      process.exit(1);
    }
    
    // Start the server components
    const reportServer = createReportServer({
      config,
      logger,
      sequelize
    });
    
    // Start HTTP Server
    const app = express();
    
    // Security middleware
    app.use(helmet({
      contentSecurityPolicy: false  // Disable CSP for compatibility with dreport
    }));
    app.use(cors());
    app.use(bodyParser.json());
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(cookieParser());
    
    // Rate limiting
    const apiLimiter = rateLimit({
      windowMs: 15 * 60 * 1000, // 15 minutes
      max: 100 // limit each IP to 100 requests per windowMs
    });
    app.use('/api/', apiLimiter);
    
    // Monitoring middleware
    app.use(monitorRequest);
    app.get('/metrics', metricsEndpoint);
    
    // Mount API routes
    app.use('/api', require('./routes/api')(reportServer));
    app.use('/auth', require('./routes/auth')());
    app.use('/dreport-api', require('./routes/dreport')(reportServer));
    
    // Health check endpoint
    app.get('/health', (req, res) => {
      res.status(200).json({ 
        status: 'ok',
        version: '1.0.0',
        database: 'connected',
        services: {
          http: 'running',
          tcp: 'running'
        }
      });
    });

    // Initialize dreport system
    app.get('/init-dreport', async (req, res) => {
      try {
        // Get settings
        const response = await fetch(`http://localhost:8080/dreport-api/settings`);
        const result = await response.json();
        
        if (result.success) {
          logger.info('DReport system initialized successfully');
          res.status(200).json({ 
            success: true,
            message: 'DReport system initialized successfully' 
          });
        } else {
          throw new Error('Failed to initialize DReport settings');
        }
      } catch (error) {
        logger.error('Failed to initialize DReport system', { error: error.message });
        res.status(500).json({ 
          success: false,
          error: {
            message: 'Failed to initialize DReport system',
            details: error.message
          }
        });
      }
    });
    
    // Start HTTP server
    const httpPort = process.env.HTTP_PORT || config.http?.port || 8080;
    httpServer = app.listen(httpPort, () => {
      logger.info(`HTTP server listening on port ${httpPort}`);
    });
    
    // Start TCP server
    const tcpPort = process.env.TCP_PORT || config.tcp?.port || 2909;
    tcpServer = createTcpServer(reportServer, tcpPort);
    logger.info(`TCP server listening on port ${tcpPort}`);
    
    logger.info('Cloud Report Server started successfully');
    
  } catch (error) {
    console.error('Failed to initialize server:', error);
    if (logger) {
      logger.error('Server initialization failed', { error: error.message, stack: error.stack });
    }
    process.exit(1);
  }
}

// Create TCP server
function createTcpServer(reportServer, port) {
  const server = net.createServer((socket) => {
    logger.info(`TCP client connected: ${socket.remoteAddress}:${socket.remotePort}`);
    
    // Add the connection to the report server
    reportServer.handleTcpConnection(socket);
    
    socket.on('error', (err) => {
      logger.error(`TCP socket error: ${err.message}`);
    });
  });
  
  server.listen(port, () => {
    logger.info(`TCP server listening on port ${port}`);
  });
  
  return server;
}

// Handle graceful shutdown
function shutdownServer() {
  logger.info('Shutting down server...');
  
  // Close HTTP server
  if (httpServer) {
    httpServer.close(() => {
      logger.info('HTTP server closed');
    });
  }
  
  // Close TCP server
  if (tcpServer) {
    tcpServer.close(() => {
      logger.info('TCP server closed');
    });
  }
  
  // Close database connection
  sequelize.close().then(() => {
    logger.info('Database connection closed');
  });
  
  setTimeout(() => {
    logger.info('Server shutdown complete');
    process.exit(0);
  }, 1000);
}

// Handle process termination
process.on('SIGTERM', shutdownServer);
process.on('SIGINT', shutdownServer);

// Export app for testing
const app = express();
module.exports = { app };

// Start the server if not imported as a module
if (require.main === module) {
  initServer().catch(err => {
    console.error('Failed to start server:', err);
    process.exit(1);
  });
} 
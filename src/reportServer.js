/**
 * Report Server
 * Linux-compatible version of ReportServerUnit.pas
 */

const fs = require('fs-extra');
const path = require('path');
const net = require('net');
const express = require('express');
const bodyParser = require('body-parser');
const ini = require('ini');
const moment = require('moment');
const zlib = require('zlib');
const { RemoteConnection, HttpConnection, TcpConnection } = require('./remoteConnection');
const constants = require('./constants');

/**
 * Report Server main class
 */
class ReportServer {
  constructor(iniFileName, iniSection, logPath, defPortHttp = 8080, defPortTcp = 8016) {
    this.iniFileName = iniFileName;
    this.logPath = logPath;
    this.localPath = process.cwd();
    this.serverName = '';
    this.serverId = 0;
    this.lastCleanup = moment().subtract(2, 'days').toDate();
    this.loginList = {};
    
    // Load settings from INI file
    this.loadIniSettings(iniFileName, iniSection, defPortHttp, defPortTcp);
    
    this.serverName = `Tcp:${this.set_Tcp_Interface}:${this.set_Tcp_Port} / Http:${this.set_Http_Interface}:${this.set_Http_Port}`;
    
    // Create critical section (mutex) equivalents
    this.csUserInterface = false;
    this.csFilesUpdate = false;
    
    // Timer for checking inactive connections
    this.idleTimer = setInterval(() => this.idleTimerHandler(), 
                                 (constants.C_DropDeviceWoSerialTimeSec / 2) * 1000);
    
    // HTTP server
    this.httpServer = express();
    this.httpServer.use(bodyParser.json());
    this.httpServer.use(bodyParser.urlencoded({ extended: true }));
    this.httpConnections = new Map();
    
    // TCP server
    this.tcpServer = net.createServer();
    this.tcpConnections = new Map();
    
    // Event handlers
    this.onError = null;
    this.onConnect = null;
    this.onDisconnect = null;
    this.onCommand = null;
    
    // Initialize servers
    this.setupHttpServer();
    this.setupTcpServer();
    
    // Start servers
    this.startServers();
  }
  
  /**
   * Destructor
   */
  destroy() {
    clearInterval(this.idleTimer);
    
    // Close all connections
    for (const conn of this.httpConnections.values()) {
      conn.mustDisconnect = true;
    }
    
    for (const conn of this.tcpConnections.values()) {
      conn.mustDisconnect = true;
      if (conn.socket && !conn.socket.destroyed) {
        conn.socket.destroy();
      }
    }
    
    // Close servers
    if (this.httpServerInstance) {
      this.httpServerInstance.close();
    }
    
    if (this.tcpServer) {
      this.tcpServer.close();
    }
    
    // Clear data
    this.httpConnections.clear();
    this.tcpConnections.clear();
    this.loginList = {};
  }
  
  /**
   * Load settings from INI file
   */
  loadIniSettings(iniFileName, iniSection, defPortHttp, defPortTcp) {
    try {
      // Create INI file if it doesn't exist
      if (!fs.existsSync(iniFileName)) {
        const defaultConfig = {
          [`${iniSection}COMMON`]: {
            TraceLogEnabled: false,
            UpdateFolder: ''
          },
          [`${iniSection}HTTP`]: {
            HTTP_IPInterface: '0.0.0.0',
            HTTP_Port: defPortHttp
          },
          [`${iniSection}TCP`]: {
            TCP_IPInterface: '0.0.0.0',
            TCP_Port: defPortTcp
          },
          [`${iniSection}AUTHSERVER`]: {
            REST_URL: 'http://localhost/'
          },
          [`${iniSection}HTTPLOGINS`]: {
            user: 'pass$123'
          }
        };
        
        fs.writeFileSync(iniFileName, ini.stringify(defaultConfig));
      }
      
      // Read INI file
      const config = ini.parse(fs.readFileSync(iniFileName, 'utf-8'));
      
      // Read settings
      this.set_TraceLogEnbld = config[`${iniSection}COMMON`]?.TraceLogEnabled === 'true' || 
                              config[`${iniSection}COMMON`]?.TraceLogEnabled === '1' || 
                              config[`${iniSection}COMMON`]?.TraceLogEnabled === true || 
                              false;
      
      this.set_UpdatePath = config[`${iniSection}COMMON`]?.UpdateFolder || '';
      this.set_Http_Interface = config[`${iniSection}HTTP`]?.HTTP_IPInterface || '0.0.0.0';
      this.set_Http_Port = parseInt(config[`${iniSection}HTTP`]?.HTTP_Port, 10) || defPortHttp;
      this.set_Tcp_Interface = config[`${iniSection}TCP`]?.TCP_IPInterface || '0.0.0.0';
      this.set_Tcp_Port = parseInt(config[`${iniSection}TCP`]?.TCP_Port, 10) || defPortTcp;
      this.set_AuthServerUrl = config[`${iniSection}AUTHSERVER`]?.REST_URL || '';
      
      // Read HTTP logins
      this.loginList = config[`${iniSection}HTTPLOGINS`] || {};
      
      // Create update folder if specified
      if (this.set_UpdatePath !== '') {
        this.set_UpdatePath = path.join(this.localPath, this.set_UpdatePath);
        fs.ensureDirSync(this.set_UpdatePath);
      }
    } catch (error) {
      console.error('Error loading INI settings:', error);
      throw new Error(`Failed to load INI settings: ${error.message}`);
    }
  }
  
  /**
   * Setup HTTP server
   */
  setupHttpServer() {
    // Setup HTTP routes
    this.httpServer.get('/', (req, res) => this.httpRouteIndex(req, res));
    this.httpServer.get('/report/:document', (req, res) => this.httpRouteGenerateReport(req, res));
    
    // Connection handling
    this.httpServer.use((req, res, next) => {
      const connection = new HttpConnection(this.logPath);
      connection.doConnect({
        remoteAddress: req.socket.remoteAddress,
        remotePort: req.socket.remotePort,
        localPort: req.socket.localPort
      });
      
      // Store connection
      const connectionId = `${req.socket.remoteAddress}:${req.socket.remotePort}`;
      this.httpConnections.set(connectionId, connection);
      
      // Handle disconnect
      res.on('finish', () => {
        connection.doDisconnect();
        this.httpConnections.delete(connectionId);
      });
      
      next();
    });
  }
  
  /**
   * Setup TCP server
   */
  setupTcpServer() {
    // Handle connection
    this.tcpServer.on('connection', (socket) => {
      const connection = new TcpConnection(socket, this.logPath);
      connection.doConnect(socket);
      
      // Store connection
      const connectionId = `${socket.remoteAddress}:${socket.remotePort}`;
      this.tcpConnections.set(connectionId, connection);
      
      // Process incoming data
      socket.on('data', (data) => {
        this.handleTcpData(connection, data);
      });
      
      // Handle disconnect
      socket.on('close', () => {
        connection.doDisconnect();
        this.tcpConnections.delete(connectionId);
        
        // Call disconnect handler
        if (this.onDisconnect) {
          this.csUserInterface = true;
          try {
            this.onDisconnect(this, connection);
          } finally {
            this.csUserInterface = false;
          }
        }
      });
      
      // Handle errors
      socket.on('error', (error) => {
        const errorMessage = `Socket error: ${error.message}`;
        console.error(errorMessage);
        
        // Call error handler
        if (this.onError) {
          this.csUserInterface = true;
          try {
            this.onError(this, connection, errorMessage);
          } finally {
            this.csUserInterface = false;
          }
        }
      });
      
      // Call connect handler
      if (this.onConnect) {
        this.csUserInterface = true;
        try {
          this.onConnect(this, connection);
        } finally {
          this.csUserInterface = false;
        }
      }
    });
    
    // Handle server errors
    this.tcpServer.on('error', (error) => {
      console.error('TCP server error:', error);
    });
  }
  
  /**
   * Start both HTTP and TCP servers
   */
  startServers() {
    try {
      // Start HTTP server
      this.httpServerInstance = this.httpServer.listen(this.set_Http_Port, this.set_Http_Interface, () => {
        console.log(`HTTP server listening on ${this.set_Http_Interface}:${this.set_Http_Port}`);
      });
      
      // Start TCP server
      this.tcpServer.listen(this.set_Tcp_Port, this.set_Tcp_Interface, () => {
        console.log(`TCP server listening on ${this.set_Tcp_Interface}:${this.set_Tcp_Port}`);
      });
    } catch (error) {
      console.error('Failed to start servers:', error);
      throw new Error(`Fail to start: ${error.message}`);
    }
  }
  
  /**
   * Handle TCP data
   */
  handleTcpData(connection, data) {
    try {
      const dataStr = data.toString('utf8');
      
      // Parse command and data
      const spaceIndex = dataStr.indexOf(' ');
      const command = spaceIndex > 0 ? dataStr.substring(0, spaceIndex).trim() : dataStr.trim();
      const commandData = spaceIndex > 0 ? dataStr.substring(spaceIndex + 1).trim() : '';
      
      // Update last action time
      connection.connectionInfo.lastAction = new Date();
      
      // Handle commands
      switch (command.toUpperCase()) {
        case 'INIT':
          this.handleTcpCommand_Init(connection, commandData);
          break;
        case 'INFO':
          this.handleTcpCommand_Info(connection, commandData);
          break;
        case 'PING':
          this.handleTcpCommand_Ping(connection, commandData);
          break;
        case 'SRSP':
          this.handleTcpCommand_SendResponse(connection, commandData);
          break;
        case 'GREQ':
          this.handleTcpCommand_GetRequest(connection, commandData);
          break;
        case 'VERS':
          this.handleTcpCommand_CheckVersion(connection, commandData);
          break;
        case 'DWNL':
          this.handleTcpCommand_Download(connection, commandData);
          break;
        case 'ERRL':
          this.handleTcpCommand_ErrorLog(connection, commandData);
          break;
        default:
          // Unknown command
          connection.sendRequest(`ERR ${constants.C_TcpErr_CommandUnknown} Unknown command: ${command}`);
          break;
      }
      
      // Call command handler
      if (this.onCommand) {
        this.csUserInterface = true;
        try {
          this.onCommand(this, connection, `${command} ${commandData}`);
        } finally {
          this.csUserInterface = false;
        }
      }
    } catch (error) {
      console.error('Error handling TCP data:', error);
      connection.sendRequest(`ERR ${constants.C_TcpErr_InvalidDataPacket} ${error.message}`);
    }
  }
  
  /**
   * Handle various TCP commands
   */
  handleTcpCommand_Init(connection, data) {
    // Implementation of Init command
    // ...
    connection.sendRequest('OK 200 Initialization successful');
  }
  
  handleTcpCommand_Info(connection, data) {
    // Implementation of Info command
    // ...
    connection.sendRequest('OK 200 Server info');
  }
  
  handleTcpCommand_Ping(connection, data) {
    // Simple ping-pong response
    connection.sendRequest('OK 200 PONG');
  }
  
  handleTcpCommand_SendResponse(connection, data) {
    // Implementation of SendResponse command
    // ...
    connection.sendRequest('OK 200 Response received');
  }
  
  handleTcpCommand_GetRequest(connection, data) {
    // Implementation of GetRequest command
    // ...
    connection.sendRequest('OK 200 Request processed');
  }
  
  handleTcpCommand_CheckVersion(connection, data) {
    // Implementation of CheckVersion command
    // ...
    connection.sendRequest('OK 200 Version check completed');
  }
  
  handleTcpCommand_Download(connection, data) {
    // Implementation of Download command
    // ...
    connection.sendRequest('OK 200 Download processed');
  }
  
  handleTcpCommand_ErrorLog(connection, data) {
    // Implementation of ErrorLog command
    // ...
    connection.sendRequest('OK 200 Error log received');
  }
  
  /**
   * HTTP route handlers
   */
  httpRouteIndex(req, res) {
    res.send('EBO Cloud Report Server');
  }
  
  httpRouteGenerateReport(req, res) {
    const document = req.params.document;
    const clientId = req.query.clientId;
    
    if (!clientId) {
      return res.status(400).json({
        error: constants.C_HttpErr_MissingClientID,
        message: 'Missing client ID'
      });
    }
    
    // Generate report
    const reportData = this.httpGenerateReport(document, clientId);
    
    res.json({ report: reportData });
  }
  
  /**
   * Generate report for HTTP request
   */
  httpGenerateReport(document, clientId) {
    // Implementation here
    // This would involve finding the appropriate TCP client and requesting a report
    return `Report for document ${document} and client ${clientId}`;
  }
  
  /**
   * Check HTTP login
   */
  doHttpLogin(user, pass) {
    // Check if user exists and password matches
    return this.loginList[user] === pass;
  }
  
  /**
   * Check for duplicate client IDs
   */
  checkDuplicateClientId(currentConn) {
    for (const conn of this.tcpConnections.values()) {
      if (conn !== currentConn && 
          conn.clientID === currentConn.clientID &&
          conn.clientID !== '') {
        return true;
      }
    }
    return false;
  }
  
  /**
   * Get list of update files
   */
  getUpdateFileList() {
    if (!this.set_UpdatePath) return [];
    
    this.csFilesUpdate = true;
    try {
      return fs.readdirSync(this.set_UpdatePath)
        .filter(file => fs.statSync(path.join(this.set_UpdatePath, file)).isFile())
        .map(file => {
          const stats = fs.statSync(path.join(this.set_UpdatePath, file));
          return {
            name: file,
            size: stats.size,
            date: stats.mtime
          };
        });
    } catch (error) {
      console.error('Error getting update file list:', error);
      return [];
    } finally {
      this.csFilesUpdate = false;
    }
  }
  
  /**
   * Send update file to client
   */
  sendUpdateFile(fname, connection) {
    if (!this.set_UpdatePath) return false;
    
    const filePath = path.join(this.set_UpdatePath, fname);
    
    if (!fs.existsSync(filePath)) {
      connection.sendRequest(`ERR ${constants.C_TcpErr_CheckUpdateError} File not found: ${fname}`);
      return false;
    }
    
    this.csFilesUpdate = true;
    try {
      const fileData = fs.readFileSync(filePath);
      
      // Compress data
      const compressed = zlib.deflateSync(fileData);
      
      // Convert to Base64
      const base64Data = compressed.toString('base64');
      
      // Send data
      connection.sendRequest(`OK 200 ${base64Data}`);
      return true;
    } catch (error) {
      console.error('Error sending update file:', error);
      connection.sendRequest(`ERR ${constants.C_TcpErr_CheckUpdateError} ${error.message}`);
      return false;
    } finally {
      this.csFilesUpdate = false;
    }
  }
  
  /**
   * Clear old files
   */
  clearOldFiles(dirPath, ext, days) {
    if (!dirPath || !fs.existsSync(dirPath)) return;
    
    try {
      const files = fs.readdirSync(dirPath);
      const cutoffDate = moment().subtract(days, 'days').toDate();
      
      for (const file of files) {
        if (ext && !file.endsWith(ext)) continue;
        
        const filePath = path.join(dirPath, file);
        const stats = fs.statSync(filePath);
        
        if (stats.isFile() && stats.mtime < cutoffDate) {
          fs.unlinkSync(filePath);
        }
      }
    } catch (error) {
      console.error('Error clearing old files:', error);
    }
  }
  
  /**
   * Add trace log entry
   */
  addTraceLog(procName, message) {
    if (!this.set_TraceLogEnbld) return;
    
    try {
      const timestamp = moment().format('YYYY-MM-DD HH:mm:ss');
      const logEntry = `[${timestamp}] [${procName}] ${message}\n`;
      
      fs.appendFileSync(path.join(this.logPath, constants.C_FileName_TraceLog), logEntry);
    } catch (error) {
      console.error('Failed to write to trace log:', error);
    }
  }
  
  /**
   * Timer handler to check for inactive connections
   */
  idleTimerHandler() {
    // Check HTTP connections
    for (const [id, conn] of this.httpConnections.entries()) {
      if (conn.mustDisconnect) {
        this.httpConnections.delete(id);
        continue;
      }
      
      if (conn.getIdleTimeSec() > constants.C_DropDeviceWoActivity) {
        const msg = `HTTP session without activity. TERMINATING... ${id}`;
        this.addTraceLog('WatchDog', msg);
        
        if (this.onError) {
          this.csUserInterface = true;
          try {
            this.onError(this, conn, msg);
          } finally {
            this.csUserInterface = false;
          }
        }
        
        conn.mustDisconnect = true;
        this.httpConnections.delete(id);
      }
    }
    
    // Check TCP connections
    for (const [id, conn] of this.tcpConnections.entries()) {
      if (conn.mustDisconnect || conn.destroying) {
        if (conn.socket && !conn.socket.destroyed) {
          conn.socket.destroy();
        }
        this.tcpConnections.delete(id);
        continue;
      }
      
      if (conn.clientID === '' && conn.getConnectedTimeSec() > constants.C_DropDeviceWoSerialTimeSec) {
        const msg = `TCP session without client ID. TERMINATING... ${id}`;
        this.addTraceLog('WatchDog', msg);
        
        if (this.onError) {
          this.csUserInterface = true;
          try {
            this.onError(this, conn, msg);
          } finally {
            this.csUserInterface = false;
          }
        }
        
        if (conn.socket && !conn.socket.destroyed) {
          conn.socket.destroy();
        }
        this.tcpConnections.delete(id);
      }
      else if (conn.getIdleTimeSec() > constants.C_DropDeviceWoActivity) {
        const msg = `TCP session without activity. TERMINATING... ${id}`;
        this.addTraceLog('WatchDog', msg);
        
        if (this.onError) {
          this.csUserInterface = true;
          try {
            this.onError(this, conn, msg);
          } finally {
            this.csUserInterface = false;
          }
        }
        
        if (conn.socket && !conn.socket.destroyed) {
          conn.socket.destroy();
        }
        this.tcpConnections.delete(id);
      }
    }
    
    // Clear old files once per day
    const now = moment();
    if (now.diff(this.lastCleanup, 'days') >= 1) {
      this.clearOldFiles(this.logPath, '.log', 30); // Keep logs for 30 days
      this.lastCleanup = now.toDate();
    }
  }
  
  /**
   * Get server active status
   */
  get active() {
    return this.httpServerInstance !== null && this.tcpServer !== null;
  }
}

module.exports = ReportServer; 
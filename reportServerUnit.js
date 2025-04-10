/**
 * Report Server Unit - Main server implementation
 */
const path = require('path');
const fs = require('fs');
const EventEmitter = require('events');
const TcpConnection = require('./models/TcpConnection');
const HttpConnection = require('./models/HttpConnection');
const { Client, Report } = require('./models/db');
const { HTTP_ERRORS, DROP_DEVICE_WITHOUT_ACTIVITY } = require('./utils/constants');
const { encryptAES, decryptAES } = require('./utils/encryption');
const { metrics } = require('./middleware/monitoring');

class ReportServer {
  /**
   * Create a new report server instance
   * @param {Object} options - Server options
   */
  constructor(options) {
    this.config = options.config;
    this.logger = options.logger;
    this.sequelize = options.sequelize;
    
    // Paths
    this.logPath = options.config.server?.logPath || './logs';
    this.updatePath = options.config.server?.updatePath || './updates';
    this.localPath = process.cwd();
    
    // Server state
    this.serverId = 0;
    this.serverName = `Tcp:${this.config.SRV_1_TCP?.TCP_IPInterface || this.config.tcp?.interface || '0.0.0.0'}:${this.config.SRV_1_TCP?.TCP_Port || this.config.tcp?.port || 8016} / ` +
                      `Http:${this.config.SRV_1_HTTP?.HTTP_IPInterface || this.config.http?.interface || '0.0.0.0'}:${this.config.SRV_1_HTTP?.HTTP_Port || this.config.http?.port || 8080}`;
    
    // Auth server URL
    this.authServerUrl = this.config.SRV_1_AUTHSERVER?.REST_URL || this.config.server?.authServerUrl || 'http://10.150.40.7/dreport/api.php';
    
    // Connection management
    this.tcpConnections = new Map();
    this.httpConnections = new Map();
    this.loginList = new Map();
    
    // Initialize logins from config
    if (this.config.SRV_1_HTTPLOGINS) {
      Object.entries(this.config.SRV_1_HTTPLOGINS).forEach(([username, password]) => {
        this.loginList.set(username, password);
      });
    }
    
    // Event emitters
    this.events = new EventEmitter();
    
    // Cleanup timer
    this.idleTimer = setInterval(() => this.cleanupConnections(), 30000);
    
    this.logger.info(`Report Server initialized: ${this.serverName}`);
    
    // Create necessary directories
    this.ensureDirectoriesExist();
  }
  
  /**
   * Ensure required directories exist
   */
  ensureDirectoriesExist() {
    const directories = [
      this.logPath,
      this.updatePath
    ];
    
    directories.forEach(dir => {
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
        this.logger.info(`Created directory: ${dir}`);
      }
    });
  }
  
  /**
   * Clean up old files in a directory
   * @param {string} dirPath - Directory path
   * @param {string} extension - File extension to match
   * @param {number} maxAgeDays - Maximum age in days
   */
  cleanupOldFiles(dirPath, extension, maxAgeDays) {
    try {
      const now = new Date();
      const files = fs.readdirSync(dirPath);
      
      files.forEach(file => {
        if (path.extname(file) === extension) {
          const filePath = path.join(dirPath, file);
          const stats = fs.statSync(filePath);
          const fileAgeDays = (now - stats.mtime) / (1000 * 60 * 60 * 24);
          
          if (fileAgeDays > maxAgeDays) {
            fs.unlinkSync(filePath);
            this.logger.info(`Deleted old file: ${filePath}`);
          }
        }
      });
    } catch (error) {
      this.logger.error(`Error cleaning up old files: ${error.message}`);
    }
  }
  
  /**
   * Clean up idle connections
   */
  cleanupConnections() {
    try {
      // Clean up old files periodically
      this.cleanupOldFiles(this.logPath, '.log', 7);
      this.cleanupOldFiles(this.logPath, '.txt', 7);
      
      // Clean up idle TCP connections
      for (const [id, connection] of this.tcpConnections.entries()) {
        if (connection.shouldDisconnect()) {
          this.logger.info(`Closing idle TCP connection: ${connection.connectionInfoAsText()}`);
          
          if (connection.socket && !connection.socket.destroyed) {
            connection.socket.destroy();
          }
          
          this.tcpConnections.delete(id);
          
          // Update client status in database if client ID is available
          if (connection.clientID) {
            this.updateClientStatus(connection.clientID, false);
          }
          
          // Update metrics
          metrics.tcpConnectionGauge.set(this.tcpConnections.size);
        }
      }
      
      // Clean up HTTP connections (typically not needed as they are short-lived)
      for (const [id, connection] of this.httpConnections.entries()) {
        if (connection.getIdleTimeSec() > DROP_DEVICE_WITHOUT_ACTIVITY) {
          this.httpConnections.delete(id);
        }
      }
    } catch (error) {
      this.logger.error(`Error in cleanup timer: ${error.message}`);
    }
  }
  
  /**
   * Update client status in database
   * @param {string} clientId - Client ID
   * @param {boolean} isOnline - Online status
   */
  async updateClientStatus(clientId, isOnline) {
    try {
      const [client] = await Client.findOrCreate({
        where: { clientId },
        defaults: {
          clientId,
          isOnline,
          lastActivity: new Date(),
          lastConnected: isOnline ? new Date() : null
        }
      });
      
      if (client) {
        client.isOnline = isOnline;
        if (isOnline) {
          client.lastConnected = new Date();
          client.connectionCount += 1;
        }
        client.lastActivity = new Date();
        await client.save();
      }
    } catch (error) {
      this.logger.error(`Error updating client status: ${error.message}`);
    }
  }
  
  /**
   * Update client information in database
   * @param {string} clientId - Client ID
   * @param {Object} clientInfo - Client information
   */
  async updateClientInfo(clientId, clientInfo) {
    try {
      const [client] = await Client.findOrCreate({
        where: { clientId },
        defaults: {
          clientId,
          isOnline: true,
          lastActivity: new Date(),
          lastConnected: new Date()
        }
      });
      
      if (client) {
        // Update client info
        if (clientInfo.clientName) client.clientName = clientInfo.clientName;
        if (clientInfo.clientHost) client.clientHost = clientInfo.clientHost;
        if (clientInfo.appType) client.appType = clientInfo.appType;
        if (clientInfo.appVersion) client.appVersion = clientInfo.appVersion;
        if (clientInfo.dbType) client.dbType = clientInfo.dbType;
        
        client.isOnline = true;
        client.lastActivity = new Date();
        await client.save();
      }
    } catch (error) {
      this.logger.error(`Error updating client info: ${error.message}`);
    }
  }
  
  /**
   * Handle a new TCP connection
   * @param {Object} socket - TCP socket
   */
  handleTcpConnection(socket) {
    try {
      const connectionId = `${socket.remoteAddress}:${socket.remotePort}`;
      
      // Create a new connection
      const connection = new TcpConnection(socket, this.logPath);
      connection.doConnect(socket);
      
      // Add to connections map
      this.tcpConnections.set(connectionId, connection);
      
      this.logger.info(`TCP connection established: ${connectionId}`);
      this.events.emit('connect', connection);
      
      // Update metrics
      metrics.tcpConnectionCounter.inc();
      metrics.tcpConnectionGauge.set(this.tcpConnections.size);
      
      // Set up disconnect handler
      socket.on('close', () => {
        connection.doDisconnect();
        this.tcpConnections.delete(connectionId);
        this.logger.info(`TCP connection closed: ${connectionId}`);
        this.events.emit('disconnect', connection);
        
        // Update client status in database if client ID is available
        if (connection.clientID) {
          this.updateClientStatus(connection.clientID, false);
        }
        
        // Update metrics
        metrics.tcpConnectionGauge.set(this.tcpConnections.size);
      });
      
    } catch (error) {
      this.logger.error(`Error handling TCP connection: ${error.message}`);
      socket.destroy();
    }
  }
  
  /**
   * Handle an HTTP request
   * @param {Object} req - Express request
   * @param {Object} res - Express response
   * @returns {HttpConnection} - HTTP connection
   */
  handleHttpRequest(req, res) {
    try {
      const connectionId = `${req.ip || req.socket.remoteAddress}:${req.socket.remotePort}`;
      
      // Create a new connection
      const connection = new HttpConnection(req, res, this.logPath);
      
      // Add to connections map
      this.httpConnections.set(connectionId, connection);
      
      this.logger.info(`HTTP connection received: ${connectionId}`);
      this.events.emit('connect', connection);
      
      // Set up cleanup on response finish
      res.on('finish', () => {
        this.httpConnections.delete(connectionId);
        this.events.emit('disconnect', connection);
      });
      
      return connection;
    } catch (error) {
      this.logger.error(`Error handling HTTP connection: ${error.message}`);
      res.status(500).json({ error: 'Internal Server Error' });
      return null;
    }
  }
  
  /**
   * Check if login credentials are valid
   * @param {string} username - Username
   * @param {string} password - Password
   * @returns {boolean} - True if credentials are valid
   */
  checkLogin(username, password) {
    // This is a placeholder implementation
    // In a real application, this would check against a database or other auth system
    return this.loginList.has(username) && this.loginList.get(username) === password;
  }
  
  /**
   * Find a TCP connection by client ID
   * @param {string} clientId - Client ID to find
   * @returns {TcpConnection|null} - Found connection or null
   */
  findClientById(clientId) {
    for (const [_, connection] of this.tcpConnections.entries()) {
      if (connection.clientID === clientId) {
        return connection;
      }
    }
    return null;
  }
  
  /**
   * Generate a report via the HTTP API
   * @param {string} document - Document ID
   * @param {string} clientId - Client ID
   * @param {number} userId - User ID requesting the report
   * @returns {Object} - Report data or error
   */
  async generateHttpReport(document, clientId, userId = null) {
    try {
      // Start timer for metrics
      const timerEnd = metrics.reportGenerationDuration.startTimer();
      
      // Find the client connection
      const connection = this.findClientById(clientId);
      
      // Check if client exists in database
      const clientRecord = await Client.findOne({ where: { clientId } });
      
      if (!clientRecord || !clientRecord.isOnline) {
        metrics.reportGenerationCounter.inc({ status: 'client_offline' });
        timerEnd({ status: 'client_offline' });
        
        return {
          success: false,
          error: {
            code: HTTP_ERRORS.CLIENT_IS_OFFLINE,
            message: 'Client is offline'
          }
        };
      }
      
      if (!connection) {
        // Update client status in database
        await this.updateClientStatus(clientId, false);
        
        metrics.reportGenerationCounter.inc({ status: 'client_offline' });
        timerEnd({ status: 'client_offline' });
        
        return {
          success: false,
          error: {
            code: HTTP_ERRORS.CLIENT_IS_OFFLINE,
            message: 'Client is offline'
          }
        };
      }
      
      if (connection.busy) {
        metrics.reportGenerationCounter.inc({ status: 'client_busy' });
        timerEnd({ status: 'client_busy' });
        
        return {
          success: false,
          error: {
            code: HTTP_ERRORS.CLIENT_IS_BUSY,
            message: 'Client is busy'
          }
        };
      }
      
      // Create report record in database
      const report = await Report.create({
        clientId,
        documentId: document,
        requestedBy: userId,
        status: 'pending',
        title: `Report ${document} for ${clientId}`
      });
      
      // In a real implementation, this would send a request to the client
      // and wait for the response. For now, we'll simulate it with a dummy response.
      
      // Simulate successful processing
      connection.busy = true;
      
      // Update report status
      report.status = 'processing';
      await report.save();
      
      // Simulate processing time
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Generate mock data
      const reportData = {
        reportId: report.reportId,
        documentId: document,
        clientId,
        generatedAt: new Date().toISOString(),
        data: `Sample report data for document ${document}`
      };
      
      // Update report in database
      report.status = 'completed';
      report.completedAt = new Date();
      report.processingTime = 1000; // 1 second
      report.data = JSON.stringify(reportData);
      await report.save();
      
      // Release the client
      connection.busy = false;
      
      // Update metrics
      metrics.reportGenerationCounter.inc({ status: 'success' });
      timerEnd({ status: 'success' });
      
      return {
        success: true,
        reportId: report.reportId,
        data: reportData
      };
      
    } catch (error) {
      this.logger.error(`Error generating report: ${error.message}`);
      
      // Update metrics
      metrics.reportGenerationCounter.inc({ status: 'error' });
      
      return {
        success: false,
        error: {
          code: HTTP_ERRORS.CLIENT_NOT_RESPOND,
          message: 'Error generating report'
        }
      };
    }
  }
  
  /**
   * Get update file list
   * @returns {Array} - List of update files
   */
  getUpdateFileList() {
    try {
      const updatePath = this.updatePath;
      
      if (!fs.existsSync(updatePath)) {
        return [];
      }
      
      const files = fs.readdirSync(updatePath);
      
      return files.map(file => {
        const filePath = path.join(updatePath, file);
        const stats = fs.statSync(filePath);
        
        return {
          name: file,
          size: stats.size,
          modified: stats.mtime.toISOString(),
          url: `/api/updates/${file}`
        };
      });
      
    } catch (error) {
      this.logger.error(`Error getting update file list: ${error.message}`);
      return [];
    }
  }
  
  /**
   * Register event listener
   */
  on(event, handler) {
    this.events.on(event, handler);
  }
  
  /**
   * Stop the server
   */
  stop() {
    // Clear the cleanup timer
    if (this.idleTimer) {
      clearInterval(this.idleTimer);
    }
    
    // Close all TCP connections
    for (const [_, connection] of this.tcpConnections.entries()) {
      if (connection.socket && !connection.socket.destroyed) {
        connection.socket.destroy();
      }
    }
    
    this.tcpConnections.clear();
    this.httpConnections.clear();
    
    this.logger.info('Report server stopped');
  }
}

/**
 * Create a new report server
 */
function createReportServer(options) {
  return new ReportServer(options);
}

module.exports = {
  createReportServer
}; 
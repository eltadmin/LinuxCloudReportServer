/**
 * TcpConnection class - handles TCP connections
 */
const RemoteConnection = require('./RemoteConnection');
const { encryptAES, decryptAES, getCryptoKey } = require('../utils/encryption');
const { TCP_ERRORS, TCP_COMMANDS } = require('../utils/constants');
const axios = require('axios');
const EventEmitter = require('events');
const path = require('path');

class TcpConnection extends RemoteConnection {
  /**
   * Create a new TcpConnection instance
   * @param {Object} socket - Socket connection
   * @param {string} logPath - Path for log files
   */
  constructor(socket, logPath) {
    super(logPath);
    
    this.socket = socket;
    this.clientID = '';
    this.timeDiffSec = 0;
    this.serverKey = '';
    this.cryptoKey = '';
    this.clientHost = '';
    this.clientName = '';
    this.appType = '';
    this.appVersion = '';
    this.dbType = '';
    this.expireDate = null;
    this.busy = false;
    this.requestCount = 0;
    this.lastRequest = '';
    this.lastResponse = '';
    this.destroying = false;
    
    // Create event emitter for asynchronous response handling
    this.responseEmitter = new EventEmitter();
    
    // Setup socket event handlers
    this.setupSocketHandlers();
  }
  
  /**
   * Setup socket event handlers
   */
  setupSocketHandlers() {
    // Handle data received from client
    this.socket.on('data', (data) => {
      this.updateLastAction();
      
      try {
        const message = data.toString().trim();
        // Check if this is a command or response to previous request
        if (message.indexOf(' ') > 0) {
          // This is a command with parameters
          const parts = message.split(' ');
          const command = parts[0];
          const parameters = parts.slice(1).join(' ');
          
          this.handleCommand(command, parameters);
        } else {
          // This could be a simple response
          this.responseEmitter.emit('response', message);
        }
      } catch (error) {
        this.handleError(`Error processing data: ${error.message}`);
      }
    });
    
    // Handle connection errors
    this.socket.on('error', (error) => {
      this.handleError(`Socket error: ${error.message}`);
    });
    
    // Handle connection close
    this.socket.on('close', () => {
      this.doDisconnect();
    });
  }
  
  /**
   * Handle a command received from client
   * @param {string} command - Command name
   * @param {string} parameters - Command parameters
   */
  handleCommand(command, parameters) {
    this.lastRequest = `${command} ${parameters}`;
    this.requestCount++;
    
    // Log the command
    this.logToFile(`Command received: ${command}`);
    
    switch(command) {
      case TCP_COMMANDS.INIT:
        this.handleInitCommand(parameters);
        break;
      case TCP_COMMANDS.INFO:
        this.handleInfoCommand(parameters);
        break;
      case TCP_COMMANDS.PING:
        this.handlePingCommand(parameters);
        break;
      case TCP_COMMANDS.SRSP:
        this.handleSendReportCommand(parameters);
        break;
      case TCP_COMMANDS.GREQ:
        this.handleGetReportCommand(parameters);
        break;
      case TCP_COMMANDS.VERS:
        this.handleVersionCommand(parameters);
        break;
      case TCP_COMMANDS.DWNL:
        this.handleDownloadCommand(parameters);
        break;
      case TCP_COMMANDS.ERRL:
        this.handleErrorLogCommand(parameters);
        break;
      default:
        this.sendResponse(`ERROR ${TCP_ERRORS.COMMAND_UNKNOWN} Unknown command: ${command}`);
    }
  }
  
  /**
   * Handle INIT command
   * @param {string} parameters - Command parameters
   */
  handleInitCommand(parameters) {
    try {
      // Example parameters: DeviceType Serial Version
      const parts = parameters.split(' ');
      if (parts.length < 3) {
        throw new Error('Invalid INIT parameters');
      }
      
      const deviceType = parts[0];
      const serial = parts[1];
      const version = parts[2];
      
      // Store device info
      this.appType = deviceType;
      this.clientID = serial;
      this.appVersion = version;
      
      // Generate crypto key (simplified for this example)
      this.cryptoKey = getCryptoKey(0);
      
      // Send successful response with crypto key
      this.sendResponse(`OK ${this.cryptoKey}`);
      
    } catch (error) {
      this.handleError(`INIT command error: ${error.message}`);
      this.sendResponse(`ERROR ${TCP_ERRORS.INVALID_DATA_PACKET} ${error.message}`);
    }
  }
  
  /**
   * Handle INFO command
   * @param {string} parameters - Command parameters
   */
  handleInfoCommand(parameters) {
    try {
      // Parse client info from parameters
      // Format could be: ClientName|ClientHost|AppType|AppVersion|DbType
      const parts = parameters.split('|');
      if (parts.length >= 5) {
        this.clientName = parts[0];
        this.clientHost = parts[1];
        this.appType = parts[2];
        this.appVersion = parts[3];
        this.dbType = parts[4];
      }
      
      const serverInfo = {
        serverName: 'CloudReportServer',
        serverVersion: '1.0.0',
        serverTime: new Date().toISOString()
      };
      
      this.sendResponse(`OK ${JSON.stringify(serverInfo)}`);
      
    } catch (error) {
      this.handleError(`INFO command error: ${error.message}`);
      this.sendResponse(`ERROR ${TCP_ERRORS.INVALID_DATA_PACKET} ${error.message}`);
    }
  }
  
  /**
   * Handle PING command
   * @param {string} parameters - Command parameters
   */
  handlePingCommand(parameters) {
    // Simple ping response
    this.sendResponse('OK PONG');
  }
  
  /**
   * Handle send report command (placeholder implementation)
   * @param {string} parameters - Command parameters
   */
  handleSendReportCommand(parameters) {
    // This would normally process received report data
    this.sendResponse(`OK Report received`);
  }
  
  /**
   * Handle get report command (placeholder implementation)
   * @param {string} parameters - Command parameters
   */
  handleGetReportCommand(parameters) {
    // This would normally generate and return report data
    const reportData = {
      reportId: Date.now(),
      data: 'Sample report data'
    };
    
    this.sendResponse(`OK ${JSON.stringify(reportData)}`);
  }
  
  /**
   * Handle version command
   * @param {string} parameters - Command parameters
   */
  handleVersionCommand(parameters) {
    // Send server version info
    const versionInfo = {
      version: '1.0.0',
      buildDate: '2023-04-08'
    };
    
    this.sendResponse(`OK ${JSON.stringify(versionInfo)}`);
  }
  
  /**
   * Handle download command
   * @param {string} parameters - Command parameters
   */
  handleDownloadCommand(parameters) {
    try {
      // Parameters should contain the file to download
      const fileName = parameters.trim();
      
      // In a real implementation, we would check if the file exists and send it
      // For this example, we'll just acknowledge the request
      this.sendResponse(`OK Ready to send ${fileName}`);
      
    } catch (error) {
      this.handleError(`DWNL command error: ${error.message}`);
      this.sendResponse(`ERROR ${TCP_ERRORS.INVALID_DATA_PACKET} ${error.message}`);
    }
  }
  
  /**
   * Handle error log command
   * @param {string} parameters - Command parameters
   */
  handleErrorLogCommand(parameters) {
    try {
      // Log the error from the client
      this.logToFile(`Client error log: ${parameters}`);
      this.sendResponse('OK Error logged');
      
    } catch (error) {
      this.handleError(`ERRL command error: ${error.message}`);
      this.sendResponse(`ERROR ${TCP_ERRORS.INVALID_DATA_PACKET} ${error.message}`);
    }
  }
  
  /**
   * Send a response to the client
   * @param {string} response - Response text
   * @returns {boolean} - True if sent successfully
   */
  sendResponse(response) {
    this.lastResponse = response;
    
    try {
      if (!this.socket || this.socket.destroyed) {
        return false;
      }
      
      // In a real implementation, you might encrypt the response
      // For simplicity, sending in plain text here
      this.socket.write(response + '\r\n');
      return true;
      
    } catch (error) {
      this.handleError(`Failed to send response: ${error.message}`);
      return false;
    }
  }
  
  /**
   * Send a request to the client and wait for response
   * @param {string} request - Request text
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise<string>} - Response from client
   */
  async sendRequest(request, timeout = 5000) {
    return new Promise((resolve, reject) => {
      try {
        if (!this.socket || this.socket.destroyed) {
          reject(new Error('Socket is not connected'));
          return;
        }
        
        // Send the request
        this.socket.write(request + '\r\n');
        
        // Set up response listener
        const responseHandler = (response) => {
          cleanup();
          resolve(response);
        };
        
        // Set up timeout
        const timeoutId = setTimeout(() => {
          cleanup();
          reject(new Error('Request timed out'));
        }, timeout);
        
        // Cleanup function
        const cleanup = () => {
          clearTimeout(timeoutId);
          this.responseEmitter.removeListener('response', responseHandler);
        };
        
        // Register response handler
        this.responseEmitter.once('response', responseHandler);
        
      } catch (error) {
        reject(error);
      }
    });
  }
  
  /**
   * Handle errors
   * @param {string} message - Error message
   */
  handleError(message) {
    this.lastError = message;
    this.logToFile(`ERROR: ${message}`);
  }
  
  /**
   * Set time difference between client and server
   * @param {string} dateStr - Date string
   * @param {string} timeStr - Time string
   */
  setTimeDiff(dateStr, timeStr) {
    try {
      const dateTimeParts = `${dateStr} ${timeStr}`.split(/[\s:-]/);
      if (dateTimeParts.length >= 6) {
        const year = parseInt(dateTimeParts[0]);
        const month = parseInt(dateTimeParts[1]) - 1; // JS months are 0-based
        const day = parseInt(dateTimeParts[2]);
        const hour = parseInt(dateTimeParts[3]);
        const minute = parseInt(dateTimeParts[4]);
        const second = parseInt(dateTimeParts[5]);
        
        const clientTime = new Date(year, month, day, hour, minute, second);
        const serverTime = new Date();
        
        // Calculate difference in seconds
        this.timeDiffSec = Math.floor((serverTime - clientTime) / 1000);
      }
    } catch (error) {
      this.handleError(`Error setting time difference: ${error.message}`);
    }
  }
  
  /**
   * Initialize client ID using authentication server
   * @param {string} authServerUrl - URL of the authentication server
   * @returns {Promise<boolean>} - True if successful
   */
  async initClientId(authServerUrl) {
    try {
      if (!this.clientID) {
        throw new Error('Client ID not set');
      }
      
      // Create client info object
      const clientInfo = {
        id: this.clientID,
        name: this.clientName,
        host: this.clientHost,
        appType: this.appType,
        appVersion: this.appVersion,
        dbType: this.dbType,
        ipAddress: this.connectionInfo.remoteIP
      };
      
      // Call the authentication server
      const response = await axios.get(`${authServerUrl}/objectinfo`, {
        params: clientInfo
      });
      
      if (response.data && response.data.result === 0) {
        // Extract subscription info
        this.expireDate = new Date(response.data.expiredate);
        return true;
      } else {
        throw new Error(response.data?.message || 'Authentication failed');
      }
      
    } catch (error) {
      this.handleError(`Failed to initialize client ID: ${error.message}`);
      return false;
    }
  }
  
  /**
   * Get connection info as text
   * @returns {string} - Connection info text
   */
  connectionInfoAsText() {
    const baseInfo = super.connectionInfoAsText();
    return `${baseInfo}, ClientID: ${this.clientID}, AppType: ${this.appType}, Version: ${this.appVersion}`;
  }
}

module.exports = TcpConnection; 
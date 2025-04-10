/**
 * RemoteConnection class - base class for handling remote connections
 */
const { DROP_DEVICE_WITHOUT_ACTIVITY } = require('../utils/constants');
const path = require('path');
const fs = require('fs');

class RemoteConnection {
  /**
   * Create a new RemoteConnection instance
   * @param {string} logPath - Path for log files
   */
  constructor(logPath) {
    this.logPath = logPath;
    this.lastError = '';
    this.mustDisconnect = false;
    
    // Connection info
    this.connectionInfo = {
      remoteHost: '',
      remoteIP: '',
      remotePort: 0,
      localPort: 0,
      connectTime: new Date(),
      disconnectTime: null,
      lastAction: new Date()
    };
  }
  
  /**
   * Get the connected time in seconds
   * @returns {number} - Time in seconds
   */
  getConnectedTimeSec() {
    const now = new Date();
    return Math.floor((now - this.connectionInfo.connectTime) / 1000);
  }
  
  /**
   * Get the connected time in milliseconds
   * @returns {number} - Time in milliseconds
   */
  getConnectedTimeMSecs() {
    const now = new Date();
    return now - this.connectionInfo.connectTime;
  }
  
  /**
   * Get the idle time in seconds
   * @returns {number} - Time in seconds
   */
  getIdleTimeSec() {
    const now = new Date();
    return Math.floor((now - this.connectionInfo.lastAction) / 1000);
  }
  
  /**
   * Check if connection should be disconnected due to inactivity
   * @returns {boolean} - True if connection should be disconnected
   */
  shouldDisconnect() {
    return this.mustDisconnect || this.getIdleTimeSec() > DROP_DEVICE_WITHOUT_ACTIVITY;
  }
  
  /**
   * Handle connect event
   * @param {Object} socket - Socket connection object
   */
  doConnect(socket) {
    this.connectionInfo.remoteHost = socket.remoteAddress || '';
    this.connectionInfo.remoteIP = socket.remoteAddress || '';
    this.connectionInfo.remotePort = socket.remotePort || 0;
    this.connectionInfo.localPort = socket.localPort || 0;
    this.connectionInfo.connectTime = new Date();
    this.connectionInfo.lastAction = new Date();
  }
  
  /**
   * Handle disconnect event
   */
  doDisconnect() {
    this.connectionInfo.disconnectTime = new Date();
  }
  
  /**
   * Log message to a file
   * @param {string} message - Message to log
   */
  logToFile(message) {
    const logFileName = 'connection.log';
    const logFile = path.join(this.logPath, logFileName);
    const timestamp = new Date().toISOString().replace(/T/, ' ').replace(/\..+/, '');
    const logMessage = `${timestamp} ${message}\n`;
    
    fs.appendFile(logFile, logMessage, (err) => {
      if (err) {
        console.error(`Failed to write to log file: ${err.message}`);
      }
    });
  }
  
  /**
   * Update last action timestamp
   */
  updateLastAction() {
    this.connectionInfo.lastAction = new Date();
  }
  
  /**
   * Get connection info as text
   * @returns {string} - Connection info text
   */
  connectionInfoAsText() {
    const info = this.connectionInfo;
    return `RemoteHost: ${info.remoteHost}, IP: ${info.remoteIP}, Port: ${info.remotePort}, Connected: ${info.connectTime.toISOString()}`;
  }
}

module.exports = RemoteConnection; 
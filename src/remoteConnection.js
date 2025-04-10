/**
 * Remote Connection classes
 * Linux-compatible version of RemoteConnectionUnit.pas
 */

const fs = require('fs');
const path = require('path');
const moment = require('moment');
const CryptoJS = require('crypto-js');
const constants = require('./constants');
const restApi = require('./restApiInterface');

/**
 * Base class for remote connections
 */
class RemoteConnection {
  constructor(logPath) {
    this.logPath = logPath;
    this.mustDisconnect = false;
    this.lastError = '';
    
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
   * Get connected time in seconds
   */
  getConnectedTimeSec() {
    return Math.floor((new Date() - this.connectionInfo.connectTime) / 1000);
  }
  
  /**
   * Get connected time in milliseconds
   */
  getConnectedTimeMSecs() {
    return (new Date() - this.connectionInfo.connectTime);
  }
  
  /**
   * Get idle time in seconds
   */
  getIdleTimeSec() {
    return Math.floor((new Date() - this.connectionInfo.lastAction) / 1000);
  }
  
  /**
   * Handle connection from a client
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
   * Handle disconnection of a client
   */
  doDisconnect() {
    this.connectionInfo.disconnectTime = new Date();
  }
}

/**
 * HTTP Connection class
 */
class HttpConnection extends RemoteConnection {
  constructor(logPath) {
    super(logPath);
  }
  
  /**
   * Get connection info as string
   */
  connectionInfoAsText() {
    return `HTTP:[${this.connectionInfo.remoteIP}:${this.connectionInfo.remotePort}->${this.connectionInfo.localPort}]`;
  }
  
  /**
   * Handle HTTP GET request
   */
  doHttpGetRequest(req, res) {
    this.connectionInfo.lastAction = new Date();
    // Implementation here
  }
  
  /**
   * Handle HTTP chunk
   */
  doHttpChunk(req, res) {
    this.connectionInfo.lastAction = new Date();
    // Implementation here
  }
}

/**
 * TCP Connection class
 */
class TcpConnection extends RemoteConnection {
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
    this.bussy = false;
    this.requestCnt = 0;
    this.lastRequest = '';
    this.lastResponse = '';
    this.waitResponse = null;
    this.destroying = false;
  }
  
  /**
   * Get connection info as string
   */
  connectionInfoAsText() {
    return `TCP:[${this.connectionInfo.remoteIP}:${this.connectionInfo.remotePort}->${this.connectionInfo.localPort}]`;
  }
  
  /**
   * Handle TCP request
   */
  doTcpRequest(command, data) {
    // Implementation here
  }
  
  /**
   * Set time difference
   */
  setTimeDiff(sDate, sTime) {
    try {
      // Parse date from client
      const clientDate = moment(`${sDate} ${sTime}`, 'YYYY-MM-DD HH:mm:ss');
      // Get server time
      const serverDate = moment();
      // Calculate difference in seconds
      this.timeDiffSec = serverDate.diff(clientDate, 'seconds');
    } catch (error) {
      console.error('Error setting time difference:', error);
      this.timeDiffSec = 0;
    }
  }
  
  /**
   * Post error to file
   */
  postErrorToFile(msg) {
    try {
      const timestamp = moment().format('YYYY-MM-DD HH:mm:ss');
      const logEntry = `[${timestamp}] [${this.clientID}] ${msg}\n`;
      
      fs.appendFileSync(path.join(this.logPath, 'errors.log'), logEntry);
    } catch (error) {
      console.error('Failed to write to error log:', error);
    }
  }
  
  /**
   * Send request to client
   */
  sendRequest(data, resetEvent = true) {
    if (this.socket && !this.socket.destroyed) {
      try {
        this.socket.write(data);
        return true;
      } catch (error) {
        console.error('Error sending data:', error);
        return false;
      }
    }
    return false;
  }
  
  /**
   * Get response from client
   */
  getResponse(rCounter, data) {
    // Implementation here
    return true;
  }
  
  /**
   * Initialize crypto key
   */
  initCryptoKey(data, len) {
    try {
      // Implementation based on original code
      // This is a simplified version
      this.cryptoKey = constants.C_CryptoDictionary[0]; // Use first key for now
      return true;
    } catch (error) {
      console.error('Error initializing crypto key:', error);
      return false;
    }
  }
  
  /**
   * Initialize client ID
   */
  async initClientId(data, restUrl) {
    try {
      // Parse the data to get client information
      const clientData = JSON.parse(data);
      
      // Call REST API to validate client
      const result = await restApi.checkClient(restUrl, clientData);
      
      if (result && result.objectid) {
        this.clientID = result.objectid;
        this.clientName = result.objectname || '';
        this.appType = result.apptype || '';
        this.appVersion = result.appver || '';
        this.dbType = result.appdbtype || '';
        this.expireDate = result.expiredate ? new Date(result.expiredate) : null;
        
        // Update data with the response
        data = JSON.stringify(result);
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('Error initializing client ID:', error);
      return false;
    }
  }
  
  /**
   * Decrypt data using the crypto key
   */
  decryptData(source, dest) {
    try {
      // Implementation based on original code
      // This is a simplified version using CryptoJS
      const bytes = CryptoJS.AES.decrypt(
        source,
        this.cryptoKey,
        { mode: CryptoJS.mode.CFB, padding: CryptoJS.pad.NoPadding }
      );
      
      dest = bytes.toString(CryptoJS.enc.Utf8);
      return true;
    } catch (error) {
      console.error('Error decrypting data:', error);
      return false;
    }
  }
  
  /**
   * Encrypt data using the crypto key
   */
  encryptData(ioData) {
    try {
      // Implementation based on original code
      // This is a simplified version using CryptoJS
      const encrypted = CryptoJS.AES.encrypt(
        ioData,
        this.cryptoKey,
        { mode: CryptoJS.mode.CFB, padding: CryptoJS.pad.NoPadding }
      );
      
      ioData = encrypted.toString();
      return true;
    } catch (error) {
      console.error('Error encrypting data:', error);
      return false;
    }
  }
}

module.exports = {
  RemoteConnection,
  HttpConnection,
  TcpConnection
}; 
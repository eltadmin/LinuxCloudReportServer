/**
 * HttpConnection class - handles HTTP connections
 */
const RemoteConnection = require('./RemoteConnection');

class HttpConnection extends RemoteConnection {
  /**
   * Create a new HttpConnection instance
   * @param {Object} req - Express request object
   * @param {Object} res - Express response object
   * @param {string} logPath - Path for log files
   */
  constructor(req, res, logPath) {
    super(logPath);
    
    this.request = req;
    this.response = res;
    
    // Initialize connection info from request
    this.connectionInfo.remoteHost = req.hostname || '';
    this.connectionInfo.remoteIP = req.ip || req.socket.remoteAddress || '';
    this.connectionInfo.remotePort = req.socket.remotePort || 0;
    this.connectionInfo.localPort = req.socket.localPort || 0;
  }
  
  /**
   * Handle HTTP GET request
   * @param {Object} requestData - Additional request data
   * @returns {Object} - Response data
   */
  handleGetRequest(requestData = {}) {
    this.updateLastAction();
    
    // Log the request
    this.logToFile(`HTTP GET request from ${this.connectionInfo.remoteIP}`);
    
    // Check content type if needed
    const contentType = this.request.headers['content-type'] || '';
    
    return {
      success: true,
      message: 'Request processed',
      timestamp: new Date().toISOString()
    };
  }
  
  /**
   * Handle HTTP POST request
   * @param {Object} requestData - Request data
   * @returns {Object} - Response data
   */
  handlePostRequest(requestData = {}) {
    this.updateLastAction();
    
    // Log the request
    this.logToFile(`HTTP POST request from ${this.connectionInfo.remoteIP}`);
    
    return {
      success: true,
      message: 'Request processed',
      timestamp: new Date().toISOString()
    };
  }
  
  /**
   * Send a JSON response
   * @param {Object} data - Response data
   * @param {number} statusCode - HTTP status code
   */
  sendJsonResponse(data, statusCode = 200) {
    this.response.status(statusCode).json(data);
  }
  
  /**
   * Send an error response
   * @param {string} message - Error message
   * @param {number} statusCode - HTTP status code
   * @param {number} errorCode - Custom error code
   */
  sendErrorResponse(message, statusCode = 400, errorCode = 0) {
    this.lastError = message;
    this.logToFile(`ERROR: ${message}`);
    
    this.response.status(statusCode).json({
      success: false,
      error: {
        code: errorCode,
        message: message
      }
    });
  }
  
  /**
   * Generate a report document (placeholder implementation)
   * @param {string} documentId - Document ID
   * @param {string} clientId - Client ID
   * @returns {Object} - Report data
   */
  generateReport(documentId, clientId) {
    // This is a placeholder implementation
    // In a real application, this would generate actual report data
    return {
      documentId,
      clientId,
      content: `Report content for document ${documentId}`,
      timestamp: new Date().toISOString()
    };
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

module.exports = HttpConnection; 
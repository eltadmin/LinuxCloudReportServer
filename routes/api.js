/**
 * API Routes for Cloud Report Server
 */
const express = require('express');
const { HTTP_ERRORS } = require('../utils/constants');

/**
 * Create API router
 * @param {Object} reportServer - Report server instance
 * @returns {express.Router} - Express router
 */
module.exports = function(reportServer) {
  const router = express.Router();
  
  /**
   * Health check endpoint
   */
  router.get('/health', (req, res) => {
    res.status(200).json({
      status: 'ok',
      serverName: reportServer.serverName,
      connections: {
        tcp: reportServer.tcpConnections.size,
        http: reportServer.httpConnections.size
      }
    });
  });
  
  /**
   * Generate report endpoint
   */
  router.get('/report', (req, res) => {
    const connection = reportServer.handleHttpRequest(req, res);
    if (!connection) return;
    
    const { document, clientId } = req.query;
    
    // Validate parameters
    if (!clientId) {
      return connection.sendErrorResponse(
        'Missing clientId parameter', 
        400, 
        HTTP_ERRORS.MISSING_CLIENT_ID
      );
    }
    
    if (!document) {
      return connection.sendErrorResponse(
        'Missing document parameter', 
        400, 
        HTTP_ERRORS.DOCUMENT_UNKNOWN
      );
    }
    
    // Generate the report
    const result = reportServer.generateHttpReport(document, clientId);
    
    if (result.success) {
      connection.sendJsonResponse(result);
    } else {
      connection.sendErrorResponse(
        result.error.message,
        400,
        result.error.code
      );
    }
  });
  
  /**
   * Login endpoint
   */
  router.post('/login', (req, res) => {
    const connection = reportServer.handleHttpRequest(req, res);
    if (!connection) return;
    
    const { username, password } = req.body;
    
    // Validate parameters
    if (!username || !password) {
      return connection.sendErrorResponse(
        'Missing username or password', 
        400, 
        HTTP_ERRORS.MISSING_LOGIN_INFO
      );
    }
    
    // Check login
    if (reportServer.checkLogin(username, password)) {
      connection.sendJsonResponse({
        success: true,
        token: 'sample-token-' + Date.now() // In a real app, generate a proper JWT token
      });
    } else {
      connection.sendErrorResponse(
        'Invalid username or password', 
        401, 
        HTTP_ERRORS.LOGIN_INCORRECT
      );
    }
  });
  
  /**
   * List clients endpoint
   */
  router.get('/clients', (req, res) => {
    const connection = reportServer.handleHttpRequest(req, res);
    if (!connection) return;
    
    // Get all connected clients
    const clients = [];
    reportServer.tcpConnections.forEach(conn => {
      if (conn.clientID) {
        clients.push({
          id: conn.clientID,
          name: conn.clientName,
          host: conn.clientHost,
          appType: conn.appType,
          appVersion: conn.appVersion,
          connectedTime: conn.getConnectedTimeSec(),
          idle: conn.getIdleTimeSec()
        });
      }
    });
    
    connection.sendJsonResponse({
      count: clients.length,
      clients
    });
  });
  
  /**
   * Get updates endpoint
   */
  router.get('/updates', (req, res) => {
    const connection = reportServer.handleHttpRequest(req, res);
    if (!connection) return;
    
    const updates = reportServer.getUpdateFileList();
    
    connection.sendJsonResponse({
      count: updates.length,
      updates
    });
  });
  
  /**
   * Download update file endpoint
   */
  router.get('/updates/:filename', (req, res) => {
    const { filename } = req.params;
    const filePath = require('path').join(reportServer.updatePath, filename);
    
    // Check if file exists
    if (!require('fs').existsSync(filePath)) {
      return res.status(404).json({
        success: false,
        error: {
          code: HTTP_ERRORS.DOCUMENT_UNKNOWN,
          message: 'File not found'
        }
      });
    }
    
    // Send the file
    res.sendFile(filePath);
  });
  
  /**
   * Server info endpoint
   */
  router.get('/info', (req, res) => {
    const connection = reportServer.handleHttpRequest(req, res);
    if (!connection) return;
    
    connection.sendJsonResponse({
      serverName: reportServer.serverName,
      version: '1.0.0',
      serverTime: new Date().toISOString(),
      connections: {
        tcp: reportServer.tcpConnections.size,
        http: reportServer.httpConnections.size
      }
    });
  });
  
  return router;
}; 
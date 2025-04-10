/**
 * DReport integration routes for Cloud Report Server
 */
const express = require('express');
const mysql = require('mysql2/promise');
const { HTTP_ERRORS } = require('../utils/constants');

/**
 * Create DReport integration router
 * @param {Object} reportServer - Report server instance
 * @returns {express.Router} - Express router
 */
module.exports = function(reportServer) {
  const router = express.Router();

  // Connection pool for MySQL (dreport database)
  const mysqlPool = mysql.createPool({
    host: process.env.DREPORT_DB_HOST || 'mysql',
    user: process.env.DREPORT_DB_USER || 'dreports',
    password: process.env.DREPORT_DB_PASSWORD || 'ftUk58_HoRs3sAzz8jk',
    database: process.env.DREPORT_DB_NAME || 'dreports',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  });

  /**
   * Synchronize connected clients to dreport database
   */
  router.get('/sync-clients', async (req, res) => {
    try {
      const connection = reportServer.handleHttpRequest(req, res);
      if (!connection) return;

      // Get all connected clients
      const clients = [];
      reportServer.tcpConnections.forEach(conn => {
        if (conn.clientID) {
          clients.push({
            objectid: conn.clientID,
            objectname: conn.clientName || '',
            hostname: conn.clientHost || '',
            apptype: conn.appType || '',
            appver: conn.appVersion || '',
            appdbtype: conn.dbType || '',
            appip: conn.clientAddress || '',
            customername: conn.customerName || '',
            eik: conn.customerEik || '',
            address: conn.customerAddress || ''
          });
        }
      });

      // Update clients in dreport database
      const conn = await mysqlPool.getConnection();
      
      try {
        for (const client of clients) {
          // Check if client exists
          const [rows] = await conn.execute(
            'SELECT * FROM t_subscription WHERE s_objectid = ?', 
            [client.objectid]
          );

          if (rows.length === 0) {
            // Insert new client
            await conn.execute(
              'INSERT INTO t_subscription (s_objectid, s_objectname, s_hostname, s_apptype, s_appver, s_appdbtype, s_appip, s_customername, s_eik, s_address, s_active, s_createdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
              [client.objectid, client.objectname, client.hostname, client.apptype, client.appver, client.appdbtype, client.appip, client.customername, client.eik, client.address]
            );
          } else {
            // Update existing client
            await conn.execute(
              'UPDATE t_subscription SET s_objectname = ?, s_hostname = ?, s_apptype = ?, s_appver = ?, s_appdbtype = ?, s_appip = ?, s_customername = ?, s_eik = ?, s_address = ?, s_lastupdatedate = NOW() WHERE s_objectid = ?',
              [client.objectname, client.hostname, client.apptype, client.appver, client.appdbtype, client.appip, client.customername, client.eik, client.address, client.objectid]
            );
          }
        }
        
        conn.release();
        
        connection.sendJsonResponse({
          success: true,
          message: 'Clients synchronized successfully',
          count: clients.length
        });
      } catch (error) {
        conn.release();
        throw error;
      }
    } catch (error) {
      console.error('Error syncing clients:', error);
      res.status(500).json({
        success: false,
        error: {
          code: HTTP_ERRORS.INTERNAL_ERROR,
          message: 'Internal server error'
        }
      });
    }
  });

  /**
   * Get server settings for dreport
   */
  router.get('/settings', async (req, res) => {
    try {
      const connection = reportServer.handleHttpRequest(req, res);
      if (!connection) return;

      // Get settings
      const settings = {
        rpt_server_host: process.env.RPT_SERVER_HOST || 'localhost',
        rpt_server_port: process.env.TCP_PORT || reportServer.config.tcp?.port || 2909,
        rpt_server_user: process.env.RPT_SERVER_USER || 'admin',
        rpt_server_pswd: process.env.RPT_SERVER_PSWD || 'admin',
        log_level: process.env.LOG_LEVEL || '2'
      };

      // Update settings in dreport database
      const conn = await mysqlPool.getConnection();
      
      try {
        for (const [key, value] of Object.entries(settings)) {
          // Check if setting exists
          const [rows] = await conn.execute(
            'SELECT * FROM t_settings WHERE s_name = ?', 
            [key]
          );

          if (rows.length === 0) {
            // Insert new setting
            await conn.execute(
              'INSERT INTO t_settings (s_name, s_value) VALUES (?, ?)',
              [key, value]
            );
          } else {
            // Update existing setting
            await conn.execute(
              'UPDATE t_settings SET s_value = ? WHERE s_name = ?',
              [value, key]
            );
          }
        }
        
        conn.release();
        
        connection.sendJsonResponse({
          success: true,
          message: 'Settings synchronized successfully',
          settings
        });
      } catch (error) {
        conn.release();
        throw error;
      }
    } catch (error) {
      console.error('Error syncing settings:', error);
      res.status(500).json({
        success: false,
        error: {
          code: HTTP_ERRORS.INTERNAL_ERROR,
          message: 'Internal server error'
        }
      });
    }
  });

  return router;
}; 
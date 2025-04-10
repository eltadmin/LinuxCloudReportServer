/**
 * Database Connection Module
 * For integration with the MySQL database used by the web interface
 */

const mysql = require('mysql2/promise');
const fs = require('fs-extra');
const path = require('path');

// Database connection pool
let pool = null;

// Default database configuration
const dbConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USER || 'dreports',
  password: process.env.DB_PASSWORD || 'ftUk58_HoRs3sAzz8jk',
  database: process.env.DB_NAME || 'dreports',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

/**
 * Initialize database connection
 * @param {Object} config Optional custom configuration
 * @returns {Promise<Object>} MySQL connection pool
 */
async function initializeDatabase(config = null) {
  try {
    const finalConfig = config || dbConfig;
    
    console.log(`Connecting to database at ${finalConfig.host}`);
    
    // Create connection pool
    pool = mysql.createPool(finalConfig);
    
    // Test connection
    const connection = await pool.getConnection();
    console.log('Database connection established successfully');
    connection.release();
    
    return pool;
  } catch (error) {
    console.error('Failed to initialize database connection:', error);
    throw error;
  }
}

/**
 * Get database connection pool
 * @returns {Object} MySQL connection pool
 */
function getPool() {
  if (!pool) {
    throw new Error('Database not initialized. Call initializeDatabase() first.');
  }
  return pool;
}

/**
 * Execute a query
 * @param {string} sql SQL query
 * @param {Array} params Parameters for the query
 * @returns {Promise<Array>} Query results
 */
async function query(sql, params = []) {
  try {
    const [rows] = await getPool().execute(sql, params);
    return rows;
  } catch (error) {
    console.error('Error executing query:', error);
    throw error;
  }
}

/**
 * Log an event to the database
 * @param {number} opertype Operation type (see constants)
 * @param {string} operid Related ID
 * @param {string} operdecription Description
 * @returns {Promise<boolean>} Success status
 */
async function logEvent(opertype, operid, operdecription) {
  try {
    // First check log level setting from database
    const settingsRows = await query('SELECT * FROM t_settings WHERE s_name = ?', ['log_level']);
    const logLevel = settingsRows.length > 0 ? settingsRows[0].s_value : '0';
    
    // Determine if we should save this log based on log level
    let saveLog = false;
    
    switch (logLevel) {
      case '1': // log errors and REST
        if ([4, 5].includes(opertype)) {
          saveLog = true;
        }
        break;
      case '2': // log errors, REST, add/delete objects
        if ([2, 3, 4, 5].includes(opertype)) {
          saveLog = true;
        }
        break;
      case '3': // log all operations
        if ([0, 1, 2, 3, 4, 5].includes(opertype)) {
          saveLog = true;
        }
        break;
      default:
        saveLog = false;
    }
    
    if (saveLog) {
      await query(
        'INSERT INTO t_statistics(s_opertype, s_operid, s_description) VALUES (?, ?, ?)',
        [opertype, operid, operdecription]
      );
      return true;
    }
    
    return false;
  } catch (error) {
    console.error('Error logging event:', error);
    return false;
  }
}

/**
 * Get client information by ID
 * @param {string} clientId Client ID
 * @returns {Promise<Object>} Client information
 */
async function getClientInfo(clientId) {
  try {
    const rows = await query(
      'SELECT * FROM t_subscriptions WHERE s_objectid = ?',
      [clientId]
    );
    
    return rows.length > 0 ? rows[0] : null;
  } catch (error) {
    console.error('Error getting client info:', error);
    throw error;
  }
}

/**
 * Check device authentication
 * @param {string} deviceId Device ID
 * @param {string} objectId Object ID
 * @param {string} password Device password
 * @returns {Promise<Object>} Authentication result
 */
async function authenticateDevice(deviceId, objectId, password) {
  try {
    const rows = await query(
      'SELECT * FROM t_devices WHERE d_deviceid = ? AND d_objectid = ?',
      [deviceId, objectId]
    );
    
    if (rows.length === 0) {
      return { success: false, message: 'Device not found' };
    }
    
    const device = rows[0];
    if (device.d_objectpswd === password) {
      return { 
        success: true, 
        device: {
          deviceId: device.d_deviceid,
          objectId: device.d_objectid,
          timeOffset: device.d_timeoffset
        }
      };
    }
    
    return { success: false, message: 'Invalid password' };
  } catch (error) {
    console.error('Error authenticating device:', error);
    throw error;
  }
}

/**
 * Get TCP error message
 * @param {number} resultCode Error code
 * @param {Object} lang Language object
 * @returns {string} Error message
 */
function getTcpErrorMessage(resultCode, lang) {
  const errorCodes = [100, 102, 103, 200, 201, 202, 203, 204, 205, 
                    1000, 1001, 1002, 1003, 1004, 1005, 1006, 1007, 
                    1008, 1009, 1010, 1011, 1020];
                    
  if (errorCodes.includes(resultCode)) {
    return lang[resultCode.toString()];
  }
  
  return lang["C_HttpErr_NotDefined"];
}

module.exports = {
  initializeDatabase,
  getPool,
  query,
  logEvent,
  getClientInfo,
  authenticateDevice,
  getTcpErrorMessage
}; 
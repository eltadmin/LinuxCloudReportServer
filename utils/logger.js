/**
 * Logger module for Cloud Report Server
 */
const winston = require('winston');
const path = require('path');
const fs = require('fs');

/**
 * Initialize the logger
 * @param {string} logPath - Path where log files will be stored
 * @returns {winston.Logger} - Configured winston logger
 */
function initLogger(logPath) {
  // Create log directory if it doesn't exist
  if (!fs.existsSync(logPath)) {
    fs.mkdirSync(logPath, { recursive: true });
  }

  // Configure logger format
  const logFormat = winston.format.combine(
    winston.format.timestamp({
      format: 'YYYY-MM-DD HH:mm:ss.SSS'
    }),
    winston.format.printf(info => `${info.timestamp} [${info.level.toUpperCase()}]: ${info.message}`)
  );

  // Create the logger
  const logger = winston.createLogger({
    level: process.env.LOG_LEVEL || 'info',
    format: logFormat,
    transports: [
      // Console transport
      new winston.transports.Console({
        format: winston.format.combine(
          winston.format.colorize(),
          logFormat
        )
      }),
      // File transport for all logs
      new winston.transports.File({ 
        filename: path.join(logPath, 'cloud-report-server.log'),
        maxsize: 5242880, // 5MB
        maxFiles: 5,
        tailable: true
      }),
      // File transport for error logs
      new winston.transports.File({ 
        filename: path.join(logPath, 'error.log'),
        level: 'error',
        maxsize: 5242880, // 5MB
        maxFiles: 5
      })
    ]
  });

  // Add functionality to log to specific files
  logger.logToFile = (filename, message) => {
    const logFile = path.join(logPath, filename);
    const timestamp = new Date().toISOString().replace(/T/, ' ').replace(/\..+/, '');
    const logMessage = `${timestamp} ${message}\n`;
    
    fs.appendFile(logFile, logMessage, (err) => {
      if (err) {
        logger.error(`Failed to write to log file ${filename}: ${err.message}`);
      }
    });
  };

  return logger;
}

module.exports = {
  initLogger
}; 
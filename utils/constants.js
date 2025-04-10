/**
 * Constants for Cloud Report Server
 */

// Server constants
const LOG_FILE_NAME = 'CloudReportLog.txt';
const DROP_DEVICE_WITHOUT_SERIAL_TIME_SEC = 60;  // Time in seconds to drop a connection without a serial number
const DROP_DEVICE_WITHOUT_ACTIVITY = 120;        // Time in seconds to drop an inactive connection

// Error codes for HTTP responses
const HTTP_ERRORS = {
  MISSING_CLIENT_ID: 100,
  MISSING_LOGIN_INFO: 102,
  LOGIN_INCORRECT: 103,
  CLIENT_IS_OFFLINE: 200,
  CLIENT_IS_BUSY: 201,
  CLIENT_DUPLICATE: 202,
  CLIENT_NOT_RESPOND: 203,
  CLIENT_FAIL_SEND: 204,
  DOCUMENT_UNKNOWN: 205
};

// Error codes for TCP responses
const TCP_ERRORS = {
  OK: 200,
  INVALID_CRYPTO_KEY: 501,
  INVALID_DATA_PACKET: 502,
  COMMAND_UNKNOWN: 503,
  FAIL_DECODE_DATA: 504,
  FAIL_ENCODE_DATA: 505,
  FAIL_INIT_CLIENT_ID: 510,
  DUPLICATE_CLIENT_ID: 511,
  RECEIVE_REPORT_ERROR: 520,
  CHECK_UPDATE_ERROR: 530
};

// Crypto dictionary array for encryption - similar to the original implementation
const CRYPTO_DICTIONARY = [
  '123hk12h8dcal',
  'FT676Ugug6sFa',
  'a6xbBa7A8a9la',
  'qMnxbtyTFvcqi',
  'cx7812vcxFRCC',
  'bab7u682ftysv',
  'YGbsux&Ygsyxg',
  'MSN><hu8asG&&',
  '23yY88syHXvvs',
  '987sX&sysy891'
];

// TCP commands 
const TCP_COMMANDS = {
  INIT: 'INIT', // Initialization of connection
  INFO: 'INFO', // Getting server/client info
  PING: 'PING', // Connection check
  SRSP: 'SRSP', // Send report server response
  GREQ: 'GREQ', // Get report request
  VERS: 'VERS', // Get version information
  DWNL: 'DWNL', // Download files
  ERRL: 'ERRL'  // Error logs
};

// Content types
const CONTENT_TYPES = {
  TEXT_HTML: 'text/html',
  TEXT_XML: 'text/xml',
  APPLICATION_JSON: 'application/json',
  TEXT_PLAIN: 'text/plain'
};

module.exports = {
  LOG_FILE_NAME,
  DROP_DEVICE_WITHOUT_SERIAL_TIME_SEC,
  DROP_DEVICE_WITHOUT_ACTIVITY,
  HTTP_ERRORS,
  TCP_ERRORS,
  CRYPTO_DICTIONARY,
  TCP_COMMANDS,
  CONTENT_TYPES
}; 
/**
 * Constants for EBO Cloud Report Server
 * Linux-compatible version
 */

// Logging
const C_FileName_TraceLog = 'TraceLog_Server.txt';

// Connection timeouts
const C_DropDeviceWoSerialTimeSec = 60;  // Timeout for connections without serial
const C_DropDeviceWoActivity = 120;      // Timeout for connections without activity

// HTTP error codes
const C_HttpErr_MissingClientID = 100;
const C_HttpErr_MissingLoginInfo = 102;
const C_HttpErr_LoginIncorrect = 103;
const C_HttpErr_ClientIsOffline = 200;
const C_HttpErr_ClientIsByssy = 201;
const C_HttpErr_ClientDuplicate = 202;
const C_HttpErr_ClientNotRespond = 203;
const C_HttpErr_ClientFailSend = 204;
const C_HttpErr_DocumentUnknown = 205;

// TCP error codes
const C_TcpErr_OK = 200;
const C_TcpErr_InvalidCryptoKey = 501;
const C_TcpErr_InvalidDataPacket = 502;
const C_TcpErr_CommandUnknown = 503;
const C_TcpErr_FailDecodeData = 504;
const C_TcpErr_FailEncodeData = 505;
const C_TcpErr_FailInitClientId = 510;
const C_TcpErr_DuplicateClientId = 511;
const C_TcpErr_ReceiveReportError = 520;
const C_TcpErr_CheckUpdateError = 530;

// Crypto dictionary
const C_CryptoDictionary = [
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

class HandledException extends Error {
  constructor(errorCode, message, sysEvent = '') {
    super(message);
    this.name = 'HandledException';
    this.errorCode = errorCode;
    this.systemMessage = sysEvent;
  }
}

/**
 * Check registration key using encryption
 * @param {string} serial Serial number
 * @param {string} key Registration key
 * @returns {boolean} Valid key
 */
function checkRegistrationKey(serial, key) {
  const CryptoJS = require('crypto-js');
  
  try {
    // This is a simplified version of the Delphi Rijndael implementation
    // For a complete implementation, a more specialized library might be needed
    const keyBytes = CryptoJS.enc.Base64.parse(key);
    const decrypted = CryptoJS.AES.decrypt(
      { ciphertext: keyBytes },
      CryptoJS.enc.Utf8.parse(serial),
      { mode: CryptoJS.mode.CFB, padding: CryptoJS.pad.NoPadding }
    );
    
    const decryptedText = decrypted.toString(CryptoJS.enc.Utf8);
    return decryptedText.toLowerCase() === 'elcloudrepsrv'.toLowerCase();
  } catch (error) {
    console.error('Error checking registration key:', error);
    return false;
  }
}

module.exports = {
  C_FileName_TraceLog,
  C_DropDeviceWoSerialTimeSec,
  C_DropDeviceWoActivity,
  
  C_HttpErr_MissingClientID,
  C_HttpErr_MissingLoginInfo,
  C_HttpErr_LoginIncorrect,
  C_HttpErr_ClientIsOffline,
  C_HttpErr_ClientIsByssy,
  C_HttpErr_ClientDuplicate,
  C_HttpErr_ClientNotRespond,
  C_HttpErr_ClientFailSend,
  C_HttpErr_DocumentUnknown,
  
  C_TcpErr_OK,
  C_TcpErr_InvalidCryptoKey,
  C_TcpErr_InvalidDataPacket,
  C_TcpErr_CommandUnknown,
  C_TcpErr_FailDecodeData,
  C_TcpErr_FailEncodeData,
  C_TcpErr_FailInitClientId,
  C_TcpErr_DuplicateClientId,
  C_TcpErr_ReceiveReportError,
  C_TcpErr_CheckUpdateError,
  
  C_CryptoDictionary,
  
  HandledException,
  checkRegistrationKey
}; 
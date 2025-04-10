/**
 * Encryption utilities for Cloud Report Server
 */
const crypto = require('crypto');
const { CRYPTO_DICTIONARY } = require('./constants');

/**
 * Generate a crypto key based on index
 * @param {number} index - Index for the crypto dictionary
 * @returns {string} - Crypto key
 */
function getCryptoKey(index) {
  // Ensure index is within bounds of the dictionary
  const safeIndex = Math.abs(index) % CRYPTO_DICTIONARY.length;
  return CRYPTO_DICTIONARY[safeIndex];
}

/**
 * Generate a server key based on client serial
 * @param {string} serial - Client serial number
 * @returns {string} - Generated server key
 */
function generateServerKey(serial) {
  const plainText = 'ElCloudRepSrv';
  return encryptAES(plainText, serial);
}

/**
 * Check if a registration key is valid
 * @param {string} serial - Client serial number
 * @param {string} key - Key to verify
 * @returns {boolean} - True if key is valid
 */
function checkRegistrationKey(serial, key) {
  try {
    const decrypted = decryptAES(key, serial);
    return decrypted === 'ElCloudRepSrv';
  } catch (error) {
    return false;
  }
}

/**
 * Encrypt data using AES algorithm
 * @param {string} data - Data to encrypt
 * @param {string} key - Encryption key
 * @returns {string} - Base64 encoded encrypted data
 */
function encryptAES(data, key) {
  // Create a key buffer of appropriate length for AES-256
  const keyBuffer = Buffer.alloc(32);
  Buffer.from(key).copy(keyBuffer);
  
  // Create an initialization vector
  const iv = crypto.randomBytes(16);
  
  // Create cipher
  const cipher = crypto.createCipheriv('aes-256-cbc', keyBuffer, iv);
  
  // Encrypt the data
  let encrypted = cipher.update(data, 'utf8', 'base64');
  encrypted += cipher.final('base64');
  
  // Prepend IV to the encrypted data (so we can decrypt later)
  return iv.toString('base64') + ':' + encrypted;
}

/**
 * Decrypt data using AES algorithm
 * @param {string} encryptedData - Base64 encoded encrypted data with IV
 * @param {string} key - Decryption key
 * @returns {string} - Decrypted data
 */
function decryptAES(encryptedData, key) {
  // Create a key buffer of appropriate length for AES-256
  const keyBuffer = Buffer.alloc(32);
  Buffer.from(key).copy(keyBuffer);
  
  // Split IV and encrypted data
  const parts = encryptedData.split(':');
  if (parts.length !== 2) {
    throw new Error('Invalid encrypted data format');
  }
  
  const iv = Buffer.from(parts[0], 'base64');
  const encrypted = parts[1];
  
  // Create decipher
  const decipher = crypto.createDecipheriv('aes-256-cbc', keyBuffer, iv);
  
  // Decrypt the data
  let decrypted = decipher.update(encrypted, 'base64', 'utf8');
  decrypted += decipher.final('utf8');
  
  return decrypted;
}

/**
 * Convert data to Base64
 * @param {string} data - Data to encode
 * @returns {string} - Base64 encoded data
 */
function toBase64(data) {
  return Buffer.from(data).toString('base64');
}

/**
 * Decode Base64 data
 * @param {string} base64Data - Base64 encoded data
 * @returns {string} - Decoded data
 */
function fromBase64(base64Data) {
  return Buffer.from(base64Data, 'base64').toString('utf8');
}

module.exports = {
  getCryptoKey,
  generateServerKey,
  checkRegistrationKey,
  encryptAES,
  decryptAES,
  toBase64,
  fromBase64
}; 
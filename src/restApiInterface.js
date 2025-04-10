/**
 * REST API Interface
 * Linux-compatible version of RestApiInterface.pas
 */

const http = require('http');
const https = require('https');
const url = require('url');
const querystring = require('querystring');

/**
 * Check client with REST API
 * @param {string} baseUrl REST API URL
 * @param {object} clientData Client data (objectid, objectname, etc)
 * @returns {Promise<object>} Response from REST API
 */
async function checkClient(baseUrl, clientData) {
  return new Promise((resolve, reject) => {
    try {
      // Parse the base URL
      const parsedUrl = new URL(baseUrl);
      
      // Prepare the query parameters
      const params = querystring.stringify({
        objectid: clientData.objectid || '-1',
        objectname: clientData.objectname || '',
        customername: clientData.customername || '',
        eik: clientData.eik || '',
        address: clientData.address || '',
        hostname: clientData.hostname || '',
        appip: clientData.appip || '',
        apptype: clientData.apptype || '',
        appver: clientData.appver || '',
        appdbtype: clientData.appdbtype || '',
        comment: clientData.comment || ''
      });
      
      // Build the request path
      const requestPath = `${parsedUrl.pathname.replace(/\/+$/, '')}/objectinfo?${params}`;
      
      // Choose protocol based on URL
      const protocol = parsedUrl.protocol === 'https:' ? https : http;
      
      // Prepare request options
      const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || (parsedUrl.protocol === 'https:' ? 443 : 80),
        path: requestPath,
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        }
      };
      
      // Send the request
      const req = protocol.request(options, (res) => {
        let data = '';
        
        // Accumulate data
        res.on('data', (chunk) => {
          data += chunk;
        });
        
        // Process the complete response
        res.on('end', () => {
          if (res.statusCode === 200) {
            try {
              const jsonData = JSON.parse(data);
              resolve(jsonData);
            } catch (error) {
              console.error('Error parsing JSON response:', error);
              reject(error);
            }
          } else {
            console.error(`REST API returned status code ${res.statusCode}`);
            reject(new Error(`HTTP status code: ${res.statusCode}`));
          }
        });
      });
      
      // Handle errors
      req.on('error', (error) => {
        console.error('Error calling REST API:', error);
        reject(error);
      });
      
      // End the request
      req.end();
    } catch (error) {
      console.error('Error in checkClient:', error);
      reject(error);
    }
  });
}

/**
 * Get report from REST API
 * @param {string} baseUrl REST API URL
 * @param {string} clientId Client ID
 * @param {string} documentType Document type
 * @returns {Promise<object>} Response from REST API
 */
async function getReport(baseUrl, clientId, documentType) {
  return new Promise((resolve, reject) => {
    try {
      // Parse the base URL
      const parsedUrl = new URL(baseUrl);
      
      // Prepare the query parameters
      const params = querystring.stringify({
        objectid: clientId,
        documenttype: documentType
      });
      
      // Build the request path
      const requestPath = `${parsedUrl.pathname.replace(/\/+$/, '')}/getreport?${params}`;
      
      // Choose protocol based on URL
      const protocol = parsedUrl.protocol === 'https:' ? https : http;
      
      // Prepare request options
      const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || (parsedUrl.protocol === 'https:' ? 443 : 80),
        path: requestPath,
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        }
      };
      
      // Send the request
      const req = protocol.request(options, (res) => {
        let data = '';
        
        // Accumulate data
        res.on('data', (chunk) => {
          data += chunk;
        });
        
        // Process the complete response
        res.on('end', () => {
          if (res.statusCode === 200) {
            try {
              const jsonData = JSON.parse(data);
              resolve(jsonData);
            } catch (error) {
              console.error('Error parsing JSON response:', error);
              reject(error);
            }
          } else {
            console.error(`REST API returned status code ${res.statusCode}`);
            reject(new Error(`HTTP status code: ${res.statusCode}`));
          }
        });
      });
      
      // Handle errors
      req.on('error', (error) => {
        console.error('Error calling REST API:', error);
        reject(error);
      });
      
      // End the request
      req.end();
    } catch (error) {
      console.error('Error in getReport:', error);
      reject(error);
    }
  });
}

/**
 * Submit report to REST API
 * @param {string} baseUrl REST API URL
 * @param {string} clientId Client ID
 * @param {string} reportData Report data
 * @returns {Promise<object>} Response from REST API
 */
async function submitReport(baseUrl, clientId, reportData) {
  return new Promise((resolve, reject) => {
    try {
      // Parse the base URL
      const parsedUrl = new URL(baseUrl);
      
      // Prepare the data to send
      const postData = JSON.stringify({
        objectid: clientId,
        reportdata: reportData
      });
      
      // Choose protocol based on URL
      const protocol = parsedUrl.protocol === 'https:' ? https : http;
      
      // Prepare request options
      const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || (parsedUrl.protocol === 'https:' ? 443 : 80),
        path: `${parsedUrl.pathname.replace(/\/+$/, '')}/submitreport`,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(postData)
        }
      };
      
      // Send the request
      const req = protocol.request(options, (res) => {
        let data = '';
        
        // Accumulate data
        res.on('data', (chunk) => {
          data += chunk;
        });
        
        // Process the complete response
        res.on('end', () => {
          if (res.statusCode === 200) {
            try {
              const jsonData = JSON.parse(data);
              resolve(jsonData);
            } catch (error) {
              console.error('Error parsing JSON response:', error);
              reject(error);
            }
          } else {
            console.error(`REST API returned status code ${res.statusCode}`);
            reject(new Error(`HTTP status code: ${res.statusCode}`));
          }
        });
      });
      
      // Handle errors
      req.on('error', (error) => {
        console.error('Error calling REST API:', error);
        reject(error);
      });
      
      // Write data to request
      req.write(postData);
      
      // End the request
      req.end();
    } catch (error) {
      console.error('Error in submitReport:', error);
      reject(error);
    }
  });
}

module.exports = {
  checkClient,
  getReport,
  submitReport
}; 
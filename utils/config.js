/**
 * Configuration utility for Cloud Report Server
 */
const fs = require('fs');
const ini = require('ini');
const path = require('path');

/**
 * Default configuration values
 */
const defaultConfig = {
  http: {
    port: 8080,
    interface: '0.0.0.0'
  },
  tcp: {
    port: 2909,
    interface: '0.0.0.0'
  },
  server: {
    logPath: './logs',
    updatePath: './updates',
    traceLogEnabled: true,
    authServerUrl: 'http://localhost:8081/auth'
  }
};

/**
 * Load configuration from INI file
 * @param {string} configPath - Path to the configuration file
 * @returns {Object} - Configuration object
 */
function loadConfig(configPath) {
  try {
    // Create default config if it doesn't exist
    const configDir = path.dirname(configPath);
    if (!fs.existsSync(configDir)) {
      fs.mkdirSync(configDir, { recursive: true });
    }
    
    if (!fs.existsSync(configPath)) {
      fs.writeFileSync(configPath, ini.stringify(defaultConfig));
      console.log(`Created default configuration at ${configPath}`);
      return defaultConfig;
    }
    
    // Read and parse config file
    const configFile = fs.readFileSync(configPath, 'utf-8');
    const config = ini.parse(configFile);
    
    // Merge with defaults
    return {
      http: {
        ...defaultConfig.http,
        ...(config.http || {})
      },
      tcp: {
        ...defaultConfig.tcp,
        ...(config.tcp || {})
      },
      server: {
        ...defaultConfig.server,
        ...(config.server || {})
      }
    };
  } catch (error) {
    console.error(`Failed to load configuration: ${error.message}`);
    return defaultConfig;
  }
}

/**
 * Save configuration to INI file
 * @param {string} configPath - Path to save the configuration
 * @param {Object} config - Configuration object to save
 */
function saveConfig(configPath, config) {
  try {
    const configString = ini.stringify(config);
    fs.writeFileSync(configPath, configString);
  } catch (error) {
    console.error(`Failed to save configuration: ${error.message}`);
  }
}

module.exports = {
  loadConfig,
  saveConfig,
  defaultConfig
}; 
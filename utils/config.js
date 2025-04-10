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
    port: 8016,
    interface: '0.0.0.0'
  },
  server: {
    logPath: './logs',
    updatePath: './updates',
    traceLogEnabled: true,
    authServerUrl: 'http://10.150.40.8/dreport/api.php'
  }
};

/**
 * Default configuration in original format
 */
const defaultOriginalConfig = {
  COMMONSETTINGS: {
    CommInterfaceCount: 1
  },
  'REGISTRATION INFO': {
    'SERIAL NUMBER': '141298787',
    'KEY': 'BszXj0gTaKILS6Ap56=='
  },
  SRV_1_COMMON: {
    TraceLogEnabled: 1,
    UpdateFolder: 'Updates'
  },
  SRV_1_HTTP: {
    HTTP_IPInterface: '0.0.0.0',
    HTTP_Port: 8080
  },
  SRV_1_TCP: {
    TCP_IPInterface: '0.0.0.0',
    TCP_Port: 8016
  },
  SRV_1_AUTHSERVER: {
    REST_URL: 'http://10.150.40.8/dreport/api.php'
  },
  SRV_1_HTTPLOGINS: {
    user: 'pass$123'
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
      fs.writeFileSync(configPath, ini.stringify(defaultOriginalConfig));
      console.log(`Created default configuration at ${configPath}`);
      return { ...defaultConfig, ...defaultOriginalConfig };
    }
    
    // Read and parse config file
    const configFile = fs.readFileSync(configPath, 'utf-8');
    const config = ini.parse(configFile);
    
    // Check if this is the original format (has COMMONSETTINGS section)
    const isOriginalFormat = config.COMMONSETTINGS || config.SRV_1_TCP;
    
    if (isOriginalFormat) {
      // Create a merged config with both new and old formats
      const mergedConfig = { ...defaultConfig, ...config };
      
      // Map the old format values to new format if needed
      if (config.SRV_1_TCP && config.SRV_1_TCP.TCP_Port) {
        mergedConfig.tcp.port = parseInt(config.SRV_1_TCP.TCP_Port);
      }
      
      if (config.SRV_1_HTTP && config.SRV_1_HTTP.HTTP_Port) {
        mergedConfig.http.port = parseInt(config.SRV_1_HTTP.HTTP_Port);
      }
      
      if (config.SRV_1_AUTHSERVER && config.SRV_1_AUTHSERVER.REST_URL) {
        mergedConfig.server.authServerUrl = config.SRV_1_AUTHSERVER.REST_URL;
      }
      
      return mergedConfig;
    } else {
      // Standard new format
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
    }
  } catch (error) {
    console.error(`Failed to load configuration: ${error.message}`);
    return { ...defaultConfig, ...defaultOriginalConfig };
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
  defaultConfig,
  defaultOriginalConfig
}; 
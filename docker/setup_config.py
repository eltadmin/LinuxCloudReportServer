#!/usr/bin/env python3
"""
Setup script to configure the server.ini and keys.json files
This ensures proper configuration even with case-sensitive checks
"""
import configparser
import json
import os
import socket
import sys
import traceback

def setup_server_ini():
    """
    Set up the server.ini file with all required sections
    """
    print("Setting up server.ini...")
    
    config_path = '/app/config/server.ini'
    
    if not os.path.exists(config_path):
        print(f"Error: {config_path} not found!")
        return False
    
    try:
        # Read existing config
        config = configparser.ConfigParser()
        config.read(config_path)
        
        # Make sure all necessary sections exist with proper case
        
        # Common settings
        if 'COMMONSETTINGS' not in config:
            config['COMMONSETTINGS'] = {}
        
        if 'CommInterfaceCount' not in config['COMMONSETTINGS']:
            config['COMMONSETTINGS']['CommInterfaceCount'] = '1'
        
        # Registration info
        if 'REGISTRATION INFO' not in config:
            config['REGISTRATION INFO'] = {}
            
        if 'SERIAL NUMBER' not in config['REGISTRATION INFO']:
            config['REGISTRATION INFO']['SERIAL NUMBER'] = '141298787'
            
        if 'KEY' not in config['REGISTRATION INFO']:
            config['REGISTRATION INFO']['KEY'] = 'BszXj0gTaKILS6Ap56=='
        
        # Server section (with both uppercase and lowercase)
        # The code might be checking for either version
        if 'SERVER' not in config:
            config['SERVER'] = {}
            
        if 'Server' not in config:
            config['Server'] = {}
            
        # Ensure same values in both sections
        for section in ['SERVER', 'Server']:
            if 'Name' not in config[section]:
                config[section]['Name'] = 'LinuxCloudReportServer'
                
            if 'Version' not in config[section]:
                config[section]['Version'] = '1.0.0'
                
            if 'LogLevel' not in config[section]:
                config[section]['LogLevel'] = 'INFO'
                
            if 'MaxConnections' not in config[section]:
                config[section]['MaxConnections'] = '100'
        
        # SRV_1_COMMON section
        if 'SRV_1_COMMON' not in config:
            config['SRV_1_COMMON'] = {}
            
        if 'TraceLogEnabled' not in config['SRV_1_COMMON']:
            config['SRV_1_COMMON']['TraceLogEnabled'] = '1'
            
        if 'UpdateFolder' not in config['SRV_1_COMMON']:
            config['SRV_1_COMMON']['UpdateFolder'] = 'updates'
        
        # SRV_1_HTTP section
        if 'SRV_1_HTTP' not in config:
            config['SRV_1_HTTP'] = {}
            
        if 'HTTP_IPInterface' not in config['SRV_1_HTTP']:
            config['SRV_1_HTTP']['HTTP_IPInterface'] = '0.0.0.0'
            
        if 'HTTP_Port' not in config['SRV_1_HTTP']:
            config['SRV_1_HTTP']['HTTP_Port'] = '8080'
        
        # SRV_1_TCP section
        if 'SRV_1_TCP' not in config:
            config['SRV_1_TCP'] = {}
            
        if 'TCP_IPInterface' not in config['SRV_1_TCP']:
            config['SRV_1_TCP']['TCP_IPInterface'] = '0.0.0.0'
            
        if 'TCP_Port' not in config['SRV_1_TCP']:
            config['SRV_1_TCP']['TCP_Port'] = '8016'
        
        # SRV_1_AUTHSERVER section
        if 'SRV_1_AUTHSERVER' not in config:
            config['SRV_1_AUTHSERVER'] = {}
            
        if 'REST_URL' not in config['SRV_1_AUTHSERVER']:
            config['SRV_1_AUTHSERVER']['REST_URL'] = 'http://10.150.40.8:8010/dreport/api.php'
        
        # SRV_1_HTTPLOGINS section
        if 'SRV_1_HTTPLOGINS' not in config:
            config['SRV_1_HTTPLOGINS'] = {}
            
        if 'user' not in config['SRV_1_HTTPLOGINS']:
            config['SRV_1_HTTPLOGINS']['user'] = 'pass$123'
        
        # Write updated config
        with open(config_path, 'w') as f:
            config.write(f)
            
        print(f"Successfully updated {config_path}")
        return True
        
    except Exception as e:
        print(f"Error updating server.ini: {e}")
        traceback.print_exc()
        return False

def setup_keys_json():
    """
    Set up the keys.json file with proper keys
    """
    print("Setting up keys.json...")
    
    keys_path = '/app/config/keys.json'
    
    try:
        # Create keys dictionary
        keys = {
            "server_keys": {
                "default": "D5F2",
                "special": {
                    "8": "D028"
                }
            },
            "client_keys": {
                "2": "D5F2aRD-",
                "5": "D5F2cNE-",
                "6": "D5F26NE-",
                "9": "D5F22NE-"
            }
        }
        
        # Write keys to file
        with open(keys_path, 'w') as f:
            json.dump(keys, f, indent=2)
            
        print(f"Successfully created/updated {keys_path}")
        return True
        
    except Exception as e:
        print(f"Error creating keys.json: {e}")
        traceback.print_exc()
        return False

def main():
    """
    Main function to set up all configuration
    """
    print("Starting server configuration setup...")
    
    success = True
    
    # Set up server.ini
    if not setup_server_ini():
        success = False
    
    # Set up keys.json
    if not setup_keys_json():
        success = False
    
    # Print result
    if success:
        print("Server configuration setup completed successfully!")
        return 0
    else:
        print("Server configuration setup failed with errors!")
        return 1

if __name__ == "__main__":
    sys.exit(main()) 
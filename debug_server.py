#!/usr/bin/env python3
"""
Diagnostic script for Cloud Report Server
Checks system requirements, network connectivity and configuration
"""
import os
import sys
import socket
import platform
import importlib
import configparser
import json
from pathlib import Path
import ssl


class DebugServer:
    def __init__(self):
        self.results = {
            "system": [],
            "network": [],
            "configuration": [],
            "crypto": []
        }
        self.errors = 0
        self.warnings = 0

    def log_success(self, category, message):
        self.results[category].append({"status": "success", "message": message})
        print(f"✅ {message}")

    def log_warning(self, category, message):
        self.results[category].append({"status": "warning", "message": message})
        print(f"⚠️ {message}")
        self.warnings += 1

    def log_error(self, category, message):
        self.results[category].append({"status": "error", "message": message})
        print(f"❌ {message}")
        self.errors += 1

    def check_python_version(self):
        current_version = platform.python_version()
        if current_version.startswith('3.'):
            major, minor, _ = current_version.split('.')
            if int(major) == 3 and int(minor) >= 6:
                self.log_success("system", f"Python version is {current_version}")
            else:
                self.log_warning("system", f"Python version is {current_version}, recommended is 3.6+")
        else:
            self.log_error("system", f"Python version is {current_version}, required is 3.6+")

    def check_crypto_modules(self):
        try:
            importlib.import_module('Crypto')
            self.log_success("crypto", "PyCrypto module is installed")
        except ImportError:
            try:
                importlib.import_module('Cryptodome')
                self.log_success("crypto", "PyCryptodome module is installed")
            except ImportError:
                self.log_error("crypto", "Neither PyCrypto nor PyCryptodome modules are installed")

    def check_required_modules(self):
        required_modules = ["socket", "threading", "json", "configparser", "ssl"]
        for module in required_modules:
            try:
                importlib.import_module(module)
                self.log_success("system", f"Required module '{module}' is installed")
            except ImportError:
                self.log_error("system", f"Required module '{module}' is not installed")

    def check_network_ports(self):
        ports_to_check = [8016, 8080]
        for port in ports_to_check:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            try:
                sock.bind(('', port))
                self.log_success("network", f"Port {port} is available")
            except socket.error:
                self.log_error("network", f"Port {port} is already in use")
            finally:
                sock.close()

    def check_config_files(self):
        config_files = {
            "/app/config/server.ini": "Server configuration file",
            "/app/config/keys.json": "Encryption keys file"
        }
        
        for config_file, description in config_files.items():
            if os.path.exists(config_file):
                self.log_success("configuration", f"{description} exists at {config_file}")
                
                # Additional checks for server.ini
                if config_file.endswith('server.ini'):
                    try:
                        config = configparser.ConfigParser()
                        config.read(config_file)
                        if 'Server' in config:
                            self.log_success("configuration", "Server section exists in config file")
                        else:
                            self.log_error("configuration", "Missing Server section in config file")
                    except Exception as e:
                        self.log_error("configuration", f"Error parsing server.ini: {str(e)}")
                
                # Additional checks for keys.json
                if config_file.endswith('keys.json'):
                    try:
                        with open(config_file, 'r') as f:
                            keys_data = json.load(f)
                        if isinstance(keys_data, dict) and keys_data:
                            self.log_success("configuration", "Keys file contains valid JSON data")
                        else:
                            self.log_warning("configuration", "Keys file may be empty or invalid")
                    except Exception as e:
                        self.log_error("configuration", f"Error parsing keys.json: {str(e)}")
            else:
                self.log_error("configuration", f"{description} does not exist at {config_file}")

    def check_directory_permissions(self):
        dirs_to_check = {
            "/app/logs": "Logs directory",
            "/app/updates": "Updates directory",
            "/app/config": "Configuration directory"
        }
        
        for directory, description in dirs_to_check.items():
            if os.path.exists(directory):
                if os.access(directory, os.R_OK | os.W_OK):
                    self.log_success("system", f"{description} exists and is writable")
                else:
                    self.log_error("system", f"{description} exists but is not writable")
            else:
                self.log_error("system", f"{description} does not exist at {directory}")

    def run_all_checks(self):
        print("Starting diagnostic checks for Cloud Report Server...")
        print("=" * 60)
        
        # System checks
        print("\n📋 SYSTEM CHECKS:")
        self.check_python_version()
        self.check_required_modules()
        self.check_directory_permissions()
        
        # Crypto checks
        print("\n🔐 CRYPTO CHECKS:")
        self.check_crypto_modules()
        
        # Network checks
        print("\n🌐 NETWORK CHECKS:")
        self.check_network_ports()
        
        # Configuration checks
        print("\n⚙️ CONFIGURATION CHECKS:")
        self.check_config_files()
        
        # Summary
        print("\n" + "=" * 60)
        print(f"DIAGNOSTICS SUMMARY: {self.errors} errors, {self.warnings} warnings")
        
        return self.errors, self.warnings

if __name__ == "__main__":
    debug = DebugServer()
    errors, warnings = debug.run_all_checks()
    
    if errors > 0:
        print("\n❌ Diagnostics failed with errors. Please fix the issues before running the server.")
        sys.exit(1)
    elif warnings > 0:
        print("\n⚠️ Diagnostics completed with warnings. The server may not function correctly.")
        sys.exit(0)
    else:
        print("\n✅ All diagnostics passed successfully!")
        sys.exit(0) 
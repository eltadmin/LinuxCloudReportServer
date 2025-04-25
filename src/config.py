"""
Configuration module for Cloud Report Server
"""

import configparser
import os
from typing import Dict, List, Optional, Any

class ServerConfig:
    """Server configuration class"""
    
    def __init__(self, config_file: str):
        """
        Initialize the server configuration
        
        Args:
            config_file: Path to the configuration file
        """
        self.config_file = config_file
        self.config = configparser.ConfigParser()
        
        # Load configuration
        if os.path.exists(config_file):
            self.config.read(config_file)
        else:
            raise FileNotFoundError(f"Configuration file not found: {config_file}")
    
    def get_str(self, section: str, key: str, default: str = "") -> str:
        """Get a string value from the configuration"""
        return self.config.get(section, key, fallback=default)
    
    def get_int(self, section: str, key: str, default: int = 0) -> int:
        """Get an integer value from the configuration"""
        try:
            return self.config.getint(section, key, fallback=default)
        except ValueError:
            return default
    
    def get_bool(self, section: str, key: str, default: bool = False) -> bool:
        """Get a boolean value from the configuration"""
        try:
            return self.config.getboolean(section, key, fallback=default)
        except ValueError:
            return default
    
    def get_section(self, section: str) -> Dict[str, str]:
        """Get all key-value pairs in a section"""
        if section in self.config:
            return dict(self.config[section])
        return {}
    
    def get_server_count(self) -> int:
        """Get the number of server interfaces configured"""
        return self.get_int("COMMONSETTINGS", "CommInterfaceCount", 1)
    
    def get_server_settings(self, server_num: int) -> Dict[str, Any]:
        """
        Get settings for a specific server interface
        
        Args:
            server_num: Server interface number (1-based)
            
        Returns:
            Dictionary of server settings
        """
        settings = {}
        
        # Common settings
        common_section = f"SRV_{server_num}_COMMON"
        settings["trace_log_enabled"] = self.get_bool(common_section, "TraceLogEnabled", False)
        settings["update_folder"] = self.get_str(common_section, "UpdateFolder", "updates")
        
        # HTTP settings
        http_section = f"SRV_{server_num}_HTTP"
        settings["http_interface"] = self.get_str(http_section, "HTTP_IPInterface", "0.0.0.0")
        settings["http_port"] = self.get_int(http_section, "HTTP_Port", 8080)
        
        # TCP settings
        tcp_section = f"SRV_{server_num}_TCP"
        settings["tcp_interface"] = self.get_str(tcp_section, "TCP_IPInterface", "0.0.0.0")
        settings["tcp_port"] = self.get_int(tcp_section, "TCP_Port", 8016)
        
        # Auth server settings
        auth_section = f"SRV_{server_num}_AUTHSERVER"
        settings["auth_server_url"] = self.get_str(auth_section, "REST_URL", "")
        
        # HTTP logins
        login_section = f"SRV_{server_num}_HTTPLOGINS"
        settings["http_logins"] = self.get_section(login_section)
        
        return settings
    
    def get_registration_info(self) -> Dict[str, str]:
        """Get registration information"""
        return {
            "serial_number": self.get_str("REGISTRATION INFO", "SERIAL NUMBER", ""),
            "key": self.get_str("REGISTRATION INFO", "KEY", "")
        } 
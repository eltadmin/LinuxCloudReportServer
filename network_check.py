#!/usr/bin/env python3
"""
Network Diagnostics Tool for Cloud Report Server

This script performs various network diagnostics to help troubleshoot
connectivity issues with the Cloud Report Server.
"""

import os
import sys
import socket
import subprocess
import time
import traceback
from datetime import datetime
import configparser

def log(message):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}")

def get_config_value(config_path, section, key, default=None):
    """Read a value from the configuration file."""
    if not os.path.exists(config_path):
        return default
    
    config = configparser.ConfigParser()
    try:
        config.read(config_path)
        if section in config and key in config[section]:
            return config[section][key]
    except Exception as e:
        log(f"Error reading config: {str(e)}")
    
    return default

def check_port(host, port):
    """Check if a port is open on a given host."""
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(2)
    try:
        s.connect((host, port))
        s.close()
        return True
    except (socket.timeout, ConnectionRefusedError):
        return False
    except Exception as e:
        log(f"Error checking port {port}: {str(e)}")
        return False

def ping_host(host):
    """Ping a host to check connectivity."""
    try:
        param = '-n' if sys.platform.lower() == 'win32' else '-c'
        command = ['ping', param, '3', host]
        return subprocess.call(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE) == 0
    except Exception as e:
        log(f"Error pinging host {host}: {str(e)}")
        return False

def traceroute(host):
    """Perform a traceroute to the host."""
    try:
        command = 'tracert' if sys.platform.lower() == 'win32' else 'traceroute'
        result = subprocess.run([command, host], capture_output=True, text=True, timeout=30)
        return result.stdout
    except Exception as e:
        return f"Error performing traceroute: {str(e)}"

def check_dns(domain):
    """Check DNS resolution for a domain."""
    try:
        return socket.gethostbyname(domain)
    except socket.gaierror:
        return None
    except Exception as e:
        log(f"Error checking DNS for {domain}: {str(e)}")
        return None

def main():
    log("Starting network diagnostics...")
    
    # Get server information
    config_path = "/app/config/server.ini"
    if os.path.exists(config_path):
        log(f"Reading configuration from {config_path}")
        server_host = get_config_value(config_path, "Network", "Host", "localhost")
        server_port = int(get_config_value(config_path, "Network", "Port", "8016"))
        external_hosts = get_config_value(config_path, "Network", "ExternalHosts", "").split(',')
    else:
        log(f"Configuration file not found at {config_path}")
        server_host = "localhost"
        server_port = 8016
        external_hosts = []
    
    # Check local network configuration
    log("\n=== Local Network Configuration ===")
    hostname = socket.gethostname()
    log(f"Hostname: {hostname}")
    
    try:
        log(f"IP Addresses:")
        for ip in socket.gethostbyname_ex(hostname)[2]:
            log(f"  - {ip}")
    except Exception as e:
        log(f"Error getting IP addresses: {str(e)}")
    
    # Check if server ports are open
    log("\n=== Server Ports ===")
    log(f"Checking if server port {server_port} is open on {server_host}...")
    if check_port(server_host, server_port):
        log(f"✅ Port {server_port} is OPEN on {server_host}")
    else:
        log(f"❌ Port {server_port} is CLOSED on {server_host}")
    
    log(f"Checking if HTTP port 8080 is open on {server_host}...")
    if check_port(server_host, 8080):
        log(f"✅ Port 8080 is OPEN on {server_host}")
    else:
        log(f"❌ Port 8080 is CLOSED on {server_host}")
    
    # Check connectivity to external hosts
    if external_hosts:
        log("\n=== External Connectivity ===")
        for host in external_hosts:
            host = host.strip()
            if not host:
                continue
                
            log(f"Testing connectivity to {host}...")
            
            # Check DNS
            ip = check_dns(host)
            if ip:
                log(f"✅ DNS resolution for {host}: {ip}")
            else:
                log(f"❌ Failed to resolve DNS for {host}")
                continue
            
            # Ping
            log(f"Pinging {host}...")
            if ping_host(host):
                log(f"✅ Ping to {host} successful")
            else:
                log(f"❌ Ping to {host} failed")
            
            # Traceroute (optional - can be time-consuming)
            log(f"Performing traceroute to {host}...")
            trace_result = traceroute(host)
            log(f"Traceroute results:\n{trace_result}")
    
    # Check if we can access common external services
    log("\n=== Internet Connectivity ===")
    external_services = [
        ("Google DNS", "8.8.8.8", 53),
        ("Cloudflare DNS", "1.1.1.1", 53),
        ("Google HTTP", "www.google.com", 80),
    ]
    
    for name, host, port in external_services:
        log(f"Testing connectivity to {name} ({host}:{port})...")
        if check_port(host, port):
            log(f"✅ Connection to {name} successful")
        else:
            log(f"❌ Connection to {name} failed")
    
    log("\nNetwork diagnostics completed.")

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        log(f"An error occurred during network diagnostics: {str(e)}")
        log(traceback.format_exc()) 
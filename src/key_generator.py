#!/usr/bin/env python3
"""
Key Generator for Cloud Report Server
Replacement for the original ServerKeyGen utility
"""

import argparse
import base64
import hashlib
import platform
import subprocess
import sys
from typing import Optional

from Crypto.Cipher import AES
from Crypto.Util.Padding import pad

def get_hardware_serial() -> str:
    """
    Get hardware serial number for registration key generation
    
    Returns:
        Hardware serial number or fallback ID
    """
    serial = ""
    system = platform.system()
    
    try:
        if system == "Linux":
            # Try to get disk serial
            try:
                # Get first disk device
                disk_id_proc = subprocess.run(
                    ["lsblk", "-no", "NAME,TYPE"], 
                    stdout=subprocess.PIPE, 
                    stderr=subprocess.PIPE,
                    text=True
                )
                
                lines = disk_id_proc.stdout.strip().split("\n")
                disk_device = None
                
                for line in lines:
                    if "disk" in line:
                        parts = line.split()
                        disk_device = parts[0]
                        break
                
                if disk_device:
                    # Get disk serial
                    disk_serial_proc = subprocess.run(
                        ["udevadm", "info", "--query=property", f"/dev/{disk_device}"], 
                        stdout=subprocess.PIPE, 
                        stderr=subprocess.PIPE,
                        text=True
                    )
                    
                    for line in disk_serial_proc.stdout.split("\n"):
                        if "ID_SERIAL=" in line:
                            serial = line.split("=")[1].strip()
                            break
                
            except Exception:
                pass
            
            # Fallback to machine-id
            if not serial:
                try:
                    with open("/etc/machine-id", "r") as f:
                        serial = f.read().strip()
                except Exception:
                    pass
            
        elif system == "Windows":
            # Get Windows disk serial using wmic
            try:
                disk_serial_proc = subprocess.run(
                    ["wmic", "diskdrive", "get", "SerialNumber"], 
                    stdout=subprocess.PIPE, 
                    stderr=subprocess.PIPE,
                    text=True
                )
                
                lines = disk_serial_proc.stdout.strip().split("\n")
                if len(lines) > 1:
                    serial = lines[1].strip()
            except Exception:
                pass
        
        elif system == "Darwin":  # macOS
            # Get macOS hardware UUID
            try:
                hw_uuid_proc = subprocess.run(
                    ["system_profiler", "SPHardwareDataType"], 
                    stdout=subprocess.PIPE, 
                    stderr=subprocess.PIPE,
                    text=True
                )
                
                for line in hw_uuid_proc.stdout.split("\n"):
                    if "Hardware UUID" in line:
                        serial = line.split(":")[1].strip()
                        break
            except Exception:
                pass
    
    except Exception:
        pass
    
    # Fallback to a combination of hostname and platform info
    if not serial:
        hostname = platform.node()
        machine = platform.machine()
        serial = f"{hostname}-{machine}-{system}"
    
    return serial

def generate_key(serial: str) -> str:
    """
    Generate a registration key based on the serial number
    
    Args:
        serial: Hardware serial number
        
    Returns:
        Registration key encoded in Base64
    """
    # The string to encrypt
    data = "ElCloudRepSrv"
    
    # Create MD5 hash of the serial as the key
    key = hashlib.md5(serial.encode('utf-8')).digest()
    
    # Create AES cipher in CFB mode
    cipher = AES.new(key, AES.MODE_CFB, iv=bytes(16), segment_size=128)
    
    # Encrypt the data
    encrypted = cipher.encrypt(pad(data.encode('utf-8'), AES.block_size))
    
    # Encode as Base64
    key_b64 = base64.b64encode(encrypted).decode('utf-8')
    
    return key_b64

def main():
    """Main function"""
    parser = argparse.ArgumentParser(description="Key Generator for Cloud Report Server")
    parser.add_argument("--serial", help="Hardware serial number (optional)")
    parser.add_argument("--output", help="Output file (optional)")
    
    args = parser.parse_args()
    
    # Get serial number
    serial = args.serial or get_hardware_serial()
    
    print(f"Hardware Serial: {serial}")
    
    # Generate key
    key = generate_key(serial)
    
    print(f"Generated Key: {key}")
    
    # Write to output file if specified
    if args.output:
        try:
            with open(args.output, "w") as f:
                f.write("[REGISTRATION INFO]\n")
                f.write(f"SERIAL NUMBER={serial}\n")
                f.write(f"KEY={key}\n")
                
            print(f"Registration information saved to {args.output}")
        except Exception as e:
            print(f"Error writing to output file: {e}")
            return 1
    
    return 0

if __name__ == "__main__":
    sys.exit(main()) 
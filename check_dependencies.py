#!/usr/bin/env python3
"""
Check dependencies for Cloud Report Server
"""

import sys
import importlib
import os

def check_module(module_name, alternative_name=None):
    try:
        importlib.import_module(module_name)
        print(f"✅ {module_name} is installed")
        return True
    except ImportError:
        if alternative_name:
            try:
                importlib.import_module(alternative_name)
                print(f"✅ {alternative_name} is installed (alternative to {module_name})")
                return True
            except ImportError:
                print(f"❌ Neither {module_name} nor {alternative_name} is installed")
                return False
        else:
            print(f"❌ {module_name} is not installed")
            return False

def check_file_permissions(path):
    if not os.path.exists(path):
        try:
            # Try to create directory if it doesn't exist
            os.makedirs(path, exist_ok=True)
            print(f"✅ Created directory: {path}")
        except Exception as e:
            print(f"❌ Could not create directory {path}: {e}")
            return False
    
    # Check if we can write to the directory
    try:
        test_file = os.path.join(path, ".test_write_access")
        with open(test_file, "w") as f:
            f.write("test")
        os.remove(test_file)
        print(f"✅ Write access to {path}")
        return True
    except Exception as e:
        print(f"❌ Cannot write to {path}: {e}")
        return False

def main():
    print("Checking dependencies for Cloud Report Server...")
    
    # Check required modules
    required_modules = {
        "flask": None,
        "Crypto": "Cryptodome",
        "zlib": None,
        "requests": None
    }
    
    all_modules_installed = True
    for module, alternative in required_modules.items():
        if not check_module(module, alternative):
            all_modules_installed = False
    
    # Check directory permissions
    script_dir = os.path.dirname(os.path.abspath(__file__))
    directories = [
        os.path.join(script_dir, "logs"),
        os.path.join(script_dir, "updates")
    ]
    
    all_dirs_accessible = True
    for directory in directories:
        if not check_file_permissions(directory):
            all_dirs_accessible = False
    
    # Check configuration
    config_file = os.path.join(script_dir, "config", "server.ini")
    if os.path.exists(config_file):
        print(f"✅ Configuration file {config_file} exists")
    else:
        print(f"❌ Configuration file {config_file} does not exist")
        all_dirs_accessible = False
    
    # Summary
    print("\nSummary:")
    if all_modules_installed and all_dirs_accessible:
        print("✅ All dependencies are satisfied!")
        return 0
    else:
        print("❌ Some dependencies are missing. Please install missing packages and ensure directory permissions.")
        return 1

if __name__ == "__main__":
    sys.exit(main()) 
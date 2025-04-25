#!/usr/bin/env python3
"""
Simple script to check if required crypto modules are available
"""
import sys
import traceback

def main():
    """Check if Crypto or Cryptodome is available"""
    try:
        import Crypto
        print("Crypto module imported successfully")
        return 0
    except ImportError:
        print("Crypto module not found, trying Cryptodome...")
        try:
            import Cryptodome
            print("Cryptodome module imported successfully")
            return 0
        except ImportError:
            print("Failed to import crypto modules", file=sys.stderr)
            traceback.print_exc()
            return 1

if __name__ == "__main__":
    sys.exit(main()) 
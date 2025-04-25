#!/usr/bin/env python3
"""
Test custom registration key for Cloud Report Server
"""

import sys
from src.crypto import check_registration_key

def main():
    """Main function"""
    # Hardcoded serial and key from server.ini
    serial1 = "141298787"
    key1 = "BszXj0gTaKILS6Ap56=="
    
    # Custom generated serial and key
    serial2 = "LPT-LYUBO-ADM-AMD64-Windows"
    key2 = "umr1i3ilEQy9H7Iesg2isw=="
    
    print("Testing existing key from server.ini:")
    result1 = check_registration_key(serial1, key1)
    print(f"  Serial: {serial1}")
    print(f"  Key: {key1}")
    print(f"  Valid: {result1}")
    print()
    
    print("Testing newly generated key:")
    result2 = check_registration_key(serial2, key2)
    print(f"  Serial: {serial2}")
    print(f"  Key: {key2}")
    print(f"  Valid: {result2}")
    print()
    
    # Cross-check (should be invalid)
    print("Cross-checking keys (should be invalid):")
    result3 = check_registration_key(serial1, key2)
    print(f"  Serial: {serial1} with Key: {key2}")
    print(f"  Valid: {result3}")
    
    result4 = check_registration_key(serial2, key1)
    print(f"  Serial: {serial2} with Key: {key1}")
    print(f"  Valid: {result4}")

if __name__ == "__main__":
    sys.exit(main()) 
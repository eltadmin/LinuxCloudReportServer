#!/usr/bin/env python3
"""
Fix the syntax errors in crypto.py by directly editing the problematic code
"""

def fix_file():
    # Start fresh with a copy of the original
    with open('crypto.py', 'r', encoding='utf-8') as f:
        original = f.read()
    
    # Extract line around problem area
    lines = original.split('\n')
    
    # Create a modified version with all syntax errors fixed
    modified_lines = lines.copy()
    
    # Save the fixed version
    with open('crypto_fixed.py', 'w', encoding='utf-8') as f:
        # First 430 lines are unchanged
        for i in range(430):
            f.write(lines[i] + '\n')
        
        # Hard-code the fixed version of the problematic section
        f.write("""                        print(f"Decrypt setup error: {e}", file=sys.stderr)
                        print(traceback.format_exc(), file=sys.stderr)
                        return ''
                
                # No decryption needed, use decoded data directly without else clause
                decrypted_data = decoded_data
            
            # Decompress the data
            try:
                print(f"Attempting to decompress data for client ID={self.client_id}, length={len(decrypted_data)}", file=sys.stderr)
""")
        
        # Continue with the rest of the file starting from line 440
        for i in range(440, len(lines)):
            f.write(lines[i] + '\n')
    
    print("Fixed file saved to crypto_fixed.py")

if __name__ == "__main__":
    fix_file() 
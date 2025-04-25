"""
Script to fix the syntax error in crypto.py
"""

with open('crypto.py', 'r', encoding='utf-8') as infile:
    content = infile.read()

# Find the problematic section and replace it
fixed_content = content.replace("""                        self.last_error = f'[decompress_data] Decrypt setup error: {str(e)}'
                        print(f"Decrypt setup error: {e}", file=sys.stderr)
                        print(traceback.format_exc(), file=sys.stderr)
                        return ''
                else:
                    decrypted_data = decoded_data""", 
"""                        self.last_error = f'[decompress_data] Decrypt setup error: {str(e)}'
                        print(f"Decrypt setup error: {e}", file=sys.stderr)
                        print(traceback.format_exc(), file=sys.stderr)
                        return ''
                
                # No decryption needed, use decoded data directly
                decrypted_data = decoded_data""")

with open('crypto_fixed.py', 'w', encoding='utf-8') as outfile:
    outfile.write(fixed_content)

print("Fixed file created as crypto_fixed.py") 
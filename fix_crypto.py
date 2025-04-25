#!/usr/bin/env python3
"""
Fix script for the syntax error in crypto.py
"""

import re

# Read the original file
with open('src/crypto.py', 'r', encoding='utf-8') as f:
    content = f.read()

# Find the problematic section around line 430-440
pattern = r"(                        return '')(\s+)(else:)(\s+)(decrypted_data = decoded_data)"
replacement = r"\1\2# No decryption needed, use decoded data directly\4    decrypted_data = decoded_data"

# Apply the fix
fixed_content = re.sub(pattern, replacement, content)

# Write the fixed content to a new file
with open('src/crypto_fixed.py', 'w', encoding='utf-8') as f:
    f.write(fixed_content)

print("Fixed file created at src/crypto_fixed.py") 
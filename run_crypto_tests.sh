#!/bin/bash
# Run crypto tests with different options

# Make sure script is run from correct directory
cd "$(dirname "$0")"

# Set Python path to include the current directory
export PYTHONPATH=$PYTHONPATH:$(pwd)

show_help() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -a, --all           Run all tests"
    echo "  -c, --compression   Test compression functions"
    echo "  -r, --reg-key       Test registration key validation"
    echo "  -k, --key-gen       Test key generation"
    echo "  -e, --encrypt       Test encryption/decryption"
    echo "  -i, --client-id ID  Specify client ID (default: 1)"
    echo "  -d, --data DATA     Specify data for encryption test"
    echo "  -h, --help          Show this help message"
    echo ""
    echo "Example: $0 --all"
    echo "Example: $0 --encrypt --client-id 8"
}

# Default values
RUN_ALL=0
TEST_COMPRESSION=0
TEST_REG_KEY=0
TEST_KEY_GEN=0
TEST_ENCRYPT=0
CLIENT_ID=1
DATA="TT=Test\r\nID=1\r\nFN=TestFirm\r\nHS=testhost"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -a|--all)
            RUN_ALL=1
            shift
            ;;
        -c|--compression)
            TEST_COMPRESSION=1
            shift
            ;;
        -r|--reg-key)
            TEST_REG_KEY=1
            shift
            ;;
        -k|--key-gen)
            TEST_KEY_GEN=1
            shift
            ;;
        -e|--encrypt)
            TEST_ENCRYPT=1
            shift
            ;;
        -i|--client-id)
            CLIENT_ID=$2
            shift 2
            ;;
        -d|--data)
            DATA=$2
            shift 2
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Build command
CMD="python test_crypto.py"

if [ $RUN_ALL -eq 1 ]; then
    CMD="$CMD --test-all"
else
    if [ $TEST_COMPRESSION -eq 1 ]; then
        CMD="$CMD --test-compression"
    fi
    
    if [ $TEST_REG_KEY -eq 1 ]; then
        CMD="$CMD --test-reg-key"
    fi
    
    if [ $TEST_KEY_GEN -eq 1 ]; then
        CMD="$CMD --test-key-gen"
    fi
    
    if [ $TEST_ENCRYPT -eq 1 ]; then
        CMD="$CMD --test-encrypt"
    fi
fi

# Add client ID
CMD="$CMD --client-id $CLIENT_ID"

# Add data if testing encryption
if [ $TEST_ENCRYPT -eq 1 ] || [ $RUN_ALL -eq 1 ]; then
    CMD="$CMD --data \"$DATA\""
fi

# Display and execute command
echo "Running: $CMD"
eval $CMD 
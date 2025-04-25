package server

import (
	"math/rand"
	"strings"
	"time"
)

func init() {
	// Seed the random number generator
	rand.Seed(time.Now().UnixNano())
}

// randomInt returns a random integer between min and max (inclusive)
func randomInt(min, max int) int {
	return rand.Intn(max-min+1) + min
}

// parseCommand parses a command line into a command and params map
func parseCommand(line string) (string, map[string]string) {
	// Trim whitespace and remove CR+LF
	line = strings.TrimSpace(line)
	
	// Split into parts by space
	parts := strings.Split(line, " ")
	if len(parts) == 0 {
		return "", nil
	}
	
	// The first part is the command
	command := strings.ToUpper(parts[0])
	
	// Parse parameters (key=value pairs)
	params := make(map[string]string)
	
	for i := 1; i < len(parts); i++ {
		part := parts[i]
		// Look for key=value format
		if idx := strings.Index(part, "="); idx > 0 {
			key := part[:idx]
			value := part[idx+1:]
			params[key] = value
		} else {
			// For parameters that are just keys without values
			params[part] = ""
		}
	}
	
	return command, params
}

// parseKeyValueData parses a string of key=value pairs separated by line breaks
func parseKeyValueData(data string) map[string]string {
	result := make(map[string]string)
	
	// Try different line break formats (fallback logic)
	var lines []string
	
	// Try CR+LF first
	if strings.Contains(data, "\r\n") {
		lines = strings.Split(data, "\r\n")
	} else if strings.Contains(data, "\n") {
		// Try LF only
		lines = strings.Split(data, "\n")
	} else {
		// Try semicolon as last resort
		lines = strings.Split(data, ";")
	}
	
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		
		// Split into key=value
		if idx := strings.Index(line, "="); idx > 0 {
			key := line[:idx]
			value := line[idx+1:]
			result[key] = value
		}
	}
	
	return result
}

// formatKeyValueData formats a map of key=value pairs as a string
func formatKeyValueData(data map[string]string) string {
	var lines []string
	
	// Ensure TT=Test is first if present
	if val, ok := data["TT"]; ok {
		lines = append(lines, "TT="+val)
	}
	
	// Add all other key=value pairs
	for key, value := range data {
		if key == "TT" {
			continue // Already added
		}
		lines = append(lines, key+"="+value)
	}
	
	// Join with CR+LF
	return strings.Join(lines, "\r\n")
} 
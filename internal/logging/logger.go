package logging

import (
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"time"
)

// Logger handles all logging operations
type Logger struct {
	logPath   string
	logFile   *os.File
	traceFile *os.File
	mu        sync.Mutex
	lineCount int
	maxLines  int
}

// NewLogger creates a new logger instance
func NewLogger(logPath string) *Logger {
	// Ensure the log directory exists
	if err := os.MkdirAll(logPath, 0755); err != nil {
		fmt.Printf("Error creating log directory: %v\n", err)
	}

	logger := &Logger{
		logPath:  logPath,
		maxLines: 600, // Rotate logs after 600 lines
	}

	// Initialize main log file
	var err error
	logger.logFile, err = os.OpenFile(
		filepath.Join(logPath, "CloudReportLog.txt"),
		os.O_CREATE|os.O_WRONLY|os.O_APPEND,
		0644,
	)
	if err != nil {
		fmt.Printf("Error opening log file: %v\n", err)
	}

	// Write startup entry
	logger.Info("==============================")
	logger.Info("Log started at %s", time.Now().Format("2006-01-02 15:04:05"))
	logger.Info("==============================")

	return logger
}

// Close closes all log files
func (l *Logger) Close() {
	l.mu.Lock()
	defer l.mu.Unlock()

	if l.logFile != nil {
		l.logFile.Close()
	}

	if l.traceFile != nil {
		l.traceFile.Close()
	}
}

// EnableTrace enables trace logging to a separate file
func (l *Logger) EnableTrace() error {
	l.mu.Lock()
	defer l.mu.Unlock()

	if l.traceFile != nil {
		return nil
	}

	var err error
	l.traceFile, err = os.OpenFile(
		filepath.Join(l.logPath, "TraceLog_Server.txt"),
		os.O_CREATE|os.O_WRONLY|os.O_APPEND,
		0644,
	)
	if err != nil {
		return fmt.Errorf("error opening trace log file: %v", err)
	}

	return nil
}

// Info logs an informational message
func (l *Logger) Info(format string, args ...interface{}) {
	l.log("INFO", format, args...)
}

// Error logs an error message
func (l *Logger) Error(format string, args ...interface{}) {
	l.log("ERROR", format, args...)
}

// Warning logs a warning message
func (l *Logger) Warning(format string, args ...interface{}) {
	l.log("WARNING", format, args...)
}

// Debug logs a debug message
func (l *Logger) Debug(format string, args ...interface{}) {
	l.log("DEBUG", format, args...)
}

// Trace logs a trace message (only if trace is enabled)
func (l *Logger) Trace(format string, args ...interface{}) {
	l.mu.Lock()
	defer l.mu.Unlock()

	if l.traceFile == nil {
		return
	}

	now := time.Now()
	message := fmt.Sprintf(format, args...)
	logEntry := fmt.Sprintf("%s [TRACE] %s\n", now.Format("2006-01-02 15:04:05.000"), message)

	l.traceFile.WriteString(logEntry)
}

// ClientLog logs a message to a client-specific log file
func (l *Logger) ClientLog(clientID, format string, args ...interface{}) {
	if clientID == "" {
		return
	}

	l.mu.Lock()
	defer l.mu.Unlock()

	clientLogPath := filepath.Join(l.logPath, fmt.Sprintf("ClientLog_%s.txt", clientID))
	clientFile, err := os.OpenFile(
		clientLogPath,
		os.O_CREATE|os.O_WRONLY|os.O_APPEND,
		0644,
	)
	if err != nil {
		fmt.Printf("Error opening client log file: %v\n", err)
		return
	}
	defer clientFile.Close()

	now := time.Now()
	message := fmt.Sprintf(format, args...)
	logEntry := fmt.Sprintf("%s %s\n", now.Format("2006-01-02 15:04:05.000"), message)

	clientFile.WriteString(logEntry)
}

// ClientError logs an error message to a client-specific log file
func (l *Logger) ClientError(clientID, format string, args ...interface{}) {
	if clientID == "" {
		return
	}

	l.mu.Lock()
	defer l.mu.Unlock()

	clientErrorPath := filepath.Join(l.logPath, fmt.Sprintf("ErrLog_%s.txt", clientID))
	clientFile, err := os.OpenFile(
		clientErrorPath,
		os.O_CREATE|os.O_WRONLY|os.O_APPEND,
		0644,
	)
	if err != nil {
		fmt.Printf("Error opening client error log file: %v\n", err)
		return
	}
	defer clientFile.Close()

	now := time.Now()
	message := fmt.Sprintf(format, args...)
	logEntry := fmt.Sprintf("%s %s\n", now.Format("2006-01-02 15:04:05.000"), message)

	clientFile.WriteString(logEntry)
}

// log handles the actual logging with rotation
func (l *Logger) log(level, format string, args ...interface{}) {
	l.mu.Lock()
	defer l.mu.Unlock()

	if l.logFile == nil {
		return
	}

	now := time.Now()
	message := fmt.Sprintf(format, args...)
	logEntry := fmt.Sprintf("%s [%s] %s\n", now.Format("2006-01-02 15:04:05.000"), level, message)

	l.logFile.WriteString(logEntry)
	l.lineCount++

	// Check if rotation is needed
	if l.lineCount >= l.maxLines {
		l.rotateLog()
	}
}

// rotateLog handles log file rotation
func (l *Logger) rotateLog() {
	if l.logFile == nil {
		return
	}

	// Close the current log file
	l.logFile.Close()

	// Generate a timestamp for the rotated log file
	timestamp := time.Now().Format("060102_150405")
	oldPath := filepath.Join(l.logPath, "CloudReportLog.txt")
	newPath := filepath.Join(l.logPath, fmt.Sprintf("%s_CloudReportLog.txt", timestamp))

	// Rename the current log file
	if err := os.Rename(oldPath, newPath); err != nil {
		fmt.Printf("Error rotating log file: %v\n", err)
		// Try to reopen the original file
		var reopenErr error
		l.logFile, reopenErr = os.OpenFile(
			oldPath,
			os.O_CREATE|os.O_WRONLY|os.O_APPEND,
			0644,
		)
		if reopenErr != nil {
			fmt.Printf("Error reopening log file: %v\n", reopenErr)
		}
		return
	}

	// Create a new log file
	var err error
	l.logFile, err = os.OpenFile(
		oldPath,
		os.O_CREATE|os.O_WRONLY|os.O_TRUNC,
		0644,
	)
	if err != nil {
		fmt.Printf("Error creating new log file: %v\n", err)
		return
	}

	// Reset line count
	l.lineCount = 0

	// Write header to new log file
	l.logFile.WriteString(fmt.Sprintf(
		"%s [INFO] ==============================\n",
		time.Now().Format("2006-01-02 15:04:05.000"),
	))
	l.logFile.WriteString(fmt.Sprintf(
		"%s [INFO] Log rotated. Previous log: %s\n",
		time.Now().Format("2006-01-02 15:04:05.000"),
		newPath,
	))
	l.logFile.WriteString(fmt.Sprintf(
		"%s [INFO] ==============================\n",
		time.Now().Format("2006-01-02 15:04:05.000"),
	))
	
	// Also rotate trace log if it exists
	if l.traceFile != nil {
		l.traceFile.Close()
		
		oldTracePath := filepath.Join(l.logPath, "TraceLog_Server.txt")
		newTracePath := filepath.Join(l.logPath, fmt.Sprintf("%s_TraceLog_Server.txt", timestamp))
		
		if err := os.Rename(oldTracePath, newTracePath); err != nil {
			fmt.Printf("Error rotating trace log file: %v\n", err)
		}
		
		l.traceFile, err = os.OpenFile(
			oldTracePath,
			os.O_CREATE|os.O_WRONLY|os.O_TRUNC,
			0644,
		)
		if err != nil {
			fmt.Printf("Error creating new trace log file: %v\n", err)
			l.traceFile = nil
		}
	}
	
	// Clean up old logs (older than 40 days)
	l.cleanupOldLogs()
}

// cleanupOldLogs removes log files older than 40 days
func (l *Logger) cleanupOldLogs() {
	// Get the cutoff time (40 days ago)
	cutoffTime := time.Now().AddDate(0, 0, -40)
	
	// Get a list of all log files
	files, err := os.ReadDir(l.logPath)
	if err != nil {
		fmt.Printf("Error reading log directory: %v\n", err)
		return
	}
	
	// Check each file
	for _, file := range files {
		// Skip directories
		if file.IsDir() {
			continue
		}
		
		// Get file info
		fileInfo, err := file.Info()
		if err != nil {
			continue
		}
		
		// Check if the file is old enough to delete
		if fileInfo.ModTime().Before(cutoffTime) {
			// Delete the file
			filePath := filepath.Join(l.logPath, fileInfo.Name())
			if err := os.Remove(filePath); err != nil {
				fmt.Printf("Error deleting old log file %s: %v\n", filePath, err)
			}
		}
	}
} 
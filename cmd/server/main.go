package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"

	"github.com/eltrade/reportcom/internal/config"
	"github.com/eltrade/reportcom/internal/logging"
	"github.com/eltrade/reportcom/internal/server"
)

func main() {
	// Parse command line arguments
	configPath := flag.String("config", "config.ini", "Path to configuration file")
	logPath := flag.String("log", "logs", "Path to log directory")
	flag.Parse()

	// Initialize logger
	logger := logging.NewLogger(*logPath)
	defer logger.Close()

	logger.Info("ReportCom Server starting...")
	logger.Info("Using config file: %s", *configPath)

	// Load configuration
	cfg, err := config.LoadConfig(*configPath)
	if err != nil {
		logger.Error("Failed to load configuration: %v", err)
		os.Exit(1)
	}

	// Create server instance
	tcpServer, err := server.NewTCPServer(cfg, logger)
	if err != nil {
		logger.Error("Failed to create TCP server: %v", err)
		os.Exit(1)
	}

	// Start the server
	go func() {
		if err := tcpServer.Start(); err != nil {
			logger.Error("Server failed: %v", err)
			os.Exit(1)
		}
	}()

	// Create HTTP server if configured
	var httpServer *server.HTTPServer
	if cfg.HTTP.Enabled {
		httpServer, err = server.NewHTTPServer(cfg, logger, tcpServer)
		if err != nil {
			logger.Error("Failed to create HTTP server: %v", err)
		} else {
			go func() {
				if err := httpServer.Start(); err != nil {
					logger.Error("HTTP server failed: %v", err)
				}
			}()
		}
	}

	// Setup signal handling for graceful shutdown
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)

	// Block until we receive a signal
	sig := <-sigChan
	logger.Info("Received signal: %v, shutting down...", sig)

	// Shutdown servers
	tcpServer.Stop()
	if httpServer != nil && cfg.HTTP.Enabled {
		httpServer.Stop()
	}

	logger.Info("Server stopped")
} 
package server

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/eltrade/reportcom/internal/config"
	"github.com/eltrade/reportcom/internal/logging"
)

// HTTPServer handles HTTP requests for the REST API
type HTTPServer struct {
	cfg       *config.Config
	logger    *logging.Logger
	tcpServer *TCPServer
	server    *http.Server
}

// NewHTTPServer creates a new HTTP server
func NewHTTPServer(cfg *config.Config, logger *logging.Logger, tcpServer *TCPServer) (*HTTPServer, error) {
	httpServer := &HTTPServer{
		cfg:       cfg,
		logger:    logger,
		tcpServer: tcpServer,
	}

	// Create HTTP server
	addr := fmt.Sprintf("%s:%d", cfg.HTTP.Interface, cfg.HTTP.Port)
	server := &http.Server{
		Addr:         addr,
		Handler:      httpServer.createHandler(),
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 30 * time.Second,
	}

	httpServer.server = server

	return httpServer, nil
}

// Start starts the HTTP server
func (s *HTTPServer) Start() error {
	s.logger.Info("Starting HTTP server on %s:%d", s.cfg.HTTP.Interface, s.cfg.HTTP.Port)
	return s.server.ListenAndServe()
}

// Stop stops the HTTP server
func (s *HTTPServer) Stop() {
	if s.server != nil {
		s.logger.Info("Stopping HTTP server")
		s.server.Close()
	}
}

// createHandler creates the HTTP handler for the server
func (s *HTTPServer) createHandler() http.Handler {
	mux := http.NewServeMux()

	// Client list endpoint
	mux.HandleFunc("/server/clientlist/", s.handleClientList)

	// Client status endpoint
	mux.HandleFunc("/server/clientstat/", s.handleClientStatus)

	// Report endpoint
	mux.HandleFunc("/report/", s.handleReport)

	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Log the request
		s.logger.Trace("HTTP %s %s from %s", r.Method, r.URL.Path, r.RemoteAddr)

		// Basic authentication
		user, pass, ok := r.BasicAuth()
		if !ok {
			w.Header().Set("WWW-Authenticate", `Basic realm="ReportCom Server"`)
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		// Check credentials
		if expectedPass, exists := s.cfg.HTTP.Logins[user]; !exists || expectedPass != pass {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		// Call the handler
		mux.ServeHTTP(w, r)
	})
}

// handleClientList handles the client list endpoint
func (s *HTTPServer) handleClientList(w http.ResponseWriter, r *http.Request) {
	// Get the list of active connections
	connections := s.tcpServer.GetActiveConnections()

	// Create the response
	response := map[string]interface{}{
		"ResultCode":    0,
		"ResultMessage": "OK",
		"Clients":       connections,
	}

	// Write the response
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

// handleClientStatus handles the client status endpoint
func (s *HTTPServer) handleClientStatus(w http.ResponseWriter, r *http.Request) {
	// Get the client ID from the query parameters
	clientID := r.URL.Query().Get("id")
	if clientID == "" {
		http.Error(w, "Missing client ID", http.StatusBadRequest)
		return
	}

	// Get the connection for this client
	conn := s.tcpServer.GetConnection(clientID)
	if conn == nil {
		// Client not found
		response := map[string]interface{}{
			"ResultCode":    200,
			"ResultMessage": "Client is offline",
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(response)
		return
	}

	// Get detailed info
	info := conn.GetDetailedInfo()

	// Create the response
	response := map[string]interface{}{
		"ResultCode":    0,
		"ResultMessage": "OK",
		"Client":        info,
	}

	// Write the response
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

// handleReport handles the report endpoint
func (s *HTTPServer) handleReport(w http.ResponseWriter, r *http.Request) {
	// Extract the report name from the URL
	// The URL format is /report/REPORTNAME/
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) < 2 {
		http.Error(w, "Invalid report URL", http.StatusBadRequest)
		return
	}

	reportName := parts[1]

	// Get the client ID from the query parameters
	clientID := r.URL.Query().Get("id")
	if clientID == "" {
		http.Error(w, "Missing client ID", http.StatusBadRequest)
		return
	}

	// Optional HTTP credentials for the client
	httpUser := r.URL.Query().Get("u")
	httpPass := r.URL.Query().Get("p")

	// Get the connection for this client
	conn := s.tcpServer.GetConnection(clientID)
	if conn == nil {
		// Client not found
		response := map[string]interface{}{
			"ResultCode":    200,
			"ResultMessage": "Client is offline",
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(response)
		return
	}

	// Check if client is busy
	if conn.busy {
		response := map[string]interface{}{
			"ResultCode":    201,
			"ResultMessage": "Client is busy",
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(response)
		return
	}

	// Read the request body
	var requestBody string
	if r.ContentLength > 0 {
		// Read body from request
		buf := make([]byte, r.ContentLength)
		_, err := r.Body.Read(buf)
		if err != nil && err.Error() != "EOF" {
			s.logger.Error("Error reading request body: %v", err)
		}
		requestBody = string(buf)
	} else {
		// Create a JSON object from query parameters
		params := make(map[string]interface{})
		for key, values := range r.URL.Query() {
			if key != "id" && key != "u" && key != "p" {
				params[key] = values[0]
			}
		}

		// Convert to JSON
		jsonData, _ := json.Marshal(params)
		requestBody = string(jsonData)
	}

	// Send the request to the client
	response, err := s.tcpServer.SendRequest(clientID, requestBody, 30*time.Second)
	if err != nil {
		s.logger.Error("Error sending request to client: %v", err)
		errorResponse := map[string]interface{}{
			"ResultCode":    204,
			"ResultMessage": fmt.Sprintf("Error sending request to client: %v", err),
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(errorResponse)
		return
	}

	// Try to parse the response as JSON and pass it through
	var jsonResponse map[string]interface{}
	if err := json.Unmarshal([]byte(response), &jsonResponse); err != nil {
		// If it's not valid JSON, return the raw response
		w.Header().Set("Content-Type", "text/plain")
		w.Write([]byte(response))
	} else {
		// It's valid JSON, so return it
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(jsonResponse)
	}
}

// ErrorResponse is a standard error response for the HTTP API
type ErrorResponse struct {
	ResultCode    int    `json:"ResultCode"`
	ResultMessage string `json:"ResultMessage"`
}

// sendErrorResponse sends an error response with the given code and message
func sendErrorResponse(w http.ResponseWriter, code int, message string) {
	resp := ErrorResponse{
		ResultCode:    code,
		ResultMessage: message,
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK) // Always return 200 OK, error is in the JSON
	json.NewEncoder(w).Encode(resp)
} 
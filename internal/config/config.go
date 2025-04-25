package config

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"gopkg.in/ini.v1"
)

// Config holds all configuration for the ReportCom Server
type Config struct {
	Server struct {
		TraceLogEnabled bool
		UpdateFolder    string
	}

	TCP struct {
		Interface string
		Port      int
	}

	HTTP struct {
		Enabled   bool
		Interface string
		Port      int
		Logins    map[string]string
	}

	Auth struct {
		RestURL string
	}

	Registration struct {
		SerialNumber string
		Key          string
	}

	Paths struct {
		LogPath    string
		UpdatePath string
		BasePath   string
	}

	TimeOut struct {
		DropNoSerial int // seconds to drop connections without client ID
		DropNoActivity int // seconds to drop inactive authenticated connections
	}

	SpecialKeys map[int]struct {
		Key      string
		Length   int
		Override bool
	}
}

// LoadConfig loads the configuration from the specified INI file
func LoadConfig(path string) (*Config, error) {
	var cfg Config

	// Set default values
	cfg.Server.TraceLogEnabled = false
	cfg.Server.UpdateFolder = ""

	cfg.TCP.Interface = "0.0.0.0"
	cfg.TCP.Port = 9001

	cfg.HTTP.Enabled = true
	cfg.HTTP.Interface = "0.0.0.0"
	cfg.HTTP.Port = 9002
	cfg.HTTP.Logins = make(map[string]string)

	cfg.Auth.RestURL = "http://localhost/"

	cfg.TimeOut.DropNoSerial = 60    // 60 seconds
	cfg.TimeOut.DropNoActivity = 120 // 120 seconds

	// Create default special keys
	cfg.SpecialKeys = make(map[int]struct {
		Key      string
		Length   int
		Override bool
	})

	// Special keys configuration
	cfg.SpecialKeys[2] = struct {
		Key      string
		Length   int
		Override bool
	}{"D5F2aRD-", 1, true} // Special key for ID=2

	cfg.SpecialKeys[5] = struct {
		Key      string
		Length   int
		Override bool
	}{"D5F2cNE-", 1, true} // Special key for ID=5

	cfg.SpecialKeys[6] = struct {
		Key      string
		Length   int
		Override bool
	}{"D5F26NE-", 1, true} // Special key for ID=6

	cfg.SpecialKeys[8] = struct {
		Key      string
		Length   int
		Override bool
	}{"D028", 4, true} // Special key for ID=8

	cfg.SpecialKeys[9] = struct {
		Key      string
		Length   int
		Override bool
	}{"D5F22NE-", 2, true} // Special key for ID=9

	// Check if config file exists
	if _, err := os.Stat(path); os.IsNotExist(err) {
		return nil, fmt.Errorf("config file not found: %s", path)
	}

	// Load INI file
	iniFile, err := ini.Load(path)
	if err != nil {
		return nil, fmt.Errorf("cannot load config file: %v", err)
	}

	// Set base path based on executable location
	execPath, err := os.Executable()
	if err != nil {
		execPath = "."
	}
	cfg.Paths.BasePath = filepath.Dir(execPath)

	// [SRV_COMMON] section
	commonSec := iniFile.Section("SRV_COMMON")
	cfg.Server.TraceLogEnabled = commonSec.Key("TraceLogEnabled").MustBool(false)
	cfg.Server.UpdateFolder = commonSec.Key("UpdateFolder").String()

	// [SRV_HTTP] section
	httpSec := iniFile.Section("SRV_HTTP")
	cfg.HTTP.Enabled = httpSec.Key("HTTP_Enabled").MustBool(true)
	cfg.HTTP.Interface = httpSec.Key("HTTP_IPInterface").MustString("0.0.0.0")
	cfg.HTTP.Port = httpSec.Key("HTTP_Port").MustInt(9002)

	// [SRV_TCP] section
	tcpSec := iniFile.Section("SRV_TCP")
	cfg.TCP.Interface = tcpSec.Key("TCP_IPInterface").MustString("0.0.0.0")
	cfg.TCP.Port = tcpSec.Key("TCP_Port").MustInt(9001)

	// [SRV_AUTHSERVER] section
	authSec := iniFile.Section("SRV_AUTHSERVER")
	cfg.Auth.RestURL = authSec.Key("REST_URL").MustString("http://localhost/")

	// [REGISTRATION INFO] section
	regSec := iniFile.Section("REGISTRATION INFO")
	cfg.Registration.SerialNumber = regSec.Key("SERIAL NUMBER").String()
	cfg.Registration.Key = regSec.Key("KEY").String()

	// [SRV_HTTPLOGINS] section
	loginSec := iniFile.Section("SRV_HTTPLOGINS")
	for _, key := range loginSec.Keys() {
		cfg.HTTP.Logins[key.Name()] = key.String()
	}

	// If no logins defined, add a default
	if len(cfg.HTTP.Logins) == 0 {
		cfg.HTTP.Logins["user"] = "pass$123"
	}

	// Set paths
	if logPath := os.Getenv("LOG_PATH"); logPath != "" {
		cfg.Paths.LogPath = logPath
	} else {
		cfg.Paths.LogPath = filepath.Join(cfg.Paths.BasePath, "logs")
	}

	if updatePath := commonSec.Key("UpdateFolder").String(); updatePath != "" {
		cfg.Paths.UpdatePath = filepath.Join(cfg.Paths.BasePath, updatePath)
	} else {
		cfg.Paths.UpdatePath = filepath.Join(cfg.Paths.BasePath, "updates")
	}

	// Create directories if they don't exist
	os.MkdirAll(cfg.Paths.LogPath, 0755)
	os.MkdirAll(cfg.Paths.UpdatePath, 0755)

	return &cfg, nil
}

// Save writes the current configuration to the specified file
func (c *Config) Save(path string) error {
	file := ini.Empty()

	// [SRV_COMMON] section
	commonSec, _ := file.NewSection("SRV_COMMON")
	commonSec.NewKey("TraceLogEnabled", fmt.Sprintf("%t", c.Server.TraceLogEnabled))
	commonSec.NewKey("UpdateFolder", c.Server.UpdateFolder)

	// [SRV_HTTP] section
	httpSec, _ := file.NewSection("SRV_HTTP")
	httpSec.NewKey("HTTP_Enabled", fmt.Sprintf("%t", c.HTTP.Enabled))
	httpSec.NewKey("HTTP_IPInterface", c.HTTP.Interface)
	httpSec.NewKey("HTTP_Port", fmt.Sprintf("%d", c.HTTP.Port))

	// [SRV_TCP] section
	tcpSec, _ := file.NewSection("SRV_TCP")
	tcpSec.NewKey("TCP_IPInterface", c.TCP.Interface)
	tcpSec.NewKey("TCP_Port", fmt.Sprintf("%d", c.TCP.Port))

	// [SRV_AUTHSERVER] section
	authSec, _ := file.NewSection("SRV_AUTHSERVER")
	authSec.NewKey("REST_URL", c.Auth.RestURL)

	// [REGISTRATION INFO] section
	regSec, _ := file.NewSection("REGISTRATION INFO")
	regSec.NewKey("SERIAL NUMBER", c.Registration.SerialNumber)
	regSec.NewKey("KEY", c.Registration.Key)

	// [SRV_HTTPLOGINS] section
	loginSec, _ := file.NewSection("SRV_HTTPLOGINS")
	for user, pass := range c.HTTP.Logins {
		loginSec.NewKey(user, pass)
	}

	return file.SaveTo(path)
} 
-- DReport Database Schema

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS dreports;
USE dreports;

-- Settings table
CREATE TABLE IF NOT EXISTS `t_settings` (
  `s_id` int(11) NOT NULL AUTO_INCREMENT,
  `s_name` varchar(50) NOT NULL,
  `s_value` varchar(255) NOT NULL,
  `s_descr` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`s_id`),
  UNIQUE KEY `s_name_UNIQUE` (`s_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize with default settings
INSERT INTO `t_settings` (`s_name`, `s_value`, `s_descr`) VALUES
('rpt_server_host', 'localhost', 'Report server host address'),
('rpt_server_port', '2909', 'Report server TCP port'),
('rpt_server_user', 'admin', 'Report server username'),
('rpt_server_pswd', 'admin', 'Report server password'),
('log_level', '2', 'Logging level (0-3)');

-- Subscription table
CREATE TABLE IF NOT EXISTS `t_subscription` (
  `s_id` int(11) NOT NULL AUTO_INCREMENT,
  `s_objectid` varchar(50) NOT NULL,
  `s_objectname` varchar(255) DEFAULT NULL,
  `s_customername` varchar(255) DEFAULT NULL,
  `s_eik` varchar(50) DEFAULT NULL,
  `s_address` varchar(255) DEFAULT NULL,
  `s_hostname` varchar(255) DEFAULT NULL,
  `s_expiredate` date DEFAULT NULL,
  `s_active` tinyint(1) DEFAULT 1,
  `s_createdate` datetime DEFAULT CURRENT_TIMESTAMP,
  `s_lastupdatedate` datetime DEFAULT NULL,
  `s_appip` varchar(50) DEFAULT NULL,
  `s_apptype` varchar(50) DEFAULT NULL,
  `s_appver` varchar(50) DEFAULT NULL,
  `s_appdbtype` varchar(50) DEFAULT NULL,
  `s_comment` text DEFAULT NULL,
  PRIMARY KEY (`s_id`),
  UNIQUE KEY `s_objectid_UNIQUE` (`s_objectid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Statistics/Log table
CREATE TABLE IF NOT EXISTS `t_statistics` (
  `s_id` int(11) NOT NULL AUTO_INCREMENT,
  `s_opertype` int(11) NOT NULL,
  `s_operid` varchar(50) DEFAULT NULL,
  `s_description` text DEFAULT NULL,
  `s_logtime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`s_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IP Whitelist table
CREATE TABLE IF NOT EXISTS `t_ipwhitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_UNIQUE` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add localhost to whitelist by default
INSERT INTO `t_ipwhitelist` (`ip`, `description`, `active`) VALUES
('127.0.0.1', 'Localhost', 1),
('::1', 'Localhost IPv6', 1);

-- Devices table for authentication
CREATE TABLE IF NOT EXISTS `t_devices` (
  `d_id` int(11) NOT NULL AUTO_INCREMENT,
  `d_deviceid` varchar(50) NOT NULL,
  `d_objectid` varchar(50) NOT NULL,
  `d_name` varchar(255) DEFAULT NULL,
  `d_active` tinyint(1) DEFAULT 1,
  `d_createdate` datetime DEFAULT CURRENT_TIMESTAMP,
  `d_lastlogin` datetime DEFAULT NULL,
  PRIMARY KEY (`d_id`),
  UNIQUE KEY `d_deviceid_UNIQUE` (`d_deviceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 
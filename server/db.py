import logging
import os
from typing import Dict, Any, List, Optional
from datetime import datetime

logger = logging.getLogger(__name__)

class Database:
    def __init__(self):
        self.connected = False
        self.last_error = None
        self.last_successful_query = None
        self.connection_attempts = 0
        # Mock configuration for compatibility
        self.db_config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'port': int(os.getenv('DB_PORT', '3306')),
            'user': os.getenv('DB_USER', 'dreports'),
            'password': os.getenv('DB_PASSWORD', 'dreports'),
            'db': os.getenv('DB_NAME', 'dreports'),
            'autocommit': True,
            'charset': 'utf8mb4',
            'connect_timeout': 10
        }
        
    async def connect(self):
        """Mock connection to database."""
        try:
            self.connection_attempts += 1
            self.last_error = None
            
            # Log database connection parameters (without password)
            safe_config = self.db_config.copy()
            safe_config['password'] = '********'
            logger.info(f"[MOCK] Connecting to database with parameters: {safe_config}")
            
            # Simulate successful connection
            self.connected = True
            self.last_successful_query = datetime.now()
            logger.info("[MOCK] Database connection successful")
                        
        except Exception as e:
            self.connected = False
            self.last_error = str(e)
            logger.error(f"[MOCK] Database connection error (attempt {self.connection_attempts}): {e}")
            raise
            
    async def disconnect(self):
        """Mock closing database connection."""
        logger.info("[MOCK] Closing database connection")
        self.connected = False
            
    async def check_connection(self) -> bool:
        """Check if mock database connection is valid."""
        logger.info("[MOCK] Checking database connection")
        return self.connected
            
    async def execute(self, query: str, params: tuple = None) -> List[tuple]:
        """Execute mock SQL query."""
        logger.info(f"[MOCK] Executing query: {query} with params: {params}")
        self.last_successful_query = datetime.now()
        # Return empty result set
        return []
                
    async def generate_report(self, report_type: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """Generate mock report."""
        start_time = datetime.now()
        logger.info(f"[MOCK] Generating report type '{report_type}' with params: {params}")
        
        # Validate report type for API compatibility
        valid_types = ['daily', 'weekly', 'monthly', 'custom']
        if report_type not in valid_types:
            error_msg = f"Invalid report type: {report_type}"
            logger.error(error_msg)
            raise ValueError(error_msg)
        
        # Return empty mock report
        return {
            "report_type": report_type,
            "params": params,
            "rows": [],
            "generated_at": datetime.now().isoformat(),
            "mock": True
        } 
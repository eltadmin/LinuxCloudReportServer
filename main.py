import asyncio
import logging
import os
from pathlib import Path
from configparser import ConfigParser
from server.server import ReportServer
import logging.handlers
import time

# Configure logging with rotation
log_dir = Path('logs')
log_dir.mkdir(exist_ok=True)

# Create formatter
log_formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')

# Create rotating file handler (10MB per file, keep 10 files maximum)
rotating_handler = logging.handlers.RotatingFileHandler(
    'logs/server.log',
    maxBytes=10*1024*1024,  # 10MB
    backupCount=10,
    encoding='utf-8'
)
rotating_handler.setFormatter(log_formatter)

# Create console handler
console_handler = logging.StreamHandler()
console_handler.setFormatter(log_formatter)

# Configure root logger
logging.basicConfig(
    level=logging.INFO,
    handlers=[rotating_handler, console_handler]
)

logger = logging.getLogger(__name__)

def load_config(config_path: str) -> ConfigParser:
    """Load server configuration from INI file."""
    config = ConfigParser()
    config.read(config_path)
    return config

async def main():
    """Main entry point for the server."""
    try:
        start_time = time.time()
        logger.info("Starting Linux Cloud Report Server...")
        
        # Load configuration
        config_path = Path(os.getenv('CONFIG_PATH', 'config/eboCloudReportServer.ini'))
        if not config_path.exists():
            raise FileNotFoundError(f"Configuration file not found: {config_path}")
        
        config = load_config(str(config_path))
        
        # Create and start server
        server = ReportServer(config)
        await server.start()
        
        logger.info(f"Server started successfully in {time.time() - start_time:.2f} seconds")
        
        # Keep the server running
        while True:
            await asyncio.sleep(1)
            
    except Exception as e:
        logger.error(f"Server error: {e}", exc_info=True)
        raise

if __name__ == '__main__':
    try:
        logger.info("Starting server process")
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Server shutdown requested")
    except Exception as e:
        logger.error(f"Fatal error: {e}", exc_info=True)
        raise 
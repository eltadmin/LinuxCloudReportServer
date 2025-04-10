import asyncio
import logging
import os
from pathlib import Path
from configparser import ConfigParser
from server import ReportServer

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('logs/server.log'),
        logging.StreamHandler()
    ]
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
        # Load configuration
        config_path = Path(os.getenv('CONFIG_PATH', 'config/eboCloudReportServer.ini'))
        if not config_path.exists():
            raise FileNotFoundError(f"Configuration file not found: {config_path}")
        
        config = load_config(str(config_path))
        
        # Create and start server
        server = ReportServer(config)
        await server.start()
        
        # Keep the server running
        while True:
            await asyncio.sleep(1)
            
    except Exception as e:
        logger.error(f"Server error: {e}", exc_info=True)
        raise

if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Server shutdown requested")
    except Exception as e:
        logger.error(f"Fatal error: {e}", exc_info=True)
        raise 
import asyncio
import logging
from typing import Dict, Optional
import json
from aiohttp import web
import base64
from pathlib import Path
from datetime import datetime
import traceback
import time
import os

logger = logging.getLogger(__name__)

class HTTPServer:
    def __init__(self, report_server):
        self.report_server = report_server
        self.app = web.Application()
        self.runner = None
        self.site = None
        self.start_time = None
        self.request_count = 0
        self.error_count = 0
        
        # Setup routes
        self.app.router.add_get('/', self.handle_root)
        self.app.router.add_get('/status', self.handle_status)
        self.app.router.add_post('/report', self.handle_report)
        self.app.router.add_get('/updates', self.handle_updates)
        self.app.router.add_get('/download/{filename}', self.handle_download)
        self.app.router.add_get('/health', self.handle_health)
        
        # Add middleware for authentication and request logging
        self.app.middlewares.append(self.auth_middleware)
        self.app.middlewares.append(self.logging_middleware)
        
        # Add error handling
        self.app.on_startup.append(self.on_startup)
        self.app.on_shutdown.append(self.on_shutdown)
        
    async def on_startup(self, app):
        """Called when the application is starting up."""
        self.start_time = time.time()
        logger.info("HTTP Server starting up")
        
    async def on_shutdown(self, app):
        """Called when the application is shutting down."""
        uptime = time.time() - self.start_time if self.start_time else 0
        logger.info(f"HTTP Server shutting down. Uptime: {uptime:.2f}s, "
                    f"Processed {self.request_count} requests with {self.error_count} errors")
        
    async def start(self, host: str, port: int):
        """Start HTTP server."""
        try:
            logger.info(f"Starting HTTP server on {host}:{port}")
            self.runner = web.AppRunner(self.app)
            await self.runner.setup()
            self.site = web.TCPSite(self.runner, host, port)
            await self.site.start()
            logger.info(f"HTTP server started successfully on {host}:{port}")
        except Exception as e:
            logger.error(f"Failed to start HTTP server: {e}", exc_info=True)
            raise
        
    async def stop(self):
        """Stop HTTP server."""
        try:
            logger.info("Stopping HTTP server")
            if self.site:
                await self.site.stop()
                logger.info("HTTP site stopped")
            if self.runner:
                await self.runner.cleanup()
                logger.info("HTTP runner cleaned up")
        except Exception as e:
            logger.error(f"Error stopping HTTP server: {e}", exc_info=True)
            raise
            
    @web.middleware
    async def logging_middleware(self, request, handler):
        """Middleware to log requests and handle exceptions."""
        self.request_count += 1
        request_id = f"{self.request_count:08d}"
        start_time = time.time()
        client_ip = request.remote
        
        logger.info(f"[{request_id}] Request from {client_ip}: {request.method} {request.path}")
        
        try:
            response = await handler(request)
            duration = time.time() - start_time
            logger.info(f"[{request_id}] Response: {response.status} ({duration:.3f}s)")
            return response
        except web.HTTPException as e:
            # Expected HTTP exceptions (like 404, 401, etc.)
            self.error_count += 1
            duration = time.time() - start_time
            logger.warning(f"[{request_id}] HTTP Exception: {e.status} - {e.reason} ({duration:.3f}s)")
            raise
        except Exception as e:
            # Unexpected exceptions
            self.error_count += 1
            duration = time.time() - start_time
            logger.error(f"[{request_id}] Unhandled exception: {str(e)} ({duration:.3f}s)", exc_info=True)
            return web.json_response({
                'error': True,
                'message': str(e),
                'request_id': request_id
            }, status=500)
            
    @web.middleware
    async def auth_middleware(self, request: web.Request, handler):
        """Middleware to handle basic authentication."""
        # Skip authentication for certain paths
        public_paths = ['/', '/health']
        if request.path in public_paths:
            return await handler(request)
            
        auth = request.headers.get('Authorization')
        if not auth:
            logger.warning(f"Authentication missing for {request.path}")
            return self.unauthorized_response()
            
        try:
            scheme, credentials = auth.split()
            if scheme.lower() != 'basic':
                logger.warning(f"Invalid auth scheme: {scheme}")
                return self.unauthorized_response()
                
            decoded = base64.b64decode(credentials).decode('utf-8')
            username, password = decoded.split(':')
            
            authenticated = await self.report_server.verify_http_login(username, password)
            if not authenticated:
                logger.warning(f"Failed login attempt for user '{username}'")
                return self.unauthorized_response()
                
            logger.debug(f"User '{username}' authenticated successfully")
                
        except Exception as e:
            logger.error(f"Authentication error: {e}")
            return self.unauthorized_response()
            
        return await handler(request)
        
    def unauthorized_response(self):
        """Return unauthorized response."""
        response = web.Response(status=401)
        response.headers['WWW-Authenticate'] = 'Basic realm="Report Server"'
        return response
        
    async def handle_root(self, request: web.Request) -> web.Response:
        """Handle root endpoint."""
        server_info = {
            'service': 'Linux Cloud Report Server',
            'status': 'running',
            'uptime': time.time() - self.start_time if self.start_time else 0,
            'version': os.getenv('SERVER_VERSION', '1.0.0')
        }
        return web.json_response(server_info)
        
    async def handle_status(self, request: web.Request) -> web.Response:
        """Handle status endpoint."""
        try:
            status = self.report_server.get_server_status()
            status.update({
                'http_requests': self.request_count,
                'http_errors': self.error_count,
                'http_uptime': time.time() - self.start_time if self.start_time else 0
            })
            return web.json_response(status)
        except Exception as e:
            logger.error(f"Error generating status: {e}", exc_info=True)
            return web.json_response({
                'error': True,
                'message': f"Failed to get status: {str(e)}"
            }, status=500)
        
    async def handle_health(self, request: web.Request) -> web.Response:
        """Handle health check endpoint."""
        # Simple health check that doesn't require auth
        try:
            # Check if database is connected
            if not self.report_server.db_connected:
                return web.json_response({
                    'status': 'degraded',
                    'message': 'Database disconnected'
                }, status=503)
                
            return web.json_response({
                'status': 'healthy',
                'uptime': time.time() - self.start_time if self.start_time else 0
            })
        except Exception as e:
            logger.error(f"Health check failed: {e}")
            return web.json_response({
                'status': 'error',
                'message': str(e)
            }, status=500)
        
    async def handle_report(self, request: web.Request) -> web.Response:
        """Handle report generation endpoint."""
        try:
            data = await request.json()
            if 'type' not in data or 'params' not in data:
                logger.warning("Report request missing required fields")
                return web.json_response({
                    'error': True,
                    'message': "Missing required fields: 'type' and 'params'"
                }, status=400)
                
            logger.info(f"Generating report type '{data['type']}'")
            start_time = time.time()
            
            result = await self.report_server.db.generate_report(
                data['type'],
                data['params']
            )
            
            duration = time.time() - start_time
            logger.info(f"Report generation completed in {duration:.3f}s")
            
            # Check if there was an error in report generation
            if result.get('error', False):
                logger.warning(f"Report generation error: {result.get('message', 'Unknown error')}")
                return web.json_response(result, status=400)
                
            return web.json_response(result)
            
        except json.JSONDecodeError:
            logger.warning("Invalid JSON in report request")
            return web.json_response({
                'error': True,
                'message': "Invalid JSON format"
            }, status=400)
        except Exception as e:
            logger.error(f"Error handling report request: {e}", exc_info=True)
            return web.json_response({
                'error': True,
                'message': f"Server error: {str(e)}"
            }, status=500)
            
    async def handle_updates(self, request: web.Request) -> web.Response:
        """Handle updates list endpoint."""
        try:
            updates = []
            update_dir = Path(self.report_server.update_folder)
            
            if not update_dir.exists():
                logger.warning(f"Update directory does not exist: {update_dir}")
                return web.json_response({'updates': []})
                
            for file in update_dir.glob('*'):
                if file.is_file():
                    file_stat = file.stat()
                    updates.append({
                        'name': file.name,
                        'size': file_stat.st_size,
                        'modified': datetime.fromtimestamp(file_stat.st_mtime).isoformat(),
                        'url': f"/download/{file.name}"
                    })
                    
            logger.info(f"Listed {len(updates)} update files")
            return web.json_response({'updates': updates})
            
        except Exception as e:
            logger.error(f"Error listing updates: {e}", exc_info=True)
            return web.json_response({
                'error': True,
                'message': f"Failed to list updates: {str(e)}"
            }, status=500)
        
    async def handle_download(self, request: web.Request) -> web.Response:
        """Handle file download endpoint."""
        filename = request.match_info['filename']
        file_path = Path(self.report_server.update_folder) / filename
        
        try:
            if not file_path.exists() or not file_path.is_file():
                logger.warning(f"Download requested for non-existent file: {filename}")
                return web.json_response({
                    'error': True,
                    'message': "File not found"
                }, status=404)
                
            # Check if path traversal attempt
            if not str(file_path.resolve()).startswith(str(Path(self.report_server.update_folder).resolve())):
                logger.warning(f"Path traversal attempt detected: {filename}")
                return web.json_response({
                    'error': True,
                    'message': "Invalid filename"
                }, status=400)
                
            file_size = file_path.stat().st_size
            logger.info(f"Sending file {filename} ({file_size} bytes)")
            
            return web.FileResponse(file_path)
            
        except Exception as e:
            logger.error(f"Error downloading file {filename}: {e}", exc_info=True)
            return web.json_response({
                'error': True,
                'message': f"Failed to download file: {str(e)}"
            }, status=500) 
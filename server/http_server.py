import asyncio
import logging
from typing import Dict, Optional
import json
from aiohttp import web
import base64
from pathlib import Path
from datetime import datetime

logger = logging.getLogger(__name__)

class HTTPServer:
    def __init__(self, report_server):
        self.report_server = report_server
        self.app = web.Application()
        self.runner = None
        self.site = None
        
        # Setup routes
        self.app.router.add_get('/', self.handle_root)
        self.app.router.add_get('/status', self.handle_status)
        self.app.router.add_post('/report', self.handle_report)
        self.app.router.add_get('/updates', self.handle_updates)
        self.app.router.add_get('/download/{filename}', self.handle_download)
        
        # Add middleware for authentication
        self.app.middlewares.append(self.auth_middleware)
        
    async def start(self, host: str, port: int):
        """Start HTTP server."""
        self.runner = web.AppRunner(self.app)
        await self.runner.setup()
        self.site = web.TCPSite(self.runner, host, port)
        await self.site.start()
        
    async def stop(self):
        """Stop HTTP server."""
        if self.site:
            await self.site.stop()
        if self.runner:
            await self.runner.cleanup()
            
    @web.middleware
    async def auth_middleware(self, request: web.Request, handler):
        """Middleware to handle basic authentication."""
        if request.path == '/':
            return await handler(request)
            
        auth = request.headers.get('Authorization')
        if not auth:
            return self.unauthorized_response()
            
        try:
            scheme, credentials = auth.split()
            if scheme.lower() != 'basic':
                return self.unauthorized_response()
                
            decoded = base64.b64decode(credentials).decode('utf-8')
            username, password = decoded.split(':')
            
            if not await self.report_server.verify_http_login(username, password):
                return self.unauthorized_response()
                
        except Exception:
            return self.unauthorized_response()
            
        return await handler(request)
        
    def unauthorized_response(self):
        """Return unauthorized response."""
        response = web.Response(status=401)
        response.headers['WWW-Authenticate'] = 'Basic realm="Report Server"'
        return response
        
    async def handle_root(self, request: web.Request) -> web.Response:
        """Handle root endpoint."""
        return web.Response(text="Report Server is running")
        
    async def handle_status(self, request: web.Request) -> web.Response:
        """Handle status endpoint."""
        status = {
            'tcp_clients': len(self.report_server.tcp_server.connections),
            'update_files': len(list(Path(self.report_server.update_folder).glob('*'))),
            'trace_log_enabled': self.report_server.trace_log_enabled
        }
        return web.json_response(status)
        
    async def handle_report(self, request: web.Request) -> web.Response:
        """Handle report generation endpoint."""
        try:
            data = await request.json()
            if 'type' not in data or 'params' not in data:
                raise web.HTTPBadRequest(text="Missing required fields")
                
            result = await self.report_server.db.generate_report(
                data['type'],
                data['params']
            )
            return web.json_response(result)
            
        except json.JSONDecodeError:
            raise web.HTTPBadRequest(text="Invalid JSON")
            
    async def handle_updates(self, request: web.Request) -> web.Response:
        """Handle updates list endpoint."""
        updates = []
        for file in Path(self.report_server.update_folder).glob('*'):
            updates.append({
                'name': file.name,
                'size': file.stat().st_size,
                'modified': datetime.fromtimestamp(file.stat().st_mtime).isoformat()
            })
        return web.json_response({'updates': updates})
        
    async def handle_download(self, request: web.Request) -> web.Response:
        """Handle file download endpoint."""
        filename = request.match_info['filename']
        file_path = Path(self.report_server.update_folder) / filename
        
        if not file_path.exists():
            raise web.HTTPNotFound(text="File not found")
            
        return web.FileResponse(file_path) 
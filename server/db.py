import logging
import os
from typing import Dict, Any
import aiomysql
import json

logger = logging.getLogger(__name__)

class Database:
    def __init__(self):
        self.pool = None
        
    async def connect(self):
        """Connect to MySQL database."""
        try:
            self.pool = await aiomysql.create_pool(
                host=os.getenv('DB_HOST', 'localhost'),
                port=3306,
                user=os.getenv('DB_USER', 'dreports'),
                password=os.getenv('DB_PASSWORD', 'dreports'),
                db=os.getenv('DB_NAME', 'dreports'),
                autocommit=True
            )
            logger.info("Database connection established")
        except Exception as e:
            logger.error(f"Database connection error: {e}", exc_info=True)
            raise
            
    async def disconnect(self):
        """Close database connection."""
        if self.pool:
            self.pool.close()
            await self.pool.wait_closed()
            
    async def execute(self, query: str, params: tuple = None) -> Any:
        """Execute SQL query."""
        async with self.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(query, params)
                return await cur.fetchall()
                
    async def generate_report(self, report_type: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """Generate report based on type and parameters."""
        try:
            # Validate report type
            valid_types = ['daily', 'weekly', 'monthly', 'custom']
            if report_type not in valid_types:
                raise ValueError(f"Invalid report type: {report_type}")
                
            # Build query based on report type
            query = "SELECT * FROM reports WHERE "
            query_params = []
            
            if report_type == 'daily':
                query += "DATE(timestamp) = DATE(%s)"
                query_params.append(params.get('date'))
                
            elif report_type == 'weekly':
                query += "YEARWEEK(timestamp) = YEARWEEK(%s)"
                query_params.append(params.get('date'))
                
            elif report_type == 'monthly':
                query += "YEAR(timestamp) = YEAR(%s) AND MONTH(timestamp) = MONTH(%s)"
                date = params.get('date')
                query_params.extend([date, date])
                
            elif report_type == 'custom':
                query += "timestamp BETWEEN %s AND %s"
                query_params.extend([
                    params.get('start_date'),
                    params.get('end_date')
                ])
                
            # Add filters
            if 'client_id' in params:
                query += " AND client_id = %s"
                query_params.append(params['client_id'])
                
            if 'status' in params:
                query += " AND status = %s"
                query_params.append(params['status'])
                
            # Execute query
            results = await self.execute(query, tuple(query_params))
            
            # Process results
            report_data = []
            for row in results:
                report_data.append({
                    'id': row[0],
                    'client_id': row[1],
                    'timestamp': row[2].isoformat(),
                    'status': row[3],
                    'data': json.loads(row[4]) if row[4] else None
                })
                
            return {
                'type': report_type,
                'params': params,
                'count': len(report_data),
                'data': report_data
            }
            
        except Exception as e:
            logger.error(f"Report generation error: {e}", exc_info=True)
            raise 
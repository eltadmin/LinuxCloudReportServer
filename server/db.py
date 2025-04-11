import logging
import os
from typing import Dict, Any, List, Optional
import aiomysql
import json
import asyncio
import traceback
from datetime import datetime

logger = logging.getLogger(__name__)

class Database:
    def __init__(self):
        self.pool = None
        self.connected = False
        self.last_error = None
        self.last_successful_query = None
        self.connection_attempts = 0
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
        """Connect to MySQL database with retry logic."""
        try:
            self.connection_attempts += 1
            self.last_error = None
            
            # Log database connection parameters (without password)
            safe_config = self.db_config.copy()
            safe_config['password'] = '********'
            logger.info(f"Connecting to database with parameters: {safe_config}")
            
            self.pool = await aiomysql.create_pool(**self.db_config)
            
            # Test connection with a simple query
            async with self.pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute("SELECT 1")
                    result = await cur.fetchone()
                    if result and result[0] == 1:
                        self.connected = True
                        self.last_successful_query = datetime.now()
                        logger.info("Database connection test succeeded")
                    else:
                        raise Exception("Database connection test failed")
                        
        except Exception as e:
            self.connected = False
            self.last_error = str(e)
            logger.error(f"Database connection error (attempt {self.connection_attempts}): {e}", exc_info=True)
            
            # Close pool if it was created
            if self.pool:
                self.pool.close()
                await self.pool.wait_closed()
                self.pool = None
                
            raise
            
    async def disconnect(self):
        """Close database connection."""
        if self.pool:
            logger.info("Closing database connection pool")
            self.pool.close()
            await self.pool.wait_closed()
            self.pool = None
            self.connected = False
            
    async def check_connection(self) -> bool:
        """Check if database connection is still valid."""
        if not self.pool:
            logger.warning("No database pool exists")
            return False
            
        try:
            async with self.pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute("SELECT 1")
                    result = await cur.fetchone()
                    if result and result[0] == 1:
                        self.connected = True
                        self.last_successful_query = datetime.now()
                        return True
                    else:
                        logger.warning("Database connection check failed")
                        self.connected = False
                        return False
        except Exception as e:
            logger.error(f"Database connection check error: {e}")
            self.connected = False
            self.last_error = str(e)
            return False
            
    async def execute(self, query: str, params: tuple = None) -> List[tuple]:
        """Execute SQL query with automatic reconnection if needed."""
        if not self.pool:
            logger.error("Cannot execute query: No database pool")
            await self.connect()  # Try to reconnect
            
        retry_count = 0
        max_retries = 3
        last_error = None
        
        while retry_count < max_retries:
            try:
                async with self.pool.acquire() as conn:
                    async with conn.cursor() as cur:
                        await cur.execute(query, params)
                        result = await cur.fetchall()
                        self.last_successful_query = datetime.now()
                        return result
            except (aiomysql.OperationalError, aiomysql.pool.PoolError) as e:
                # These are connection-related errors - try to reconnect
                last_error = str(e)
                logger.warning(f"Database connection error (retry {retry_count+1}/{max_retries}): {e}")
                retry_count += 1
                
                # Close existing pool
                if self.pool:
                    self.pool.close()
                    await self.pool.wait_closed()
                    self.pool = None
                
                # Try to reconnect
                try:
                    await self.connect()
                except Exception as reconnect_error:
                    logger.error(f"Database reconnection failed: {reconnect_error}")
                
                await asyncio.sleep(1)  # Wait before retry
            except Exception as e:
                # Other non-connection errors, just log and raise
                logger.error(f"Database query error: {e}", exc_info=True)
                self.last_error = str(e)
                raise
                
        # If we get here, we've exhausted retries
        logger.error(f"Database query failed after {max_retries} retries: {last_error}")
        raise Exception(f"Database query failed: {last_error}")
                
    async def generate_report(self, report_type: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """Generate report based on type and parameters."""
        start_time = datetime.now()
        logger.info(f"Generating report type '{report_type}' with params: {params}")
        
        try:
            # Validate report type
            valid_types = ['daily', 'weekly', 'monthly', 'custom']
            if report_type not in valid_types:
                error_msg = f"Invalid report type: {report_type}"
                logger.error(error_msg)
                raise ValueError(error_msg)
                
            # Build query based on report type
            query = "SELECT * FROM reports WHERE "
            query_params = []
            
            if report_type == 'daily':
                if 'date' not in params:
                    raise ValueError("Missing 'date' parameter for daily report")
                query += "DATE(timestamp) = DATE(%s)"
                query_params.append(params.get('date'))
                
            elif report_type == 'weekly':
                if 'date' not in params:
                    raise ValueError("Missing 'date' parameter for weekly report")
                query += "YEARWEEK(timestamp) = YEARWEEK(%s)"
                query_params.append(params.get('date'))
                
            elif report_type == 'monthly':
                if 'date' not in params:
                    raise ValueError("Missing 'date' parameter for monthly report")
                query += "YEAR(timestamp) = YEAR(%s) AND MONTH(timestamp) = MONTH(%s)"
                date = params.get('date')
                query_params.extend([date, date])
                
            elif report_type == 'custom':
                if 'start_date' not in params or 'end_date' not in params:
                    raise ValueError("Missing 'start_date' or 'end_date' parameters for custom report")
                query += "timestamp BETWEEN %s AND %s"
                query_params.extend([
                    params.get('start_date'),
                    params.get('end_date')
                ])
                
            # Add filters
            if 'client_id' in params and params['client_id']:
                query += " AND client_id = %s"
                query_params.append(params['client_id'])
                
            if 'status' in params and params['status']:
                query += " AND status = %s"
                query_params.append(params['status'])
                
            # Add sorting
            query += " ORDER BY timestamp DESC"
            
            # Add limit if specified
            if 'limit' in params and params['limit']:
                try:
                    limit = int(params['limit'])
                    query += f" LIMIT {limit}"
                except (ValueError, TypeError):
                    logger.warning(f"Invalid limit parameter: {params['limit']}")
            
            # Execute query
            logger.debug(f"Executing query: {query} with params: {query_params}")
            results = await self.execute(query, tuple(query_params))
            
            # Process results
            report_data = []
            for row in results:
                try:
                    json_data = json.loads(row[4]) if row[4] else None
                except json.JSONDecodeError:
                    logger.warning(f"Invalid JSON data in record {row[0]}")
                    json_data = None
                    
                report_data.append({
                    'id': row[0],
                    'client_id': row[1],
                    'timestamp': row[2].isoformat() if row[2] else None,
                    'status': row[3],
                    'data': json_data
                })
                
            # Calculate query execution time
            execution_time = (datetime.now() - start_time).total_seconds()
            logger.info(f"Report generated with {len(report_data)} records in {execution_time:.2f} seconds")
                
            return {
                'type': report_type,
                'params': params,
                'count': len(report_data),
                'execution_time': execution_time,
                'data': report_data
            }
            
        except ValueError as e:
            # Parameter validation errors
            logger.error(f"Report parameter error: {e}")
            return {
                'error': True,
                'type': report_type,
                'message': str(e),
                'params': params
            }
        except Exception as e:
            # Other unexpected errors
            error_msg = f"Report generation error: {e}"
            logger.error(error_msg, exc_info=True)
            logger.error(traceback.format_exc())
            return {
                'error': True,
                'type': report_type,
                'message': error_msg,
                'params': params
            } 
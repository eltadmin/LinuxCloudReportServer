"""
Logger module for Cloud Report Server
"""

import datetime
import os
import threading
from typing import List, Optional

class Logger:
    """Logger class for handling log files"""
    
    def __init__(self, log_path: str, log_filename: Optional[str] = None):
        """
        Initialize the logger
        
        Args:
            log_path: Path to log files
            log_filename: Optional specific log file name (defaults to CloudReportLog.txt)
        """
        self.log_path = log_path
        self.log_filename = log_filename or "CloudReportLog.txt"
        self.lock = threading.Lock()
        
        # Create log directory if it doesn't exist
        os.makedirs(log_path, exist_ok=True)
    
    def log(self, message: str, include_timestamp: bool = True) -> None:
        """
        Log a message to the log file
        
        Args:
            message: Message to log
            include_timestamp: Whether to include timestamp in log
        """
        with self.lock:
            try:
                log_file_path = os.path.join(self.log_path, self.log_filename)
                
                # Check if log file exists and its size
                if os.path.exists(log_file_path):
                    file_size = os.path.getsize(log_file_path)
                    if file_size > 500 * 1024:  # 500 KB max size
                        self._rotate_log_file(log_file_path)
                
                # Format timestamp if needed
                timestamp = ""
                if include_timestamp:
                    timestamp = f"{datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')[:-3]} "
                
                # Write to log file
                with open(log_file_path, "a", encoding="utf-8") as f:
                    f.write(f"{timestamp}{message}\n")
            
            except Exception as e:
                # If we can't log, just print to stderr
                print(f"Error writing to log: {e}", flush=True)
    
    def _rotate_log_file(self, log_file_path: str) -> None:
        """
        Rotate log file when it gets too large
        
        Args:
            log_file_path: Path to log file
        """
        try:
            # Create new filename with timestamp
            timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
            base_name, ext = os.path.splitext(os.path.basename(log_file_path))
            new_filename = f"{timestamp}_{base_name}{ext}"
            new_path = os.path.join(self.log_path, new_filename)
            
            # Rename existing file
            os.rename(log_file_path, new_path)
            
            # Delete old log files (keep last 30 days)
            self._cleanup_old_logs()
            
        except Exception as e:
            # If rotation fails, just continue
            print(f"Error rotating log file: {e}", flush=True)
    
    def _cleanup_old_logs(self) -> None:
        """Clean up old log files (older than 30 days)"""
        try:
            # Get current time
            now = datetime.datetime.now()
            max_age = datetime.timedelta(days=30)
            
            # Get list of log files
            base_name, ext = os.path.splitext(os.path.basename(self.log_filename))
            
            # Iterate over log files
            for file_name in os.listdir(self.log_path):
                if file_name.endswith(ext) and file_name.find(base_name) > 0:
                    file_path = os.path.join(self.log_path, file_name)
                    
                    # Check file age
                    file_time = datetime.datetime.fromtimestamp(os.path.getmtime(file_path))
                    if now - file_time > max_age:
                        # Delete old file
                        os.remove(file_path)
        
        except Exception as e:
            # If cleanup fails, just continue
            print(f"Error cleaning up old logs: {e}", flush=True)
    
    def log_trace(self, method_name: str, messages: List[str]) -> None:
        """
        Log trace information with method name and messages
        
        Args:
            method_name: Name of the method
            messages: List of messages to log
        """
        with self.lock:
            try:
                # Save original filename
                original_filename = self.log_filename
                
                # Set trace log filename
                self.log_filename = "TraceLog_Server.txt"
                
                # Log method name and messages
                timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                self.log(f"[{timestamp}] {method_name}", include_timestamp=False)
                
                for message in messages:
                    self.log(f"  {message}", include_timestamp=False)
                
                # Restore original filename
                self.log_filename = original_filename
            
            except Exception as e:
                # If trace logging fails, just print to stderr
                print(f"Error writing to trace log: {e}", flush=True) 
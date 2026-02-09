"""
Logging configuration for LCMTV AI Services
"""
import logging
import sys
from pathlib import Path
from .config import settings

# Create logs directory
logs_dir = Path("logs")
logs_dir.mkdir(exist_ok=True)

# Configure logging
def setup_logging():
    """Setup structured logging for the application"""

    # Clear existing handlers
    logging.getLogger().handlers.clear()

    # Create formatters
    detailed_formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )

    json_formatter = logging.Formatter(
        '{"timestamp": "%(asctime)s", "level": "%(levelname)s", "service": "%(name)s", "message": "%(message)s"}'
    )

    # Console handler
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(detailed_formatter)
    console_handler.setLevel(logging.INFO)

    # File handler
    file_handler = logging.FileHandler(logs_dir / "ai_services.log")
    file_handler.setFormatter(detailed_formatter)
    file_handler.setLevel(logging.DEBUG)

    # JSON file handler for structured logs
    json_file_handler = logging.FileHandler(logs_dir / "ai_services.json")
    json_file_handler.setFormatter(json_formatter)
    json_file_handler.setLevel(logging.INFO)

    # Root logger configuration
    root_logger = logging.getLogger()
    root_logger.setLevel(getattr(logging, settings.log_level.upper(), logging.INFO))
    root_logger.addHandler(console_handler)
    root_logger.addHandler(file_handler)
    root_logger.addHandler(json_file_handler)

    # Service-specific loggers
    services = ['recommendation', 'search', 'analytics', 'database']

    for service in services:
        logger = logging.getLogger(f'lmtv_ai.{service}')
        logger.setLevel(logging.DEBUG)

    # Suppress noisy loggers
    logging.getLogger('sentence_transformers').setLevel(logging.WARNING)
    logging.getLogger('transformers').setLevel(logging.WARNING)
    logging.getLogger('torch').setLevel(logging.WARNING)

    return root_logger


def get_logger(name: str) -> logging.Logger:
    """Get a configured logger instance"""
    return logging.getLogger(f'lmtv_ai.{name}')


# Performance monitoring decorator
def log_performance(logger_name: str = "performance"):
    """Decorator to log function performance"""
    def decorator(func):
        logger = get_logger(logger_name)

        def wrapper(*args, **kwargs):
            import time
            start_time = time.time()

            try:
                result = func(*args, **kwargs)
                execution_time = time.time() - start_time
                logger.info(f"{func.__name__} completed in {execution_time:.3f}s")
                return result
            except Exception as e:
                execution_time = time.time() - start_time
                logger.error(f"{func.__name__} failed after {execution_time:.3f}s: {str(e)}")
                raise

        return wrapper
    return decorator

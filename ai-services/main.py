#!/usr/bin/env python3
"""
Main entry point for LCMTV AI Recommendation Service
"""
import uvicorn
from app.core.config import settings
from app.core.logging import setup_logging

if __name__ == "__main__":
    # Setup logging
    setup_logging()

    # Start the FastAPI server
    uvicorn.run(
        "app.services.recommendation_service:app",
        host="0.0.0.0",
        port=settings.recommendation_service_port,
        reload=True,
        log_level=settings.log_level.lower(),
        access_log=True
    )

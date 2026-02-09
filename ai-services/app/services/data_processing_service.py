"""
Data Processing Service for LCMTV AI
Handles periodic data processing and user profile updates
"""
from fastapi import FastAPI, BackgroundTasks
from pydantic import BaseModel
from typing import Dict, Any, Optional
import asyncio
from datetime import datetime
import json

from ..core.config import settings
from ..core.logging import setup_logging, get_logger
from ..utils.data_pipeline import data_pipeline

# Setup logging
setup_logging()
logger = get_logger("data_processing")

# Initialize FastAPI app
app = FastAPI(
    title="LCMTV Data Processing Service",
    description="Background data processing and user profile management",
    version="1.0.0"
)

# Global processing state
processing_status = {
    "is_running": False,
    "last_run": None,
    "next_run": None,
    "current_task": None,
    "progress": 0,
    "errors": []
}


class ProcessingRequest(BaseModel):
    task: str  # "update_profiles", "export_training_data", "full_refresh"
    days_back: Optional[int] = 30
    force_refresh: bool = False


class ProcessingStatus(BaseModel):
    is_running: bool
    last_run: Optional[str]
    next_run: Optional[str]
    current_task: Optional[str]
    progress: int
    errors: list
    status: str


@app.on_event("startup")
async def startup_event():
    """Initialize data processing service"""
    logger.info("Starting LCMTV Data Processing Service")

    # Schedule periodic tasks
    asyncio.create_task(schedule_periodic_tasks())


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "data_processing",
        "timestamp": datetime.now().isoformat()
    }


@app.get("/status", response_model=ProcessingStatus)
async def get_processing_status():
    """Get current processing status"""
    return ProcessingStatus(
        is_running=processing_status["is_running"],
        last_run=processing_status["last_run"],
        next_run=processing_status["next_run"],
        current_task=processing_status["current_task"],
        progress=processing_status["progress"],
        errors=processing_status["errors"][-10:],  # Last 10 errors
        status="running" if processing_status["is_running"] else "idle"
    )


@app.post("/process", response_model=ProcessingStatus)
async def trigger_processing(request: ProcessingRequest, background_tasks: BackgroundTasks):
    """Manually trigger data processing"""
    if processing_status["is_running"]:
        return ProcessingStatus(
            is_running=True,
            last_run=processing_status["last_run"],
            next_run=processing_status["next_run"],
            current_task=processing_status["current_task"],
            progress=processing_status["progress"],
            errors=["Processing already running"],
            status="busy"
        )

    # Start background processing
    background_tasks.add_task(run_processing_task, request.task, request.days_back, request.force_refresh)

    return ProcessingStatus(
        is_running=True,
        last_run=processing_status["last_run"],
        next_run=None,
        current_task=request.task,
        progress=0,
        errors=[],
        status="starting"
    )


async def run_processing_task(task: str, days_back: int = 30, force_refresh: bool = False):
    """Execute data processing task in background"""
    global processing_status

    try:
        processing_status["is_running"] = True
        processing_status["current_task"] = task
        processing_status["progress"] = 0
        processing_status["errors"] = []

        logger.info(f"Starting data processing task: {task}")

        if task == "update_profiles":
            await update_user_profiles(days_back)
        elif task == "export_training_data":
            await export_training_data(days_back)
        elif task == "full_refresh":
            await full_data_refresh()
        else:
            raise ValueError(f"Unknown task: {task}")

        processing_status["last_run"] = datetime.now().isoformat()
        logger.info(f"Data processing task completed: {task}")

    except Exception as e:
        error_msg = f"Processing task failed: {str(e)}"
        logger.error(error_msg)
        processing_status["errors"].append({
            "timestamp": datetime.now().isoformat(),
            "task": task,
            "error": error_msg
        })

    finally:
        processing_status["is_running"] = False
        processing_status["current_task"] = None
        processing_status["progress"] = 100


async def update_user_profiles(days_back: int):
    """Update user profiles with latest behavior data"""
    logger.info(f"Updating user profiles with {days_back} days of data")

    # Step 1: Collect interactions
    processing_status["progress"] = 10
    interactions_df = data_pipeline.collect_user_interactions(days_back)

    if interactions_df.empty:
        logger.warning("No interaction data found for profile updates")
        return

    # Step 2: Build user profiles
    processing_status["progress"] = 50
    user_profiles_df = data_pipeline.build_user_profiles(interactions_df)

    if user_profiles_df.empty:
        logger.warning("No user profiles generated")
        return

    # Step 3: Update database
    processing_status["progress"] = 80
    data_pipeline.update_user_profiles(user_profiles_df)

    processing_status["progress"] = 100
    logger.info(f"Updated profiles for {len(user_profiles_df)} users")


async def export_training_data(days_back: int):
    """Export training data for model updates"""
    logger.info(f"Exporting training data for {days_back} days")

    import os
    output_dir = "data/training"
    os.makedirs(output_dir, exist_ok=True)

    processing_status["progress"] = 25
    result = data_pipeline.export_training_data(output_dir, days_back)

    processing_status["progress"] = 100
    logger.info(f"Exported training data: {result}")


async def full_data_refresh():
    """Perform complete data refresh"""
    logger.info("Starting full data refresh")

    # Update profiles (30 days)
    processing_status["progress"] = 10
    await update_user_profiles(30)

    # Update profiles (90 days for training)
    processing_status["progress"] = 40
    await update_user_profiles(90)

    # Export training data
    processing_status["progress"] = 70
    await export_training_data(90)

    processing_status["progress"] = 100
    logger.info("Full data refresh completed")


async def schedule_periodic_tasks():
    """Schedule periodic data processing tasks"""
    while True:
        try:
            now = datetime.now()

            # Update user profiles daily at 2 AM
            if now.hour == 2 and now.minute == 0 and not processing_status["is_running"]:
                logger.info("Running scheduled user profile update")
                asyncio.create_task(run_processing_task("update_profiles", 30, False))

            # Full refresh weekly on Sunday at 3 AM
            elif (now.weekday() == 6 and now.hour == 3 and now.minute == 0
                  and not processing_status["is_running"]):
                logger.info("Running scheduled full data refresh")
                asyncio.create_task(run_processing_task("full_refresh", 90, False))

        except Exception as e:
            logger.error(f"Error in periodic task scheduler: {str(e)}")

        # Check every minute
        await asyncio.sleep(60)


@app.post("/admin/force-refresh")
async def force_full_refresh(background_tasks: BackgroundTasks):
    """Force immediate full data refresh (admin only)"""
    logger.warning("Admin requested force full refresh")

    background_tasks.add_task(run_processing_task, "full_refresh", 90, True)

    return {
        "status": "started",
        "message": "Full data refresh initiated",
        "timestamp": datetime.now().isoformat()
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "data_processing_service:app",
        host="0.0.0.0",
        port=8003,  # Different port from recommendation service
        reload=True,
        log_level="info"
    )

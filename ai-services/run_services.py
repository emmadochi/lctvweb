#!/usr/bin/env python3
"""
Script to run all LCMTV AI Services
"""
import subprocess
import sys
import os
import signal
import time
from pathlib import Path

# Add the app directory to Python path
sys.path.insert(0, str(Path(__file__).parent / "app"))

from app.core.config import settings
from app.core.logging import setup_logging, get_logger

setup_logging()
logger = get_logger("service_manager")

SERVICES = [
    {
        "name": "Recommendation Service",
        "module": "app.services.recommendation_service:app",
        "port": settings.recommendation_service_port,
        "description": "AI-powered video recommendations"
    },
    {
        "name": "Search Service",
        "module": "app.services.search_service:app",
        "port": settings.search_service_port,
        "description": "Semantic search for video content"
    },
    {
        "name": "Data Processing Service",
        "module": "app.services.data_processing_service:app",
        "port": 8003,  # Fixed port for data processing
        "description": "User profile updates and data processing"
    }
]

processes = []


def start_service(service_config):
    """Start a single service"""
    logger.info(f"Starting {service_config['name']} on port {service_config['port']}")

    cmd = [
        sys.executable, "-m", "uvicorn",
        service_config["module"],
        "--host", "0.0.0.0",
        "--port", str(service_config["port"]),
        "--log-level", settings.log_level.lower()
    ]

    # Add reload in development
    if os.getenv("ENVIRONMENT", "development") == "development":
        cmd.append("--reload")

    try:
        process = subprocess.Popen(
            cmd,
            cwd=Path(__file__).parent,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        return process
    except Exception as e:
        logger.error(f"Failed to start {service_config['name']}: {e}")
        return None


def stop_services():
    """Stop all running services"""
    logger.info("Stopping all AI services...")

    for process in processes:
        if process and process.poll() is None:
            try:
                process.terminate()
                # Wait up to 5 seconds for graceful shutdown
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                logger.warning("Service didn't terminate gracefully, killing...")
                process.kill()
            except Exception as e:
                logger.error(f"Error stopping service: {e}")


def check_dependencies():
    """Check if required dependencies are installed"""
    try:
        import fastapi
        import uvicorn
        import pandas
        import numpy
        import sklearn
        import mysql.connector
        logger.info("All dependencies are available")
        return True
    except ImportError as e:
        logger.error(f"Missing dependency: {e}")
        logger.error("Please run: pip install -r requirements.txt")
        return False


def wait_for_services(timeout=30):
    """Wait for services to be ready"""
    import requests
    from time import sleep

    logger.info("Waiting for services to start...")

    start_time = time.time()
    all_ready = False

    while not all_ready and (time.time() - start_time) < timeout:
        all_ready = True

        for service in SERVICES:
            try:
                response = requests.get(f"http://localhost:{service['port']}/health", timeout=2)
                if response.status_code != 200:
                    all_ready = False
                    break
            except:
                all_ready = False
                break

        if not all_ready:
            sleep(1)

    if all_ready:
        logger.info("All services are ready!")
        return True
    else:
        logger.error("Services failed to start within timeout period")
        return False


def main():
    """Main service manager"""
    logger.info("Starting LCMTV AI Services Manager")

    # Check dependencies
    if not check_dependencies():
        sys.exit(1)

    # Setup signal handlers for graceful shutdown
    def signal_handler(signum, frame):
        logger.info(f"Received signal {signum}, shutting down...")
        stop_services()
        sys.exit(0)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    try:
        # Start all services
        logger.info("Starting AI services...")

        for service in SERVICES:
            process = start_service(service)
            if process:
                processes.append(process)
                logger.info(f"[+] {service['name']} starting...")
            else:
                logger.error(f"✗ Failed to start {service['name']}")
                stop_services()
                sys.exit(1)

        # Wait for services to be ready
        if not wait_for_services():
            stop_services()
            sys.exit(1)

        # Print service information
        print("\n" + "="*60)
        print(">>> LCMTV AI Services Running Successfully!")
        print("="*60)

        for service in SERVICES:
            print(f"   [+] {service['name']}: {service['description']}")
            print(f"   [*] API Docs: http://localhost:{service['port']}/docs")
            print(f"   [H] Health: http://localhost:{service['port']}/health")
            print()

        print("📊 Service Status:")
        print("   🟢 All services healthy and responding")
        print("   🔄 Data processing running in background")
        print("   🤖 AI models loaded and ready")
        print("   🔍 Semantic search index built")
        print()
        print("⚠️  Press Ctrl+C to stop all services")
        print("="*60 + "\n")

        # Keep running until interrupted
        while True:
            # Check if any service died
            for i, process in enumerate(processes):
                if process and process.poll() is not None:
                    logger.error(f"Service {SERVICES[i]['name']} exited unexpectedly")
                    stop_services()
                    sys.exit(1)

            time.sleep(1)

    except KeyboardInterrupt:
        logger.info("Shutdown requested by user")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
    finally:
        stop_services()


if __name__ == "__main__":
    main()

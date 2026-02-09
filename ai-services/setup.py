#!/usr/bin/env python3
"""
Setup script for LCMTV AI Services
Handles initial configuration and database setup
"""
import os
import sys
import json
import argparse
from pathlib import Path
import mysql.connector
from mysql.connector import Error
import logging

# Setup basic logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


class AISetup:
    """AI Services setup and configuration"""

    def __init__(self):
        self.project_root = Path(__file__).parent.parent
        self.ai_root = Path(__file__).parent

    def check_requirements(self):
        """Check if all requirements are met"""
        logger.info("Checking system requirements...")

        # Check Python version
        if sys.version_info < (3, 8):
            logger.error("Python 3.8+ is required")
            return False

        # Check if in virtual environment (recommended)
        if not hasattr(sys, 'real_prefix') and sys.base_prefix == sys.prefix:
            logger.warning("Not running in virtual environment. Consider using venv or conda.")

        logger.info("✓ System requirements met")
        return True

    def create_env_file(self):
        """Create .env file with default configuration"""
        env_file = self.ai_root / ".env"

        if env_file.exists():
            response = input(".env file already exists. Overwrite? (y/N): ")
            if response.lower() != 'y':
                logger.info("Skipping .env creation")
                return

        logger.info("Creating .env configuration file...")

        env_config = f"""# LCMTV AI Services Configuration

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=lcmtv_db
DB_PORT=3306

# AI Service Configuration
RECOMMENDATION_PORT=8000
SEARCH_PORT=8001
ANALYTICS_PORT=8002

# ML Model Configuration
MODEL_CACHE_DIR=./models
SENTENCE_MODEL=all-MiniLM-L6-v2

# Performance Settings
MAX_RECOMMENDATIONS=20
SEARCH_TIMEOUT=5.0
CACHE_TTL=3600

# Security
CORS_ORIGINS=http://localhost:3000,http://localhost

# Logging
LOG_LEVEL=INFO

# Development Settings
ENVIRONMENT=development
"""

        with open(env_file, 'w') as f:
            f.write(env_config)

        logger.info(f"✓ Created .env file at {env_file}")
        logger.info("Please edit the .env file with your database credentials")

    def test_database_connection(self):
        """Test database connection"""
        try:
            # Load environment variables
            from dotenv import load_dotenv
            load_dotenv(self.ai_root / ".env")

            db_config = {
                'host': os.getenv('DB_HOST', 'localhost'),
                'user': os.getenv('DB_USER', 'root'),
                'password': os.getenv('DB_PASS', ''),
                'database': os.getenv('DB_NAME', 'lcmtv_db'),
                'port': int(os.getenv('DB_PORT', '3306'))
            }

            logger.info("Testing database connection...")
            conn = mysql.connector.connect(**db_config)

            # Test query
            cursor = conn.cursor()
            cursor.execute("SELECT COUNT(*) as user_count FROM users")
            result = cursor.fetchone()
            cursor.close()
            conn.close()

            logger.info(f"✓ Database connection successful. Found {result[0]} users.")
            return True

        except Error as e:
            logger.error(f"✗ Database connection failed: {e}")
            logger.error("Please check your database credentials in .env file")
            return False
        except Exception as e:
            logger.error(f"✗ Unexpected error: {e}")
            return False

    def create_ai_tables(self):
        """Create AI-specific database tables"""
        logger.info("Creating AI-specific database tables...")

        ai_tables_sql = """
        -- User preferences for personalization
        CREATE TABLE IF NOT EXISTS user_preferences (
            user_id INT PRIMARY KEY,
            preferred_categories JSON,
            watch_patterns JSON,
            time_preferences JSON,
            content_preferences JSON,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Recommendation cache for performance
        CREATE TABLE IF NOT EXISTS recommendation_cache (
            user_id INT,
            video_id INT,
            recommendation_score DECIMAL(5,4),
            recommendation_type ENUM('collaborative', 'content', 'trending', 'personal'),
            context_data JSON,
            expires_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (user_id, video_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            INDEX idx_cache_expires (expires_at),
            INDEX idx_cache_user (user_id)
        );

        -- A/B testing results
        CREATE TABLE IF NOT EXISTS recommendation_experiments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            experiment_name VARCHAR(100),
            variant_shown VARCHAR(50),
            recommendation_shown JSON,
            user_action ENUM('click', 'watch', 'skip', 'dismiss'),
            confidence_score DECIMAL(3,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_experiment_name (experiment_name),
            INDEX idx_experiment_action (user_action)
        );

        -- AI model performance tracking
        CREATE TABLE IF NOT EXISTS model_metrics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            model_name VARCHAR(100),
            model_version VARCHAR(50),
            metric_name VARCHAR(100),
            metric_value DECIMAL(10,4),
            dataset_size INT,
            training_time_seconds INT,
            evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_model_name (model_name),
            INDEX idx_model_version (model_version),
            INDEX idx_metric_name (metric_name)
        );

        -- Enhanced video view tracking
        ALTER TABLE video_views
        ADD COLUMN IF NOT EXISTS watch_percentage DECIMAL(5,2) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS interaction_score INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS context_tags JSON;

        -- Create indexes for performance
        CREATE INDEX IF NOT EXISTS idx_video_views_percentage ON video_views(watch_percentage);
        CREATE INDEX IF NOT EXISTS idx_video_views_interaction ON video_views(interaction_score);
        CREATE INDEX IF NOT EXISTS idx_user_prefs_updated ON user_preferences(updated_at);
        """

        try:
            # Load database config
            from dotenv import load_dotenv
            load_dotenv(self.ai_root / ".env")

            db_config = {
                'host': os.getenv('DB_HOST', 'localhost'),
                'user': os.getenv('DB_USER', 'root'),
                'password': os.getenv('DB_PASS', ''),
                'database': os.getenv('DB_NAME', 'lcmtv_db'),
                'port': int(os.getenv('DB_PORT', '3306'))
            }

            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor()

            # Execute table creation
            for statement in ai_tables_sql.strip().split(';'):
                if statement.strip():
                    cursor.execute(statement)

            conn.commit()
            cursor.close()
            conn.close()

            logger.info("✓ AI database tables created successfully")
            return True

        except Error as e:
            logger.error(f"✗ Failed to create AI tables: {e}")
            return False

    def initialize_sample_data(self):
        """Initialize sample user preferences for testing"""
        logger.info("Initializing sample user preferences...")

        try:
            from dotenv import load_dotenv
            load_dotenv(self.ai_root / ".env")

            import mysql.connector
            db_config = {
                'host': os.getenv('DB_HOST', 'localhost'),
                'user': os.getenv('DB_USER', 'root'),
                'password': os.getenv('DB_PASS', ''),
                'database': os.getenv('DB_NAME', 'lcmtv_db'),
                'port': int(os.getenv('DB_PORT', '3306'))
            }

            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor()

            # Get sample users
            cursor.execute("SELECT id FROM users LIMIT 5")
            users = cursor.fetchall()

            if not users:
                logger.warning("No users found in database. Skipping sample data initialization.")
                return True

            # Create sample preferences
            for user in users:
                user_id = user[0]

                # Sample preferences
                preferences = {
                    'preferred_categories': ['News', 'Sports', 'Music'],
                    'watch_patterns': {'avg_session_length': 30, 'preferred_content_length': 'medium'},
                    'time_preferences': {'preferred_hour': 20, 'preferred_day': 2, 'weekend_preference': 0.7},
                    'content_preferences': {'preferred_duration': 600, 'freshness_preference': 14}
                }

                cursor.execute("""
                    INSERT INTO user_preferences
                    (user_id, preferred_categories, watch_patterns, time_preferences, content_preferences)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                    preferred_categories = VALUES(preferred_categories),
                    watch_patterns = VALUES(watch_patterns),
                    time_preferences = VALUES(time_preferences),
                    content_preferences = VALUES(content_preferences)
                """, (
                    user_id,
                    json.dumps(preferences['preferred_categories']),
                    json.dumps(preferences['watch_patterns']),
                    json.dumps(preferences['time_preferences']),
                    json.dumps(preferences['content_preferences'])
                ))

            conn.commit()
            cursor.close()
            conn.close()

            logger.info(f"✓ Initialized sample preferences for {len(users)} users")
            return True

        except Exception as e:
            logger.error(f"✗ Failed to initialize sample data: {e}")
            return False

    def create_directories(self):
        """Create necessary directories"""
        directories = [
            self.ai_root / "models",
            self.ai_root / "logs",
            self.ai_root / "data",
            self.ai_root / "data" / "training"
        ]

        for directory in directories:
            directory.mkdir(parents=True, exist_ok=True)
            logger.info(f"✓ Created directory: {directory}")

    def run_full_setup(self):
        """Run complete setup process"""
        logger.info("🚀 Starting LCMTV AI Services Setup")
        logger.info("="*50)

        steps = [
            ("Checking requirements", self.check_requirements),
            ("Creating directories", self.create_directories),
            ("Creating .env file", self.create_env_file),
            ("Testing database connection", self.test_database_connection),
            ("Creating AI tables", self.create_ai_tables),
            ("Initializing sample data", self.initialize_sample_data)
        ]

        completed_steps = 0
        total_steps = len(steps)

        for step_name, step_func in steps:
            logger.info(f"[{completed_steps + 1}/{total_steps}] {step_name}...")

            try:
                if step_func():
                    completed_steps += 1
                    logger.info(f"✓ {step_name} completed")
                else:
                    logger.error(f"✗ {step_name} failed")
                    return False
            except Exception as e:
                logger.error(f"✗ {step_name} failed with error: {e}")
                return False

        logger.info("="*50)
        logger.info("🎉 LCMTV AI Services Setup Complete!")
        logger.info("")
        logger.info("Next steps:")
        logger.info("1. Review and update .env configuration")
        logger.info("2. Run: python run_services.py")
        logger.info("3. Visit: http://localhost:8000/docs (Recommendation API)")
        logger.info("4. Visit: http://localhost:8001/docs (Search API)")
        logger.info("5. Visit: http://localhost:8003/docs (Data Processing API)")
        logger.info("")
        logger.info("For development:")
        logger.info("- Run tests: python -m pytest tests/")
        logger.info("- View logs: tail -f logs/ai_services.log")

        return True


def main():
    parser = argparse.ArgumentParser(description="LCMTV AI Services Setup")
    parser.add_argument("--full", action="store_true", help="Run full setup")
    parser.add_argument("--env", action="store_true", help="Create .env file only")
    parser.add_argument("--db-test", action="store_true", help="Test database connection only")
    parser.add_argument("--tables", action="store_true", help="Create AI tables only")

    args = parser.parse_args()

    setup = AISetup()

    if args.full:
        success = setup.run_full_setup()
        sys.exit(0 if success else 1)
    elif args.env:
        setup.create_env_file()
    elif args.db_test:
        success = setup.test_database_connection()
        sys.exit(0 if success else 1)
    elif args.tables:
        success = setup.create_ai_tables()
        sys.exit(0 if success else 1)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()

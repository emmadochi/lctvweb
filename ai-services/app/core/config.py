"""
Configuration settings for LCMTV AI Services
"""
import os
from typing import Optional
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # Database Configuration
    db_host: str = os.getenv("DB_HOST", "localhost")
    db_user: str = os.getenv("DB_USER", "root")
    db_password: str = os.getenv("DB_PASS", "")
    db_name: str = os.getenv("DB_NAME", "lcmtv_db")
    db_port: int = int(os.getenv("DB_PORT", "3306"))

    # AI Service Configuration
    recommendation_service_port: int = int(os.getenv("RECOMMENDATION_PORT", "8000"))
    search_service_port: int = int(os.getenv("SEARCH_PORT", "8001"))
    analytics_service_port: int = int(os.getenv("ANALYTICS_PORT", "8002"))

    # ML Model Configuration
    model_cache_dir: str = os.getenv("MODEL_CACHE_DIR", "./models")
    sentence_transformer_model: str = os.getenv("SENTENCE_MODEL", "all-MiniLM-L6-v2")

    # Performance Settings
    max_recommendations: int = int(os.getenv("MAX_RECOMMENDATIONS", "20"))
    search_timeout: float = float(os.getenv("SEARCH_TIMEOUT", "5.0"))
    cache_ttl_seconds: int = int(os.getenv("CACHE_TTL", "3600"))  # 1 hour

    # Security
    cors_origins: list = os.getenv("CORS_ORIGINS", "http://localhost:3000,http://localhost").split(",")

    # Logging
    log_level: str = os.getenv("LOG_LEVEL", "INFO")

    class Config:
        env_file = ".env"
        case_sensitive = False


# Global settings instance
settings = Settings()

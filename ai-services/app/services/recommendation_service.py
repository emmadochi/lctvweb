"""
FastAPI service for LCMTV Recommendation Engine
"""
from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
import logging
from datetime import datetime
import asyncio

from ..core.config import settings
from ..core.logging import setup_logging, get_logger
from ..models.recommendation_engine import RecommendationEngine

# Setup logging
setup_logging()
logger = get_logger("recommendation_service")

# Initialize FastAPI app
app = FastAPI(
    title="LCMTV AI Recommendation Service",
    description="AI-powered video recommendation engine",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize recommendation engine
recommendation_engine = RecommendationEngine()


# Pydantic models
class RecommendationRequest(BaseModel):
    user_id: int = Field(..., description="User ID for recommendations")
    context_video_id: Optional[int] = Field(None, description="Currently watching video ID")
    limit: int = Field(10, ge=1, le=50, description="Number of recommendations to return")
    include_explanations: bool = Field(True, description="Include recommendation explanations")
    use_cache: bool = Field(True, description="Use cached recommendations if available")


class RecommendationResponse(BaseModel):
    recommendations: List[Dict[str, Any]] = Field(..., description="List of recommended videos")
    total_count: int = Field(..., description="Total number of recommendations")
    generated_at: str = Field(..., description="Timestamp when recommendations were generated")
    algorithm_version: str = Field(..., description="Version of recommendation algorithm")
    cache_used: bool = Field(..., description="Whether cached results were used")


class UserInsightsRequest(BaseModel):
    user_id: int
    days_back: int = Field(30, ge=1, le=365)


class UserInsightsResponse(BaseModel):
    user_id: int
    insights: Dict[str, Any]
    generated_at: str


@app.on_event("startup")
async def startup_event():
    """Initialize the recommendation engine on startup"""
    logger.info("Starting LCMTV Recommendation Service")
    try:
        # Pre-build matrices for better performance
        logger.info("Pre-building recommendation matrices...")
        recommendation_engine.build_user_item_matrix()
        recommendation_engine.calculate_item_similarity()
        logger.info("Recommendation engine initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize recommendation engine: {e}")


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    logger.info("Shutting down LCMTV Recommendation Service")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "recommendation_engine",
        "timestamp": datetime.now().isoformat(),
        "version": "1.0.0"
    }


@app.post("/api/v1/recommendations", response_model=RecommendationResponse)
async def get_recommendations(request: RecommendationRequest, background_tasks: BackgroundTasks):
    """Get personalized video recommendations"""
    start_time = datetime.now()
    logger.info(f"Processing recommendation request for user {request.user_id}")

    try:
        # Get recommendations
        recommendations = recommendation_engine.get_hybrid_recommendations(
            user_id=request.user_id,
            context_video_id=request.context_video_id,
            n_recommendations=request.limit
        )

        # Enrich with video metadata if requested
        if request.include_explanations:
            recommendations = await enrich_recommendations_with_metadata(recommendations)

        processing_time = (datetime.now() - start_time).total_seconds()

        logger.info(f"Generated {len(recommendations)} recommendations for user {request.user_id} in {processing_time:.3f}s")

        return RecommendationResponse(
            recommendations=recommendations,
            total_count=len(recommendations),
            generated_at=datetime.now().isoformat(),
            algorithm_version="1.0.0",
            cache_used=False  # TODO: Implement cache detection
        )

    except Exception as e:
        logger.error(f"Recommendation error for user {request.user_id}: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Recommendation failed: {str(e)}")


@app.post("/api/v1/recommendations/popular", response_model=RecommendationResponse)
async def get_popular_recommendations(limit: int = 10):
    """Get popular video recommendations (fallback)"""
    try:
        recommendations = recommendation_engine.get_popular_recommendations(limit)

        # Enrich with metadata
        recommendations = await enrich_recommendations_with_metadata(recommendations)

        return RecommendationResponse(
            recommendations=recommendations,
            total_count=len(recommendations),
            generated_at=datetime.now().isoformat(),
            algorithm_version="popular_fallback",
            cache_used=False
        )

    except Exception as e:
        logger.error(f"Popular recommendations error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get popular recommendations")


@app.post("/api/v1/user/insights", response_model=UserInsightsResponse)
async def get_user_insights(request: UserInsightsRequest):
    """Get user behavior insights"""
    try:
        insights = recommendation_engine.get_user_insights(request.user_id)

        return UserInsightsResponse(
            user_id=request.user_id,
            insights=insights,
            generated_at=datetime.now().isoformat()
        )

    except Exception as e:
        logger.error(f"User insights error for user {request.user_id}: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get user insights")


@app.post("/api/v1/admin/rebuild-matrices")
async def rebuild_matrices(background_tasks: BackgroundTasks):
    """Rebuild recommendation matrices (admin endpoint)"""
    logger.info("Starting matrix rebuild process")

    try:
        # Run in background to avoid blocking
        background_tasks.add_task(rebuild_matrices_background)

        return {
            "status": "started",
            "message": "Matrix rebuild initiated",
            "timestamp": datetime.now().isoformat()
        }

    except Exception as e:
        logger.error(f"Matrix rebuild error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to start matrix rebuild")


async def rebuild_matrices_background():
    """Background task to rebuild recommendation matrices"""
    try:
        logger.info("Rebuilding user-item matrix...")
        recommendation_engine.build_user_item_matrix()

        logger.info("Rebuilding item similarity matrix...")
        recommendation_engine.calculate_item_similarity()

        logger.info("Matrix rebuild completed successfully")

    except Exception as e:
        logger.error(f"Matrix rebuild failed: {str(e)}")


async def enrich_recommendations_with_metadata(recommendations: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """Enrich recommendations with video metadata"""
    if not recommendations:
        return recommendations

    video_ids = [rec['video_id'] for rec in recommendations]

    try:
        from ..core.database import get_video_metadata
        metadata = get_video_metadata(video_ids)

        # Merge metadata with recommendations
        for rec in recommendations:
            video_id = rec['video_id']
            if video_id in metadata:
                rec.update({
                    'title': metadata[video_id]['title'],
                    'thumbnail_url': metadata[video_id]['thumbnail_url'],
                    'channel_title': metadata[video_id]['channel_title'],
                    'duration': metadata[video_id]['duration'],
                    'view_count': metadata[video_id]['view_count']
                })

    except Exception as e:
        logger.warning(f"Failed to enrich recommendations with metadata: {str(e)}")

    return recommendations


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "recommendation_service:app",
        host="0.0.0.0",
        port=settings.recommendation_service_port,
        reload=True,
        log_level="info"
    )

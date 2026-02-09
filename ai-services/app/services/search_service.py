"""
FastAPI service for LCMTV Semantic Search
Provides AI-powered natural language video search
"""
from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional, Dict, Any
import logging
from datetime import datetime

from ..core.config import settings
from ..core.logging import setup_logging, get_logger
from ..models.semantic_search import SemanticSearchEngine

# Setup logging
setup_logging()
logger = get_logger("search_service")

# Initialize FastAPI app
app = FastAPI(
    title="LCMTV AI Search Service",
    description="Semantic search engine for video content",
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

# Initialize search engine
search_engine = SemanticSearchEngine()


# Pydantic models
class SearchRequest(BaseModel):
    query: str
    user_id: Optional[int] = None
    limit: int = 20
    include_metadata: bool = True
    search_type: str = "hybrid"  # "semantic", "hybrid"


class SearchResult(BaseModel):
    video_id: int
    title: str
    description: str
    channel_title: str
    category_name: Optional[str] = None
    similarity_score: float
    relevance_reason: str
    thumbnail_url: Optional[str] = None
    duration: Optional[int] = None
    view_count: Optional[int] = None
    published_at: Optional[str] = None
    days_since_publish: Optional[int] = None
    personalized: Optional[bool] = False


class SearchResponse(BaseModel):
    results: List[SearchResult]
    total_results: int
    search_time_ms: float
    query_processed: str
    search_type: str
    generated_at: str


class SimilarVideosRequest(BaseModel):
    video_id: int
    limit: int = 10


class SimilarVideosResponse(BaseModel):
    video_id: int
    similar_videos: List[Dict[str, Any]]
    total_similar: int
    generated_at: str


@app.on_event("startup")
async def startup_event():
    """Initialize the search engine on startup"""
    logger.info("Starting LCMTV Search Service")
    try:
        # Pre-build semantic index for better performance
        logger.info("Pre-building semantic search index...")
        search_engine.build_content_index()
        logger.info("Semantic search engine initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize search engine: {e}")


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    logger.info("Shutting down LCMTV Search Service")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    index_stats = search_engine.get_index_stats()

    return {
        "status": "healthy",
        "service": "semantic_search",
        "timestamp": datetime.now().isoformat(),
        "index_stats": index_stats
    }


@app.post("/api/v1/search", response_model=SearchResponse)
async def semantic_search(request: SearchRequest):
    """Perform semantic search on video content"""
    start_time = datetime.now()
    logger.info(f"Processing search request: '{request.query}' (user: {request.user_id})")

    try:
        # Perform search based on type
        if request.search_type == "semantic":
            raw_results = search_engine.semantic_search(
                query=request.query,
                top_k=request.limit,
                user_id=request.user_id
            )
        elif request.search_type == "hybrid":
            raw_results = search_engine.hybrid_search(
                query=request.query,
                user_id=request.user_id,
                top_k=request.limit
            )
        else:
            raise HTTPException(status_code=400, detail=f"Unknown search type: {request.search_type}")

        # Convert to response format
        results = []
        for result in raw_results:
            search_result = SearchResult(
                video_id=result['video_id'],
                title=result['title'],
                description=result['description'],
                channel_title=result['channel_title'],
                category_name=result.get('category_name'),
                similarity_score=result['similarity_score'],
                relevance_reason=result['relevance_reason'],
                thumbnail_url=result.get('thumbnail_url'),
                duration=result.get('duration'),
                view_count=result.get('view_count'),
                published_at=result.get('published_at'),
                days_since_publish=result.get('days_since_publish'),
                personalized=result.get('personalized', False)
            )
            results.append(search_result)

        search_time = (datetime.now() - start_time).total_seconds() * 1000

        logger.info(f"Search completed: {len(results)} results in {search_time:.1f}ms")

        return SearchResponse(
            results=results,
            total_results=len(results),
            search_time_ms=search_time,
            query_processed=request.query,
            search_type=request.search_type,
            generated_at=datetime.now().isoformat()
        )

    except Exception as e:
        logger.error(f"Search error for query '{request.query}': {str(e)}")
        raise HTTPException(status_code=500, detail=f"Search failed: {str(e)}")


@app.post("/api/v1/search/similar", response_model=SimilarVideosResponse)
async def find_similar_videos(request: SimilarVideosRequest):
    """Find videos similar to a given video"""
    try:
        logger.info(f"Finding videos similar to video {request.video_id}")

        similar_videos = search_engine.find_similar_videos(
            video_id=request.video_id,
            top_k=request.limit
        )

        return SimilarVideosResponse(
            video_id=request.video_id,
            similar_videos=similar_videos,
            total_similar=len(similar_videos),
            generated_at=datetime.now().isoformat()
        )

    except Exception as e:
        logger.error(f"Similar videos error for video {request.video_id}: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to find similar videos")


@app.post("/api/v1/search/rebuild-index")
async def rebuild_search_index(background_tasks: BackgroundTasks, force: bool = False):
    """Rebuild the semantic search index"""
    logger.info("Starting search index rebuild")

    try:
        # Run in background to avoid blocking
        background_tasks.add_task(rebuild_index_background, force)

        return {
            "status": "started",
            "message": "Search index rebuild initiated",
            "force_rebuild": force,
            "timestamp": datetime.now().isoformat()
        }

    except Exception as e:
        logger.error(f"Index rebuild error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to start index rebuild")


async def rebuild_index_background(force: bool = False):
    """Background task to rebuild search index"""
    try:
        logger.info("Rebuilding semantic search index...")
        search_engine.build_content_index(force_rebuild=force)
        logger.info("Search index rebuild completed successfully")

    except Exception as e:
        logger.error(f"Search index rebuild failed: {str(e)}")


@app.get("/api/v1/search/stats")
async def get_search_stats():
    """Get search engine statistics"""
    try:
        index_stats = search_engine.get_index_stats()

        return {
            "index_stats": index_stats,
            "model_info": {
                "model_name": search_engine.model_name,
                "cache_dir": str(search_engine.cache_dir)
            },
            "generated_at": datetime.now().isoformat()
        }

    except Exception as e:
        logger.error(f"Stats retrieval error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get search stats")


@app.get("/api/v1/search/suggestions")
async def get_search_suggestions(query: str, limit: int = 5):
    """Get search suggestions based on partial query"""
    try:
        # This would implement autocomplete functionality
        # For now, return some basic suggestions
        suggestions = [
            f"{query} videos",
            f"{query} tutorial",
            f"{query} news",
            f"{query} documentary",
            f"latest {query}"
        ][:limit]

        return {
            "query": query,
            "suggestions": suggestions,
            "generated_at": datetime.now().isoformat()
        }

    except Exception as e:
        logger.error(f"Suggestions error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get suggestions")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "search_service:app",
        host="0.0.0.0",
        port=settings.search_service_port,
        reload=True,
        log_level="info"
    )

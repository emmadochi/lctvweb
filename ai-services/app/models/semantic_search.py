"""
Semantic Search Engine for LCMTV
Uses vector embeddings for natural language video search
"""
import pandas as pd
import numpy as np
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
from typing import List, Dict, Any, Optional, Tuple
import logging
import json
import os
from pathlib import Path

from ..core.database import execute_query, get_db_connection
from ..core.logging import get_logger
from ..core.config import settings

logger = get_logger("semantic_search")


class SemanticSearchEngine:
    """AI-powered semantic search using vector embeddings"""

    def __init__(self, model_name: str = None):
        self.model_name = model_name or settings.sentence_transformer_model
        self.model = None
        self.video_embeddings = None
        self.video_data = None
        self.index_built = False

        # Create model cache directory
        self.cache_dir = Path(settings.model_cache_dir)
        self.cache_dir.mkdir(parents=True, exist_ok=True)

    def load_model(self):
        """Load the sentence transformer model"""
        if self.model is None:
            logger.info(f"Loading semantic search model: {self.model_name}")
            try:
                self.model = SentenceTransformer(
                    self.model_name,
                    cache_folder=str(self.cache_dir)
                )
                logger.info("✓ Semantic search model loaded successfully")
            except Exception as e:
                logger.error(f"Failed to load semantic search model: {e}")
                raise

    def build_content_index(self, force_rebuild: bool = False) -> Tuple[np.ndarray, pd.DataFrame]:
        """Build semantic index of all video content"""
        logger.info("Building semantic content index")

        # Check if index already exists and is recent
        index_file = self.cache_dir / "content_index.npz"
        data_file = self.cache_dir / "content_data.pkl"

        if not force_rebuild and index_file.exists() and data_file.exists():
            try:
                # Load cached index
                cached_data = np.load(index_file)
                self.video_embeddings = cached_data['embeddings']
                self.video_data = pd.read_pickle(data_file)

                # Check if data is still valid (basic check)
                if len(self.video_data) > 0:
                    logger.info(f"Loaded cached semantic index with {len(self.video_data)} videos")
                    self.index_built = True
                    return self.video_embeddings, self.video_data
            except Exception as e:
                logger.warning(f"Failed to load cached index: {e}")

        # Build new index
        self.load_model()

        # Fetch all active videos with metadata
        query = """
        SELECT
            v.id,
            v.title,
            v.description,
            v.tags,
            v.channel_title,
            v.category_id,
            c.name as category_name,
            v.view_count,
            v.like_count,
            v.duration,
            v.published_at,
            TIMESTAMPDIFF(DAY, v.published_at, NOW()) as days_since_publish
        FROM videos v
        LEFT JOIN categories c ON v.category_id = c.id
        WHERE v.is_active = 1 AND v.title IS NOT NULL
        ORDER BY v.view_count DESC, v.published_at DESC
        """

        results = execute_query(query)

        if not results:
            logger.warning("No video data found for semantic indexing")
            return np.array([]), pd.DataFrame()

        # Convert to DataFrame
        df = pd.DataFrame(results)

        # Create searchable content for each video
        logger.info("Creating searchable content representations")
        df['searchable_content'] = df.apply(self._create_searchable_content, axis=1)

        # Generate embeddings
        logger.info(f"Generating embeddings for {len(df)} videos")
        embeddings = self.model.encode(
            df['searchable_content'].tolist(),
            show_progress_bar=True,
            batch_size=32
        )

        # Store results
        self.video_embeddings = embeddings
        self.video_data = df
        self.index_built = True

        # Cache the index
        try:
            np.savez_compressed(index_file, embeddings=embeddings)
            df.to_pickle(data_file)
            logger.info("✓ Semantic index cached successfully")
        except Exception as e:
            logger.warning(f"Failed to cache semantic index: {e}")

        logger.info(f"✓ Built semantic index with {len(df)} videos")
        return embeddings, df

    def _create_searchable_content(self, row: pd.Series) -> str:
        """Create comprehensive searchable content from video data"""
        content_parts = []

        # Title (most important - repeat for emphasis)
        if row['title']:
            content_parts.extend([str(row['title'])] * 3)

        # Description
        if row['description']:
            content_parts.append(str(row['description']))

        # Tags (if stored as JSON)
        if row['tags']:
            try:
                if isinstance(row['tags'], str):
                    tags = json.loads(row['tags'])
                else:
                    tags = row['tags']

                if isinstance(tags, list):
                    content_parts.extend([str(tag) for tag in tags])
                elif isinstance(tags, str):
                    content_parts.append(tags)
            except:
                content_parts.append(str(row['tags']))

        # Channel name
        if row['channel_title']:
            content_parts.append(f"by {row['channel_title']}")

        # Category
        if row['category_name']:
            content_parts.append(f"category {row['category_name']}")

        # Content type hints based on duration
        duration = row.get('duration', 0)
        if duration:
            if duration < 300:  # 5 minutes
                content_parts.append("short video")
            elif duration < 1800:  # 30 minutes
                content_parts.append("medium video")
            else:
                content_parts.append("long video")

        # Freshness indicator
        days_old = row.get('days_since_publish', 0)
        if days_old < 1:
            content_parts.append("new video")
        elif days_old < 7:
            content_parts.append("recent video")
        elif days_old < 30:
            content_parts.append("this month")
        else:
            content_parts.append("older video")

        return ' '.join(content_parts)

    def semantic_search(
        self,
        query: str,
        top_k: int = 20,
        threshold: float = 0.1,
        user_id: Optional[int] = None
    ) -> List[Dict[str, Any]]:
        """Perform semantic search on video content"""
        if not self.index_built:
            self.build_content_index()

        if self.video_embeddings is None or len(self.video_embeddings) == 0:
            logger.warning("No semantic index available for search")
            return []

        self.load_model()

        logger.info(f"Performing semantic search for: '{query}'")

        # Encode query
        query_embedding = self.model.encode([query])[0]

        # Calculate similarities
        similarities = cosine_similarity([query_embedding], self.video_embeddings)[0]

        # Get top results above threshold
        top_indices = np.argsort(similarities)[::-1]
        top_indices = top_indices[similarities[top_indices] > threshold][:top_k]

        results = []
        for idx in top_indices:
            video = self.video_data.iloc[idx]

            # Get additional metadata for enriched results
            video_details = self._get_video_details(video['id'])

            result = {
                'video_id': int(video['id']),
                'title': str(video['title']),
                'description': str(video.get('description', ''))[:200],
                'channel_title': str(video.get('channel_title', '')),
                'category_name': str(video.get('category_name', '')),
                'similarity_score': float(similarities[idx]),
                'relevance_reason': self._generate_relevance_reason(query, video, similarities[idx]),
                'thumbnail_url': video_details.get('thumbnail_url'),
                'duration': video_details.get('duration'),
                'view_count': video_details.get('view_count'),
                'published_at': str(video.get('published_at', '')),
                'days_since_publish': int(video.get('days_since_publish', 0))
            }

            results.append(result)

        logger.info(f"Found {len(results)} semantic search results")
        return results

    def _get_video_details(self, video_id: int) -> Dict[str, Any]:
        """Get additional video details for search results"""
        try:
            query = """
            SELECT thumbnail_url, duration, view_count, published_at
            FROM videos WHERE id = %s
            """
            result = execute_query(query, (video_id,))
            return result[0] if result else {}
        except Exception as e:
            logger.warning(f"Failed to get video details for {video_id}: {e}")
            return {}

    def _generate_relevance_reason(self, query: str, video: pd.Series, score: float) -> str:
        """Generate human-readable relevance explanation"""
        reasons = []
        query_lower = query.lower()

        # Title match
        title_lower = str(video.get('title', '')).lower()
        if query_lower in title_lower:
            reasons.append("Title matches your search")

        # Description match
        desc_lower = str(video.get('description', '')).lower()
        if query_lower in desc_lower:
            reasons.append("Description contains relevant content")

        # Category match
        category_lower = str(video.get('category_name', '')).lower()
        if query_lower in category_lower:
            reasons.append(f"From {video.get('category_name')} category")

        # Channel match
        channel_lower = str(video.get('channel_title', '')).lower()
        if query_lower in channel_lower:
            reasons.append(f"By {video.get('channel_title')}")

        # Tag matches
        if video.get('tags'):
            try:
                tags = video['tags']
                if isinstance(tags, str):
                    tags = json.loads(tags)

                if isinstance(tags, list):
                    matching_tags = [tag for tag in tags if query_lower in str(tag).lower()]
                    if matching_tags:
                        reasons.append(f"Tags: {', '.join(matching_tags[:2])}")
            except:
                pass

        # Content type hints
        duration = video.get('duration', 0)
        if duration:
            if 'short' in query_lower and duration < 300:
                reasons.append("Short video format")
            elif 'long' in query_lower and duration > 1800:
                reasons.append("Long-form content")

        # Freshness hints
        days_old = video.get('days_since_publish', 0)
        if 'new' in query_lower and days_old < 1:
            reasons.append("Recently published")
        elif 'recent' in query_lower and days_old < 7:
            reasons.append("Published this week")

        # Semantic similarity score
        if score > 0.8:
            reasons.append("Highly relevant content")
        elif score > 0.6:
            reasons.append("Semantically similar")
        else:
            reasons.append("Related topic")

        return " • ".join(reasons[:3])  # Limit to top 3 reasons

    def hybrid_search(
        self,
        query: str,
        user_id: Optional[int] = None,
        top_k: int = 20
    ) -> List[Dict[str, Any]]:
        """Combine semantic search with personalization"""
        semantic_results = self.semantic_search(query, top_k=top_k, user_id=user_id)

        if user_id:
            # Add personalization based on user preferences
            semantic_results = self._personalize_search_results(semantic_results, user_id)

        return semantic_results

    def _personalize_search_results(self, results: List[Dict[str, Any]], user_id: int) -> List[Dict[str, Any]]:
        """Personalize search results based on user preferences"""
        try:
            # Get user preferences
            pref_query = "SELECT preferred_categories, time_preferences FROM user_preferences WHERE user_id = %s"
            pref_result = execute_query(pref_query, (user_id,))

            if not pref_result:
                return results

            preferences = pref_result[0]

            # Parse preferred categories
            preferred_categories = []
            if preferences.get('preferred_categories'):
                try:
                    preferred_categories = json.loads(preferences['preferred_categories'])
                except:
                    pass

            # Boost results from preferred categories
            for result in results:
                category_name = result.get('category_name', '')
                if category_name in preferred_categories:
                    result['similarity_score'] *= 1.3  # Boost score
                    result['personalized'] = True
                    result['relevance_reason'] += " • Matches your preferences"

            # Sort by boosted scores
            results.sort(key=lambda x: x['similarity_score'], reverse=True)

        except Exception as e:
            logger.warning(f"Failed to personalize search results: {e}")

        return results

    def find_similar_videos(self, video_id: int, top_k: int = 10) -> List[Dict[str, Any]]:
        """Find videos similar to a given video"""
        if not self.index_built:
            self.build_content_index()

        try:
            # Get the video's embedding
            video_idx = self.video_data[self.video_data['id'] == video_id].index
            if len(video_idx) == 0:
                return []

            video_embedding = self.video_embeddings[video_idx[0]]

            # Calculate similarities with all other videos
            similarities = cosine_similarity([video_embedding], self.video_embeddings)[0]

            # Get top similar videos (excluding the video itself)
            top_indices = np.argsort(similarities)[::-1]
            top_indices = top_indices[top_indices != video_idx[0]][:top_k]

            results = []
            for idx in top_indices:
                video = self.video_data.iloc[idx]
                results.append({
                    'video_id': int(video['id']),
                    'title': str(video['title']),
                    'similarity_score': float(similarities[idx]),
                    'reason': 'Similar content and theme'
                })

            return results

        except Exception as e:
            logger.error(f"Failed to find similar videos: {e}")
            return []

    def update_index(self, video_ids: Optional[List[int]] = None):
        """Update the semantic index for specific videos or rebuild completely"""
        if video_ids is None:
            # Full rebuild
            logger.info("Performing full semantic index rebuild")
            self.build_content_index(force_rebuild=True)
        else:
            # Partial update (more complex, would need incremental updates)
            logger.info(f"Partial index update requested for {len(video_ids)} videos")
            # For now, trigger full rebuild
            self.build_content_index(force_rebuild=True)

    def get_index_stats(self) -> Dict[str, Any]:
        """Get statistics about the semantic index"""
        if not self.index_built:
            return {"status": "not_built"}

        return {
            "status": "ready",
            "total_videos": len(self.video_data) if self.video_data is not None else 0,
            "embedding_dimensions": self.video_embeddings.shape[1] if self.video_embeddings is not None else 0,
            "model_name": self.model_name,
            "cache_dir": str(self.cache_dir)
        }

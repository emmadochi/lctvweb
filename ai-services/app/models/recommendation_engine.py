"""
Recommendation Engine for LCMTV
Implements collaborative filtering and content-based recommendations
"""
import pandas as pd
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.preprocessing import StandardScaler
from typing import List, Dict, Any, Optional, Tuple
import logging
from datetime import datetime, timedelta
from ..core.database import execute_query, get_user_behavior_data, update_recommendation_cache, get_cached_recommendations
from ..core.logging import get_logger

logger = get_logger("recommendation")


class RecommendationEngine:
    """AI-powered recommendation engine using collaborative filtering"""

    def __init__(self):
        self.user_item_matrix = None
        self.item_similarity_matrix = None
        self.user_data_cache = {}
        self.cache_timeout = 3600  # 1 hour

    def build_user_item_matrix(self) -> pd.DataFrame:
        """Build user-item interaction matrix from video_views data"""
        logger.info("Building user-item interaction matrix")

        query = """
        SELECT
            vv.user_id,
            vv.video_id,
            (vv.watch_duration / NULLIF(vv.total_duration, 0)) * 100 as watch_percentage,
            CASE
                WHEN vv.completed = 1 THEN 5.0  -- Completed video
                WHEN vv.watch_percentage >= 75 THEN 4.0  -- High engagement
                WHEN vv.watch_percentage >= 50 THEN 3.0  -- Medium engagement
                WHEN vv.watch_percentage >= 25 THEN 2.0  -- Low engagement
                ELSE 1.0  -- Minimal engagement
            END as interaction_score,
            TIMESTAMPDIFF(DAY, vv.created_at, NOW()) as days_since_watch
        FROM video_views vv
        JOIN videos v ON vv.video_id = v.id
        WHERE vv.user_id IS NOT NULL
        AND v.is_active = 1
        AND vv.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)  -- Last 90 days
        """

        results = execute_query(query)

        if not results:
            logger.warning("No user interaction data found")
            return pd.DataFrame()

        df = pd.DataFrame(results)

        # Apply time decay (recent interactions weigh more)
        df['time_weight'] = np.exp(-df['days_since_watch'] / 30)  # 30-day half-life
        df['weighted_score'] = df['interaction_score'] * df['time_weight']

        # Create pivot table
        self.user_item_matrix = df.pivot_table(
            index='user_id',
            columns='video_id',
            values='weighted_score',
            aggfunc='max',  # Take the highest score for each user-video pair
            fill_value=0
        )

        logger.info(f"Built user-item matrix: {self.user_item_matrix.shape[0]} users x {self.user_item_matrix.shape[1]} videos")
        return self.user_item_matrix

    def calculate_item_similarity(self) -> np.ndarray:
        """Calculate cosine similarity between items"""
        logger.info("Calculating item similarity matrix")

        if self.user_item_matrix is None:
            self.build_user_item_matrix()

        if self.user_item_matrix.empty:
            return np.array([])

        # Transpose for item-to-item similarity
        item_user_matrix = self.user_item_matrix.T

        # Calculate cosine similarity
        self.item_similarity_matrix = cosine_similarity(item_user_matrix)

        logger.info(f"Calculated similarity matrix for {self.item_similarity_matrix.shape[0]} items")
        return self.item_similarity_matrix

    def get_collaborative_recommendations(
        self,
        user_id: int,
        n_recommendations: int = 10,
        min_similarity_threshold: float = 0.1
    ) -> List[Dict[str, Any]]:
        """Generate collaborative filtering recommendations"""
        logger.info(f"Generating collaborative recommendations for user {user_id}")

        if self.user_item_matrix is None:
            self.build_user_item_matrix()

        if self.user_item_matrix.empty or user_id not in self.user_item_matrix.index:
            logger.info(f"No collaborative data for user {user_id}, using popular recommendations")
            return self.get_popular_recommendations(n_recommendations)

        user_ratings = self.user_item_matrix.loc[user_id]

        # Find videos the user has interacted with
        watched_videos = user_ratings[user_ratings > 0].index.tolist()

        if not watched_videos:
            return self.get_popular_recommendations(n_recommendations)

        # Calculate recommendation scores
        recommendations = {}

        for video_id in watched_videos:
            user_rating = user_ratings[video_id]

            if video_id not in self.user_item_matrix.columns:
                continue

            # Find similar videos
            video_idx = self.user_item_matrix.columns.get_loc(video_id)
            similar_scores = self.item_similarity_matrix[video_idx]

            # Weight by user's rating and similarity
            for idx, similarity in enumerate(similar_scores):
                if similarity < min_similarity_threshold:
                    continue

                similar_video_id = self.user_item_matrix.columns[idx]

                # Don't recommend videos already watched
                if similar_video_id in watched_videos:
                    continue

                weighted_score = similarity * user_rating

                if similar_video_id not in recommendations:
                    recommendations[similar_video_id] = {
                        'score': 0,
                        'similarities': []
                    }

                recommendations[similar_video_id]['score'] += weighted_score
                recommendations[similar_video_id]['similarities'].append({
                    'video_id': video_id,
                    'similarity': similarity,
                    'user_rating': user_rating
                })

        # Convert to sorted list
        sorted_recommendations = sorted(
            recommendations.items(),
            key=lambda x: x[1]['score'],
            reverse=True
        )[:n_recommendations]

        result = []
        for video_id, data in sorted_recommendations:
            result.append({
                'video_id': int(video_id),
                'score': float(data['score']),
                'reason': f"Based on {len(data['similarities'])} similar videos you've watched"
            })

        logger.info(f"Generated {len(result)} collaborative recommendations for user {user_id}")
        return result

    def get_content_based_recommendations(
        self,
        video_id: int,
        n_recommendations: int = 10
    ) -> List[Dict[str, Any]]:
        """Generate content-based recommendations using category and metadata"""
        logger.info(f"Generating content-based recommendations for video {video_id}")

        # Get video metadata
        video_query = """
        SELECT v.*, c.name as category_name
        FROM videos v
        LEFT JOIN categories c ON v.category_id = c.id
        WHERE v.id = %s AND v.is_active = 1
        """
        video_data = execute_query(video_query, (video_id,))

        if not video_data:
            return []

        video = video_data[0]
        category_id = video['category_id']

        # Find videos in same category with high engagement
        content_query = """
        SELECT
            v.id,
            v.title,
            AVG(vv.watch_percentage) as avg_completion_rate,
            COUNT(DISTINCT vv.user_id) as unique_viewers,
            COUNT(vv.id) as total_views,
            (v.like_count / NULLIF(v.view_count, 0)) * 100 as engagement_rate
        FROM videos v
        LEFT JOIN video_views vv ON v.id = vv.video_id
        WHERE v.category_id = %s
        AND v.id != %s
        AND v.is_active = 1
        GROUP BY v.id
        HAVING total_views > 0
        ORDER BY
            (avg_completion_rate * 0.4 + engagement_rate * 0.4 + total_views * 0.2) DESC
        LIMIT %s
        """

        results = execute_query(content_query, (category_id, video_id, n_recommendations))

        recommendations = []
        for row in results:
            recommendations.append({
                'video_id': row['id'],
                'score': float(row['avg_completion_rate'] or 0) / 100,  # Normalize to 0-1
                'reason': f"Popular in {video['category_name']} category"
            })

        return recommendations

    def get_popular_recommendations(self, n_recommendations: int = 10) -> List[Dict[str, Any]]:
        """Fallback: Get most popular videos"""
        logger.info("Generating popular video recommendations")

        query = """
        SELECT
            v.id,
            v.title,
            v.view_count,
            v.like_count,
            AVG(vv.watch_percentage) as avg_completion,
            COUNT(DISTINCT vv.user_id) as unique_viewers
        FROM videos v
        LEFT JOIN video_views vv ON v.id = vv.video_id
        WHERE v.is_active = 1
        GROUP BY v.id
        ORDER BY
            (v.view_count * 0.4 + v.like_count * 0.4 + AVG(vv.watch_percentage) * 0.2) DESC
        LIMIT %s
        """

        results = execute_query(query, (n_recommendations,))

        recommendations = []
        for row in results:
            score = (
                (row['view_count'] or 0) * 0.4 +
                (row['like_count'] or 0) * 0.4 +
                (row['avg_completion'] or 0) * 0.2
            ) / 1000  # Normalize

            recommendations.append({
                'video_id': row['id'],
                'score': float(score),
                'reason': "Popular and highly rated"
            })

        return recommendations

    def get_hybrid_recommendations(
        self,
        user_id: int,
        context_video_id: Optional[int] = None,
        n_recommendations: int = 10
    ) -> List[Dict[str, Any]]:
        """Combine collaborative and content-based recommendations"""
        logger.info(f"Generating hybrid recommendations for user {user_id}")

        # Check cache first
        cached = get_cached_recommendations(user_id, n_recommendations)
        if cached:
            logger.info(f"Using cached recommendations for user {user_id}")
            return [{'video_id': row['video_id'], 'score': row['recommendation_score'],
                    'reason': 'Personalized recommendation'} for row in cached]

        # Get collaborative recommendations
        collaborative = self.get_collaborative_recommendations(user_id, n_recommendations * 2)

        # Get content-based recommendations if context video provided
        content_based = []
        if context_video_id:
            content_based = self.get_content_based_recommendations(context_video_id, n_recommendations // 2)

        # Merge and deduplicate
        all_recommendations = {}

        # Add collaborative recommendations
        for rec in collaborative:
            video_id = rec['video_id']
            all_recommendations[video_id] = {
                'video_id': video_id,
                'score': rec['score'] * 0.7,  # Weight collaborative
                'reason': rec['reason']
            }

        # Add content-based recommendations
        for rec in content_based:
            video_id = rec['video_id']
            if video_id not in all_recommendations:
                all_recommendations[video_id] = {
                    'video_id': video_id,
                    'score': rec['score'] * 0.3,  # Weight content-based
                    'reason': rec['reason']
                }

        # Sort and limit
        sorted_recommendations = sorted(
            all_recommendations.values(),
            key=lambda x: x['score'],
            reverse=True
        )[:n_recommendations]

        # Cache results for 1 hour
        expires_at = datetime.now() + timedelta(hours=1)
        for rec in sorted_recommendations:
            update_recommendation_cache(user_id, rec['video_id'], rec['score'], expires_at)

        logger.info(f"Generated {len(sorted_recommendations)} hybrid recommendations for user {user_id}")
        return sorted_recommendations

    def get_user_insights(self, user_id: int) -> Dict[str, Any]:
        """Get insights about user behavior for analytics"""
        user_data = get_user_behavior_data(user_id, days_back=30)

        if not user_data['behavior_data']:
            return {'insights': 'Limited user data available'}

        df = pd.DataFrame(user_data['behavior_data'])

        insights = {
            'total_videos_watched': len(df),
            'avg_watch_percentage': df['watch_percentage'].mean(),
            'favorite_categories': df['category_name'].value_counts().head(3).to_dict(),
            'watch_patterns': self._analyze_watch_patterns(df),
            'engagement_score': self._calculate_engagement_score(df)
        }

        return insights

    def _analyze_watch_patterns(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Analyze user's watching patterns"""
        if df.empty:
            return {}

        patterns = {
            'most_active_day': df['watch_date'].mode().iloc[0] if not df.empty else None,
            'avg_daily_videos': len(df) / max(1, df['watch_date'].nunique()),
            'preferred_completion_level': 'high' if df['watch_percentage'].mean() > 75 else
                                        'medium' if df['watch_percentage'].mean() > 50 else 'low'
        }

        return patterns

    def _calculate_engagement_score(self, df: pd.DataFrame) -> float:
        """Calculate user engagement score (0-1)"""
        if df.empty:
            return 0.0

        watch_score = min(df['watch_percentage'].mean() / 100, 1.0)
        frequency_score = min(len(df) / 30, 1.0)  # Videos per day
        completion_score = (df['completed'].sum() / len(df)) if len(df) > 0 else 0

        return (watch_score * 0.4 + frequency_score * 0.3 + completion_score * 0.3)

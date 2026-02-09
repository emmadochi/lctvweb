"""
Data Pipeline for LCMTV AI Services
Handles user behavior data collection, processing, and feature engineering
"""
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Any, Optional
import logging
from collections import defaultdict

from ..core.database import execute_query, get_db_connection
from ..core.logging import get_logger

logger = get_logger("data_pipeline")


class DataPipeline:
    """Data processing pipeline for AI model training and inference"""

    def __init__(self):
        self.user_behavior_cache = {}
        self.cache_timeout = 1800  # 30 minutes

    def collect_user_interactions(self, days_back: int = 30) -> pd.DataFrame:
        """Collect comprehensive user interaction data"""
        logger.info(f"Collecting user interaction data for last {days_back} days")

        query = """
        SELECT
            vv.user_id,
            vv.video_id,
            vv.watch_duration,
            vv.total_duration,
            vv.watch_percentage,
            vv.completed,
            vv.created_at as watch_timestamp,
            DATE(vv.created_at) as watch_date,

            -- Video metadata
            v.title as video_title,
            v.category_id,
            v.view_count,
            v.like_count,
            v.duration as video_duration,
            v.published_at,

            -- Category info
            c.name as category_name,
            c.slug as category_slug,

            -- User context
            u.created_at as user_created_at,
            u.role,
            TIMESTAMPDIFF(DAY, u.created_at, NOW()) as user_age_days,

            -- Session info
            vv.session_id,
            COUNT(*) OVER (PARTITION BY vv.session_id) as session_video_count

        FROM video_views vv
        JOIN videos v ON vv.video_id = v.id
        LEFT JOIN categories c ON v.category_id = c.id
        LEFT JOIN users u ON vv.user_id = u.id
        WHERE vv.created_at >= DATE_SUB(NOW(), INTERVAL %s DAY)
        AND vv.user_id IS NOT NULL
        AND v.is_active = 1
        ORDER BY vv.user_id, vv.created_at
        """

        results = execute_query(query, (days_back,))

        if not results:
            logger.warning("No user interaction data found")
            return pd.DataFrame()

        df = pd.DataFrame(results)

        # Convert timestamps
        df['watch_timestamp'] = pd.to_datetime(df['watch_timestamp'])
        df['published_at'] = pd.to_datetime(df['published_at'])
        df['user_created_at'] = pd.to_datetime(df['user_created_at'])

        # Calculate additional features
        df = self._add_temporal_features(df)
        df = self._add_engagement_features(df)
        df = self._add_content_features(df)

        logger.info(f"Collected {len(df)} user interactions from {df['user_id'].nunique()} users")
        return df

    def _add_temporal_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add temporal features to the dataset"""
        df = df.copy()

        # Time of day features
        df['hour_of_day'] = df['watch_timestamp'].dt.hour
        df['day_of_week'] = df['watch_timestamp'].dt.dayofweek
        df['is_weekend'] = df['day_of_week'].isin([5, 6]).astype(int)

        # Time since video published
        df['days_since_published'] = (df['watch_timestamp'] - df['published_at']).dt.days
        df['days_since_published'] = df['days_since_published'].clip(lower=0)

        # User tenure
        df['user_tenure_days'] = (df['watch_timestamp'] - df['user_created_at']).dt.days

        # Session features
        df['session_position'] = df.groupby('session_id').cumcount() + 1

        return df

    def _add_engagement_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add engagement-based features"""
        df = df.copy()

        # Normalized engagement scores
        df['engagement_score'] = (
            (df['watch_percentage'] / 100) * 0.4 +  # Watch completion
            (df['completed'].astype(int)) * 0.3 +    # Completion bonus
            (df['like_count'] / df['view_count'].replace(0, 1)).clip(0, 1) * 0.3  # Video popularity
        )

        # User-level engagement metrics
        user_stats = df.groupby('user_id').agg({
            'watch_percentage': ['mean', 'std'],
            'completed': 'mean',
            'engagement_score': 'mean',
            'video_id': 'count'
        }).round(4)

        user_stats.columns = ['avg_watch_pct', 'std_watch_pct', 'completion_rate', 'avg_engagement', 'total_videos']
        user_stats = user_stats.reset_index()

        # Merge back to main dataframe
        df = df.merge(user_stats, on='user_id', how='left')

        # Relative engagement (how this video compares to user's average)
        df['relative_engagement'] = df['engagement_score'] - df['avg_engagement']

        return df

    def _add_content_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add content-based features"""
        df = df.copy()

        # Category popularity
        category_stats = df.groupby('category_id').agg({
            'video_id': 'count',
            'view_count': 'mean',
            'engagement_score': 'mean'
        }).round(4)

        category_stats.columns = ['category_video_count', 'category_avg_views', 'category_avg_engagement']
        category_stats = category_stats.reset_index()

        df = df.merge(category_stats, on='category_id', how='left')

        # Video popularity percentile within category
        df['video_popularity_percentile'] = df.groupby('category_id')['view_count'].rank(pct=True)

        # Content freshness score
        df['content_freshness'] = 1 / (1 + df['days_since_published'] / 30)  # Decay over 30 days

        return df

    def build_user_profiles(self, interactions_df: Optional[pd.DataFrame] = None) -> pd.DataFrame:
        """Build comprehensive user profiles for personalization"""
        if interactions_df is None:
            interactions_df = self.collect_user_interactions()

        if interactions_df.empty:
            return pd.DataFrame()

        logger.info("Building user profiles")

        # Aggregate user behavior patterns
        user_profiles = interactions_df.groupby('user_id').agg({
            # Basic stats
            'video_id': 'count',
            'watch_duration': 'sum',
            'engagement_score': 'mean',

            # Temporal preferences
            'hour_of_day': lambda x: x.mode().iloc[0] if len(x) > 0 else 12,  # Most common watch hour
            'day_of_week': lambda x: x.mode().iloc[0] if len(x) > 0 else 0,   # Most common watch day
            'is_weekend': 'mean',  # Proportion of weekend watching

            # Content preferences
            'category_id': lambda x: x.mode().iloc[0] if len(x) > 0 else None,  # Favorite category
            'category_name': lambda x: x.mode().iloc[0] if len(x) > 0 else None,

            # Engagement patterns
            'avg_watch_pct': 'first',
            'completion_rate': 'first',
            'total_videos': 'first',

        }).round(4)

        # Category preferences (top 5 categories by engagement)
        category_prefs = self._calculate_category_preferences(interactions_df)
        user_profiles = user_profiles.merge(category_prefs, on='user_id', how='left')

        # Time-based preferences
        time_prefs = self._calculate_time_preferences(interactions_df)
        user_profiles = user_profiles.merge(time_prefs, on='user_id', how='left')

        # Content type preferences
        content_prefs = self._calculate_content_preferences(interactions_df)
        user_profiles = user_profiles.merge(content_prefs, on='user_id', how='left')

        logger.info(f"Built profiles for {len(user_profiles)} users")
        return user_profiles.reset_index()

    def _calculate_category_preferences(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate user's category preferences"""
        category_prefs = df.groupby(['user_id', 'category_name']).agg({
            'engagement_score': 'mean',
            'video_id': 'count'
        }).reset_index()

        # Rank categories by weighted score
        category_prefs['weighted_score'] = (
            category_prefs['engagement_score'] * 0.7 +
            (category_prefs['video_id'] / category_prefs.groupby('user_id')['video_id'].transform('max')) * 0.3
        )

        # Get top 5 categories per user
        top_categories = category_prefs.sort_values(['user_id', 'weighted_score'], ascending=[True, False])
        top_categories = top_categories.groupby('user_id').head(5)

        # Pivot to wide format
        category_wide = top_categories.pivot(
            index='user_id',
            columns=top_categories.groupby('user_id').cumcount(),
            values='category_name'
        )
        category_wide.columns = [f'preferred_category_{i+1}' for i in range(len(category_wide.columns))]

        return category_wide.reset_index()

    def _calculate_time_preferences(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate user's time-based preferences"""
        time_prefs = df.groupby('user_id').agg({
            'hour_of_day': lambda x: x.value_counts().index[0] if len(x) > 0 else 12,  # Most common hour
            'day_of_week': lambda x: x.value_counts().index[0] if len(x) > 0 else 0,   # Most common day
            'is_weekend': 'mean'  # Weekend preference ratio
        }).round(4)

        time_prefs.columns = ['preferred_hour', 'preferred_day', 'weekend_preference']
        return time_prefs.reset_index()

    def _calculate_content_preferences(self, df: pd.DataFrame) -> pd.DataFrame:
        """Calculate user's content type preferences"""
        content_prefs = df.groupby('user_id').agg({
            'video_duration': 'mean',  # Preferred video length
            'days_since_published': 'mean',  # Preference for fresh content
            'content_freshness': 'mean'  # Content freshness preference
        }).round(4)

        content_prefs.columns = ['preferred_duration', 'freshness_preference', 'avg_content_freshness']
        return content_prefs.reset_index()

    def update_user_preferences(self, user_profiles: pd.DataFrame):
        """Update user preferences in database"""
        logger.info(f"Updating preferences for {len(user_profiles)} users")

        with get_db_connection() as conn:
            cursor = conn.cursor()

            for _, user in user_profiles.iterrows():
                # Prepare preferences JSON
                preferences = {
                    'preferred_categories': [
                        user[f'preferred_category_{i}']
                        for i in range(1, 6)
                        if pd.notna(user.get(f'preferred_category_{i}'))
                    ],
                    'preferred_hour': int(user.get('preferred_hour', 12)),
                    'preferred_day': int(user.get('preferred_day', 0)),
                    'weekend_preference': float(user.get('weekend_preference', 0.5)),
                    'preferred_duration': int(user.get('preferred_duration', 600)),
                    'freshness_preference': float(user.get('freshness_preference', 30))
                }

                # Update or insert user preferences
                cursor.execute("""
                    INSERT INTO user_preferences
                    (user_id, preferred_categories, watch_patterns, time_preferences, content_preferences, updated_at)
                    VALUES (%s, %s, %s, %s, %s, NOW())
                    ON DUPLICATE KEY UPDATE
                    preferred_categories = VALUES(preferred_categories),
                    watch_patterns = VALUES(watch_patterns),
                    time_preferences = VALUES(time_preferences),
                    content_preferences = VALUES(content_preferences),
                    updated_at = NOW()
                """, (
                    int(user['user_id']),
                    json.dumps(preferences['preferred_categories']),
                    json.dumps({}),  # watch_patterns placeholder
                    json.dumps({
                        'preferred_hour': preferences['preferred_hour'],
                        'preferred_day': preferences['preferred_day'],
                        'weekend_preference': preferences['weekend_preference']
                    }),
                    json.dumps({
                        'preferred_duration': preferences['preferred_duration'],
                        'freshness_preference': preferences['freshness_preference']
                    })
                ))

            conn.commit()
            logger.info("User preferences updated successfully")

    def export_training_data(self, output_path: str, days_back: int = 90):
        """Export processed data for model training"""
        logger.info(f"Exporting training data for last {days_back} days")

        interactions = self.collect_user_interactions(days_back)
        user_profiles = self.build_user_profiles(interactions)

        # Save to files
        interactions.to_csv(f"{output_path}/interactions.csv", index=False)
        user_profiles.to_csv(f"{output_path}/user_profiles.csv", index=False)

        logger.info(f"Training data exported to {output_path}")
        return {
            'interactions_file': f"{output_path}/interactions.csv",
            'user_profiles_file': f"{output_path}/user_profiles.csv",
            'total_interactions': len(interactions),
            'total_users': len(user_profiles)
        }

    def get_real_time_features(self, user_id: int, video_id: int) -> Dict[str, Any]:
        """Generate real-time features for recommendation scoring"""
        # Get recent user behavior (last 7 days)
        recent_interactions = self.collect_user_interactions(7)
        recent_user_data = recent_interactions[recent_interactions['user_id'] == user_id]

        if recent_user_data.empty:
            return self._get_default_features(video_id)

        # Calculate real-time features
        features = {
            'recent_avg_engagement': recent_user_data['engagement_score'].mean(),
            'recent_watch_count': len(recent_user_data),
            'recent_completion_rate': recent_user_data['completed'].mean(),
            'last_watch_hour': recent_user_data['hour_of_day'].iloc[-1] if len(recent_user_data) > 0 else 12,
            'current_streak': self._calculate_watch_streak(recent_user_data)
        }

        # Add video-specific features
        video_features = self._get_video_features(video_id, recent_user_data)
        features.update(video_features)

        return features

    def _get_default_features(self, video_id: int) -> Dict[str, Any]:
        """Return default features when no user data available"""
        return {
            'recent_avg_engagement': 0.5,
            'recent_watch_count': 0,
            'recent_completion_rate': 0.0,
            'last_watch_hour': 12,
            'current_streak': 0,
            'category_match': 0,
            'duration_match': 0
        }

    def _get_video_features(self, video_id: int, user_data: pd.DataFrame) -> Dict[str, Any]:
        """Get video-specific features for user"""
        # Get video metadata
        video_query = "SELECT category_id, duration FROM videos WHERE id = %s"
        video_data = execute_query(video_query, (video_id,))

        if not video_data:
            return {'category_match': 0, 'duration_match': 0}

        video = video_data[0]

        # Category match (how often user watches this category)
        category_match = 0
        if video['category_id']:
            category_count = (user_data['category_id'] == video['category_id']).sum()
            category_match = category_count / len(user_data) if len(user_data) > 0 else 0

        # Duration match (preference for similar video lengths)
        duration_match = 0
        if video['duration'] and len(user_data) > 0:
            avg_user_duration = user_data['video_duration'].mean()
            duration_diff = abs(video['duration'] - avg_user_duration)
            duration_match = max(0, 1 - (duration_diff / max(avg_user_duration, video['duration'])))

        return {
            'category_match': float(category_match),
            'duration_match': float(duration_match)
        }

    def _calculate_watch_streak(self, user_data: pd.DataFrame) -> int:
        """Calculate current watching streak in days"""
        if user_data.empty:
            return 0

        # Sort by date
        daily_watches = user_data.groupby('watch_date')['video_id'].count()

        # Find consecutive days
        streak = 0
        current_date = datetime.now().date()

        while current_date in daily_watches.index or (current_date - timedelta(days=1)) in daily_watches.index:
            if current_date in daily_watches.index:
                streak += 1
            elif streak > 0:  # Break streak if gap found
                break
            current_date -= timedelta(days=1)

        return streak


# Global data pipeline instance
data_pipeline = DataPipeline()

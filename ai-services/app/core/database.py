"""
Database connection and utilities for LCMTV AI Services
"""
import mysql.connector
from mysql.connector import Error
from contextlib import contextmanager
import logging
from typing import Optional, Dict, Any
from .config import settings

logger = logging.getLogger(__name__)


class DatabaseConnection:
    """Database connection manager with connection pooling"""

    def __init__(self):
        self._connection_pool = []
        self._max_connections = 10

    def get_connection(self):
        """Get a database connection from pool or create new one"""
        try:
            # Try to reuse existing connection
            if self._connection_pool:
                conn = self._connection_pool.pop()
                if self._is_connection_valid(conn):
                    return conn

            # Create new connection
            conn = mysql.connector.connect(
                host=settings.db_host,
                user=settings.db_user,
                password=settings.db_password,
                database=settings.db_name,
                port=settings.db_port,
                charset='utf8mb4',
                collation='utf8mb4_unicode_ci',
                autocommit=False,
                pool_name='lcmtv_ai_pool',
                pool_size=self._max_connections
            )

            logger.info(f"Connected to database: {settings.db_name}")
            return conn

        except Error as e:
            logger.error(f"Database connection error: {e}")
            raise

    def return_connection(self, conn):
        """Return connection to pool"""
        if conn and self._is_connection_valid(conn) and len(self._connection_pool) < self._max_connections:
            self._connection_pool.append(conn)
        else:
            try:
                if conn:
                    conn.close()
            except:
                pass

    def _is_connection_valid(self, conn) -> bool:
        """Check if connection is still valid"""
        try:
            conn.ping(reconnect=False, attempts=1, delay=0)
            return True
        except:
            return False

    def close_all(self):
        """Close all connections in pool"""
        for conn in self._connection_pool:
            try:
                conn.close()
            except:
                pass
        self._connection_pool.clear()


# Global database connection manager
db_manager = DatabaseConnection()


@contextmanager
def get_db_connection():
    """Context manager for database connections"""
    conn = None
    try:
        conn = db_manager.get_connection()
        yield conn
    except Exception as e:
        logger.error(f"Database operation error: {e}")
        if conn:
            conn.rollback()
        raise
    finally:
        if conn:
            try:
                conn.commit()  # Commit any pending transactions
            except:
                pass
            db_manager.return_connection(conn)


def execute_query(query: str, params: tuple = None, fetch: bool = True) -> Optional[list]:
    """Execute a database query and return results"""
    with get_db_connection() as conn:
        cursor = conn.cursor(dictionary=True)

        try:
            cursor.execute(query, params or ())

            if fetch:
                results = cursor.fetchall()
                return results
            else:
                return None

        finally:
            cursor.close()


def execute_many(query: str, params_list: list) -> bool:
    """Execute multiple queries with different parameters"""
    with get_db_connection() as conn:
        cursor = conn.cursor()

        try:
            cursor.executemany(query, params_list)
            return True
        except Exception as e:
            logger.error(f"Batch execution error: {e}")
            conn.rollback()
            return False
        finally:
            cursor.close()


def get_user_behavior_data(user_id: int, days_back: int = 30) -> Dict[str, Any]:
    """Get comprehensive user behavior data for AI processing"""
    query = """
    SELECT
        v.id as video_id,
        v.title,
        v.category_id,
        c.name as category_name,
        vv.watch_duration,
        vv.total_duration,
        vv.watch_percentage,
        vv.completed,
        DATE(vv.created_at) as watch_date,
        TIMESTAMPDIFF(DAY, vv.created_at, NOW()) as days_since_watch
    FROM video_views vv
    JOIN videos v ON vv.video_id = v.id
    LEFT JOIN categories c ON v.category_id = c.id
    WHERE vv.user_id = %s
    AND vv.created_at >= DATE_SUB(NOW(), INTERVAL %s DAY)
    AND v.is_active = 1
    ORDER BY vv.created_at DESC
    """

    results = execute_query(query, (user_id, days_back))
    return {
        'user_id': user_id,
        'behavior_data': results or [],
        'total_videos_watched': len(results) if results else 0
    }


def get_video_metadata(video_ids: list) -> Dict[int, Dict]:
    """Get metadata for multiple videos"""
    if not video_ids:
        return {}

    placeholders = ','.join(['%s'] * len(video_ids))
    query = f"""
    SELECT
        id,
        title,
        description,
        thumbnail_url,
        duration,
        category_id,
        view_count,
        like_count,
        channel_title,
        published_at
    FROM videos
    WHERE id IN ({placeholders}) AND is_active = 1
    """

    results = execute_query(query, tuple(video_ids))
    return {row['id']: row for row in results} if results else {}


def update_recommendation_cache(user_id: int, video_id: int, score: float, expires_at=None):
    """Cache recommendation scores"""
    query = """
    INSERT INTO recommendation_cache
    (user_id, video_id, recommendation_score, expires_at)
    VALUES (%s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
    recommendation_score = VALUES(recommendation_score),
    expires_at = VALUES(expires_at)
    """

    execute_query(query, (user_id, video_id, score, expires_at), fetch=False)


def get_cached_recommendations(user_id: int, limit: int = 10) -> list:
    """Get cached recommendations for user"""
    query = """
    SELECT video_id, recommendation_score
    FROM recommendation_cache
    WHERE user_id = %s AND expires_at > NOW()
    ORDER BY recommendation_score DESC
    LIMIT %s
    """

    results = execute_query(query, (user_id, limit))
    return results if results else []

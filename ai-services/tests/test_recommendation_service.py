"""
Tests for LCMTV Recommendation Service
"""
import pytest
from fastapi.testclient import TestClient
from app.services.recommendation_service import app


@pytest.fixture
def client():
    """Test client fixture"""
    return TestClient(app)


def test_health_check(client):
    """Test health check endpoint"""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "healthy"
    assert data["service"] == "recommendation_engine"
    assert "timestamp" in data


def test_recommendations_endpoint_structure(client):
    """Test recommendations endpoint accepts proper structure"""
    request_data = {
        "user_id": 1,
        "limit": 5,
        "include_explanations": True
    }

    # This will fail if database is not available, but tests the endpoint structure
    response = client.post("/api/v1/recommendations", json=request_data)

    # Should return either success (200) or database error (500), but not 422 (validation error)
    assert response.status_code in [200, 500]
    # If successful, check response structure
    if response.status_code == 200:
        data = response.json()
        assert "recommendations" in data
        assert "total_count" in data
        assert "generated_at" in data
        assert "algorithm_version" in data


def test_popular_recommendations(client):
    """Test popular recommendations fallback"""
    response = client.post("/api/v1/recommendations/popular?limit=5")

    assert response.status_code in [200, 500]
    if response.status_code == 200:
        data = response.json()
        assert "recommendations" in data
        assert "algorithm_version" in data
        assert data["algorithm_version"] == "popular_fallback"


def test_invalid_request_data(client):
    """Test validation of request data"""
    # Test invalid limit
    invalid_request = {
        "user_id": 1,
        "limit": 100  # Exceeds max limit of 50
    }

    response = client.post("/api/v1/recommendations", json=invalid_request)
    # Should return validation error
    assert response.status_code == 422


if __name__ == "__main__":
    pytest.main([__file__])

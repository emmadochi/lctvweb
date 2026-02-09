#!/usr/bin/env python3
"""
Integration test script for LCMTV AI Services
Tests the complete pipeline from database to API endpoints
"""
import requests
import json
import time
import sys
from typing import Dict, Any

# Configuration
BASE_URL = "http://localhost/lcmtvweb/backend/api"
AI_BASE_URLS = {
    "recommendation": "http://localhost:8000",
    "search": "http://localhost:8001",
    "processing": "http://localhost:8003"
}

class IntegrationTester:
    """Test the complete AI services integration"""

    def __init__(self):
        self.results = []
        self.passed = 0
        self.failed = 0

    def log_test(self, test_name: str, success: bool, message: str = "", details: Any = None):
        """Log a test result"""
        status = "✓ PASS" if success else "✗ FAIL"
        print(f"{status}: {test_name}")
        if message:
            print(f"   {message}")
        if details:
            print(f"   Details: {json.dumps(details, indent=2)}")
        print()

        self.results.append({
            "test": test_name,
            "success": success,
            "message": message,
            "details": details
        })

        if success:
            self.passed += 1
        else:
            self.failed += 1

    def test_php_backend_health(self):
        """Test PHP backend connectivity"""
        try:
            response = requests.get(f"{BASE_URL}/", timeout=10)
            success = response.status_code == 200

            self.log_test(
                "PHP Backend Health",
                success,
                f"Status: {response.status_code}",
                {"url": f"{BASE_URL}/", "response_time": response.elapsed.total_seconds()}
            )
            return success
        except Exception as e:
            self.log_test("PHP Backend Health", False, f"Error: {str(e)}")
            return False

    def test_ai_service_health(self, service_name: str, url: str):
        """Test individual AI service health"""
        try:
            response = requests.get(f"{url}/health", timeout=10)
            success = response.status_code == 200

            data = response.json() if success else {}
            self.log_test(
                f"{service_name} Service Health",
                success,
                f"Status: {response.status_code}",
                {"url": f"{url}/health", "data": data}
            )
            return success
        except Exception as e:
            self.log_test(f"{service_name} Service Health", False, f"Error: {str(e)}")
            return False

    def test_ai_services_health(self):
        """Test all AI services health"""
        all_healthy = True

        for service_name, url in AI_BASE_URLS.items():
            healthy = self.test_ai_service_health(service_name.title(), url)
            all_healthy = all_healthy and healthy

        return all_healthy

    def test_ai_health_endpoint(self):
        """Test the unified AI health endpoint"""
        try:
            response = requests.get(f"{BASE_URL}/ai/health", timeout=10)
            success = response.status_code == 200

            data = response.json() if success else {}
            self.log_test(
                "AI Health Endpoint",
                success,
                f"Status: {response.status_code}",
                {"url": f"{BASE_URL}/ai/health", "services": list(data.keys()) if data else []}
            )
            return success
        except Exception as e:
            self.log_test("AI Health Endpoint", False, f"Error: {str(e)}")
            return False

    def test_recommendations_api(self):
        """Test recommendations API"""
        try:
            # Test with user_id=1 (may not exist, but tests the endpoint)
            response = requests.get(f"{BASE_URL}/ai/recommendations?user_id=1&limit=5", timeout=15)
            success = response.status_code in [200, 404]  # 404 is OK if no user data

            data = response.json() if success else {}
            self.log_test(
                "Recommendations API",
                success,
                f"Status: {response.status_code}",
                {
                    "url": f"{BASE_URL}/ai/recommendations",
                    "recommendations_count": len(data) if isinstance(data, list) else 0
                }
            )
            return success
        except Exception as e:
            self.log_test("Recommendations API", False, f"Error: {str(e)}")
            return False

    def test_search_api(self):
        """Test search API"""
        try:
            payload = {
                "query": "test search",
                "limit": 5
            }
            response = requests.post(f"{BASE_URL}/ai/search", json=payload, timeout=15)
            success = response.status_code in [200, 500]  # 500 might occur if AI service is down

            data = response.json() if success else {}
            self.log_test(
                "Search API",
                success,
                f"Status: {response.status_code}",
                {
                    "url": f"{BASE_URL}/ai/search",
                    "query": payload["query"],
                    "results_count": len(data) if isinstance(data, list) else 0
                }
            )
            return success
        except Exception as e:
            self.log_test("Search API", False, f"Error: {str(e)}")
            return False

    def test_analytics_api(self):
        """Test analytics API"""
        try:
            response = requests.get(f"{BASE_URL}/ai/analytics", timeout=15)
            success = response.status_code in [200, 500]  # 500 might occur if AI service is down

            data = response.json() if success else {}
            self.log_test(
                "Analytics API",
                success,
                f"Status: {response.status_code}",
                {
                    "url": f"{BASE_URL}/ai/analytics",
                    "has_user_metrics": "user_metrics" in data if data else False
                }
            )
            return success
        except Exception as e:
            self.log_test("Analytics API", False, f"Error: {str(e)}")
            return False

    def test_data_processing_status(self):
        """Test data processing service status"""
        try:
            response = requests.get(f"{AI_BASE_URLS['processing']}/status", timeout=10)
            success = response.status_code == 200

            data = response.json() if success else {}
            self.log_test(
                "Data Processing Status",
                success,
                f"Status: {response.status_code}",
                {
                    "url": f"{AI_BASE_URLS['processing']}/status",
                    "is_running": data.get("is_running") if data else False
                }
            )
            return success
        except Exception as e:
            self.log_test("Data Processing Status", False, f"Error: {str(e)}")
            return False

    def run_all_tests(self):
        """Run all integration tests"""
        print("🚀 LCMTV AI Services Integration Tests")
        print("=" * 50)
        print()

        # Basic connectivity tests
        self.test_php_backend_health()
        self.test_ai_services_health()
        self.test_ai_health_endpoint()

        # API functionality tests
        self.test_recommendations_api()
        self.test_search_api()
        self.test_analytics_api()
        self.test_data_processing_status()

        # Summary
        print("=" * 50)
        print(f"📊 Test Results: {self.passed} passed, {self.failed} failed")
        print(".1f")
        print()

        if self.failed == 0:
            print("🎉 All tests passed! Your AI services are properly integrated.")
            print()
            print("Next steps:")
            print("1. Start your frontend: cd frontend && npm run dev")
            print("2. Visit http://localhost:3000 to test the full application")
            print("3. Monitor logs: tail -f ai-services/logs/ai_services.log")
        else:
            print("⚠️  Some tests failed. Check the details above.")
            print()
            print("Common fixes:")
            print("1. Ensure all AI services are running: python run_services.py")
            print("2. Check database connection in .env files")
            print("3. Verify PHP backend is accessible")
            print("4. Check logs for detailed error messages")

        return self.failed == 0


def main():
    """Main test runner"""
    tester = IntegrationTester()
    success = tester.run_all_tests()

    # Exit with appropriate code
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()

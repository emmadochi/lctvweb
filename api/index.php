<?php
// Proxy script to forward API requests to backend
$path = $_SERVER['REQUEST_URI'];
$path = str_replace('/lcmtvweb/api', '', $path);

// Forward to backend API
$backendUrl = 'http://localhost/lcmtvweb/backend/api' . $path;

// Capture and forward Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Forward the request
$options = [
    'http' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'header' => [
            'Content-Type: application/json',
            'Authorization: ' . $authHeader
        ],
        'content' => file_get_contents('php://input'),
        'ignore_errors' => true // Capture 4xx and 5xx responses from backend
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($backendUrl, false, $context);

// Capture status code from response headers
$statusCode = 200;
if (isset($http_response_header[0])) {
    preg_match('{HTTP\/\S+\s+(\d+)}', $http_response_header[0], $matches);
    $statusCode = (int)$matches[1];
}

// Set status code
http_response_code($statusCode);

// Set headers and output response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

echo $response;
?>
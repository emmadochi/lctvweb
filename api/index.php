<?php
// Proxy script to forward API requests to backend
$path = $_SERVER['REQUEST_URI'];
$path = str_replace('/lcmtvweb/api', '', $path);

// Forward to backend API
$backendUrl = 'http://localhost/lcmtvweb/backend/api' . $path;

// Forward the request
$options = [
    'http' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'header' => 'Content-Type: application/json',
        'content' => file_get_contents('php://input')
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($backendUrl, false, $context);

// Set headers and output response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

echo $response;
?>
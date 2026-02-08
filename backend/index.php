<?php
/**
 * Contact Manager API - Main Entry Point
 * Uses dynamic routing to automatically discover and route to PHP endpoints
 */

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users

// Set basic response headers
header('Content-Type: application/json');

// Log the request for debugging
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
error_log("[INDEX] Request: $requestMethod $requestUri");

// Include the dynamic router
require_once __DIR__ . '/router.php';

// The router handles everything from here
// No additional code needed - the router.php file contains all the routing logic
?>

<?php
/**
 * Playlist Sync API Routes
 * Include this file in your main API router to add playlist sync endpoints
 */

// Admin Playlist Sync Routes
case (preg_match('/^\/admin\/playlist-sync(\/(\d+))?$/', $path, $matches) ? true : false):
   require_once __DIR__ . '/../controllers/PlaylistSyncController.php';
    
    $method = $_SERVER['REQUEST_METHOD'];
    $playlistId = isset($matches[2]) ? (int)$matches[2] : null;
    
    switch ($method) {
       case 'GET':
           if ($playlistId) {
               PlaylistSyncController::show($playlistId);
            } else {
               PlaylistSyncController::index();
            }
            break;
            
       case'POST':
           if ($playlistId) {
               PlaylistSyncController::runPlaylistSync($playlistId);
            } else {
               PlaylistSyncController::create();
            }
            break;
            
       case'PUT':
           if ($playlistId) {
               PlaylistSyncController::update($playlistId);
            } else {
                Response::methodNotAllowed();
            }
            break;
            
       case 'DELETE':
           if ($playlistId) {
               PlaylistSyncController::delete($playlistId);
            } else {
                Response::methodNotAllowed();
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
    break;
    
case '/admin/playlist-sync/run':
   require_once __DIR__ . '/../controllers/PlaylistSyncController.php';
   if ($method === 'POST') {
       PlaylistSyncController::runSync();
    } else {
        Response::methodNotAllowed();
    }
    break;
    
case '/admin/playlist-sync/stats':
   require_once __DIR__ . '/../controllers/PlaylistSyncController.php';
   if ($method === 'GET') {
       PlaylistSyncController::getStats();
    } else {
        Response::methodNotAllowed();
    }
    break;
    
case '/admin/playlist-sync/logs':
   require_once __DIR__ . '/../controllers/PlaylistSyncController.php';
   if ($method === 'GET') {
       PlaylistSyncController::getLogs();
    } else {
        Response::methodNotAllowed();
    }
    break;

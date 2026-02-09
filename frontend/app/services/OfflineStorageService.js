/**
 * Offline Storage Service
 * Handles IndexedDB operations for offline content storage and sync
 */

(function() {
    'use strict';

    angular.module('ChurchTVApp')
        .factory('OfflineStorageService', ['$q', '$timeout', function($q, $timeout) {

            var service = {};

            // Database configuration
            var DB_NAME = 'lcmtv_offline';
            var DB_VERSION = 1;
            var db = null;

            // Object stores
            var STORES = {
                VIDEOS: 'videos',
                AUDIO: 'audio',
                DOCUMENTS: 'documents',
                METADATA: 'metadata',
                SYNC_QUEUE: 'sync_queue'
            };

            /**
             * Initialize IndexedDB database
             */
            service.initialize = function() {
                return $q(function(resolve, reject) {
                    if (!window.indexedDB) {
                        reject(new Error('IndexedDB not supported'));
                        return;
                    }

                    var request = indexedDB.open(DB_NAME, DB_VERSION);

                    request.onerror = function(event) {
                        console.error('IndexedDB error:', event.target.error);
                        reject(event.target.error);
                    };

                    request.onsuccess = function(event) {
                        db = event.target.result;
                        console.log('IndexedDB initialized successfully');
                        resolve(db);
                    };

                    request.onupgradeneeded = function(event) {
                        db = event.target.result;
                        createObjectStores(db);
                    };
                });
            };

            /**
             * Create object stores
             */
            function createObjectStores(db) {
                // Videos store
                if (!db.objectStoreNames.contains(STORES.VIDEOS)) {
                    var videosStore = db.createObjectStore(STORES.VIDEOS, { keyPath: 'id' });
                    videosStore.createIndex('youtube_id', 'youtube_id', { unique: true });
                    videosStore.createIndex('category_id', 'category_id', { unique: false });
                    videosStore.createIndex('downloaded_at', 'downloaded_at', { unique: false });
                }

                // Audio store (for podcasts, music, etc.)
                if (!db.objectStoreNames.contains(STORES.AUDIO)) {
                    var audioStore = db.createObjectStore(STORES.AUDIO, { keyPath: 'id' });
                    audioStore.createIndex('title', 'title', { unique: false });
                    audioStore.createIndex('downloaded_at', 'downloaded_at', { unique: false });
                }

                // Documents store (PDFs, notes, etc.)
                if (!db.objectStoreNames.contains(STORES.DOCUMENTS)) {
                    var documentsStore = db.createObjectStore(STORES.DOCUMENTS, { keyPath: 'id' });
                    documentsStore.createIndex('type', 'type', { unique: false });
                    documentsStore.createIndex('downloaded_at', 'downloaded_at', { unique: false });
                }

                // Metadata store (for app settings, user preferences, etc.)
                if (!db.objectStoreNames.contains(STORES.METADATA)) {
                    db.createObjectStore(STORES.METADATA, { keyPath: 'key' });
                }

                // Sync queue store (for offline actions to sync later)
                if (!db.objectStoreNames.contains(STORES.SYNC_QUEUE)) {
                    var syncStore = db.createObjectStore(STORES.SYNC_QUEUE, { keyPath: 'id', autoIncrement: true });
                    syncStore.createIndex('action', 'action', { unique: false });
                    syncStore.createIndex('created_at', 'created_at', { unique: false });
                }
            }

            /**
             * Store video for offline access
             */
            service.storeVideo = function(videoData, videoBlob, thumbnailBlob) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS], 'readwrite');
                    var store = transaction.objectStore(STORES.VIDEOS);

                    // Create offline video object
                    var offlineVideo = {
                        id: videoData.id,
                        youtube_id: videoData.youtube_id,
                        title: videoData.title,
                        description: videoData.description,
                        thumbnail_url: videoData.thumbnail_url,
                        channel_title: videoData.channel_title,
                        duration: videoData.duration,
                        category_id: videoData.category_id,
                        tags: videoData.tags,
                        view_count: videoData.view_count,
                        published_at: videoData.published_at,
                        downloaded_at: new Date().toISOString(),
                        video_blob: videoBlob,
                        thumbnail_blob: thumbnailBlob,
                        file_size: videoBlob ? videoBlob.size : 0,
                        thumbnail_size: thumbnailBlob ? thumbnailBlob.size : 0
                    };

                    var request = store.put(offlineVideo);

                    request.onsuccess = function() {
                        console.log('Video stored offline:', videoData.title);
                        resolve(offlineVideo);
                    };

                    request.onerror = function(event) {
                        console.error('Error storing video:', event.target.error);
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Get offline video
             */
            service.getOfflineVideo = function(videoId) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS], 'readonly');
                    var store = transaction.objectStore(STORES.VIDEOS);
                    var request = store.get(videoId);

                    request.onsuccess = function(event) {
                        var video = event.target.result;
                        if (video) {
                            resolve(video);
                        } else {
                            reject(new Error('Video not found in offline storage'));
                        }
                    };

                    request.onerror = function(event) {
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Get all offline videos
             */
            service.getAllOfflineVideos = function() {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS], 'readonly');
                    var store = transaction.objectStore(STORES.VIDEOS);
                    var request = store.getAll();

                    request.onsuccess = function(event) {
                        resolve(event.target.result || []);
                    };

                    request.onerror = function(event) {
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Remove offline video
             */
            service.removeOfflineVideo = function(videoId) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS], 'readwrite');
                    var store = transaction.objectStore(STORES.VIDEOS);
                    var request = store.delete(videoId);

                    request.onsuccess = function() {
                        console.log('Video removed from offline storage:', videoId);
                        resolve();
                    };

                    request.onerror = function(event) {
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Check if video is stored offline
             */
            service.isVideoOffline = function(videoId) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        resolve(false);
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS], 'readonly');
                    var store = transaction.objectStore(STORES.VIDEOS);
                    var request = store.get(videoId);

                    request.onsuccess = function(event) {
                        resolve(!!event.target.result);
                    };

                    request.onerror = function(event) {
                        resolve(false);
                    };
                });
            };

            /**
             * Get offline storage stats
             */
            service.getStorageStats = function() {
                return service.getAllOfflineVideos()
                    .then(function(videos) {
                        var totalSize = 0;
                        var videoCount = videos.length;

                        videos.forEach(function(video) {
                            totalSize += (video.file_size || 0) + (video.thumbnail_size || 0);
                        });

                        return {
                            videoCount: videoCount,
                            totalSize: totalSize,
                            formattedSize: formatBytes(totalSize),
                            availableSpace: navigator.storage && navigator.storage.estimate ?
                                navigator.storage.estimate() : Promise.resolve({ quota: 0, usage: 0 })
                        };
                    });
            };

            /**
             * Add action to sync queue (for offline operations)
             */
            service.addToSyncQueue = function(action, data) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.SYNC_QUEUE], 'readwrite');
                    var store = transaction.objectStore(STORES.SYNC_QUEUE);

                    var syncItem = {
                        action: action,
                        data: data,
                        created_at: new Date().toISOString(),
                        retry_count: 0
                    };

                    var request = store.add(syncItem);

                    request.onsuccess = function(event) {
                        console.log('Action added to sync queue:', action);
                        resolve(event.target.result);
                    };

                    request.onerror = function(event) {
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Process sync queue (when back online)
             */
            service.processSyncQueue = function() {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.SYNC_QUEUE], 'readwrite');
                    var store = transaction.objectStore(STORES.SYNC_QUEUE);
                    var request = store.getAll();

                    request.onsuccess = function(event) {
                        var queue = event.target.result || [];
                        var promises = [];

                        queue.forEach(function(item) {
                            // Process each sync item (this would need to be implemented
                            // based on the specific action types)
                            promises.push(processSyncItem(item, store));
                        });

                        Promise.all(promises)
                            .then(resolve)
                            .catch(reject);
                    };

                    request.onerror = function(event) {
                        reject(event.target.error);
                    };
                });
            };

            /**
             * Process individual sync item
             */
            function processSyncItem(item, store) {
                // This would implement the actual sync logic based on item.action
                // For now, just remove the item
                return new Promise(function(resolve, reject) {
                    var deleteRequest = store.delete(item.id);
                    deleteRequest.onsuccess = resolve;
                    deleteRequest.onerror = reject;
                });
            }

            /**
             * Clear all offline data
             */
            service.clearAllOfflineData = function() {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.VIDEOS, STORES.AUDIO, STORES.DOCUMENTS, STORES.SYNC_QUEUE], 'readwrite');
                    var promises = [];

                    // Clear all stores
                    [STORES.VIDEOS, STORES.AUDIO, STORES.DOCUMENTS, STORES.SYNC_QUEUE].forEach(function(storeName) {
                        if (db.objectStoreNames.contains(storeName)) {
                            var store = transaction.objectStore(storeName);
                            promises.push(clearStore(store));
                        }
                    });

                    Promise.all(promises)
                        .then(function() {
                            console.log('All offline data cleared');
                            resolve();
                        })
                        .catch(reject);
                });
            };

            /**
             * Clear a specific object store
             */
            function clearStore(store) {
                return new Promise(function(resolve, reject) {
                    var request = store.clear();
                    request.onsuccess = resolve;
                    request.onerror = reject;
                });
            }

            /**
             * Store metadata
             */
            service.setMetadata = function(key, value) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.METADATA], 'readwrite');
                    var store = transaction.objectStore(STORES.METADATA);

                    var request = store.put({ key: key, value: value, updated_at: new Date().toISOString() });

                    request.onsuccess = resolve;
                    request.onerror = reject;
                });
            };

            /**
             * Get metadata
             */
            service.getMetadata = function(key) {
                return $q(function(resolve, reject) {
                    if (!db) {
                        reject(new Error('Database not initialized'));
                        return;
                    }

                    var transaction = db.transaction([STORES.METADATA], 'readonly');
                    var store = transaction.objectStore(STORES.METADATA);
                    var request = store.get(key);

                    request.onsuccess = function(event) {
                        var result = event.target.result;
                        resolve(result ? result.value : null);
                    };

                    request.onerror = reject;
                });
            };

            // Utility functions
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                var k = 1024;
                var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Initialize on service creation
            service.initialize().catch(function(error) {
                console.warn('Offline storage initialization failed:', error);
            });

            return service;
        }]);
})();
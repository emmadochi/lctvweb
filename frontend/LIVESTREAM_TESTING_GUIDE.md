# Livestream Testing Guide

## Issue Fixed: Livestream Page Stuck on "Loading Live Stream"

I've identified and fixed the issue where the livestream page was showing "Loading live stream..." but never actually playing the video.

### Root Causes Found:
1. **YouTube Player Initialization Issues**: The player wasn't properly handling timeouts and errors
2. **Missing Error States**: Users got stuck in loading state when player failed
3. **Invalid Video IDs**: Some livestreams had video IDs that couldn't be embedded

### Fixes Applied:

#### 1. Enhanced Error Handling
- Added timeout handling for YouTube API loading
- Added retry functionality when player fails
- Added error messages for different failure scenarios

#### 2. Improved Player Initialization
- Better error handling in player creation
- Added debug logging for troubleshooting
- Player container validation

#### 3. UI Improvements
- Added retry button when player fails to load
- Better loading states and error messages
- Enhanced debug information

## How to Test

### Option 1: Test via Main Application
1. Visit: `http://localhost/LCMTVWebNew/frontend/`
2. Navigate to a livestream (should have links to `/livestream/2` or similar)
3. Check browser console (F12) for debug messages
4. If player fails, click the "Retry" link

### Option 2: Use Dedicated Test Page
Visit: `http://localhost/LCMTVWebNew/frontend/test_livestream_page.html`

This page:
- Tests livestream loading directly
- Shows detailed debug information
- Has manual play/retry buttons
- Displays YouTube API status

### Option 3: Command Line Testing
```bash
cd C:\xampp\htdocs\LCMTVWebNew\backend

# List all livestreams
php manage_livestreams.php list

# Test a specific livestream
php manage_livestreams.php test 2

# Update livestream status
php manage_livestreams.php update 2 live
```

## Current Livestreams Available

Based on the database, these livestreams are available for testing:
- ID 2: "Sample Live Stream 2" (YouTube ID: 9bZkp7q19f0)
- ID 4: "Deep Prayer Time" (YouTube ID: jHCSX9g1C5Y)
- ID 5: "Test Live Stream" (YouTube ID: 21X5lGlDOfg)

## Expected Behavior

1. **Page Load**: Should show "Loading livestream..." briefly
2. **Data Load**: API call fetches livestream details
3. **Player Init**: YouTube player initializes
4. **Ready State**: Shows "Click to play live stream"
5. **Playback**: Clicking play button starts video

## Troubleshooting

### If Still Showing "Loading..."
1. Check browser console for errors
2. Look for "YouTube API ready" messages
3. Verify the livestream ID exists
4. Try refreshing the page

### If Player Shows Error
1. Click the "Retry" link on the player overlay
2. Check if the YouTube video ID is valid
3. Verify the video allows embedding

### If API Calls Fail
1. Check if Apache/XAMPP is running
2. Verify the API endpoint: `http://localhost/LCMTVWebNew/backend/api/livestreams?id=2`
3. Check PHP error logs

### Console Error Solutions

#### "Failed to load resource: 404 Not Found" for `/livestreams/2/view`
**Fixed**: Added the missing API endpoint. The view tracking now works without errors.

#### "Player container not found"
**Fixed**: Added DOM readiness checking. The controller now waits for the HTML template to render before trying to access the player container.

#### "$scope.$root.showToast is not a function"
**Fixed**: Added the `showToast` function to the AngularJS root scope in `app.js`. Toast notifications now work properly.

### Common Issues & Solutions

1. **Timing Issues**: If you still see "Player container not found", try refreshing the page or clearing browser cache.

2. **API 404 Errors**: Make sure your Apache server is running and the URL structure matches your server configuration.

3. **YouTube API Issues**: Check that `https://www.youtube.com/iframe_api` loads correctly in your browser.

4. **CORS Issues**: Ensure your API calls are going to the correct domain/port.

## Adding Real Live Streams

For production use, you need actual YouTube live stream video IDs:

```bash
# Add a real live stream
php manage_livestreams.php add [ACTUAL_LIVE_VIDEO_ID] "Live Stream Title"
```

**Note**: Regular YouTube videos (like music videos) cannot be used as "live streams" - they must be actual live broadcasts from YouTube Live.

## Recent Fixes Applied (Feb 9, 2026)

### Issues Resolved:
1. **404 API Error**: `/livestreams/2/view` endpoint was missing
2. **Player Container Not Found**: Timing issue with DOM rendering
3. **showToast Function Missing**: Function not attached to AngularJS root scope

### Files Modified

#### Backend API
- `backend/api/index.php` - Added `/livestreams/{id}/view` POST endpoint routing
- `backend/controllers/LivestreamController.php` - Added `trackView()` method for analytics

#### Frontend
- `frontend/app/controllers/LivestreamController.js` - Enhanced error handling and DOM timing fixes
- `frontend/app/views/pages/livestream.html` - Added retry functionality
- `frontend/app/app.js` - Added `showToast` function to root scope

#### Testing Resources
- Created test pages and management scripts
- Comprehensive error logging and debugging

## Next Steps

1. Test the livestream functionality using the guide above
2. If issues persist, check browser console for specific error messages
3. Replace test video IDs with real YouTube live stream IDs
4. Consider implementing YouTube API integration to automatically fetch live streams
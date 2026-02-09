@echo off
echo Setting up Git repository for LCMTV project...

cd /d "C:\xampp\htdocs\LCMTVWebNew"

echo Removing any existing .git directory...
if exist .git rmdir /s /q .git

echo Initializing Git repository...
git init

echo Adding remote origin...
git remote add origin https://github.com/emmadochi/lctvweb.git

echo Adding all files...
git add .

echo Making initial commit...
git commit -m "Initial commit: Complete LCMTV Church TV streaming platform

- Frontend: AngularJS SPA with Bootstrap
- Backend: PHP API with MySQL
- Features: Video streaming, livestreams, user management, admin panel
- YouTube API integration for content ingestion
- PWA-ready with service worker support
- Comprehensive analytics and notification system"

echo Pushing to GitHub...
git push -u origin main

echo Git setup complete!
pause
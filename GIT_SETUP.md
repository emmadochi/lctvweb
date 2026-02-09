# Git Setup Instructions for LCMTV Project

Since automated git setup encountered permission issues, please follow these manual steps to push the project to GitHub.

## 📋 Prerequisites
- Git installed on your system
- GitHub account with access to https://github.com/emmadochi/lctvweb

## 🚀 Step-by-Step Setup

### 1. Clean any existing git files (if needed)
```bash
cd C:\xampp\htdocs\LCMTVWebNew
# Remove any existing git files
rmdir /s /q .git 2>nul
del .git 2>nul
```

### 2. Initialize Git Repository
```bash
git init
```

### 3. Configure Git (if not already done)
```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

### 4. Create .gitignore (already created)
The `.gitignore` file has been created with appropriate exclusions for:
- Environment files (.env)
- Logs and cache files
- Dependencies (node_modules, vendor)
- IDE files
- Database files

### 5. Add Remote Repository
```bash
git remote add origin https://github.com/emmadochi/lctvweb.git
```

### 6. Add All Files
```bash
git add .
```

### 7. Initial Commit
```bash
git commit -m "Initial commit: Complete LCMTV Church TV streaming platform

🎯 Features:
- Frontend: AngularJS SPA with Bootstrap styling
- Backend: PHP API with MySQL database
- Live streaming support with YouTube integration
- User authentication and favorites system
- Admin dashboard for content management
- PWA-ready with service worker support
- Comprehensive analytics and notification system
- Mobile-responsive design with accessibility features

📁 Project Structure:
- /frontend: AngularJS single-page application
- /backend: PHP REST API and database models
- /ai-services: Python AI recommendation engine
- /advance.md: Detailed enhancement roadmap

🛠️ Technology Stack:
- AngularJS 1.8.3, Bootstrap 3.4.1
- PHP 7.4+, MySQL 5.7+
- YouTube Data API v3 integration
- JWT authentication, responsive design"
```

### 8. Push to GitHub
```bash
# For first push to main branch
git push -u origin main

# If you get an error about 'main' not existing, try:
git push -u origin master
```

## 🔧 Troubleshooting

### Issue: "fatal: remote origin already exists"
```bash
# Remove existing remote and add again
git remote remove origin
git remote add origin https://github.com/emmadochi/lctvweb.git
```

### Issue: "Permission denied" or authentication issues
```bash
# Make sure you're using the correct repository URL
git remote set-url origin https://github.com/emmadochi/lctvweb.git

# If using SSH, use this instead:
# git remote set-url origin git@github.com:emmadochi/lctvweb.git
```

### Issue: Branch name mismatch
```bash
# Check current branch
git branch

# Rename master to main if needed
git branch -m master main

# Then push
git push -u origin main
```

### Issue: Large files or unwanted files committed
```bash
# If you accidentally committed large files, remove them:
git rm --cached large-file.zip
git commit --amend

# Or reset and start over:
git reset --hard HEAD~1
git add .
git commit -m "Clean initial commit"
```

## 📁 Files Included in Repository

✅ **Core Application Files:**
- Frontend AngularJS application (`/frontend`)
- Backend PHP API (`/backend`)
- AI services (`/ai-services`)
- Database schemas and migrations
- Configuration templates

✅ **Documentation:**
- Comprehensive README.md
- Advance enhancement roadmap (`advance.md`)
- Git setup instructions (`GIT_SETUP.md`)

✅ **Assets:**
- Logo files (PNG format)
- Favicon and icons
- CSS styling and themes

## 🔍 Verification

After successful push, verify on GitHub:

1. **Repository Structure**: Check that all folders are uploaded
2. **README Display**: Confirm README.md renders properly
3. **File Integrity**: Spot-check important files are present
4. **Repository Settings**: Configure visibility, description, topics

## 🎯 Next Steps

Once pushed to GitHub:

1. **Enable GitHub Pages** (optional) for documentation
2. **Set up Issues** and **Projects** for development tracking
3. **Configure Actions** for automated testing (future)
4. **Add Contributors** and set up collaboration workflows

## 📞 Support

If you encounter any issues:
1. Check the troubleshooting section above
2. Verify your GitHub permissions
3. Ensure the repository exists and is accessible
4. Try pushing a smaller test commit first

---

**Success Criteria:**
- ✅ All project files uploaded to GitHub
- ✅ Repository is publicly accessible
- ✅ README.md displays correctly
- ✅ No sensitive data (passwords, API keys) committed
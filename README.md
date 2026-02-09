# LCMTV - Church TV Streaming Platform

A modern, comprehensive streaming platform designed specifically for churches and religious organizations. Built with PHP backend, AngularJS frontend, and YouTube API integration.

![LCMTV Logo](lctv-logo-white.png)

## 🎯 Overview

LCMTV is a complete church media streaming solution that provides:

- **Live Streaming**: Real-time church services and events
- **Video-on-Demand**: Sermon archives and educational content
- **Community Features**: User engagement and social interaction
- **Admin Management**: Comprehensive content and user management
- **Analytics**: Detailed insights for ministry decision-making
- **Mobile-First**: PWA capabilities for offline viewing

## 🚀 Features

### ✅ Core Features
- **YouTube Integration**: Seamless video streaming from YouTube
- **Live Streams**: Real-time broadcasting with viewer counts
- **User Authentication**: Secure login and registration
- **Favorites System**: Save and organize preferred content
- **Search & Discovery**: Advanced content search and filtering
- **Admin Dashboard**: Complete content management system
- **Responsive Design**: Mobile-first, accessible interface

### 🔄 Recent Enhancements
- **Progressive Web App (PWA)**: Mobile app-like experience
- **Program Schedule**: Church service and event planning
- **Offline Downloads**: Sermon content for offline viewing
- **Advanced Analytics**: Ministry insights and reporting
- **Social Features**: Comments, reactions, community engagement

## 🛠️ Technology Stack

### Frontend
- **Framework**: AngularJS 1.8.3
- **Styling**: Bootstrap 3.4.1 + Custom CSS
- **PWA**: Service Worker + Web App Manifest
- **YouTube API**: Video player integration

### Backend
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **API**: RESTful JSON API
- **Authentication**: JWT tokens + session management

### Third-Party Services
- **YouTube Data API v3**: Content ingestion and playback
- **AI Services**: Content recommendations and search
- **Payment Integration**: Stripe/PayPal (planned)
- **CDN**: Content delivery optimization (planned)

## 📁 Project Structure

```
LCMTVWebNew/
├── frontend/                 # AngularJS SPA
│   ├── app/
│   │   ├── controllers/     # View controllers
│   │   ├── services/        # API services
│   │   ├── views/          # HTML templates
│   │   └── app.js          # Main application
│   └── assets/             # Static assets
├── backend/                 # PHP API backend
│   ├── controllers/        # API controllers
│   ├── models/            # Database models
│   ├── utils/             # Utilities and helpers
│   └── api/               # API entry points
├── ai-services/            # Python AI services
├── advance.md              # Enhancement roadmap
└── README.md              # This file
```

## 🚀 Quick Start

### Prerequisites
- **Web Server**: Apache/Nginx with PHP 7.4+
- **Database**: MySQL 5.7+
- **Node.js**: For frontend dependency management
- **YouTube API Key**: For video content integration

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/emmadochi/lctvweb.git
   cd lctvweb
   ```

2. **Set up the backend**
   ```bash
   cd backend
   cp env.example .env
   # Edit .env with your database credentials and API keys
   php setup.php
   ```

3. **Set up the frontend**
   ```bash
   cd ../frontend
   npm install  # or bower install
   ```

4. **Database setup**
   ```bash
   cd ../backend
   php add_livestreams_table.php
   php setup.php
   ```

5. **Start development server**
   ```bash
   # Backend (PHP built-in server)
   cd backend
   php -S localhost:8000

   # Frontend (in another terminal)
   cd frontend
   python -m http.server 8080
   ```

6. **Access the application**
   - Frontend: http://localhost:8080
   - Backend API: http://localhost:8000/api

## 🔧 Configuration

### Environment Variables
Create a `.env` file in the backend directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=lctv_db
DB_USER=your_username
DB_PASS=your_password

# YouTube API
YOUTUBE_API_KEY=your_youtube_api_key

# JWT Secret
JWT_SECRET=your_jwt_secret_key

# Application Settings
APP_NAME="Church TV"
APP_URL=http://localhost:8080
```

### YouTube API Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable YouTube Data API v3
4. Create API credentials (API Key)
5. Add the key to your `.env` file

## 📊 API Documentation

### Authentication Endpoints
```
POST /api/users
- Login: { "action": "login", "email": "...", "password": "..." }
- Register: { "action": "register", "email": "...", "password": "..." }
```

### Content Endpoints
```
GET  /api/videos           # Get featured videos
GET  /api/videos/{id}      # Get specific video
GET  /api/livestreams      # Get active live streams
GET  /api/categories       # Get content categories
GET  /api/search           # Search videos
```

### Admin Endpoints (requires authentication)
```
POST /api/admin/videos     # Upload new video
PUT  /api/admin/videos/{id} # Update video
DELETE /api/admin/videos/{id} # Delete video
GET  /api/admin/analytics  # Get analytics data
```

## 🎨 Development

### Frontend Development
```bash
cd frontend
# Install dependencies
npm install  # or bower install

# Development server
python -m http.server 8080

# Build for production
# (Add build scripts as needed)
```

### Backend Development
```bash
cd backend

# Run tests
php run_tests.php

# Import sample content
php import_content.php initial

# Check database status
php test_database.php
```

### Code Style
- **PHP**: PSR-12 coding standards
- **JavaScript**: Standard JS with JSDoc comments
- **CSS**: BEM methodology
- **HTML**: Semantic, accessible markup

## 🧪 Testing

### Unit Tests
```bash
# Frontend tests
cd frontend
karma start

# Backend tests
cd backend
php vendor/bin/phpunit
```

### Integration Tests
```bash
# API integration tests
cd backend
php test_api.php
```

## 📈 Analytics & Monitoring

### Built-in Analytics
- **User Engagement**: Watch time, completion rates
- **Content Performance**: Popular videos, categories
- **Live Stream Metrics**: Viewer counts, duration
- **Technical Metrics**: API response times, error rates

### External Monitoring
- **Google Analytics**: User behavior tracking
- **Sentry**: Error monitoring and alerting
- **Uptime Monitoring**: Service availability checks

## 🚀 Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrations run
- [ ] SSL certificate installed
- [ ] CDN configured (optional)
- [ ] Monitoring tools set up
- [ ] Backup procedures tested

### Recommended Hosting
- **Web Server**: DigitalOcean, AWS EC2, or similar
- **Database**: Managed MySQL (AWS RDS, PlanetScale)
- **CDN**: Cloudflare, AWS CloudFront
- **Storage**: AWS S3 for file uploads (if added)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow existing code style and patterns
- Add tests for new features
- Update documentation as needed
- Ensure accessibility compliance
- Test across multiple browsers

## 📝 Roadmap

See [`advance.md`](advance.md) for detailed enhancement roadmap including:

- **Phase 1**: PWA, Program Schedule, Offline Content
- **Phase 2**: Social Features, Advanced Search, Analytics
- **Phase 3**: Multi-language, Donations, Advanced Features

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built for church communities worldwide
- Powered by YouTube API for content delivery
- Community-driven development approach

## 📞 Support

For support, please:
1. Check the [Issues](https://github.com/emmadochi/lctvweb/issues) page
2. Review the [Documentation](docs/)
3. Contact the development team

---

**LCMTV** - Bringing churches together through digital ministry. 🕊️📺
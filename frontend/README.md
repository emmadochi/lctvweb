# Church TV Streaming Platform - AngularJS Frontend

A modern, church-appropriate video streaming platform built with AngularJS that organizes YouTube content into easy-to-watch channels.

## 🎯 Features

- **Church-Appropriate Content**: Clean, family-friendly interface
- **YouTube Integration**: Embedded YouTube videos with custom controls
- **Advanced Search**: Full-text search with filtering and categories
- **User Features**: Favorites, watch later, and viewing history
- **Responsive Design**: Mobile-first design for all devices
- **Category Organization**: Organized content by sermon topics, worship, youth, etc.
- **Analytics Tracking**: Video engagement and user behavior analytics

## 🚀 Quick Start

### Prerequisites

- **Backend**: PHP 7.4+ with MySQL 5.7+
- **Web Server**: Apache/Nginx with mod_rewrite
- **Node.js**: 14+ for development tools
- **YouTube API Key**: For video data and embedding

### Installation

1. **Clone and Setup Backend**
   ```bash
   # Ensure backend is running on /api/v1
   # Configure database and YouTube API key in backend/.env
   ```

2. **Install Frontend Dependencies**
   ```bash
   cd frontend
   npm install
   ```

3. **Start Development Server**
   ```bash
   npm run dev
   # Opens http://localhost:3000
   ```

4. **Build for Production**
   ```bash
   npm run build
   ```

## 📁 Project Structure

```
frontend/
├── app/                          # AngularJS Application
│   ├── controllers/              # Page Controllers
│   │   ├── HomeController.js     # Homepage logic
│   │   ├── VideoController.js    # Video player page
│   │   ├── CategoryController.js # Category browsing
│   │   ├── SearchController.js   # Search functionality
│   │   └── FavoritesController.js # User favorites/history
│   ├── services/                 # AngularJS Services
│   │   ├── VideoService.js       # Video API calls
│   │   ├── CategoryService.js    # Category management
│   │   ├── SearchService.js      # Search functionality
│   │   └── UserService.js        # User preferences
│   ├── directives/               # Custom Directives
│   │   ├── youtubePlayer.js      # YouTube player directive
│   │   └── videoThumbnail.js     # Video thumbnail component
│   ├── views/                    # HTML Templates
│   │   ├── components/           # Reusable components
│   │   │   ├── header.html       # Site navigation
│   │   │   └── footer.html       # Site footer
│   │   └── pages/                # Page templates
│   │       ├── home.html         # Homepage
│   │       ├── video.html        # Video detail page
│   │       ├── category.html     # Category page
│   │       ├── search.html       # Search results
│   │       └── favorites.html    # User favorites
│   └── app.js                    # Main application config
├── assets/                       # Static assets
│   ├── css/
│   │   └── main.css             # Main stylesheet
│   ├── js/
│   │   └── main.js              # Custom JavaScript
│   └── images/                  # Image assets
├── index.html                    # Main HTML file
├── package.json                  # NPM dependencies
├── bower.json                    # Frontend dependencies
└── README.md                     # This file
```

## 🔧 Configuration

### API Endpoints

Update these constants in `app/app.js`:

```javascript
.constant('API_BASE', '/api/v1')
.constant('YOUTUBE_API_KEY', 'YOUR_YOUTUBE_API_KEY')
```

### Content Categories

Default categories are defined in the database schema. Customize in `backend/config/schema.sql`:

- Sermons
- Worship
- Youth Ministry
- Bible Study
- Special Events
- Testimonials

## 🎨 Customization

### Styling

Modify `assets/css/main.css` for:
- Church branding colors
- Font families
- Layout adjustments
- Mobile responsiveness

### Categories and Icons

Update category icons in `CategoryService.js`:

```javascript
service.getCategoryIcon = function(slug) {
    var iconMap = {
        'sermons': 'fa-microphone',
        'worship': 'fa-music',
        // Add more mappings
    };
    return iconMap[slug] || 'fa-play-circle';
};
```

## 🔍 Available Routes

| Route | Controller | Description |
|-------|------------|-------------|
| `/` | HomeController | Homepage with featured content |
| `/category/:slug` | CategoryController | Category-specific video listing |
| `/video/:id` | VideoController | Individual video player page |
| `/search` | SearchController | Search results with filters |
| `/favorites` | FavoritesController | User favorites and history |

## 📱 Mobile Support

The application is fully responsive with:
- Touch-friendly navigation
- Optimized video player controls
- Mobile-first CSS approach
- Progressive Web App capabilities

## 🔒 Security Considerations

- Content is filtered through backend API
- No user authentication (anonymous usage)
- Local storage for user preferences only
- HTTPS recommended for production

## 🐛 Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Check backend is running on correct port
   - Verify CORS headers in backend
   - Check network/firewall settings

2. **YouTube Videos Not Loading**
   - Verify YouTube API key is valid
   - Check API quota limits
   - Ensure videos are embeddable

3. **Search Not Working**
   - Confirm backend search endpoints are functional
   - Check for JavaScript errors in console

### Development Tips

- Use browser developer tools for debugging
- Check Network tab for API call failures
- Enable AngularJS debug info in development
- Use `console.log()` for debugging services

## 🚀 Deployment

### Production Build

```bash
npm run build
```

### Server Configuration

Ensure your web server serves the `frontend/` directory and proxies API calls to the backend:

**Apache (.htaccess)**
```apache
RewriteEngine On
RewriteRule ^api/(.*)$ /backend/api/$1 [L]
```

**Nginx**
```nginx
location /api/ {
    proxy_pass http://localhost:8000/api/;
}
```

## 📊 Performance Optimization

- **Lazy Loading**: Videos load as needed
- **Caching**: API responses cached locally
- **Image Optimization**: Thumbnails compressed
- **Minification**: CSS/JS minified for production

## 🤝 Contributing

1. Follow AngularJS best practices
2. Maintain church-appropriate content guidelines
3. Test on multiple devices/browsers
4. Update documentation for new features

## 📄 License

This project is licensed under the MIT License.

## 🙏 Acknowledgments

Built with faith and technology to serve the church community. Special thanks to the YouTube API for providing access to quality Christian content.

---

**Need Help?** Check the backend README for API documentation or create an issue for technical support.
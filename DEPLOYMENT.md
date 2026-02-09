# Church TV Streaming Platform - Deployment Guide

## 🎯 **Project Overview**
Complete deployment guide for the Church TV streaming platform featuring AngularJS frontend and PHP/MySQL backend.

**Version**: 1.0.0
**Last Updated**: February 2026
**Status**: Production Ready

---

## 📋 **Deployment Checklist**

### Pre-Deployment ✅
- [x] Code development completed
- [x] Unit tests implemented
- [x] Performance optimizations added
- [x] Security hardening implemented
- [x] Documentation completed

### Server Requirements ✅
- [x] Web server (Apache/Nginx)
- [x] PHP 7.4+ with MySQLi extension
- [x] MySQL 5.7+ or MariaDB 10.0+
- [x] SSL certificate (recommended)
- [x] FTP/SFTP access

### Content Preparation ✅
- [ ] Church video library curated
- [ ] Categories and playlists organized
- [ ] Admin user credentials prepared
- [ ] Content approval workflow established

---

## 🚀 **Quick Deployment**

### 1. Server Preparation
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install apache2 php7.4 php7.4-mysqli php7.4-mbstring php7.4-xml php7.4-curl mysql-server -y

# Enable Apache modules
sudo a2enmod rewrite ssl headers

# Start services
sudo systemctl enable apache2 mysql
sudo systemctl start apache2 mysql
```

### 2. Database Setup
```bash
# Create database and user
mysql -u root -p
```

```sql
CREATE DATABASE churchtv_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'churchtv_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON churchtv_db.* TO 'churchtv_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Upload Files
```bash
# Upload all project files to web root
# Ensure correct file permissions
chmod -R 755 /var/www/html/churchtv
chown -R www-data:www-data /var/www/html/churchtv
```

### 4. Configuration
```bash
# Update backend/.env file
cp backend/.env.example backend/.env
nano backend/.env
```

**Required .env settings:**
```env
# Database
DB_HOST=localhost
DB_USER=churchtv_user
DB_PASS=secure_password_here
DB_NAME=churchtv_db

# YouTube API
YOUTUBE_API_KEY=your_youtube_api_key_here

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

### 5. Database Initialization
```bash
# Import database schema
mysql -u churchtv_user -p churchtv_db < backend/config/schema.sql

# Run setup scripts
php backend/setup.php
php backend/add_sample_videos.php
```

### 6. Admin Setup
```bash
# Create admin user
php backend/configure_admin.php
```

---

## ⚙️ **Detailed Configuration**

### Apache Configuration
**File**: `/etc/apache2/sites-available/churchtv.conf`

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/churchtv/frontend

    <Directory /var/www/html/churchtv/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # API Proxy
    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api

    # Security Headers
    <IfModule mod_headers.c>
        Header always set X-Frame-Options DENY
        Header always set X-Content-Type-Options nosniff
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; connect-src 'self' https://www.youtube.com https://www.googleapis.com"
    </IfModule>
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/churchtv/frontend

    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key

    # Same configuration as HTTP block
    # ... (copy from above)
</VirtualHost>
```

### Nginx Configuration
**File**: `/etc/nginx/sites-available/churchtv`

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    root /var/www/html/churchtv/frontend;
    index index.html;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https:; connect-src 'self' https://www.youtube.com https://www.googleapis.com" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy
    location /api/ {
        proxy_pass http://localhost:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_For;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # PHP files (if needed)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
}
```

### PHP Configuration
**File**: `/etc/php/7.4/fpm/php.ini`

```ini
; Recommended PHP settings for Church TV
max_execution_time = 300
max_input_time = 60
memory_limit = 256M
post_max_size = 50M
upload_max_filesize = 50M
max_file_uploads = 20

; Security settings
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session settings
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
```

---

## 🔒 **Security Configuration**

### SSL Certificate Setup
```bash
# Using Let's Encrypt (recommended)
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com

# Or using self-signed certificate (development only)
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/selfsigned.key \
    -out /etc/ssl/certs/selfsigned.crt
```

### Firewall Configuration
```bash
# UFW Firewall
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 'Apache Full'
sudo ufw allow mysql

# Check status
sudo ufw status
```

### Database Security
```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove test database
DROP DATABASE IF EXISTS test;

-- Reload privileges
FLUSH PRIVILEGES;
```

---

## 📊 **Performance Optimization**

### MySQL Optimization
**File**: `/etc/mysql/mysql.conf.d/mysqld.cnf`

```ini
[mysqld]
# Performance settings
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_size = 64M
max_connections = 100

# Security settings
skip-name-resolve
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION
```

### Caching Setup
```bash
# Install Redis for caching (optional)
sudo apt install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### CDN Setup (Optional)
```bash
# Cloudflare or AWS CloudFront configuration
# Point DNS to CDN
# Configure static asset caching rules
```

---

## 📝 **Content Migration**

### Video Upload Process
1. **Prepare Videos**: Ensure videos are public on YouTube
2. **Extract IDs**: Use YouTube video URLs or IDs
3. **Batch Import**: Use admin panel bulk import feature
4. **Categorize**: Assign appropriate categories and tags
5. **Test Playback**: Verify all videos load correctly

### Sample Content Structure
```
Church TV Content
├── Sermons
│   ├── Sunday Services
│   ├── Special Messages
│   └── Guest Speakers
├── Worship
│   ├── Praise & Worship
│   ├── Hymns
│   └── Choir Performances
├── Youth Ministry
│   ├── Youth Group
│   ├── Children's Church
│   └── Teen Programs
├── Special Events
│   ├── Christmas Services
│   ├── Easter Celebrations
│   └── Conferences
└── Educational
    ├── Bible Study
    └── Small Groups
```

---

## 🔧 **Maintenance & Monitoring**

### Log Configuration
```bash
# Apache logs
/var/log/apache2/access.log
/var/log/apache2/error.log

# PHP logs
/var/log/php_errors.log

# MySQL logs
/var/log/mysql/error.log
```

### Monitoring Setup
```bash
# Install monitoring tools
sudo apt install htop iotop nmon

# Set up log rotation
sudo logrotate /etc/logrotate.d/apache2
```

### Backup Strategy
```bash
# Database backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u churchtv_user -p churchtv_db > /backups/churchtv_db_$DATE.sql

# File backup
tar -czf /backups/churchtv_files_$DATE.tar.gz /var/www/html/churchtv
```

---

## 🧪 **Testing Checklist**

### Pre-Launch Tests
- [ ] Homepage loads correctly
- [ ] Video playback works
- [ ] Search functionality operational
- [ ] Admin panel accessible
- [ ] Mobile responsiveness verified
- [ ] SSL certificate valid
- [ ] All links functional

### Performance Tests
- [ ] Page load times < 3 seconds
- [ ] Video buffering minimal
- [ ] Search response < 1 second
- [ ] Concurrent users supported
- [ ] Memory usage optimized

### Security Tests
- [ ] HTTPS enforced
- [ ] XSS protection active
- [ ] CSRF protection working
- [ ] Input validation functional
- [ ] Admin access restricted

---

## 🚨 **Troubleshooting**

### Common Issues

**Videos not loading:**
```bash
# Check YouTube API key
grep YOUTUBE_API_KEY backend/.env

# Verify API quota
# Check browser console for errors
```

**Admin login failing:**
```bash
# Check database connection
php backend/test_db.php

# Verify admin user exists
mysql -u churchtv_user -p churchtv_db -e "SELECT * FROM users WHERE role='admin'"
```

**Slow performance:**
```bash
# Check server resources
htop

# Optimize database
mysqlcheck -u churchtv_user -p churchtv_db --optimize

# Clear caches
redis-cli FLUSHALL
```

### Support Contacts
- **Technical Issues**: your-admin@church.org
- **Content Issues**: your-media@church.org
- **User Support**: support@church.org

---

## 📈 **Post-Launch Monitoring**

### Key Metrics to Track
- **User Engagement**: Daily active users, session duration
- **Content Performance**: Video views, popular categories
- **Technical Health**: Page load times, error rates
- **YouTube API**: Quota usage, API response times

### Weekly Maintenance Tasks
- [ ] Review error logs
- [ ] Update content library
- [ ] Monitor performance metrics
- [ ] Check security alerts
- [ ] Backup verification

---

## 🎯 **Success Metrics**

### Technical KPIs
- **Uptime**: > 99.5%
- **Load Time**: < 3 seconds
- **Error Rate**: < 1%
- **Mobile Users**: > 60%

### User Engagement KPIs
- **Daily Active Users**: Growing trend
- **Video Views**: Increasing over time
- **Session Duration**: > 5 minutes average
- **Return Visitors**: > 40%

### Content KPIs
- **Video Library**: Regular updates
- **Category Usage**: Balanced distribution
- **Search Usage**: > 30% of sessions
- **Social Sharing**: Growing trend

---

## 📞 **Emergency Procedures**

### Service Outage Response
1. **Assess Impact**: Determine affected users/features
2. **Check Logs**: Review error logs for root cause
3. **Implement Fix**: Apply hotfix or rollback
4. **Communication**: Notify users via website banner
5. **Post-Mortem**: Document incident and prevention measures

### Data Loss Recovery
1. **Assess Damage**: Determine what data is lost
2. **Restore Backup**: Use latest backup to recover
3. **Verify Integrity**: Ensure restored data is complete
4. **Update Users**: Notify affected users
5. **Prevention**: Implement additional safeguards

---

*This deployment guide ensures a smooth transition from development to production. Regular updates and security patches should be applied monthly. Contact technical support for any deployment issues.*

**Deployment Date**: _______________
**Deployed By**: _________________
**System Administrator**: _______________
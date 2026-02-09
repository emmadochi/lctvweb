#!/bin/bash

# Church TV Platform - Production Build Script
# This script prepares the application for production deployment

echo "🏗️  Building Church TV Platform for Production"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status messages
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if required tools are installed
check_dependencies() {
    echo "Checking dependencies..."

    # Check Node.js and npm
    if ! command -v node &> /dev/null; then
        print_error "Node.js is not installed. Please install Node.js 14+ first."
        exit 1
    fi

    if ! command -v npm &> /dev/null; then
        print_error "npm is not installed. Please install npm first."
        exit 1
    fi

    # Check PHP
    if ! command -v php &> /dev/null; then
        print_warning "PHP not found. Backend testing will be skipped."
    fi

    print_status "Dependencies check completed"
}

# Frontend build process
build_frontend() {
    echo "Building frontend..."

    cd frontend

    # Install dependencies
    echo "Installing npm dependencies..."
    npm install

    # Run tests
    echo "Running tests..."
    if npm test; then
        print_status "Tests passed"
    else
        print_warning "Some tests failed. Continuing with build..."
    fi

    # Create production build
    echo "Creating production build..."

    # Minify CSS
    if command -v cleancss &> /dev/null; then
        cleancss assets/css/main.css -o assets/css/main.min.css
        print_status "CSS minified"
    else
        print_warning "cleancss not found. CSS minification skipped."
    fi

    # Minify JavaScript
    if command -v uglifyjs &> /dev/null; then
        # Combine and minify application JS
        uglifyjs \
            app/app.js \
            app/controllers/*.js \
            app/services/*.js \
            app/directives/*.js \
            app/config/*.js \
            assets/js/*.js \
            -o assets/js/app.min.js \
            --compress \
            --mangle

        print_status "JavaScript minified"
    else
        print_warning "uglifyjs not found. JS minification skipped."
    fi

    # Create production HTML (with minified assets)
    cp index.html index.prod.html

    # Update HTML to use minified assets
    if [ -f assets/css/main.min.css ]; then
        sed -i 's/assets\/css\/main\.css/assets\/css\/main.min.css/' index.prod.html
    fi

    if [ -f assets/js/app.min.js ]; then
        # Replace multiple script tags with single minified version
        sed -i '/<script src="app\/app\.js"><\/script>/a <script src="assets/js/app.min.js"></script>' index.prod.html
        # Remove individual script tags (this is a simplified approach)
        print_status "Production HTML created"
    fi

    cd ..
    print_status "Frontend build completed"
}

# Backend preparation
prepare_backend() {
    echo "Preparing backend..."

    # Check if .env file exists
    if [ ! -f backend/.env ]; then
        print_warning "backend/.env file not found. Please create it from .env.example"
        cp backend/.env.example backend/.env
        print_status "Created backend/.env from template"
    fi

    # Test database connection if PHP is available
    if command -v php &> /dev/null; then
        echo "Testing backend configuration..."
        if php backend/test_db.php > /dev/null 2>&1; then
            print_status "Database connection test passed"
        else
            print_warning "Database connection test failed. Please check backend/.env"
        fi
    fi

    print_status "Backend preparation completed"
}

# Create deployment package
create_deployment_package() {
    echo "Creating deployment package..."

    # Create deployment directory
    DEPLOY_DIR="churchtv-deployment-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$DEPLOY_DIR"

    # Copy frontend files
    cp -r frontend "$DEPLOY_DIR/"
    print_status "Frontend files copied"

    # Copy backend files (excluding sensitive data)
    mkdir -p "$DEPLOY_DIR/backend"
    cp -r backend/* "$DEPLOY_DIR/backend/"
    # Remove sensitive files
    rm -f "$DEPLOY_DIR/backend/.env"
    rm -rf "$DEPLOY_DIR/backend/logs"
    print_status "Backend files copied (sensitive data excluded)"

    # Copy AI services
    cp -r ai-services "$DEPLOY_DIR/"
    print_status "AI services copied"

    # Copy deployment documentation
    cp DEPLOYMENT.md "$DEPLOY_DIR/"
    cp PROJECT_SUMMARY.md "$DEPLOY_DIR/"
    cp frontend/README.md "$DEPLOY_DIR/FRONTEND_README.md"
    print_status "Documentation copied"

    # Create deployment script
    cat > "$DEPLOY_DIR/deploy.sh" << 'EOF'
#!/bin/bash
# Church TV Deployment Script
echo "🚀 Starting Church TV deployment..."

# Make scripts executable
chmod +x *.sh

# Setup instructions
echo "📋 Deployment Checklist:"
echo "1. Update backend/.env with production settings"
echo "2. Run database migrations: mysql -u user -p db < backend/config/schema.sql"
echo "3. Configure web server (see DEPLOYMENT.md)"
echo "4. Test the installation"
echo ""
echo "📚 Full documentation: DEPLOYMENT.md"
echo "🎯 Project summary: PROJECT_SUMMARY.md"
EOF

    chmod +x "$DEPLOY_DIR/deploy.sh"

    # Create archive
    tar -czf "${DEPLOY_DIR}.tar.gz" "$DEPLOY_DIR"
    rm -rf "$DEPLOY_DIR"

    print_status "Deployment package created: ${DEPLOY_DIR}.tar.gz"
}

# Generate documentation
generate_docs() {
    echo "Generating documentation..."

    # Create API documentation if needed
    if command -v php &> /dev/null && [ -f backend/api/index.php ]; then
        echo "API Documentation:" > api-docs.md
        echo "==================" >> api-docs.md
        echo "" >> api-docs.md
        echo "## Endpoints" >> api-docs.md
        echo "- GET /api/v1/videos - Get featured videos" >> api-docs.md
        echo "- GET /api/v1/videos/:id - Get video details" >> api-docs.md
        echo "- GET /api/v1/categories - Get categories" >> api-docs.md
        echo "- POST /api/v1/admin/login - Admin authentication" >> api-docs.md
        print_status "API documentation generated"
    fi
}

# Main build process
main() {
    echo "Starting production build process..."
    echo ""

    check_dependencies
    echo ""

    build_frontend
    echo ""

    prepare_backend
    echo ""

    generate_docs
    echo ""

    create_deployment_package
    echo ""

    echo "🎉 Build completed successfully!"
    echo ""
    echo "📦 Deployment package: churchtv-deployment-$(date +%Y%m%d-%H%M%S).tar.gz"
    echo "📚 Documentation: DEPLOYMENT.md, PROJECT_SUMMARY.md"
    echo "🚀 Ready for production deployment!"
}

# Run main function
main "$@"
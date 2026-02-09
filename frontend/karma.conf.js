// Karma configuration for Church TV AngularJS testing
module.exports = function(config) {
  config.set({
    // Base path that will be used to resolve all patterns (eg. files, exclude)
    basePath: '',

    // Frameworks to use
    frameworks: ['jasmine'],

    // List of files / patterns to load in the browser
    files: [
      // AngularJS dependencies
      'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.3/angular.min.js',
      'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.3/angular-mocks.js',
      'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.3/angular-route.min.js',
      'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.3/angular-animate.min.js',
      'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.3/angular-sanitize.min.js',

      // jQuery for Bootstrap
      'https://code.jquery.com/jquery-3.7.1.min.js',

      // Application files
      'app/app.js',
      'app/services/*.js',
      'app/controllers/*.js',
      'app/directives/*.js',

      // Test files
      'tests/**/*.spec.js'
    ],

    // List of files to exclude
    exclude: [
      'node_modules/',
      'bower_components/',
      'assets/',
      'test.html'
    ],

    // Preprocess matching files before serving them to the browser
    preprocessors: {
      'app/**/*.js': ['coverage']
    },

    // Test results reporter to use
    reporters: ['progress', 'coverage', 'html'],

    // Coverage reporter configuration
    coverageReporter: {
      type: 'html',
      dir: 'coverage/',
      subdir: '.'
    },

    // Web server port
    port: 9876,

    // Enable / disable colors in the output (reporters and logs)
    colors: true,

    // Level of logging
    logLevel: config.LOG_INFO,

    // Enable / disable watching file and executing tests whenever any file changes
    autoWatch: true,

    // Start these browsers
    browsers: ['Chrome'],

    // Continuous Integration mode
    singleRun: false,

    // Concurrency level
    concurrency: Infinity
  });
};
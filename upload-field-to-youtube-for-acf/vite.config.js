import { defineConfig } from 'vite';
import { resolve } from 'path';
import { glob } from 'glob';
import fs from 'fs';

// 🔧 Vite Dev Server & HMR Configuration Notes:
//
// - `server` configures the main Vite development server that serves static assets (JS, CSS, etc.)
//   and the Vite client injected into the page.
//
// - `hmr` (Hot Module Replacement) is a separate WebSocket connection used by the browser
//   to receive live updates when files change — it is distinct from the main HTTP server.
//
// - `server.port` is the internal port where the Vite dev server listens (e.g. 3000).
//   This is the port exposed inside the Docker container.
//
// - `hmr.port` is the internal WebSocket server port (usually same as `server.port`).
//   This tells Vite where to bind the WebSocket server.
//
// - `hmr.clientPort` is the port the browser will use to connect to the HMR WebSocket.
//   This must match the public port exposed on the host (e.g. via Docker or a reverse proxy).
//
// ✅ Example (Docker use case):
//   - Vite server inside container listens on 3000
//   - Docker maps host port 1337 to container port 3000
//   - Browser accesses https://localhost:1337
//   => Use `server.port = 3000`, `hmr.port = 3000`, `hmr.clientPort = 1337`
//
// ❌ Without `clientPort`, browser may try to connect to wss://localhost:3000,
//    which can fail if port 3000 is not directly accessible from outside the container.
// Environment and configuration utilities
const ENV = {
  isCI: process.env.CI || process.env.GITLAB_CI || process.env.GITHUB_ACTIONS || process.env.JENKINS_URL || process.env.TRAVIS || process.env.CIRCLECI,  // Support major CI platforms
  isProduction: process.env.NODE_ENV === 'production',  // Only production is true, everything else is dev-like
  
  // Server configuration (main Vite server)
  server: {
    host: process.env.VITE_SERVER_HOST || '0.0.0.0',
    port: Number(process.env.VITE_SERVER_PORT || 3000),
    https: process.env.VITE_SERVER_HTTPS !== 'false',
    strictPort: process.env.VITE_SERVER_STRICT_PORT !== 'false',
  },
  
  // HMR configuration (WebSocket for hot reload)
  hmr: {
    protocol: process.env.VITE_HMR_PROTOCOL || (process.env.VITE_HMR_HTTPS !== 'false' ? 'wss' : 'ws'),
    host: process.env.VITE_HMR_HOST || 'localhost',
    port: Number(process.env.VITE_HMR_PORT || process.env.VITE_SERVER_PORT || 3000),
    clientPort: Number(process.env.VITE_HMR_CLIENT_PORT || process.env.VITE_HMR_PORT || process.env.VITE_SERVER_PORT || 3000),
  },
  
  // Certificate configuration
  certs: {
    basePath: process.env.VITE_CERTS_PATH || '../tmp/certs',
    keyFile: process.env.VITE_CERT_KEY || 'server.key',
    certFile: process.env.VITE_CERT_CRT || 'server.crt',
  },
  
  // Build configuration
  build: {
    sourcemap: process.env.VITE_SOURCEMAP !== 'false',
    minify: process.env.VITE_MINIFY !== 'false',
    outDir: process.env.VITE_OUT_DIR || 'assets',
  }
};

// Auto-discover entry points using glob patterns
function getEntryPoints() {
  const entries = {};

  // JavaScript files pattern
  const jsFiles = glob.sync('resources/js/**/*.js', {
    ignore: [
      'resources/js/**/_*.js',      // Ignore utility files starting with _
      'resources/js/**/test/**',    // Ignore test files
      'resources/js/**/tests/**',   // Ignore test files
      'resources/js/**/*.test.js',  // Ignore test files
      'resources/js/**/*.spec.js'   // Ignore spec files
    ]
  });

  // SCSS files pattern  
  const scssFiles = glob.sync('resources/scss/**/*.scss', {
    ignore: [
      'resources/scss/**/_*.scss',  // Ignore SCSS partials
      'resources/scss/**/test/**',  // Ignore test files
      'resources/scss/**/tests/**', // Ignore test files
      'resources/scss/**/*.test.scss', // Ignore test files
      'resources/scss/**/*.spec.scss'  // Ignore spec files
    ]
  });

  // Process JavaScript files
  jsFiles.forEach(file => {
    // Convert path to entry key: resources/js/name.js -> js/name
    const key = file
      .replace('resources/', '')
      .replace(/\.(js|ts)$/, '');
    
    entries[key] = resolve(__dirname, file);
  });

  // Process SCSS files
  scssFiles.forEach(file => {
    // Convert path to entry key: resources/scss/name.scss -> css/name
    const key = file
      .replace('resources/scss/', 'css/')
      .replace(/\.(scss|sass|css)$/, '');
    
    entries[key] = resolve(__dirname, file);
  });

  console.log(`🎯 Found ${Object.keys(entries).length} entry points:`, Object.keys(entries));
  
  return entries;
}

// Get SSL certificates configuration
function getSSLConfig() {
  if (ENV.isCI || !ENV.server.https) {
    return false;
  }

  const keyPath = resolve(__dirname, ENV.certs.basePath, ENV.certs.keyFile);
  const certPath = resolve(__dirname, ENV.certs.basePath, ENV.certs.certFile);
  
  // Check if certificates exist
  if (!fs.existsSync(keyPath) || !fs.existsSync(certPath)) {
    if (!ENV.isProduction) {
      console.warn(`⚠️  SSL certificates not found, using HTTP`);
    }
    return false;
  }
  
  return {
    key: fs.readFileSync(keyPath),
    cert: fs.readFileSync(certPath)
  };
}

// Log configuration for debugging
function logConfiguration() {
  console.log('🔧 Vite Configuration:');
  console.log(`   📦 Mode: ${process.env.NODE_ENV}`);
  console.log(`   🌐 Server: ${ENV.server.https ? 'https' : 'http'}://${ENV.server.host}:${ENV.server.port}`);
  console.log(`   🔄 HMR: ${ENV.hmr.protocol}://${ENV.hmr.host}:${ENV.hmr.clientPort}`);
  
  if (ENV.server.host !== ENV.hmr.host || ENV.server.port !== ENV.hmr.clientPort) {
    console.log('   🐳 Docker mode detected');
  }
}

export default defineConfig(() => {
  // Log configuration
  logConfiguration();
  
  return {
    // Build configuration
    build: {
      // Output to WordPress assets structure
      outDir: ENV.build.outDir,
      
      // Generate manifest for PHP integration
      manifest: true,
      
      // Don't generate index.html
      emptyOutDir: true,
      
      // Auto-generated entry points
      rollupOptions: {
        input: getEntryPoints(),
        
        output: {
          // Custom file naming for JavaScript
          entryFileNames: (chunkInfo) => {
            const suffix = ENV.isProduction && ENV.build.minify ? '.min' : '';
            
            // Handle nested paths: js/dir/name -> js/dir/name[.min].js
            if (chunkInfo.name.includes('/')) {
              return `[name]${suffix}.js`;
            }
            return `[name]${suffix}.js`;
          },
          
          // Custom file naming for CSS and other assets
          assetFileNames: (assetInfo) => {
            // Use first name from names array (new API)
            const fileName = assetInfo.names?.[0] || assetInfo.name || 'asset';
            
            // CSS files: maintain directory structure
            if (fileName.endsWith('.css')) {
              const suffix = ENV.isProduction && ENV.build.minify ? '.min' : '';
              const baseName = fileName.replace(/\.css$/, '');
              return `${baseName}${suffix}.css`;
            }
            
            // Other assets (images, fonts, etc.)
            const info = fileName.split('.');
            const ext = info[info.length - 1];
            const baseName = info.slice(0, -1).join('.');
            
            // Organize by file type
            if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif'].includes(ext)) {
              return `images/${baseName}.[hash:8].${ext}`;
            }
            if (['woff', 'woff2', 'ttf', 'eot', 'otf'].includes(ext)) {
              return `fonts/${baseName}.[hash:8].${ext}`;
            }
            
            return `assets/${baseName}.[hash:8].${ext}`;
          },
          
          chunkFileNames: () => {
            const suffix = ENV.isProduction && ENV.build.minify ? '.min' : '';
            return `[name]${suffix}.[hash:8].js`;
          }
        },
        
        // External dependencies (don't bundle these)
        external: (id) => {
          // WordPress/jQuery should be external
          if (['jquery', 'jQuery', '$'].includes(id)) {
            return true;
          }
          return false;
        }
      },

      // Minification
      minify: ENV.isProduction && ENV.build.minify,
      
      // Generate source maps for development
      sourcemap: !ENV.isProduction && ENV.build.sourcemap,
      
      // Target modern browsers for better optimization
      target: ENV.isProduction ? 'es2015' : 'esnext',
      
      // Chunk size warnings
      chunkSizeWarningLimit: 1000,
      
      // Tree shaking
      treeshake: ENV.isProduction
    },
    
    // CSS preprocessing
    css: {
      preprocessorOptions: {
        scss: {
          // Global SCSS variables/mixins can be added here
          //additionalData: ``,
          
          // Silence deprecation warnings
          quietDeps: true,
          
          // Modern Sass API
          api: 'modern-compiler',
          
          // Charset
          charset: false
        }
      },
      
      // Generate source maps for CSS in development
      devSourcemap: !ENV.isProduction && ENV.build.sourcemap,
      
      // PostCSS plugins for production
      // postcss: ENV.isProduction ? {
      //   plugins: [
      //     // Add vendor prefixes
      //     // require('autoprefixer'),
      //     // Optimize CSS
      //     // require('cssnano')({ preset: 'default' })
      //   ]
      // } : undefined
    },
    
    // Development server configuration
    server: {
      host: ENV.server.host,
      port: ENV.server.port,
      strictPort: ENV.server.strictPort,
      
      // HMR configuration
      hmr: {
        protocol: ENV.hmr.protocol,
        host: ENV.hmr.host,
        port: ENV.hmr.port,
        clientPort: ENV.hmr.clientPort,
      },
      
      // CORS for WordPress development
      cors: {
        origin: true,
        credentials: true
      },
      
      // HTTPS configuration
      https: getSSLConfig(),
      
      // Proxy configuration (if needed)
      // proxy: process.env.VITE_PROXY_TARGET ? {
      //   '/api': {
      //     target: process.env.VITE_PROXY_TARGET,
      //     changeOrigin: true,
      //     secure: false
      //   }
      // } : undefined,
      
      // Watch configuration
      watch: {
        // Ignore node_modules for better performance
        ignored: ['**/node_modules/**', '**/vendor/**'],
        usePolling: process.env.VITE_USE_POLLING === 'true'
      }
    },
    
    // Preview server (for production builds)
    preview: {
      host: ENV.server.host,
      port: ENV.server.port + 1000, // Use different port for preview
      https: getSSLConfig(),
      cors: true
    },
    
    // Resolve configuration
    resolve: {
      alias: {
        '@': resolve(__dirname, 'resources'),
        '@js': resolve(__dirname, 'resources/js'),
        '@scss': resolve(__dirname, 'resources/scss'),
        '@images': resolve(__dirname, 'resources/images'),
        '@fonts': resolve(__dirname, 'resources/fonts')
      },
      
      // File extensions to resolve
      extensions: ['.js', '.ts', '.jsx', '.tsx', '.vue', '.json', '.scss', '.css']
    },
    
    // Optimization
    optimizeDeps: {
      // Include dependencies that should be pre-bundled
      include: [
        // Add commonly used libraries here
      ],
      
      // Exclude dependencies from pre-bundling
      exclude: [
        // WordPress globals
        'jquery', 'jQuery', '$'
      ]
    },
    
    // Clear console on rebuild
    clearScreen: false,
    
    // Log level
    logLevel: !ENV.isProduction ? 'info' : 'warn',
    
    // Environment variables exposed to client
    define: {
      // Expose some env vars to client-side code
      __VITE_IS_CI__: JSON.stringify(ENV.isCI),
      __VITE_IS_PRODUCTION__: JSON.stringify(ENV.isProduction),
      __VITE_IS_DEVELOPMENT__: JSON.stringify(!ENV.isProduction),
      __VITE_BUILD_TIMESTAMP__: JSON.stringify(Date.now())
    },
    
    // Plugin configuration
    plugins: [
      // Add plugins here if needed
      // e.g., @vitejs/plugin-react, @vitejs/plugin-vue, etc.
    ],
    
    // Experimental features
    experimental: {
      // Enable if you want to use experimental features
      // renderBuiltUrl: () => ({ relative: true })
    }
  };
});
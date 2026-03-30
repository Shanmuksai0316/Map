module.exports = {
  apps: [
    {
      name: 'map-hms-api',
      script: 'artisan',
      args: 'serve --host=0.0.0.0 --port=8001',
      cwd: '/Users/paragmasteh/Downloads/MAP/api',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        APP_ENV: 'production'
      }
    },
    {
      name: 'map-hms-queue',
      script: 'artisan',
      args: 'queue:work --daemon --timeout=300 --memory=512 --tries=3',
      cwd: '/Users/paragmasteh/Downloads/MAP/api',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'production',
        APP_ENV: 'production'
      }
    }
  ]
};

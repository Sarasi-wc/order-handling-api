# Supervisor Configuration

These configuration files are used to manage Laravel Horizon and the scheduler via Supervisor.

## Installation

1. Copy the configuration files to the supervisor config directory:
   ```bash
   sudo cp supervisor/*.conf /etc/supervisor/conf.d/
   ```

2. Update the paths in each `.conf` file:
   - Replace `/path/to/artisan` with the full path to your Laravel project (e.g., `/var/www/html/project/artisan`)
   - Replace `/path/to/storage/logs/` with the full path to your Laravel logs directory
   - Update the `user` field to match your web server user (e.g., `www-data`, `nginx`, or your deployment user)

3. Reload supervisor configuration:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   ```

4. Start the processes:
   ```bash
   sudo supervisorctl start horizon
   sudo supervisorctl start laravel-scheduler
   ```

## Management Commands

Check status:
```bash
sudo supervisorctl status
```

Start processes:
```bash
sudo supervisorctl start horizon
sudo supervisorctl start laravel-scheduler
```

Stop processes:
```bash
sudo supervisorctl stop horizon
sudo supervisorctl stop laravel-scheduler
```

Restart processes:
```bash
sudo supervisorctl restart horizon
sudo supervisorctl restart laravel-scheduler
```

View logs:
```bash
sudo supervisorctl tail -f horizon
sudo supervisorctl tail -f laravel-scheduler
```

## Configuration Details

### horizon.conf
- **Process**: Laravel Horizon queue worker manager
- **Command**: `php artisan horizon`
- **Auto-restart**: Yes
- **Stop wait time**: 3600 seconds (allows jobs to complete gracefully)

### scheduler.conf
- **Process**: Laravel task scheduler
- **Command**: `php artisan schedule:work`
- **Auto-restart**: Yes
- **Stop wait time**: 60 seconds

## Notes

- Horizon requires Redis to be running
- The scheduler runs continuously and checks for scheduled tasks every minute
- Make sure your `.env` file is properly configured with Redis connection details
- Logs are stored in `storage/logs/` directory

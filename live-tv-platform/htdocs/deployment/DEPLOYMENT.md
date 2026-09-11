# Deployment Guide: InfinityFree

This guide walks you through deploying the **HDHome Live TV Streaming Platform** on **InfinityFree** hosting.

## Prerequisites

- InfinityFree account with the details provided
- Code editor (VS Code recommended)
- FileZilla (optional, for FTP upload)
- phpMyAdmin access via InfinityFree control panel
- PHP 8.1+ knowledge

## Architecture Overview

The entire project lives inside the `htdocs/` folder, which serves as the web root on InfinityFree. Sensitive directories (`config/`, `includes/`, `src/`, `database/`) are protected by `.htaccess` rules so they're not directly accessible via web requests.

## Step 1: Configure Your Environment

1. Open `htdocs/config/env.example.php` in your code editor.
2. Copy the entire content and create a new file: `htdocs/config/config.php`.
3. Update the following values:

```php
// Database
define('DB_HOST', 'sql112.infinityfree.com');       // MySQL hostname
define('DB_NAME', 'if0_42677262_live_tv');          // Database name (create this in phpMyAdmin)
define('DB_USER', 'if0_42677262');                  // MySQL username
define('DB_PASS', 'YOUR_MYSQL_PASSWORD'];            // Your MySQL password

// Application
define('APP_URL', 'https://hdhome.free.je');        // Your custom domain
define('APP_NAME', 'HDHome');

// OpenRouter AI
define('OPENROUTER_API_KEY', 'YOUR_OPENROUTER_API_KEY');
define('OPENROUTER_MODEL', 'openai/gpt-4o');         // Primary model
define('OPENROUTER_FALLBACK_MODEL', 'meta-llama/llama-3.1-8b-instruct:free');

// Security
define('SECRET_KEY', 'generate_a_random_string_here');  // REQUIRED: Set this to a random string
```

> **IMPORTANT**: Never commit `config/config.php` to Git. It contains your credentials.  
> The `.gitignore` file already excludes it.

## Step 2: Create the Database

1. Log in to your InfinityFree Control Panel.
2. Navigate to **MySQL Databases** (or phpMyAdmin).
3. Create a new database named `if0_42677262_live_tv` (or a name of your choice).
4. Open **phpMyAdmin**.
5. Select your database from the left sidebar.
6. Click the **Import** tab.
7. Upload `htdocs/database/setup.sql`.
8. Click **Go** to execute.

This creates all 14 tables: `users`, `categories`, `channels`, `channel_ratings`, `user_favorites`, `channel_groups`, `group_channels`, `ai_chat_logs`, `ai_conversations`, `system_settings`, `maintenance_logs`, `error_logs`, `api_usage`, `user_remember_tokens`, `user_sessions`.

## Step 3: Upload Files

### Option A: FileZilla (Recommended)

1. **Download FileZilla** from https://filezilla-project.org/
2. Open FileZilla and enter your FTP credentials:
   - **Host**: `ftpupload.net`
   - **Username**: `if0_42677262`
   - **Password**: Your FTP password
   - **Port**: `21`
3. Connect to the server.
4. In the remote site panel, navigate to `htdocs/`.
5. Upload **all files and folders** from the local `htdocs/` directory to the remote `htdocs/` directory.
6. After upload, verify the structure looks like this on your server:

```
/htdocs/
  .htaccess
  index.php
  channels.php
  watch.php
  login.php
  register.php
  favorites.php
  _404.php
  500.php
  api/
  admin/
  assets/
  config/
  includes/
  src/
  storage/
  database/
  templates/
  deployment/
```

### Option B: InfinityFree File Manager

1. Go to the InfinityFree Control Panel → **File Management** → **File Manager**.
2. Navigate to `htdocs/`.
3. Upload files in batches (File Manager may have upload size limits).
4. Create directories: `api/`, `admin/`, `assets/css/`, `assets/js/admin/`, `assets/img/`, `config/`, `includes/`, `src/ai-agent/`, `storage/cache/`, `storage/logs/`, `templates/`, `database/`, `deployment/`.
5. Upload files into the appropriate directories.

## Step 4: Set Permissions

Via FileZilla (right-click → File Permissions):
- **.htaccess**: 644
- **PHP files**: 644
- **Directories**: 755
- `storage/cache/` and `storage/logs/`: 755 (must be writable by PHP)

## Step 5: Create Admin User

After uploading, create an admin account. You can either:

### Option A: SQL via phpMyAdmin
```sql
INSERT INTO users (username, email, password, role, status) VALUES (
    'admin',
    'admin@hdhome.free.je',
    '$2y$12$insert_bcrypt_hash_here',
    'admin',
    'active'
);
```

Generate the bcrypt hash:
```bash
# Run locally (requires PHP)
php -r "echo password_hash('your_secure_password', PASSWORD_BCRYPT, ['cost' => 12]);"
```

### Option B: Register + Promote
1. Visit `https://hdhome.free.je/register.php` and create an account.
2. Go to phpMyAdmin → Select database → Run:
```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

## Step 6: Configure Cron Job (AI Auto-Maintenance)

To enable automated AI maintenance (log cleanup, cache optimization, stream health checks):

1. In the InfinityFree Control Panel, go to **Cron Jobs**.
2. Add a new cron job:
   - **Command**: `curl -s "https://hdhome.free.je/src/ai-agent/AIAgent.php?maintenance=run&key=YOUR_SECRET_KEY"`
   - Replace `YOUR_SECRET_KEY` with the `SECRET_KEY` value from `config/config.php`
   - **Cron Schedule**: `0 3 * * *` (Daily at 3:00 AM server time)

## Step 7: Verify Installation

| Check | URL |
|---|---|
| Homepage | `https://hdhome.free.je/` |
| Channel Browser | `https://hdhome.free.je/channels.php` |
| Login | `https://hdhome.free.je/login.php` |
| Register | `https://hdhome.free.je/register.php` |
| Admin Login | `https://hdhome.free.je/admin/login.php` |
| Admin Dashboard | `https://hdhome.free.je/admin/index.php` |
| AI Chat | Click the 🤖 button on any page |

## Step 8: Replace Sample Streams

The default channels use placeholder URLs (`https://example.com/...`). Replace them with your licensed HLS streams:

1. Go to **Admin Panel** → **Channels** → **Add Channel**
2. Enter:
   - **Name**: Channel name
   - **Stream URL**: Full HLS URL (must end with `.m3u8`)
   - **Backup Stream URL**: (optional) Fallback stream
   - **Category**: Select from dropdown
   - **Logo/Poster**: Image URLs

The platform validates that stream URLs use HTTP/HTTPS and are not internal IP addresses.

## InfinityFree Constraints & Solutions

| Constraint | How It's Handled |
|---|---|
| No SSH access | Pure PHP, no Composer required at runtime |
| File Manager upload limits | Files split into small batches for upload |
| 50,000 daily hits | Lightweight caching, minimal DB queries |
| 5GB storage | Aggressive cache auto-cleanup, log rotation |
| No cron via SSH | Uses web-based cron trigger |
| File permission limits | Storage dirs created with 755 via admin panel |

## Troubleshooting

### "Please upload config/config.php"
- Ensure `htdocs/config/config.php` exists with correct credentials

### 500 Internal Server Error
- Check `storage/logs/app.log` for error details
- Verify `.htaccess` was uploaded correctly
- Ensure PHP 8.1+ is available

### "Database connection failed"
- Verify `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in config
- Check that the database was created in phpMyAdmin

### AI Chat not responding
- Verify `OPENROUTER_API_KEY` is set in `config/config.php`
- Without a valid API key, the AI runs in "demo mode" with limited responses
- Check `storage/logs/app.log` for API errors

### Stream not loading
- Verify the `.m3u8` URL is correct and publicly accessible
- The platform validates that URLs don't point to internal IPs
- Try the backup stream URL if the primary fails

### Cache directory not writable
- Contact InfinityFree support to set `storage/cache/` permissions to 755
- Or trigger cache clearing from the admin panel

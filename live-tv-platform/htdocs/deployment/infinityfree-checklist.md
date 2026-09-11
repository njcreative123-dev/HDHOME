# InfinityFree Deployment Checklist

A quick checklist to verify everything is properly set up on InfinityFree.

## Pre-Upload Checklist

- [ ] `config/config.php` created from `config/env.example.php`
- [ ] Database credentials updated in `config/config.php`
- [ ] `APP_URL` set to `https://hdhome.free.je`
- [ ] `OPENROUTER_API_KEY` set to your OpenRouter key
- [ ] `SECRET_KEY` set to a random 32-character string
- [ ] FTP password confirmed (may differ from account password)
- [ ] MySQL database created in InfinityFree control panel

## Upload Checklist

- [ ] `htdocs/index.php`, `htdocs/channels.php`, `htdocs/watch.php`, `htdocs/login.php`, `htdocs/register.php`, `htdocs/favorites.php` uploaded to `htdocs/`
- [ ] `htdocs/admin/` folder uploaded to `htdocs/`
- [ ] `config/`, `includes/`, `api/`, `src/`, `templates/`, `assets/`, `storage/` uploaded to root (parent of `htdocs`)
- [ ] `.htaccess` files uploaded (not renamed)
- [ ] File permissions verified:
  - [ ] PHP files: 644
  - [ ] Directories: 755
  - [ ] `.htaccess`: 644
  - [ ] `storage/cache/` and `storage/logs/`: 755

## Database Checklist

- [ ] `database/setup.sql` imported via phpMyAdmin
- [ ] Tables created (users, channels, categories, ai_chat_logs, etc.)
- [ ] Sample channels inserted
- [ ] Admin user created in `users` table with `role = 'admin'`

## Post-Deploy Checklist

- [ ] Homepage loads at `https://hdhome.free.je`
- [ ] Channel browsing works
- [ ] Video player loads (test with a sample HLS URL)
- [ ] AI chat button appears and responds
- [ ] Login/register pages work
- [ ] Admin panel accessible at `https://hdhome.free.je/admin/login.php`
- [ ] Cron job configured for AI maintenance:
    - [ ] URL: `https://hdhome.free.je/src/ai-agent/AIAgent.php?maintenance=run&key=YOUR_SECRET_KEY`
    - [ ] Schedule: Daily at 3 AM

## Common Issues

| Issue | Solution |
|---|---|
| "Please upload config/config.php" | Ensure config file exists at `config/config.php` (outside htdocs) |
| 500 Internal Server Error | Check `.htaccess` was uploaded properly; verify PHP 8.1+ |
| Session not working | Ensure `session` folder is writable by PHP |
| Stream won't play | Verify the `.m3u8` URL is correct and publicly accessible |
| AI chat not responding | Verify `OPENROUTER_API_KEY` is set; check logs for errors |

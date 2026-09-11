# HDHome - Live TV Streaming Platform

> A **production-ready**, **lightweight** Live TV streaming platform with built-in **AI Agent** integration (OpenRouter), designed for **PHP + MySQL** hosting environments like InfinityFree.

![Version](https://img.shields.io/badge/version-2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Project Structure

```
live-tv-platform/
├── README.md              # This file
├── .gitignore
├── composer.json
└── htdocs/                # ← Web root - upload contents to your hosting's htdocs/
    ├── .htaccess          # Apache config (blocks sensitive dirs, sets headers)
    ├── index.php          # Homepage
    ├── channels.php       # Channel browser with search
    ├── watch.php          # Video player page
    ├── login.php          # User login
    ├── register.php       # User registration
    ├── favorites.php      # User favorites
    ├── 404.php            # Error page
    ├── 500.php            # Error page
    ├── config/
    │   ├── config.php     # MAIN CONFIG - your DB/API credentials (NEVER commit)
    │   └── env.example.php# Template for config.php
    ├── includes/
    │   ├── bootstrap.php  # App initialization
    │   ├── Database.php   # PDO wrapper
    │   ├── Cache.php      # File-based cache
    │   ├── Logger.php     # Dual logger (file + DB)
    │   ├── Auth.php       # Authentication system
    │   ├── Security.php   # CSRF, rate limiting, sanitization
    │   ├── StreamHandler.php  # HLS/M3U8 parser & validator
    │   └── functions.php  # Helper functions
    ├── api/
    │   ├── channels.php   # Channel API (list, search, stream)
    │   ├── auth.php       # Auth API (login, register, logout)
    │   ├── ai_chat.php    # OpenRouter AI chat endpoint
    │   └── admin.php      # Admin management API
    ├── src/
    │   └── ai-agent/
    │       ├── OpenRouterAI.php          # OpenRouter API client
    │       ├── AIAgent.php               # Auto-healing maintenance agent
    │       └── AIConversationHistory.php # Chat session storage
    ├── templates/
    │   ├── header.php
    │   ├── footer.php
    │   ├── AdminTemplate.php
    │   └── ChannelCard.php
    ├── assets/
    │   ├── css/
    │   │   ├── style.css   # Main stylesheet
    │   │   └── admin.css   # Admin stylesheet
    │   ├── js/
    │   │   ├── main.js     # Frontend app
    │   │   ├── player.js   # Video player controller
    │   │   ├── chat.js     # AI chat widget
    │   │   └── admin/vendor.js
    │   └── img/
    ├── storage/
    │   ├── cache/          # File-based cache
    │   └── logs/           # Application logs
    ├── database/
    │   └── setup.sql       # Database schema + seed data
    └── deployment/
        ├── DEPLOYMENT.md   # Step-by-step deployment guide
        └── infinityfree-checklist.md
```

---

## Features

### 🚀 Core Streaming
- **HLS (.m3u8) streaming** with multi-quality support
- **Backup stream fallback** — automatic failover to backup URLs
- **Stream status monitoring** — checks if streams are online
- **M3U8 Playlist Parser** — parses HLS manifests for quality selection
- **Channel categorization** with icons and sorting
- **User favorites** and **channel ratings**

### 🤖 AI Agent (OpenRouter Integration)
- **AI Chat Interface** — users can search and discover channels via natural language
- **AI Admin Bot** — manages the backend: add/remove channels, check system status
- **Auto-Healing** — AI agent monitors uptime, cleans cache, handles errors
- **Log Auto-Cleanup** — removes old logs based on retention policy
- **Database Optimization** — automatic table optimization
- **Rate Limiting** — per-user API rate limiting for AI chat

### 🔒 Security
- **bcrypt password hashing** (cost factor 12)
- **CSRF protection** on all forms
- **Session security** — IP binding, session timeout, secure cookies
- **XSS prevention** — input sanitization and output escaping
- **SQL injection prevention** — PDO prepared statements
- **SSRF protection** — blocks internal IP addresses in stream URLs
- **Rate limiting** on auth and API endpoints
- **Security headers** via `.htaccess`

### 📱 Responsive UI
- **Mobile-first responsive design** — works on all devices
- **Modern dark gradient theme**
- **HLS video player** with fullscreen and keyboard controls
- **PWA-ready** structure

---

## Quick Start

### 1. Configure
Copy `htdocs/config/env.example.php` to `htdocs/config/config.php` and update:
- Database credentials
- OpenRouter API key
- APP_URL
- SECRET_KEY (random 32-char string)

### 2. Database
Import `htdocs/database/setup.sql` via phpMyAdmin.

### 3. Admin User
```sql
INSERT INTO users (username, email, password, role, status)
VALUES ('admin', 'admin@yourdomain.com',
  '$2y$12$your_bcrypt_hash_here',
  'admin', 'active');
```
Generate hash: `php -r "echo password_hash('password', PASSWORD_BCRYPT, ['cost'=>12]);"`

### 4. Deploy
Upload the **contents of `htdocs/`** to your hosting's `htdocs/` directory.
See [DEPLOYMENT.md](htdocs/deployment/DEPLOYMENT.md) for detailed InfinityFree instructions.

### 5. Set Up Cron (AI Maintenance)
```bash
# Daily at 3 AM
curl "https://hdhome.free.je/src/ai-agent/AIAgent.php?maintenance=run&key=YOUR_SECRET_KEY"
```

---

## API Endpoints

| Endpoint | Methods | Auth | Description |
|---|---|---|---|
| `/api/channels.php` | GET/POST | Public / User | List, search, stream |
| `/api/auth.php` | POST | Public | Login, register, logout |
| `/api/ai_chat.php` | POST | Public / User | AI chat, history |
| `/api/admin.php` | POST | Admin | Channel/user/settings management |
| `/src/ai-agent/AIAgent.php` | GET | Secret Key | Run maintenance |

---

## License

MIT License.

---

## Disclaimer

This platform is designed for use with **legally licensed HLS streams only**. The code includes SSRF protection, stream URL validation, and security headers. It does **not** include any circumvention technology for geo-blocking, ad-blockers, or firewalls. Ensure you have proper authorization before adding any stream URLs.

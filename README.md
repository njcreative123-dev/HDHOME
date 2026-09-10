<p align="center">
  <img src="assets/img/favicon.svg" width="80" alt="HDHome Logo">
</p>

<h1 align="center">HDHome Live TV</h1>

<p align="center">
  <strong>Enterprise-Grade, AI-Powered Live TV Streaming Platform</strong><br>
  PHP · MySQL · HLS · OpenRouter AI · InfinityFree Ready
</p>

<p align="center">
  <a href="https://hdhome.free.je">🌐 Live Demo</a> ·
  <a href="#features">Features</a> ·
  <a href="#architecture">Architecture</a> ·
  <a href="#quick-start">Quick Start</a> ·
  <a href="#deployment-guide">Deploy</a> ·
  <a href="#api-reference">API Docs</a>
</p>

---

## Features

### 📺 Streaming Engine
- **HLS / M3U8 Multi-Stream Player** — powered by [hls.js](https://github.com/video-dev/hls.js) with automatic quality switching
- **M3U/M3U8 Importer** — bulk import channels from any IPTV playlist URL or raw content
- **Stream Validator** — SSRF-protected URL checker that verifies HLS compatibility and accessibility
- **Smart M3U8 Parser** — extracts `#EXTINF` metadata (tvg-id, tvg-name, tvg-logo, group-title, country, language)

### 🤖 AI Admin Agent (OpenRouter)
- **Function Calling** — AI tool calls for channel CRUD, site stats, log cleanup, and stream validation
- **Conversation Persistence** — full chat history stored in MySQL, queryable from the AI panel
- **Tool Caching** — read-only tool results cached in DB to minimize API calls
- **Auto-Recovery** — max 6 tool-call iterations with graceful error handling
- **M3U Import via Chat** — tell the AI to "import channels from [URL]" and it does it

### 🔒 Security
- **Prepared Statements** — all database queries use PDO parameter binding (SQL injection proof)
- **SSRF Protection** — stream validator blocks private IPs, localhost, and internal hostnames
- **CSRF Tokens** — every state-changing API call requires a valid token
- **Rate Limiting** — fixed-window rate limiter (DB-backed) per IP + endpoint bucket
- **XSS Prevention** — all user output escaped with `htmlspecialchars()` (UTF-8)
- **Session Hardening** — HttpOnly, SameSite=Lax, secure cookies with periodic regeneration
- **Security Headers** — X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy

### 🎨 Frontend
- **PWA-Ready** — manifest + service worker for installability and offline caching
- **Dark Glass Theme** — modern gradient cards with backdrop-blur navigation
- **Mobile-First Responsive** — works perfectly from 320px to 4K
- **Zero-JQuery** — pure vanilla JavaScript, no frameworks, instant load
- **Search & Filter** — debounced live search with category chip filters and pagination

### ⚙️ Admin Dashboard
- **Live Stats** — total channels, active, views, DB size, AI status
- **Channel CRUD** — full table with inline toggle (active/featured), edit, delete modals
- **Category Manager** — create/delete categories with channel counts
- **AI Chat Panel** — built-in chat UI with typing indicator and tool-call visualization
- **Settings Panel** — change AI model, API key, retention days from the dashboard
- **Maintenance** — one-click log cleanup and system health check
- **API Activity Log** — real-time monitoring of all API requests

---

## Architecture

```
hdhome/
├── index.php                  # Homepage (SEO-friendly server-rendered grid)
├── admin.php                  # Admin SPA (login, dashboard, CRUD, AI chat)
├── api.php                    # API router (JSON endpoints)
│
├── includes/                  # Core bootstrap layer
│   ├── bootstrap.php          # Session, DB, cleanup cron, headers
│   ├── config.php             # .env parser + config array
│   ├── database.php           # PDO singleton (DB::run, DB::one, DB::insert)
│   └── functions.php          # Global helpers (jsonResponse, csrf, slugify, etc.)
│
├── src/
│   ├── api/                   # API endpoint handlers (one file per resource)
│   │   ├── auth.php           # login, logout, csrf, me
│   │   ├── channels.php       # CRUD + import + validate
│   │   ├── categories.php     # CRUD
│   │   ├── ai.php             # chat, history, tools, status
│   │   ├── stats.php          # overview dashboard data
│   │   ├── settings.php       # get/update dynamic settings
│   │   └── system.php         # health check
│   │
│   ├── classes/               # Domain logic (pure PHP, no dependencies)
│   │   ├── Auth.php           # Session auth + CSRF
│   │   ├── AIAgent.php        # OpenRouter function calling engine
│   │   ├── ChannelManager.php # Channel CRUD + import + validation
│   │   ├── CategoryManager.php# Category CRUD + upsert
│   │   ├── M3U8Parser.php     # HLS master + M3U IPTV playlist parser
│   │   ├── StreamValidator.php# URL validation + SSRF protection
│   │   ├── Cache.php          # DB-backed cache (get, put, remember, flush)
│   │   └── LogCleaner.php     # Auto-cleanup for logs, conversations, cache
│   │
│   └── middleware/            # Cross-cutting concerns
│       ├── CorsMiddleware.php # CORS preflight + headers
│       ├── RateLimiter.php    # IP-based fixed-window limiter
│       └── AuthMiddleware.php # Admin gate + CSRF verification
│
├── assets/
│   ├── css/
│   │   ├── style.css          # Main dark theme (responsive)
│   │   └── admin.css          # Admin panel styles
│   ├── js/
│   │   ├── api.js             # Fetch wrapper (CSRF, errors)
│   │   ├── app.js             # Homepage logic (search, grid, PWA SW)
│   │   ├── player.js          # HLS player with error recovery
│   │   ├── admin.js           # Admin SPA (all sections)
│   │   ├── ai-chat.js         # AI chat UI
│   │   └── sw.js              # Service worker (caching strategy)
│   ├── manifest.webmanifest   # PWA manifest
│   └── img/
│       └── favicon.svg        # SVG favicon
│
├── templates/partials/        # Reusable view fragments
│   ├── head.php               # <head> + opening <body>
│   ├── nav.php                # Navbar
│   ├── footer.php             # Footer
│   └── player.php             # Global player modal
│
├── database/
│   ├── schema.sql             # Full database schema (8 tables)
│   └── seed_channels.sql      # Demo categories + sample channels
│
├── tools/
│   ├── hash_password.php      # CLI bcrypt hash generator
│   └── hash.php               # Web-based hasher (DELETE after use)
│
├── logs/                      # Application logs (auto-cleaned)
│   └── .gitkeep
│
├── .env.example               # Template config (copy to .env)
├── .htaccess                  # Apache/LiteSpeed rules
├── .gitignore                 # Ignores .env, logs, vendor
├── LICENSE                    # MIT License
└── robots.txt                 # Search engine directives
```

---

## Database Schema

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `categories` | Channel categories | id, name, slug, sort_order, is_active |
| `channels` | Stream channels | id, name, slug, stream_url, category_id, is_active, is_featured, view_count |
| `admin_users` | Admin accounts | id, username, password (bcrypt), last_login |
| `api_logs` | Request monitoring | id, endpoint, method, status_code, ip_address, response_time_ms |
| `ai_conversations` | AI chat history | id, session_id, role, content, tool_name |
| `rate_limits` | Rate limiting | ip_address, bucket, window_start, request_count |
| `settings` | Dynamic config | setting_key, setting_value |
| `cache_store` | Query cache | cache_key, value (JSON), expires_at |

Full schema: `database/schema.sql`

---

## Quick Start

### Local Development

```bash
# 1. Clone the repo
git clone https://github.com/njcreative123-dev/HDHOME.git
cd HDHOME

# 2. Copy .env.example to .env and fill your values
cp .env.example .env

# 3. Create MySQL database (using schema.sql)
mysql -u root -p hdhome_db < database/schema.sql

# 4. Import seed data (optional demo channels)
mysql -u root -p hdhome_db < database/seed_channels.sql

# 5. Start PHP dev server
php -S localhost:8000

# 6. Open http://localhost:8000
#    Admin: http://localhost:8000/admin.php
```

### InfinityFree Deployment (Step-by-Step)

#### Method A: FileZilla FTP Upload

**Step 1 — Create MySQL Database**

1. Login to **InfinityFree Control Panel**
2. Go to **MySQL Databases**
3. Create a new database:
   - Database name: `hdhome` (will be created as `if0_42677262_hdhome`)
   - Set a **strong password** — save it!
4. Note down: hostname (`sql112.infinityfree.com`), username (`if0_42677262`), password, database name

**Step 2 — Import Database Schema**

1. In InfinityFree control panel, go to **phpMyAdmin** (next to your database)
2. Click the **Import** tab
3. Upload `database/schema.sql`
4. Click **Go** to execute
5. (Optional) Import `database/seed_channels.sql` for demo channels

**Step 3 — Configure .env**

1. Open `includes/config.php` OR create `.env` in the root with your settings:
```ini
DB_HOST=sql112.infinityfree.com
DB_NAME=if0_42677262_hdhome
DB_USER=if0_42677262
DB_PASS=your_mysql_password_here
SITE_URL=https://hdhome.free.je
OPENROUTER_API_KEY=sk-or-v1-your-key-here
```

**Step 4 — Upload via FileZilla**

1. Download and install [FileZilla](https://filezilla-project.org/)
2. Open Site Manager → New Site
3. Enter:
   - Host: `ftpupload.net`
   - Port: `21`
   - User: `if0_42677262`
   - Password: `your_ftp_password`
   - Protocol: FTP - File Transfer Protocol
   - Encryption: Only use plain FTP
4. Click **Connect**
5. Navigate to `htdocs/` on the remote (right panel)
6. Upload **ALL** project files to `htdocs/` (drag from left to right)
7. Wait for all transfers to complete

**Step 5 — Verify**

1. Visit `https://hdhome.free.je` — homepage should load
2. Visit `https://hdhome.free.je/admin.php` — admin login
3. Login with credentials from `.env` (default: admin / hdhome2024!)
4. Go to **AI Bot** tab — verify AI status shows "✅ Active"

#### Method B: InfinityFree File Manager

1. Login to InfinityFree control panel
2. Go to **File Manager** → navigate to `htdocs`
3. Upload files one by one or in batches (ZIP first, then extract)
4. If ZIP is not supported: upload `database/schema.sql` → run in phpMyAdmin → upload rest of files

**Important Post-Deployment Steps:**

1. **Delete `tools/hash.php`** after generating admin password hash
2. **Verify `.htaccess`** is uploaded (may be hidden by default — enable "Show hidden files" in File Manager)
3. **Set permissions**: folders `755`, files `644`
4. **Clear browser cache** (Ctrl+Shift+R) if homepage shows old content

---

## API Reference

All endpoints are accessed via `/api.php?endpoint={resource}/{action}` or `/api/{resource}/{action}` (via .htauth rewrites).

### Authentication

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `auth/csrf` | GET | — | Get CSRF token |
| `auth/login` | POST | — | Login (body: username, password) |
| `auth/logout` | POST | ✅ | End session |
| `auth/me` | GET | ✅ | Current user info |

### Channels

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `channels` | GET | — | List channels (params: q, category, page, per, sort, dir) |
| `channels/{id}` | GET | — | Get single channel |
| `channels` | POST | ✅ | Create channel |
| `channels/{id}` | PUT | ✅ | Update channel |
| `channels/{id}` | DELETE | ✅ | Delete channel |
| `channels/import` | POST | ✅ | Import from M3U URL (body: url or content) |
| `channels/{id}/views` | POST | — | Increment view count |

### Categories

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `categories` | GET | — | List categories with channel counts |
| `categories` | POST | ✅ | Create category |
| `categories/{id}` | DELETE | ✅ | Delete category |

### AI Agent

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `ai/chat` | POST | ✅ | Send message to AI (body: message, session_id) |
| `ai/history` | GET | ✅ | Load conversation history |
| `ai/tools` | GET | ✅ | List available AI tools |
| `ai/status` | GET | ✅ | Check AI configuration status |
| `ai/reset` | POST | ✅ | Clear conversation |

### System

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `system/health` | GET | — | Health check (DB, time, PHP) |
| `stats/overview` | GET | ✅ | Full dashboard stats |
| `settings` | GET | ✅ | Read current settings |
| `settings` | PUT | ✅ | Update settings (ai_model, ai_api_key, etc.) |

### AI Tool List

The AI agent has access to these tools via OpenRouter function calling:

| Tool | Description | Caching |
|------|-------------|---------|
| `get_site_status` | Site health + DB info | ✅ 2min |
| `list_channels` | Search/filter channels | ✅ 2min |
| `search_channels` | Full-text search | ✅ 2min |
| `get_channel` | Channel details by ID | ✅ 2min |
| `add_channel` | Create + auto-validate | ❌ |
| `update_channel` | Edit channel fields | ❌ |
| `delete_channel` | Delete (requires confirm) | ❌ |
| `list_categories` | Categories + counts | ✅ 2min |
| `add_category` | Create category | ❌ |
| `delete_category` | Delete (requires confirm) | ❌ |
| `get_stats` | Dashboard statistics | ✅ 2min |
| `validate_stream` | Check URL + HLS detect | ✅ 2min |
| `clean_logs` | Manual cleanup | ❌ |
| `import_m3u` | Import playlist | ❌ |

---

## Security Notes

- `.env` file is **never accessible via web** (blocked by `.htaccess`)
- All database queries use **PDO prepared statements** — no SQL injection possible
- CSRF tokens required for all POST/PUT/DELETE operations
- Rate limiting: 8 login attempts/min, 120 reads/min, 30 writes/min, 12 AI chat/min
- Stream validator blocks **all private IP ranges** (10/8, 172.16/12, 192.168/8, localhost, etc.)
- AI Agent **never reveals** API keys or passwords in responses
- Old logs auto-cleaned every 2 hours (configurable)
- Session cookies are `HttpOnly + SameSite=Lax + Secure`

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | Vanilla HTML/CSS/JS, hls.js (CDN), PWA Service Worker |
| **Backend** | PHP 8.0+ (PDO, cURL, no external dependencies) |
| **Database** | MySQL 5.7+ (InnoDB, utf8mb4) |
| **AI** | OpenRouter API (GPT-4o / Claude 3.5 Sonnet) |
| **Streaming** | HLS (.m3u8) via hls.js adaptive bitrate |
| **Hosting** | InfinityFree (Apache/LiteSpeed, no SSH required) |

---

## License

MIT License — see [LICENSE](LICENSE).

---

<p align="center">
  Built with ❤️ for live TV streaming
</p>

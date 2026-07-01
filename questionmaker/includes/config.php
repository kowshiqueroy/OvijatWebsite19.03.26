<?php
// ===== QMaker — SohojWeb =====
const APP_NAME     = 'QMaker';
const APP_TAGLINE  = 'Smart Question Paper Generator';
const APP_BRAND    = 'SohojWeb';
const APP_DOMAIN   = 'QMaker.sohojweb.com';
const APP_VERSION  = '2.1.0';

const DB_PATH      = __DIR__ . '/../data/questionmaker.sqlite';
const UPLOAD_DIR   = __DIR__ . '/../uploads/';
const UPLOAD_URL   = 'uploads/';

// Fallback server-level API keys (set via env vars; leave blank to require user keys)
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

const CLAUDE_MODEL      = 'claude-sonnet-4-6';
const OPENAI_MODEL      = 'gpt-4o';
const GEMINI_MODEL      = 'gemini-1.5-pro';

const SESSION_LIFETIME  = 86400 * 30;
const MAX_UPLOAD_SIZE   = 5 * 1024 * 1024;
const ALLOWED_IMG_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

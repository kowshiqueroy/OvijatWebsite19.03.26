# Ovijat Call Center Management System

A Core PHP-based Call Center Management System with a unified 3-column dashboard interface.

## Quick Start

1. Run `setup.php` to create database and sample data
2. Login at `login.php` with:
   - **Admin:** `admin` / `admin123`
   - **Agent:** `rahimahmed` / `agent123`

---

## Features

### 🎨 Unified Dashboard (`app.php`)

**3-Column Layout:**
1. **Column 1 - Search & Recent**
   - Search tab: Filters, results (Contacts/Calls/Logs/Tasks tabs)
   - Recent tab: Recent activity feed
   - Fetch PBX button in header
2. **Column 2 - Contact Info**
   - Collapsible Edit Contact form
   - Collapsible Summary stats
   - Tabs for Calls/Logs/Tasks for this contact
   - Click any item to view in Column 3
3. **Column 3 - Quick Actions**
   - New Call: Use opened contact or enter new phone number
   - New Log: Only works when a contact is opened
   - New Task: Works with opened contact or as independent
   - FAQ: Quick FAQ search

**New Call Feature:**
- Toggle between "Use opened contact" or "Enter new phone"
- If new phone entered, automatically creates contact and opens in Column 2

**UI Features:**
- Semi-dark glass theme with blur effects
- Collapsible sidebar (collapsed by default)
- Collapsible sections throughout
- Recent activity moved from sidebar to Column 1
- Responsive design - works on desktop and mobile
- Keyboard shortcuts: `Ctrl+K` (search), `Esc` (close panels)

### 🔐 Authentication & Roles

- **Login System** with username/password
- **Role-based access:** Admin and Agent
- **Session management** with secure redirects
- **Password change** in profile settings

### 📇 Contacts Management

- **Editable contact fields:** Name, Phone, Type, Group, Internal/External, Company, Email, Address
- **Favorite contacts** - Star important contacts
- **Contact statistics:** Total calls, talk time, logs count, tasks count
- **Search & filters:** By name, phone, type, group, internal/external
- **Date range filtering**

### 📞 Call Management

- **Call markings:** Successful, Problem, Need Action, Urgent, Failed
- **Google Drive links** - Add/manage drive links for any call (manual or PBX)
- **Recording links** - Click to play from PBX server
- **Talk time tracking** - Total talk time per contact
- **Call logs** - View and add logs directly from call details
- **Threaded replies** - Reply to logs with parent_id system

### 📋 Logs System

- **Log types:** Note, Issue, Follow-up, Resolution, Feedback, Query, Reply
- **Log statuses:** Open, Closed, Follow-up, Pending
- **Priority levels:** Low, Medium, High, Urgent
- **Lock/unlock** - Locked logs cannot be edited
- **Threaded replies** - Reply to any log
- **Agent attribution** - Shows agent name with each log

### ✅ Tasks System

- Title, Description, Priority, Due Date
- Assign to agents
- Link to contacts or calls
- Status tracking: Pending, In Progress, Completed, Cancelled
- Quick floating panel to create tasks

### ❓ FAQ Database

- Question, Answer, Category
- Usage counter
- Floating panel for quick FAQ search
- Create FAQ from resolved logs
- Copy FAQ answer to clipboard

### 🏷️ Tags (Admin Only)

- Custom call tags for categorization (Billing, Sales, Support, etc.)
- Color-coded tags
- Admin can create/edit/delete tags

### 📊 Settings (Admin Only)

- **PBX Configuration:** Username, password, auto-fetch toggle
- **Company settings:** Company name
- **Password change**

### 🔔 Notifications

- Bell icon with unread count badge
- Dropdown notification list
- Toast notifications for actions

### 📈 Activity Feed

- Recent activity shown in sidebar
- Tracks: calls, logs, tasks, status changes

---

## Interface Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ [☰] [🔍 Search...]              [📊 Stats] [🔔] [👤 User ▾]   │
├────┬────────────────────────────────────────────────────────────┤
│    │  ┌──────────────┬──────────────┬──────────────────────┐   │
│ S  │  │ SEARCH/FILTER│ CONTACT INFO │ DETAILS              │   │
│ I  │  │              │              │                      │   │
│ D  │  │ Date Range   │ [Editable    │ Call Mark Buttons    │   │
│ E  │  │ Type ▼       │  Contact     │ Drive Link Input     │   │
│ B  │  │ Group ▼      │  Form]       │ Recording Link       │   │
│ A  │  │ Int/Ext ▼    │              │                      │   │
│ R  │  │              │ Stats:       │ Logs Section         │   │
│    │  │ ──────────── │ Calls: 12    │ [Add Log Form]       │   │
│ 📊 │  │ [Contacts]   │ Talk: 45m   │                      │   │
│ 📞 │  │ [Calls]      │ Logs: 5     │ Replies Thread       │   │
│ 📋 │  │ [Logs]       │ Tasks: 2    │                      │   │
│ ✅ │  │ [Tasks]      │              │                      │   │
│    │  │              │              │                      │   │
│    │  │ [Result 1]   │              │                      │   │
│    │  │ [Result 2]   │              │                      │   │
│    │  │ [Result 3]   │              │                      │   │
│    │  └──────────────┴──────────────┴──────────────────────┘   │
│    │                              [FAQ] [Task] [Fetch PBX]     │
└────┴────────────────────────────────────────────────────────────┘
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts (admin, agents) |
| `agents` | Agent profiles (extension, phone, department) |
| `persons` | Contacts with favorites, assigned_to |
| `contact_groups` | Contact group definitions with colors |
| `contact_types` | Contact type definitions with colors |
| `tags` | Call tags for categorization |
| `call_tags` | Many-to-many call-tag relationships |
| `calls` | Call records with talk_time, drive_link |
| `logs` | Notes/issues with parent_id for replies |
| `tasks` | Task assignments |
| `faqs` | FAQ database |
| `personal_notes` | Agent private notes |
| `edit_history` | Audit trail |
| `activity_log` | Agent activity with link tracking |
| `settings` | System settings |
| `notifications` | User notifications |

---

## API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `api/activity.php` | Recent activity feed |
| `api/agents.php` | Agent CRUD |
| `api/auth.php` | Login, password change |
| `api/calls.php` | List, mark, drive link |
| `api/faqs.php` | FAQ CRUD, search, use |
| `api/fetch_pbx.php` | Fetch PBX data |
| `api/groups.php` | Contact group CRUD |
| `api/history.php` | Edit history |
| `api/logs.php` | Log CRUD, replies |
| `api/notifications.php` | Notifications |
| `api/persons.php` | Contact CRUD, favorites |
| `api/search.php` | Unified search |
| `api/stats.php` | Dashboard stats |
| `api/tasks.php` | Task CRUD |

---

## File Structure

```
├── app.php           # Main unified dashboard (3-column layout)
├── login.php         # Login page
├── logout.php        # Logout handler
├── config.php        # Database configuration
├── functions.php     # Helper functions
├── setup.php         # Database setup script
│
├── api/              # REST API endpoints
│   ├── activity.php
│   ├── agents.php
│   ├── auth.php
│   ├── calls.php
│   ├── faqs.php
│   ├── fetch_pbx.php
│   ├── groups.php
│   ├── history.php
│   ├── logs.php
│   ├── notifications.php
│   ├── persons.php
│   ├── search.php     # Unified search API
│   ├── stats.php
│   └── tasks.php
│
├── assets/
│   └── app.css       # Glass dark theme
│
├── db/
│   └── schema.sql    # Database schema
│
├── admin.php         # Legacy admin dashboard
├── agent.php         # Legacy agent dashboard
├── contacts.php      # Legacy contacts page
└── calls_report.php  # Legacy call reports
```

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Focus global search |
| `Esc` | Close floating panel/modal |

---

## Configuration

Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pbx_manager');
```

---

## Technology Stack

- **Backend:** Core PHP
- **Database:** MySQL
- **Frontend:** Vanilla JS, CSS3
- **Icons:** Font Awesome 6
- **Fonts:** Inter (Google Fonts)

---

## Security Notes

- Sessions for authentication
- Prepared statements for SQL queries
- XSS prevention via `htmlspecialchars()`
- PBX credentials stored in database settings

---

## Changelog

### v2.0
- **Unified dashboard** with 3-column layout
- **Glass dark theme** - Semi-dark with blur effects
- **Collapsible sidebar** (collapsed by default)
- **Inline forms** - No modal popups for quick actions
- **Favorites** - Star important contacts
- **Call tags** - Categorize calls
- **Drive links** - Add Google Drive links to calls
- **Talk time tracking** - Per contact talk time
- **Personal notes** - Agent private notes
- **Keyboard shortcuts** - Ctrl+K, Esc
- **Mobile responsive** - Swipe/tap navigation
- **Notification bell** - With toast notifications

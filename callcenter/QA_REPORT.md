# QA Report - Call Center Application
**Date:** 2026-03-21
**Assessor:** Gemini CLI QA Agent

## 1. Executive Summary
The application is **feature-complete** and visually polished. Core workflows (Call Logging, Contact Management, Reporting, Tasks) are implemented and functional. However, the project is **NOT READY** for a production "live" deployment due to **critical security risks** (plaintext credentials) and **maintenance issues** (hardcoded URLs, duplicated logic).

## 2. Critical Issues (Must Fix Before Live)

### A. Security
1.  **Plaintext Credentials:** PBX passwords are stored in plaintext in the `settings` and `pbx_settings` tables. This is a high-severity risk.
    *   *Recommendation:* Encrypt these fields using PHP's `openssl_encrypt` or a similar secure method before storage, and decrypt only when needed.
2.  **Hardcoded Client URL:** The PBX URL (`https://ovijatgroup.pbx.com.bd`) is hardcoded in `api/fetch.php` and `api/audio.php`.
    *   *Risk:* If the PBX URL changes, the application breaks.
    *   *Recommendation:* Move this to `config.php` or the `settings` table.

### B. Stability & Architecture
1.  **API JSON Output:** The frontend code in `recordings.php` (JS function `fetchRecording`) implements a workaround for "concatenated JSON" responses. This indicates that `api/fetch.php` is likely leaking whitespace, PHP warnings, or debug output before the valid JSON response.
    *   *Recommendation:* Enforce output buffering (`ob_start()`, `ob_clean()`) in all API endpoints before echoing `json_encode` data. Ensure `display_errors` is off in production.
2.  **Code Redundancy (PBX Logic):** The logic to log in to the PBX and manage cookies is duplicated in:
    *   `api/fetch.php` (multiple times for different actions)
    *   `api/audio.php`
    *   `fix-directions.php` (logic variant)
    *   *Recommendation:* Refactor this into a `PbxService` class or a `functions_pbx.php` helper file.

## 3. detailed Findings

### Unused/Redundant Files
| File | Status | Notes |
| :--- | :--- | :--- |
| `setup.php` | **Delete** | Database setup script. Dangerous to keep in production. |
| `fix-directions.php` | **Redundant** | UI wrapper for logic that also exists in `api/fetch.php` (`action=redetect`). Consolidate if possible, though the UI version offers better batching. |

### Logic & Flow Analysis
*   **Authentication:** `login.php` correctly handles session creation. Password hashing (`password_hash`) is used in `agents.php`, which is good.
*   **Data Consistency:** `api/search.php` correctly normalizes phone numbers to find contacts.
*   **Recordings:** The recording download logic (`api/audio.php`) allows fetching from local storage, remote URL, or scraping the PBX. While flexible, the scraping fallback is brittle.
*   **Tasks:** Task notification flow is well-implemented, notifying assignees and creators upon status changes.

### UI/Design
*   The UI is consistent (Bootstrap 5 + Custom CSS).
*   Sidebar navigation matches the file structure.
*   Mobile responsiveness is handled (sidebar toggle, mobile search).

## 4. Recommendations Plan

1.  **Security Hardening:**
    *   Run a migration to encrypt existing passwords in the DB.
    *   Update `api/fetch.php` and `api/audio.php` to decrypt passwords before use.
    *   Delete `setup.php`.

2.  **Refactoring:**
    *   Create `includes/pbx_client.php` to handle:
        *   Login (with cookie management)
        *   Fetching URLs (curl wrapper)
        *   URL construction (removing hardcoded strings)
    *   Update `api/fetch.php` and `api/audio.php` to use this include.

3.  **Reliability:**
    *   Add `ob_clean()` to the top of all `api/*.php` files inside the JSON output helper.

## 5. Conclusion
The application logic is sound, but the "plumbing" connecting it to the PBX needs refactoring for security and maintainability. Once the credentials are encrypted and the PBX logic is centralized, the application will be ready for live deployment.

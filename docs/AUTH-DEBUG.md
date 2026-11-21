# Authentication & Session Persistence Debugging Report

This document records the Phase 1 reproduction, race condition verification, and front-end session audit for customer login persistence across `wex-api` and `wex-web`.

---

## 1. Reproduction Test Results Summary

Tests executed against CodeIgniter 4 backend (`http://127.0.0.1:8081/api/v1`) in Linux/Termux environment.

| Test | Command Pattern | HTTP Status | Verdict | Details |
|---|---|---|---|---|
| **1. Email OTP Login & Cookie Creation** | `curl -i -c cookiejar.txt POST /auth/email/verify` | **200 OK** | **PASS** | Set-Cookie: `wex_refresh_token`, HttpOnly, Path=/, SameSite=Lax, Max-Age=2592000 (30 days). |
| **2. Single Refresh with Cookie Jar** | `curl -i -b cookiejar.txt -c cookiejar.txt POST /auth/refresh` | **200 OK** | **PASS** | Returns fresh `access_token` and rotated `wex_refresh_token`. Max-Age reduced (2591994), proving expiry did **not** slide. |
| **3. Parallel Refresh Race Condition** | `curl -b jar ... /auth/refresh & curl -b jar ... /auth/refresh` | **200 OK & 422/401** | **BUG REPRODUCED** | First request rotated token; second parallel request with original cookie triggered theft detection, revoking the entire family including the new token. |
| **4. Database Pattern on Reuse** | `SELECT id, user_id, family_id, expires_at, revoked_at, replaced_by_id FROM refresh_tokens` | **N/A** | **BUG REPRODUCED** | Token #5 replaced by #6. Presenting #5 immediately revokes active token #6 (`revoked_at` set), killing customer session. |
| **5. Missing Refresh Token HTTP Code** | `curl -i -X POST /auth/refresh -d '{}'` (no cookie) | **422 Unprocessable** | **FAIL (Must be 401)** | Missing cookie returns 422 with `"رمز التحديث مطلوب"` instead of 401 Unauthorized. |
| **6. Secret Stability Across Restarts** | `JWT_SECRET` in `.env` | **N/A** | **PASS** | `JWT_SECRET` is persistent in `.env` (64 hex characters) and checked at boot. |

---

## 2. Front-End Session Audit Findings

1. **Bootstrap Session Restore**:
   - `AuthContext.jsx` runs `initSession()` in `useEffect` on mount.
   - It directly calls `authApi.refresh()`, which issues an independent `fetch` request without checking or populating `performTokenRefresh()`.
2. **React StrictMode Double-Mount**:
   - In development/StrictMode, `initSession()` is called twice on startup. Because `authApi.refresh()` bypassed the single-flight promise, two identical refresh requests fired back-to-back, triggering immediate family revocation.
3. **Network Failure Logging Out Users**:
   - `AuthContext.jsx` had a bare `catch` block in `initSession()` calling `clearSession()`. Any transient network failure or server 5xx unauthenticated the customer instead of showing an offline/retry state.
4. **Missing Refresh Token Returning 422**:
   - The backend returned 422 when the refresh token was missing or empty, violating the convention where invalid or absent session credentials return 401.
5. **Absence of Reuse Grace Window**:
   - The backend `TokenService.php` had zero tolerance for reuse. A client sending an already-rotated token even 1 millisecond later caused instant nuclear revocation of the entire family.
6. **No Expiry Sliding**:
   - Rotated tokens strictly inherited the original `expires_at`, capping the session at 30 days from initial login rather than 30 days from last use.

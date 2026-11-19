# Wex API - Working Curl Examples

This document contains verified working `curl` commands for all endpoints.

## Base URL
Local Development: `http://localhost:8081/api/v1`

---

## Phase 1: Core & Health Check

### Health Check (Ping)
```bash
curl -i http://localhost:8081/api/v1/ping
```

**Expected Response (200 OK):**
```json
{
    "success": true,
    "message": "خدمة ويكس تكنولوجي تعمل بنجاح",
    "data": {
        "service": "wex-api",
        "version": "1.0.0",
        "timestamp": "2026-11-19T15:09:07Z",
        "env": "development"
    },
    "errors": null,
    "meta": null
}
```

### English Locale Negotiation
```bash
curl -i -H "Accept-Language: en" http://localhost:8081/api/v1/ping
```

### Preflight CORS OPTIONS
```bash
curl -i -X OPTIONS http://localhost:8081/api/v1/ping \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET"
```

### 404 Route Not Found
```bash
curl -i http://localhost:8081/api/v1/nonexistent
```

**Expected Response (404 Not Found):**
```json
{
    "success": false,
    "message": "الرابط المطلوب غير موجود",
    "data": null,
    "errors": null,
    "meta": null
}
```

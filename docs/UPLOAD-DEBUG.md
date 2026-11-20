# Payment Proof Upload Verification & Debugging Report

This document records the end-to-end debugging, reproduction, and verification for the Bankak manual payment transfer proof (receipt) upload endpoint (`POST /api/v1/orders/{order_number}/payment-proof`).

---

## 1. Test Results Summary

All tests executed against local CodeIgniter 4 backend (`php spark serve --port 8081`) in Linux/Termux environment.

| Test Case | Command Pattern | HTTP Status | Verdict |
|---|---|---|---|
| **Baseline valid JPEG upload** | `curl -F 'proof_image=@small.jpg;type=image/jpeg' -F 'sender_name=محمد أحمد' -F 'reference_number=REF123'` | **201 Created** | **PASS** |
| **Blob upload: `filename="blob"`** | `curl -F 'proof_image=@small.jpg;filename=blob;type=image/jpeg'` | **201 Created** | **PASS** |
| **Blob upload: `filename="receipt"` (no ext)** | `curl -F 'proof_image=@small.png;filename=receipt;type=image/png'` | **201 Created** | **PASS** |
| **Wrong field name (`image` instead of `proof_image`)** | `curl -F 'image=@small.jpg'` | **422 Unprocessable Entity** | **PASS** |
| **3 MB image upload (< 5 MB app limit)** | `curl -F 'proof_image=@3mb.jpg'` | **201 Created** | **PASS** |
| **9 MB oversize upload (> 5 MB limit)** | `curl -F 'proof_image=@9mb.jpg'` | **413 Payload Too Large** | **PASS** |
| **CORS preflight OPTIONS request** | `curl -X OPTIONS -H 'Origin: http://localhost:5173' -H 'Access-Control-Request-Method: POST'` | **204 No Content** | **PASS** |
| **Expired order lock** | `curl -F 'proof_image=@small.jpg' /orders/EXPIRED/payment-proof` | **409 Conflict** | **PASS** |
| **Non-owner user upload** | `curl -F 'proof_image=@small.jpg' -H 'Authorization: Bearer <other>'` | **403 Forbidden** | **PASS** |
| **Non-image disguised as JPG** | `curl -F 'proof_image=@fake.jpg;type=image/jpeg'` | **422 Unprocessable Entity** | **PASS** |
| **Replacement upload in `payment_review`** | `curl first.jpg -> curl second.png` (replaces previous proof) | **201 Created** | **PASS** |
| **Rate limit (6th upload within hour)** | `curl 5x valid uploads -> 6th upload` | **429 Too Many Requests** | **PASS** |

---

## 2. API Contract & Implementation Details

- **Endpoint**: `POST /api/v1/orders/{order_number}/payment-proof`
- **Authentication**: `Bearer <customer_token>`
- **Content-Type**: `multipart/form-data`
- **Fields**:
  - `proof_image` (Required, file): Image file (`image/jpeg`, `image/png`, `image/webp`).
  - `sender_name` (Optional, string, max 150): Account owner name who sent the transfer.
  - `reference_number` (Optional, string, max 100): Bankak transaction reference ID.

### MIME Validation & Nameless Blob Handling
- File extension is **never** trusted from the client.
- `finfo(FILEINFO_MIME_TYPE)` inspects raw bytes to verify real MIME.
- Nameless files from mobile camera or canvas (e.g. `blob` or `receipt`) are accepted and saved with server-generated random alphanumeric names (`bin2hex(random_bytes(20))`) and normalized extensions (`.jpg`, `.png`, `.webp`).
- If GD lacks a decoder or re-encoding fails, `ImageService` falls back gracefully to storing the validated raw file securely rather than crashing with HTTP 500.

### Replacement Uploads
- When an order is in `payment_review` and price lock has not expired, a customer can upload an updated receipt.
- Any previous pending proof is marked as `replaced`.
- The new proof is created with status `pending`.
- A timeline event is logged: `"قام العميل بتحديث إشعار التحويل واستبدال الإشعار السابق للمراجعة"`.

---

## 3. Configuration & PHP Limits

### Termux Local Development
Termux PHP defaults allow up to 64 MB. If raising or tuning upload limits locally:
Create `/data/data/com.termux/files/usr/etc/php/conf.d/uploads.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
```

### Production Deployment
In production (PHP-FPM + Nginx):
1. **PHP-FPM `php.ini`**:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   memory_limit = 256M
   max_file_uploads = 5
   ```
2. **Nginx configuration**:
   ```nginx
   client_max_body_size 12M;
   ```
3. **Application `.env`**:
   ```env
   UPLOAD_MAX_MB=5
   ```

# Private Notes Tile Delivery

Secure tile delivery is implemented in `public_html/api/private_notes_api.php` with the delivery helpers in `public_html/api/private_notes_delivery.php`.

## Endpoint Contract

All endpoints use the existing site PHP session. No separate authentication system is introduced.

### Start Viewing Session

`POST /api/private_notes_api.php?action=startViewingSession`

Form fields:
- `documentId`: processed private note document id.
- `deviceToken`: optional token previously returned for this browser/device.
- `deviceLabel`: optional label for a newly registered device.

Behavior:
- Requires an authenticated user.
- Checks the existing private-notes document access rules.
- Creates a registered device when `deviceToken` is omitted.
- Rejects unknown, malformed or revoked device tokens.
- Creates an active viewing session bound to the user, document, registered device and user-agent hash.

Response:
```json
{
  "success": true,
  "viewingSession": {
    "sessionId": "pnses-...",
    "deviceId": "pndev-...",
    "deviceToken": "returned-only-for-new-devices",
    "traceCode": "AB12CD34",
    "expiresInSeconds": 14400
  }
}
```

### Issue Tile Token

`POST /api/private_notes_api.php?action=tileToken`

Form fields:
- `documentId`
- `sessionId`
- `deviceToken`
- `pageNumber`
- `zoomLevel`
- `tileX`
- `tileY`

Behavior:
- Requires an authenticated user.
- Rechecks document access, active viewing session, registered device, user-agent binding and tile metadata.
- Signs a short-lived token for exactly one user/document/session/page/zoom/tile coordinate set.

Response:
```json
{
  "success": true,
  "tileToken": {
    "token": "base64url-json.hmac",
    "expiresAt": "2026-07-16T15:40:00+03:30",
    "expiresInSeconds": 60,
    "tile": {
      "documentId": "pndoc-...",
      "sessionId": "pnses-...",
      "pageNumber": 1,
      "zoomLevel": 0,
      "tileX": 0,
      "tileY": 0
    }
  }
}
```

### Deliver Personalized Tile

`GET /api/private_notes_api.php?action=tile&documentId=...&sessionId=...&pageNumber=1&zoomLevel=0&tileX=0&tileY=0&token=...`

Behavior:
- Requires the existing authenticated PHP session.
- Rechecks access, viewing session, registered device and signed token.
- Loads the private unwatermarked source tile from `server-only/storage/private_notes/pages/`.
- Burns a repeated diagonal personalized watermark into the returned PNG pixels at runtime.
- Records a lightweight `tile-view` event.
- Does not return or expose the original PDF path or unwatermarked tile URL.

Response headers include:
- `Content-Type: image/png`
- `Cache-Control: private, no-store, max-age=0`
- `Pragma: no-cache`
- `X-Content-Type-Options: nosniff`

## Watermark Contents

The runtime watermark includes:
- user display name
- student number when available
- masked phone number, or masked email if phone is unavailable
- current server date/time
- session trace code
- secondary trace identifier in the tile corner

The watermark is generated server-side using ImageMagick and embedded into actual image pixels. It is not a CSS overlay and the browser never receives the unwatermarked source tile.

## Environment Variables

Required:
- `DENT_PRIVATE_NOTES_TILE_SIGNING_SECRET`: high-entropy secret for tile tokens, device token hashes, session trace hashes and request hashes.

Processing/runtime tools:
- `DENT_PRIVATE_NOTES_IMAGEMAGICK_BIN`: absolute path to `magick` when it is not on `PATH`.

Optional:
- `DENT_PRIVATE_NOTES_TILE_TOKEN_TTL_SECONDS`: token lifetime, default `60`, allowed `10..600`.
- `DENT_PRIVATE_NOTES_VIEWING_SESSION_TTL_SECONDS`: active session idle lifetime, default `14400`.
- `DENT_PRIVATE_NOTES_TILE_RUNTIME_CACHE_TTL_SECONDS`: server-side runtime cache for personalized tiles, default `20`; this cache is under `server-only/tmp/` and is never public.
- `DENT_PRIVATE_NOTES_WATERMARK_OPACITY`: default `0.18`, clamped to `0.08..0.45`.
- `DENT_PRIVATE_NOTES_WATERMARK_FONT`: absolute or project-relative path to a Persian-capable TTF/OTF font.
- `DENT_PRIVATE_NOTES_WATERMARK_VERSION`: bump to invalidate short-lived runtime watermark cache.

## Security Notes

- Tile tokens use HMAC-SHA256 and constant-time signature comparison.
- Tokens are bound to user id, document id, page number, zoom level, tile coordinates, viewing session id and expiration timestamp.
- Revoked devices and inactive/expired viewing sessions are denied even if a token has not expired yet.
- Rate-limiting hooks are present in `private_notes_rate_limit_tile_hook`; the full anomaly system is intentionally left for the later security-events phase.

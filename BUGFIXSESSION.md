# Bugfix session — 2026-10-15

Branch: `bugfix-session-2026-10-15` (based on `main` @ cc9677a, v5.8.3). Nothing pushed.
Version: `includes/version.php` bumped 5.8.3 → 5.8.4 (fixes only, no `feat:` merged).
Test suite: `php tests/run.php` (PHP) and `node --test tests/<file>.test.js` (JS).

PR branches were merged with `-Xignore-cr-at-eol` because several PRs converted CRLF files to LF
(whole-file diffs); CRLF was restored where it had been lost (`includes/checksession.php`).

## Fixed — merged from PRs (reviewed: diff read in full, tests run)

| PR | Issue | Review notes |
|----|-------|--------------|
| #1260 | #1259 | Missing payer / payment method / category no longer warn in list views and sorts. Null-safe `??` lookups, missing sort last. Tests cover NULL and unresolved ids. OK. |
| #1180 | #1179-style | `isset()` guards on payment-method counters in `stats_calculations.php`. Minimal, OK. |
| #1240 | #1239 (part 1) | OIDC state is persisted to disk *before* the token exchange so a retried callback cannot redeem the same code twice. Test reads the session file from outside during the exchange. Restored CRLF in `checksession.php`. See "Needs decision" for part 2. |
| #1229 | — | VAPID keypair write made conditional + read back, so two first-use requests cannot replace the key (silent 403 for all subscribed devices). OK. |
| #1228 | #1212 | Notification cron fatal (TypeError on `CURLOPT_HTTPHEADER` null). **Changed during review:** the shared `webhook_custom_headers()` only accepted JSON, which would have silently dropped the newline-separated format the cancellation job used to read. Added a line-based fallback (kept backward compatible), and CR/LF stripping so a header value cannot smuggle another header. One of the PR's assertions was updated accordingly. |
| #1230 | — | CI only: `type=cacheonly` for PR builds, fixes red checks on fork PRs. OK. |
| #1263 | — | Theme switch no longer wipes `mobile-navigation` / `rtl` body classes. JS node test passes (4 cases). OK. |
| #1237 | — | Backup zip removed via shutdown function even if the download is abandoned. Tests only pin the shape (cannot cut a stream in the harness) — noted. OK. |
| #1236 | — | Settings API now reports failure when the CSS / colour DELETE or INSERT fails, rather than "success". OK. |
| #1252 | — | Japanese translations: 504/504 keys now match `en.php`; `ja.php` and `ja.js` lint clean. Did not review the Japanese wording itself. |

## Fixed — written in this session

| Issue | Fix | Tests |
|-------|-----|-------|
| #863 CSV export shows `Â£` | `scripts/profile.js`: CSV is prefixed with a UTF-8 BOM and served as `text/csv`, so Excel reads UTF-8. | `tests/csv_export.test.js` |
| #990 Webhook body sent as form data | JSON payloads now get `Content-Type: application/json` unless the user set their own Content-Type. Applied to both crons and the test button. `webhook_headers_with_content_type()` in `includes/webhook_headers.php`. | `tests/cases/webhook_headers_test.php` |
| #891 OIDC logout "Missing parameters: id_token_hint" | ID token kept in the session at OIDC login; logout redirect now sends `id_token_hint`, `client_id` and `post_logout_redirect_uri` (`includes/oidc/logout_url.php`). Also handles logout URLs that already contain `?`. | `tests/cases/oidc_logout_url_test.php` |
| #861 Sort by price ignores currency | Price sort is now done on the main-currency (and per-month, if that setting is on) value, in both `subscriptions.php` and the filter endpoint. Ties keep DB order; disabled-to-bottom respected. | `tests/cases/price_sort_test.php` |

## Needs your input / not done

| Item | Why |
|------|-----|
| PR #1238 (non-200 status for refused endpoint requests) | Not merged. Changes every refusal to 401/403/405. About 18 JS callers do `if (!response.ok) throw/show generic error` *before* reading the body, so the user would see a generic "failed" instead of the server's "session expired" message. Needs a decision, and a pass over `scripts/*.js` if wanted. |
| PR #1249 (grid layout for #1149 column alignment) | Not merged. Large CSS change to the main list, also hides the renewal icon on mobile. I cannot judge it without looking at it in a browser. Worth a visual check on devhub. |
| #1239 part 2 | The second half (don't delete the old session on `session_regenerate_id`, leave a short-lived pointer so the retry lands in the new session) touches the whole sign-in path. The reporter offered to do it; I did not, since it is a design decision. |
| #1142 dashboard currency | The maintainer says it is intentional (next payments show the real amount). It is a feature request, not a bug. |
| #930 custom payment method | Reporter found the cause: SMB-mounted logos folder. Environment problem, not code. |
| #862 502 behind nginx | Reporter's IPv6 setup; `nginx.default.conf` already uses `ipv6only=off`. No repro, no change. |

(Further items below as the session continues.)

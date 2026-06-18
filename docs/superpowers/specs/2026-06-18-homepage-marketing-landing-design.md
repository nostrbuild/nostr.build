# Homepage marketing landing + browser-upload removal

**Date:** 2026-06-18
**Status:** Approved (design)

## Goal

Browser-based free media uploads are being retired entirely (there is no longer
even a limited allowance for logged-in paid users). Free uploads now happen only
through the nostr.build **API** and the Nostr clients that integrate it.

Turn the homepage from an upload tool into a presentable **marketing landing
page** that:

1. Explains that free uploads are supported through the API / integrated apps.
2. Removes every browser-facing upload entry point.
3. Gives logged-in subscribers an obvious, grandmother-proof path to
   `account.nostr.build`.
4. Shortens (unchains) redirects so links point directly at the final
   destination on `account.nostr.build`.

The API upload endpoints (`api/v2/routes_upload.php`) are **out of scope** and
remain live — they are the channel being marketed.

## 1. Homepage redesign — `index.php`

Remove the permission gate and the entire `.drag-area` upload form (the
logged-in box and the guest else-branch). The page is now the same structure for
everyone, with one light `$perm->isGuest()` switch on the hero call-to-action.

Page structure inside `<main>`, top → bottom:

- **Account banner** (always shown — both states): a prominent card at the very
  top giving an obvious, can't-miss path to `https://account.nostr.build/`.
  - Signed-in here (`!$perm->isGuest()`): personalized green card — circular
    profile pic (`$_SESSION['ppic']`, fallback to the existing temp avatar),
    "Welcome back, {nym}" (fallback "Welcome back"), button **"Open my account →"**.
  - Guest here (`$perm->isGuest()`): neutral card — generic account icon,
    "Already have an account?" + copy noting they may still be signed in on
    account.nostr.build, button **"Go to your account →"**.
  - **Why always show it:** this site's PHP session and the account.nostr.build
    session are independent, and the account session is much longer-lived. A
    subscriber whose nostr.build session lapsed (expired cookie) but whose account
    session is still active would otherwise see no account path. We can't read the
    other app's cookies, so we always offer the link and let `account.nostr.build/`
    resolve the real session (dashboard if active, else its own login).
- **Hero** — H1 `nostr media uploader` (kept), marketing headline, subcopy, the
  live stat pills (kept: GB used / total uploads), and the CTA row:
  - Guests: primary `Get started` → `account.nostr.build/plans`, secondary
    `Explore features` → `account.nostr.build/features`. (Login/return is handled
    by the guest account banner above, so no separate "Log in" link here.)
  - Logged-in: the welcome banner above is the primary action; hero CTA row shows
    `Explore features` (secondary) only, to avoid redundancy.
- **3-up value-prop grid** (same for everyone):
  1. **Powered by our API** — Damus, Amethyst, Primal, Snort, YakiHonne,
     noStrudel, Coracle and more upload directly to nostr.build. Free uploads,
     baked into the apps you already use.
  2. **Privacy by default** — Every file is stripped of EXIF and location
     metadata before it goes live. Your media, not your whereabouts.
  3. **Built for Nostr — Bitcoin only** — Purpose-built media hosting for Nostr.
     No ads, ever. Upgrade for more storage and pro features, paid in Bitcoin.
- **Supported-clients strip** — Trusted across Damus · Primal · Amethyst · Snort ·
  YakiHonne · noStrudel · Coracle · Blossom.
- **Developer/API callout** — "Building a Nostr client? Integrate free and pro
  uploads with NIP-98 auth and Blossom support." CTA → `account.nostr.build/features`.
- **Terms** line (kept; link unchained — see §3).

Cleanup in `index.php`: remove the six upload-only SVG heredocs
(`$svg_sharing_container`, `$svg_image_address`, `$svg_drag_area_header`,
`$svg_upload_button`, `$svg_import_icon`, `$svg_drag_area_loading`) and the
`<script defer src="/scripts/index.js">` tag. Keep the `UploadsData` stats and the
`config.php` / `permissions.class.php` includes (`$perm` is still used for the
guest/logged-in CTA switch). Refresh the meta description to reflect API-based
free uploads.

### Styling — `styles/index.css`

Reuse existing design tokens: card gradient `#292556 → #1e1530`, gradient text
(`#ffffff → #884ea4`), stat pills (`#150d29` bg, `#2b2157` border), light CTA
button gradient (`#ffffff → #b098bb`, text `#120a24`), green accent
(`#2edf95 → #07847c`), secondary text `#a58ead`, primary text `#d0bed8`.

- Append new marketing classes (welcome block, hero CTA row, value-prop grid,
  clients strip, dev callout). Content width stays `max-width: 696px` to match
  the old `.drag-area`, with the value-prop grid allowed to go wider on desktop.
- Remove the now-dead `.drag-area*`, `.import*`, `.upload_button*`, `.metadata_*`,
  `.sharing_*`, `.loading_*`, `.spinner*`, `.cancel_upload`, `.preview`,
  `.profilepic`, `.toast*` rules that only served the upload widget. (Verify each
  is not referenced by another page before deleting; `upload.php` success page
  reuses `.drag-area`/`.metadata_*`/`.toast` — see §2, those go away with it.)

## 2. Remove browser upload paths

- `index.php`: handled in §1.
- `scripts/index.js`: **delete** — only ever powered the homepage drag-area
  widget. Confirm via grep that no other page references it.
- `v1/index.php`: replace the legacy uploader page with a redirect to `/` (the new
  marketing homepage), so users land on-site where the CTAs live.
- `upload.php`: retire the browser handler. Respond `410 Gone` with a short
  message directing to the API / account site; redirect stray GET requests to `/`.
  Do not process uploads. (API path `api/v2/routes_upload.php` untouched.)

## 3. Collapse redirects + unchain in-app links

Direct redirects already exist: `/login`, `/account`, `/plans`, `/features`,
`/about`, `/tos` → their `account.nostr.build` equivalents.

Collapse the two-hop chains so they skip the intermediate local hop:

- `signup/index.php`, `signup/new/index.php`, `register/index.php`:
  `→ https://account.nostr.build/plans`.
- `account/ai/index.php`: `→ https://account.nostr.build/` (keep 301).

Unchain in-app links that point at known local redirect pages so they go straight
to `account.nostr.build`:

- `components/footer.php`: `/about` → `account.nostr.build/about`,
  `/plans` → `account.nostr.build/plans`.
- `components/mainnav.php`: mobile-menu `/about` → `account.nostr.build/about`.
- `index.php` terms link `/tos/` → `account.nostr.build/tos`.

Leave `/creators`, `/delete/`, `/builders`, `/edu` alone — not account redirects.

## Files touched

`index.php`, `styles/index.css`, `scripts/index.js` (delete), `v1/index.php`,
`upload.php`, `signup/index.php`, `signup/new/index.php`, `register/index.php`,
`account/ai/index.php`, `components/footer.php`, `components/mainnav.php`.

## Verification

- Logged-out: homepage shows marketing landing + guest CTAs; no upload box; no
  console error from missing `index.js`.
- Logged-in: prominent "Open my account" block appears at top, links to
  `account.nostr.build`.
- `POST /upload.php` returns 410; `GET /upload.php` redirects to `/`.
- `/v1/` redirects to `/`.
- `signup`, `signup/new`, `register` land on `account.nostr.build/plans` in one
  hop; `account/ai` lands on `account.nostr.build/` in one hop.
- Nav/footer/terms links to about/plans/tos resolve directly to
  `account.nostr.build` with no intermediate redirect.
</content>
</invoke>

# Sales Script Builder

WordPress plugin that stores products/services, pain points, competitor
comparisons, objection handling, and upsell paths, then assembles them into
live call scripts (Cold Call, Inbound, Upsell) for paid members.

## Status

Early scaffold — core structure is in place and locally testable. Not yet
installed on the live site. Needs a developer for: server installation,
resolving environment-specific issues (PHP version, caching), and anything
requiring build tooling not configured locally.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- **MemberPress** (membership/paywall — see Access Control below). Still
  being evaluated as the permanent membership solution as of this writing.
- WPML or Polylang for EN/ES translation (matching the setup already used on
  the Meta Ads Image Generator plugin) — not yet wired in, just assumed.
- GA4 (`gtag.js`) already loaded on the site elsewhere. This plugin does not
  load the base GA4 snippet itself, only fires events into it.

## Structure

```
sales-script-builder/
├── sales-script-builder.php     Main plugin file, bootstraps everything
├── includes/
│   ├── class-post-types.php     CPTs: ssb_product, ssb_special + ssb_category taxonomy
│   ├── class-meta-fields.php    Repeater meta boxes + read helpers
│   ├── class-favorites.php      User-level favoriting (AJAX)
│   ├── class-ga4-events.php     Enqueues anonymous GA4 event tracking
│   ├── class-access-control.php Membership gate (MemberPress wrapper)
│   └── class-shortcodes.php     [ssb_script_builder] front-end shortcode
├── templates/
│   └── script-view.php          Rep-facing script display
├── assets/
│   ├── js/                      admin-repeater, favorites, ga4-events, copy-protect
│   └── css/                     admin.css, frontend.css
└── languages/                   .mo/.po files (empty — pending WPML/Polylang setup)
```

## Data model

**`ssb_product`** (Product/Service)
- Title, description (post_content), price/tier
- Repeater: pain points + trigger phrases
- Repeater: competitor comparisons (name, feature, us, them, why it matters)
- Repeater: objections (concern, response, tone)
- Repeater: upsell paths (linked next product, benefit, ideal timing)
- Taxonomy: `ssb_category`

**`ssb_special`** (Special/Discount)
- Title, description, start/end date, applicable products (checkbox list)
- "Active" is calculated from today's date vs. start/end — no manual expiry

## Access control

`SSB_Access_Control::user_has_access()` is the **only** place that should
check membership status. Everything else (shortcode, template, AJAX
handlers) calls this method rather than touching MemberPress directly. If
the membership plugin changes, only `class-access-control.php` needs to be
rewritten.

Currently checks `MeprUser::is_active()`. Falls back to
"logged in = access" if MemberPress isn't active, so the plugin stays
testable in local dev before MemberPress is installed. **Remove that
fallback once MemberPress is confirmed as permanent.**

Tier-based gating (e.g. Premium sees specials/upsell, Basic doesn't) is not
implemented yet but the wrapper is structured to support it later via
`get_user_membership_level()`.

## Favorites

Stored as user meta (`ssb_favorite_scripts`), keyed as `{product_id}_{call_type}`
so the same product can be favorited differently per call type. AJAX toggle,
no separate DB table needed at this scale.

**Two front-end shortcodes:**
- `[ssb_script_builder]` — the main product/call-type picker + script output
- `[ssb_favorites]` — "My Scripts" dashboard listing everything the user has
  starred, each linking straight into the script view

Both shortcodes should be placed on their own pages. The favorites dashboard
links back to the script-view page using a `SSB_SCRIPT_VIEW_SLUG` constant
(defined in `templates/favorites-dashboard.php`, defaults to `sales-scripts`)
— **this must match the actual slug of the page hosting `[ssb_script_builder]`**,
and the same slug is checked in `class-access-control.php`'s `is_page()` call.
All three should be updated together if the page slug ever changes.

## GA4 tracking

Fully anonymous/aggregate by design — **no `user_id`, email, or username is
ever passed into an event.** Only `product_id`, `category`, and `call_type`.
Events fired: `select_product`, `select_call_type`, `favorite_script`,
`view_special`.

## Copy protection

Deterrent only, not a guarantee — screenshots and dev tools remain an
option for a determined user. Implemented: disabled text selection,
disabled right-click, disabled Ctrl/Cmd+C with a brief on-screen notice.
The actual control is server-side: `class-access-control.php` re-verifies
membership on every request rather than only at login, so access dies the
moment a subscription is cancelled.

## Known open items / needs developer input

1. **Front-end page slug**: `class-access-control.php` currently checks
   `is_page( 'sales-scripts' )` — confirm the real page slug once created.
2. **Membership/join page URL**: hardcoded placeholder `/membership/` in
   the redirect — confirm real URL.
3. **WPML/Polylang**: not yet wired in. Need to confirm which one is used
   on the Image Generator plugin and match it here.
4. **Tiered access**: not implemented (all active members get full access
   for now). Flag if Basic vs. Premium gating is needed before launch.
5. **MemberPress confirmation**: once confirmed as permanent, remove the
   "logged in = access" fallback in `class-access-control.php`.
6. **Page slug consistency**: `sales-scripts` is used in three places
   (`class-access-control.php`, `templates/favorites-dashboard.php`, and
   wherever `[ssb_script_builder]` is placed). Confirm all three match once
   the real page is created.

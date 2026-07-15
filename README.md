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
│   ├── class-competitors.php    Competitors library CPT (pros/cons/counters)
│   ├── class-meta-fields.php    Repeater meta boxes + read helpers
│   ├── class-favorites.php      User-level favoriting (AJAX)
│   ├── class-ga4-events.php     Enqueues anonymous GA4 event tracking
│   ├── class-access-control.php Membership gate (MemberPress wrapper)
│   ├── class-shortcodes.php     [ssb_script_builder] + [ssb_favorites] shortcodes
│   ├── class-settings.php       Settings page (script-view page slug)
│   ├── class-sample-content.php One-click insert/remove of example data
│   └── class-admin-columns.php  Custom admin list table columns
├── templates/
│   ├── script-view.php          Rep-facing script display
│   └── favorites-dashboard.php  "My Scripts" favorites list
├── assets/
│   ├── js/                      admin-repeater, favorites, ga4-events, copy-protect,
│   │                             objection-buttons, discovery
│   └── css/                     admin.css, frontend.css
└── languages/                   .mo/.po files (empty — pending WPML/Polylang setup)
```

## Data model

**`ssb_product`** (Product/Service)
- Title, description (post_content), price/tier
- Repeater: pain points + trigger phrases
- Repeater: competitor comparisons (name, feature, us, them, why it matters) — manual, feature-by-feature
- Linked Competitors — checkbox list referencing entries in the Competitors library (see below)
- Repeater: objections — objection type, concern, script, key points (recap, one per line), counter script (optional), tone
- Repeater: upsell paths (linked next product, benefit, ideal timing)
- Taxonomy: `ssb_category`

**`ssb_special`** (Special/Discount)
- Title, description, start/end date, applicable products (checkbox list)
- "Active" is calculated from today's date vs. start/end — no manual expiry

**`ssb_competitor`** (Competitors library)
- Title (competitor name)
- Their pros (textarea, one per line — stay honest here, it's what makes the counter credible)
- Their cons (textarea, one per line)
- Counter talking points (textarea, one per line)
- Referenced from products via "Linked Competitors" and surfaced during the
  outbound discovery step when a rep selects which competitor a prospect
  named. Separate from the per-product comparison repeater above: this is
  general reusable knowledge, entered once; the repeater is a manual,
  feature-by-feature table entered per product.

## Discovery step

Shown at the top of the script view, before pain points, on **cold** and
**inbound** calls only (upsell scripts skip straight to the pitch, since the
rep already knows the customer).

- **Cold call**: asks what the prospect currently uses. "Using a competitor"
  opens a picker (built from the product's Linked Competitors, or the whole
  library if none are linked) showing that competitor's pros/cons/counters
  inline. "Not using anyone" shows a prompt to ask why not.
- **Inbound call**: asks why they're calling, with three branches — looking
  for a new provider, shopping around (scrolls to the comparison table),
  or already ready to buy (guidance to skip to close).

All of this is presentation only, driven by `assets/js/discovery.js` reading
a JSON data attribute built server-side — nothing here writes back to the
database.

## Objection handling UI

Objections render as tappable buttons (`assets/js/objection-buttons.js`),
not a static list:

- All objections show as buttons at the start of a call. Once tapped, an
  objection's button is removed from the row and listed under "Already
  discussed" — this state lives in memory for that page load only and
  resets on the next call, it is never saved.
- Selecting an objection shows the script on the left (or on top, on mobile)
  and a **key points recap** on the right (or below) — short phrases pulled
  from the `key_points` field, meant for a rep who wants to go off script
  but stay on the main points.
- If an objection has a `counter_script`, a **"Try to counter"** button
  appears. Clicking it reveals the counter script plus four outcome
  buttons: *still hesitant* (off-ramp, ends the branch), *compare* (scrolls
  to the `#ssb-compare` comparison table), *close sale*, and *discuss
  upsell* (scrolls to `#ssb-upsell`, only present on upsell-type scripts).
  This pattern isn't limited to timing objections — any objection with a
  counter script filled in gets the same "Try to counter" option.

## Access control

`SSB_Access_Control::user_has_access()` is the **only** place that should
check membership status. Everything else (shortcode, template, favorites
AJAX handler) calls this method rather than touching MemberPress directly.
If the membership plugin changes, only `class-access-control.php` needs to
be rewritten.

**Enforcement toggle** — Products/Services > Settings has a "Require an
active MemberPress subscription" checkbox, off by default:
- **OFF** (default): any logged-in user has access. Intended for testing
  before MemberPress is live on the site.
- **ON**: checks `MeprUser::is_active()`. If MemberPress isn't loaded while
  enforcement is on, access fails closed (denied) rather than silently
  granting it.

Site admins (`manage_options`) always have access regardless of the toggle,
so testing/previewing scripts never depends on holding a membership.

**Turn enforcement ON before opening the site to real members** — that's
the one manual step once MemberPress is confirmed as the permanent
membership solution.

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
links back to the script-view page using the slug configured under
**Products/Services > Settings** in wp-admin (`SSB_Settings::get_slug()`,
option key `ssb_script_view_slug`, defaults to `sales-scripts`). This is now
a single source of truth — `class-access-control.php` and
`favorites-dashboard.php` both read from it instead of hardcoding the slug.
The settings page also shows a live check confirming whether a published
page currently matches the configured slug.

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

## Sample content

**Products/Services > Sample Content** in wp-admin has an "Insert Sample
Data" button that creates two fully populated example products (FastNet 300
and FastNet 1 Gig, each with pain points, competitor comparisons, and
objections — including one with a counter script — linked by an upsell
path), one active special tied to the first product, and one Competitors
library entry ("MegaCable," with pros/cons/counters) linked to both
products. Useful for confirming the discovery step, objection buttons, and
comparison table all render correctly before entering real content.

All sample posts are tagged with post meta so they can be found and removed
cleanly via the "Remove Sample Data" button on the same page — it won't
touch any real content you've already added.

## Admin list table columns

**Products/Services** list adds: Category, Price, Pain Points (count),
Objections (count), and Upsell Path (linked product title). Price is
sortable.

**Specials/Discounts** list adds: Status (Active/Upcoming/Expired badge,
calculated live from today's date), Date Range, and Applies To (linked
product titles). Date Range is sortable.

## Fixed: picker form dropped the page's own query string

`templates/script-view.php`'s product/call-type picker uses `method="get"`
with no `action`. A GET form with no explicit action submits to the current
*path* only and drops any existing query string -- on a staging site using
`?page_id=X&preview=true` (or any site with plain, non-pretty permalinks),
submitting the picker would strip those params and land the visitor
somewhere else entirely (wrong page or lost preview mode), instead of
reloading the script-view page with the script content. Fixed by carrying
forward any existing `$_GET` params (other than `product_id`/`call_type`)
as hidden fields.

## Known open items / needs developer input

1. **Set the page slug**: visit **Products/Services > Settings** in wp-admin
   once the script-view page is created, and enter its slug there (defaults
   to `sales-scripts`). The settings page will confirm whether it matches a
   real published page.
2. **Membership/join page URL**: hardcoded placeholder `/membership/` in
   the redirect (`class-access-control.php`) — confirm real URL.
3. **WPML/Polylang**: not yet wired in. Need to confirm which one is used
   on the Image Generator plugin and match it here.
4. **Tiered access**: not implemented (all active members get full access
   for now). Flag if Basic vs. Premium gating is needed before launch.
5. **Turn on membership enforcement**: once MemberPress is live, check the
   box at **Products/Services > Settings** ("Require an active MemberPress
   subscription"). It's off by default so the plugin stays testable now.
6. **"Close sale" outcome has no dedicated destination yet**: in the
   objection counter-script outcomes, "still hesitant" and "close sale"
   just show an inline note (no section to scroll to); "compare" and
   "upsell" scroll to existing sections. If a dedicated close/checkout flow
   gets built later, wire that outcome button to it in
   `assets/js/objection-buttons.js`.
7. **Tier-matched comparisons are manual for now**: the Linked Competitors
   field surfaces general pros/cons/counters during discovery, but the
   per-product "How We Compare" table is still entered by hand per tier
   rather than auto-pulling from the library. Revisit if that manual entry
   becomes a bottleneck once more tiers exist.

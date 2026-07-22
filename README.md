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
│   ├── class-meta-fields.php    Repeater meta boxes + read helpers (static, reusable)
│   ├── class-favorites.php      User-level favoriting (AJAX)
│   ├── class-ga4-events.php     Enqueues anonymous GA4 event tracking
│   ├── class-access-control.php Membership gate (MemberPress wrapper)
│   ├── class-shortcodes.php     [ssb_script_builder] + [ssb_favorites] shortcodes
│   ├── class-settings.php       Settings page (every plugin page slug + enforcement)
│   ├── class-sample-content.php One-click insert/remove of example data
│   ├── class-admin-columns.php  Custom admin list table columns
│   ├── class-hub.php            [ssb_hub] landing page (members' entry point)
│   ├── class-frontend-editor.php      Front-end product create/edit (members)
│   ├── class-frontend-competitors.php Front-end competitor create/edit (members)
│   └── class-frontend-specials.php    Front-end special create/edit (members)
├── templates/
│   ├── script-view.php               Rep-facing script display
│   ├── favorites-dashboard.php       "My Scripts" favorites list
│   ├── manage-products-list.php      Front-end product list
│   ├── manage-product-form.php       Front-end product create/edit form
│   ├── manage-competitors-list.php   Front-end competitor list
│   ├── manage-competitor-form.php    Front-end competitor create/edit form
│   ├── manage-specials-list.php      Front-end special list
│   └── manage-special-form.php       Front-end special create/edit form
├── assets/
│   ├── js/                      admin-repeater, favorites, ga4-events, copy-protect,
│   │                             objection-buttons, discovery
│   └── css/                     admin.css, frontend.css, manage.css, hub.css
└── languages/                   .mo/.po files (empty — pending WPML/Polylang setup)
```

## Data model

**`ssb_product`** (Product/Service)
- Title, price/tier
- **No native editor support** — the old single post_content "Description"
  field was split into two, per the SPA redesign spec (see
  `/mnt/user-data/outputs/sales-script-builder-spa-spec.md` for the full
  rationale):
  - **Internal Notes** (`_ssb_internal_notes`) — admin/management-only,
    never shown to reps
  - **Overview Highlights** (`_ssb_overview_highlights`) — repeater of
    short, individually-mutable rep-facing highlights, replacing the old
    single paragraph
- Repeater: pain points — pain point text, trigger phrases, and an
  **acknowledgment/pivot script** (what the rep says when a customer names
  this specific issue)
- Repeater: competitor comparisons (name, feature, us, them, why it
  matters) — manual, feature-by-feature
- Linked Competitors — checkbox list referencing entries in the Competitors
  library (see below)
- Repeater: objections — objection type, concern, script, key points
  (recap, one per line), counter script (optional), tone
- Repeater: upsell paths (linked next product, benefit, ideal timing)
- Taxonomy: `ssb_category`

**`ssb_special`** (Special/Discount)
- Title, description (post_content — unaffected by the Products change
  above), start/end date, applicable products (checkbox list)
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
- **Reverse lookup:** `SSB_Meta_Fields::get_products_linked_to_competitor()`
  finds every product linked to a given competitor (the reverse of Linked
  Competitors), and flags each one's next upsell tier if it has one. Built
  for the planned "Competitors At A Glance" flashcard tool — iterates all
  products in PHP rather than a meta_query, since a serialized-array field
  can't be reliably searched via meta_query; fine at this scale, reconsider
  only if the product catalog grows into the hundreds.

### Known interim gap

`templates/script-view.php` still echoes `$product->post_content` in its
"Overview" section (line ~213) — since products no longer write to
post_content, this will render empty for any product created or edited
after this change. This is intentional and temporary: the SPA redesign
(see the spec doc) replaces this entire section with the new tappable Pain
Points + mutable Overview Highlights interaction. Rebuilding it here first
would be throwaway work.

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

## Front-end management (members) + hub

Members manage everything through five pages, none of which touch wp-admin.
Each page's slug is set independently under **Products/Services >
Settings**, since a member-facing site rarely uses the same slugs an
internal tool would.

| Shortcode | Purpose | Default slug |
|---|---|---|
| `[ssb_hub]` | Landing page — links to the four below | `sales-hub` |
| `[ssb_script_builder]` | Jump straight into a script for a call | `sales-scripts` |
| `[ssb_manage_products]` | Add/edit products | `manage-scripts` |
| `[ssb_manage_competitors]` | Add/edit Competitors library entries | `manage-competitors` |
| `[ssb_manage_specials]` | Add/edit specials/discounts (start date, end date, terms, applicable products) | `manage-specials` |

**The hub (`[ssb_hub]`) is meant to be the page members land on first** —
it's a simple set of cards linking to the other four, nothing more. It
doesn't manage any content itself, so it has no save/delete logic.

**How every manage page stays in sync with wp-admin:** none of the three
front-end editors (products, competitors, specials) reimplement their
fields. Each one calls the exact same static rendering methods wp-admin's
meta boxes use — `SSB_Meta_Fields::render_*()` for products and specials,
`SSB_Competitors::render_*()` for competitors — and saving goes through the
matching static save method (`save_product_fields_from_post()`,
`save_competitor_fields_from_post()`, `save_special_fields_from_post()`).
There is exactly one place each field's sanitization rules live; a field
added or changed later is automatically picked up by both wp-admin and the
front end.

**Specials already had start date, end date, terms, and applicable
products** as fields (see Data model) — the front-end specials editor
reuses `render_special_details()` directly, so those fields didn't need to
be rebuilt, just exposed outside wp-admin.

**Access, as currently decided:** any member with
`SSB_Access_Control::user_has_access()` can create or edit **any** product,
competitor, or special — there's no per-team or per-owner restriction yet,
since team/account structure isn't built out. Every record still gets
`post_author` set to whoever created it, so ownership-based filtering later
(e.g. "only see your own team's content") doesn't require a data
migration — just a query change. Each editor class has its own
`user_can_manage_*()` static method (`SSB_Frontend_Editor`,
`SSB_Frontend_Competitors`, `SSB_Frontend_Specials`) as the single place to
tighten this later, e.g. to a dedicated capability or role.

**Membership enforcement now covers all five pages**, not just the script
view — `class-access-control.php`'s page block checks against
`SSB_Settings::get_all_protected_slugs()`, which includes every page in the
table above. Adding a plugin page in the future means adding one row to
`SSB_Settings::page_configs()`; it's gated automatically from there.

**Deletion is a soft delete** (`wp_trash_post`) everywhere — recoverable
from wp-admin's trash if a member deletes something by mistake.

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

1. **Set every page slug**: visit **Products/Services > Settings** in
   wp-admin once all five pages exist (hub, script view, manage products,
   manage competitors, manage specials), and confirm each slug matches.
   Each field shows a live check for whether it currently matches a real
   published page.
2. **Membership/join page URL**: hardcoded placeholder `/membership/` in
   the redirect (`class-access-control.php`) — confirm real URL.
3. **WPML/Polylang**: not yet wired in. Need to confirm which one is used
   on the Image Generator plugin and match it here.
4. **Tiered access**: not implemented (all active members get full access
   for now). Flag if Basic vs. Premium gating is needed before launch.
5. **Turn on membership enforcement**: once MemberPress is live, check the
   box at **Products/Services > Settings** ("Require an active MemberPress
   subscription"). It's off by default so the plugin stays testable now.
   This now gates all five front-end pages, not just script view.
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
8. **Logged-out submissions to the front-end save/delete handlers are a
   silent no-op** (no `admin_post_nopriv_*` hook is registered on purpose,
   so nothing happens rather than exposing an error). A nicer
   redirect-to-login could be added if that edge case matters in practice.
9. **No per-team ownership restriction yet** — by design, for now. Revisit
   `user_can_manage_*()` in `class-frontend-editor.php`,
   `class-frontend-competitors.php`, and `class-frontend-specials.php` once
   team/account structure exists.

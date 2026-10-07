# MVF Voting

🇧🇷 [Leia em português](README.pt-BR.md)

A WordPress plugin for music video voting, built for the [Music Video Festival](https://www.musicvideofestival.com.br/) (MVF), a Brazilian music video awards.

The original version of this plugin ran the festival's votes in **2022, 2023 and 2024**. This repository is a rebuild of that project for my portfolio, rewritten with current WordPress practices. It is a **legacy project**: it is not the voting system the site uses today.

## My role

I built the original plugin solo, end to end: requirements, data model, PHP back end, REST API, admin screens, public voting page, security, and testing.

## The problem

The MVF website gets around 30,000 visits a month, peaking at up to 90,000 during voting periods. The festival needed two independent votes:

- **Public:** anonymous, no sign-up and no vote limit, but resistant to bots.
- **Jury:** logged-in jurors rank the videos in the categories assigned to them, and each position earns points.

The organizers also needed to manage yearly editions, categories with a maximum number of videos, YouTube videos, and jurors per category. Rankings are visible only in the admin, never on the public site.

## Technical decisions

### Handling traffic peaks

- **The voting page can be fully cached.** The HTML is static and votes are sent as a `POST` to the REST API (`/wp-json/mvf/v1/vote`), which never goes through the cache. Only the vote itself reaches PHP.
- **A dedicated table for votes.** Each vote is one row in a table indexed by category, video and IP, instead of post meta. Counting the ranking is a plain `GROUP BY`.
- **YouTube player on click.** The page shows the thumbnail and swaps in the iframe when the visitor clicks, so a page with dozens of videos stays light.
- **Block theme support.** Testing surfaced a bug: in block themes the shortcode runs before `wp_enqueue_scripts`, so the JavaScript configuration was never printed. Assets are now registered when the shortcode runs.

### Anonymous voting without easy fraud

- **Cloudflare Turnstile, verified server-side**, with a fresh token for every vote. In most cases the challenge is invisible to the visitor.
- **Minimum interval between votes per IP** (5 seconds by default, configurable). The public can vote as much as they like; the limit only stops scripts.
- **Real IP behind Cloudflare only when enabled.** The `CF-Connecting-IP` header is ignored unless the site sits behind the proxy, because otherwise anyone could forge it.
- **Auditing instead of blind blocking.** An admin screen shows the IPs with the most votes and votes per hour, to spot suspicious spikes. Votes can be invalidated and restored in bulk, and are never deleted.

### Privacy (LGPD, Brazil's GDPR)

- IP addresses and user agents are stored only as HMAC-SHA256 hashes, keyed with the site's own salt.
- A first-party cookie holding a random identifier is used for auditing only.
- The privacy notice text is configurable and shown below the voting area.

### Jury rules

- **Points are computed on read, not stored.** Each ballot stores only the position of each video. Changing the points table recalculates every result.
- **Tie-break by placements:** most 1st places wins; if still tied, most 2nd places, and so on. A tie across every position shares the rank.
- **Server-side validation:** jurors can only save ballots for their assigned categories, within the jury period, and the submitted list must contain exactly the category's videos, with no duplicates.
- **Outdated ballots are flagged.** If a video is added to or removed from a category after submission, the progress screen shows it and the juror sees a prompt to review.

### Data integrity

- **The per-category video limit is enforced server-side**, not just in the UI. Even when the checkbox is forced through the browser's dev tools, the video is not added to a full category.
- **The same category name across editions.** WordPress rejects duplicate term names, so the slug is generated as `name-edition`. A duplicate name within the same edition is still blocked.
- **Editions can copy categories** from a previous year instead of rebuilding them.

### Admin security

- Custom capabilities: `mvf_manage` for organizers and `mvf_vote_jury` for jurors, who can only reach their own ballot within the plugin.
- Nonces on every admin form and on the jury API, queries through `$wpdb->prepare`, and escaped HTML output.

## How it was validated

The plugin was tested on a local WordPress (WordPress Playground) running **PHP 7.4 and PHP 8.0**, with `WP_DEBUG` on and no warnings on any admin screen or on the public page:

- **Public vote:** accepted with a valid Turnstile token, rejected with an invalid one, blocked by the per-IP interval (HTTP 429), and rejected for a video outside the category or while voting was closed.
- **Jury:** ordering by drag and drop and by arrow buttons, draft and submit, blocked access to unassigned categories, and a deliberately built tie to check points and the tie-break.
- **Admin:** category limit forced through the browser, copying categories between editions, bulk invalidation in the audit screen reflected in the ranking, and all three CSV exports opening with correct accents.
- **Mobile:** layout at 375px with no horizontal scrolling.

There are no automated tests.

## Stack

- PHP 7.4+ and WordPress 6.4+ (custom post type, taxonomies, REST API, Settings API)
- MySQL with custom tables created through `dbDelta`
- JavaScript with no build step; jQuery UI Sortable, bundled with WordPress, for the jury ballot
- Plain CSS with custom properties the theme can override
- Cloudflare Turnstile

## Code structure

```
mvf-voting.php              bootstrap and constants
includes/
  class-public-vote.php     public vote endpoint
  class-jury.php            jury ballot, juror assignment, endpoint
  class-results.php         ranking calculation and tie-break
  class-admin-results.php   results, progress and audit screens
  class-export.php          CSV export
  class-clip-admin.php      video management and per-category limit
  class-taxonomy-admin.php  editions (voting periods) and categories
  class-turnstile.php       Turnstile verification
  class-request.php         IP, hashes and audit cookie
templates/                  voting page HTML (theme-overridable)
assets/                     CSS and JS for the site and the admin
```

## Running it

The admin interface is in Brazilian Portuguese; menu names below are shown as they appear, with a translation in parentheses.

1. Copy the folder to `wp-content/plugins/` and activate the plugin. Activation creates the tables, the **Jurado MVF** (MVF Juror) role and the **MVF Votação** menu.
2. Under **MVF Votação → Configurações** (Settings), enter the Turnstile keys. Cloudflare's test keys (`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`) work for local runs.
3. Create an edition, its categories and videos, and assign jurors (users with the *Jurado MVF* role).
4. Add the shortcode to a page:

```
[mvf_votacao]                         active edition, all categories
[mvf_votacao categoria="slug"]        a single category
[mvf_votacao edicao="2026"]           another edition (slug or ID)
```

If the site uses page caching (WP Rocket, LiteSpeed, Cloudflare cache rules), exclude `/wp-json/mvf/*`. Without a persistent object cache, the per-IP limit writes a temporary row to `wp_options` on every vote, so enabling Redis or Memcached during voting periods is worthwhile.

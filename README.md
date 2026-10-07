# MVF Voting

[Português](README.pt-BR.md)

A WordPress plugin for music video voting, built for the [Music Video Festival](https://www.musicvideofestival.com.br/) (MVF) in Brazil.

The original version of this plugin ran the festival's votes in 2022, 2023 and 2024. This repository rebuilds that project for my portfolio with current WordPress practices. It's a legacy project; the site uses a different voting system today.

## My role

I built the original plugin solo, end to end: requirements, data model, PHP back end, REST API, admin screens, public voting page, security, and testing.

## The problem

The MVF website gets around 30,000 visits a month and up to 90,000 during voting periods. The festival needed two separate votes. The public votes anonymously, with no sign-up and no vote limit, so the system has to keep bots out. Jurors log in and rank the videos in their assigned categories, and each position earns points.

Organizers also needed to manage yearly editions, categories with a cap on videos, YouTube videos, and the jurors for each category. Rankings appear only in the admin.

## Technical decisions

### Traffic peaks

- Page caches can store the whole voting page. Votes go to the REST API as a `POST` to `/wp-json/mvf/v1/vote`, which caches skip, so PHP runs for the vote and nothing else.
- Votes live in their own table, one row per vote, indexed by category, video and IP. A ranking takes a single `GROUP BY` instead of a search through post meta.
- Each video card shows a thumbnail and loads the YouTube iframe on click, which keeps a page with dozens of videos light.
- Block themes render the shortcode before `wp_enqueue_scripts` fires, so the plugin registers its scripts inside the shortcode.

### Anonymous voting and fraud

- Cloudflare Turnstile checks every vote on the server, with a fresh token each time. Most visitors never see a challenge.
- Each IP waits 5 seconds between votes (configurable). People can still vote as often as they want; the wait slows down scripts.
- The plugin reads the `CF-Connecting-IP` header only when the admin confirms the site runs behind Cloudflare's proxy. Without the proxy, anyone could forge that header.
- An audit screen lists the IPs with the most votes and the votes per hour, so organizers can spot suspicious spikes. They can invalidate and restore votes in bulk, and the plugin never deletes them.

### Privacy (LGPD, Brazil's data protection law)

- The plugin stores IP addresses and user agents as HMAC-SHA256 hashes keyed with the site's own salt, and never in plain text.
- A first-party cookie holds a random ID for auditing.
- Organizers write the privacy notice in the settings, and the plugin shows it below the voting area.

### Jury rules

- Ballots store each video's position, and the plugin computes points when it reads them. Changing the points table recalculates every result.
- Ties go to the video with more 1st places, then more 2nd places, and so on. Videos tied at every position share the rank.
- The server accepts a ballot only from a juror assigned to that category, during the jury period, and only if it lists every video in the category exactly once.
- If a video joins or leaves a category after a juror submits, the progress screen flags the ballot and the juror gets a prompt to review it.

### Data integrity

- The server enforces the per-category video limit. Forcing the checkbox through dev tools still won't add a video to a full category.
- WordPress rejects duplicate term names, so the plugin builds category slugs as `name-edition`. The same name can exist in two editions but not twice in one.
- A new edition can copy the previous year's categories.

### Admin security

- Two custom capabilities: `mvf_manage` for organizers and `mvf_vote_jury` for jurors. Inside the plugin, jurors reach their own ballot and nothing else.
- Every admin form and the jury API check a nonce, queries go through `$wpdb->prepare`, and the templates escape HTML output.

## Testing

The plugin ran on a local WordPress (WordPress Playground) under PHP 7.4 and PHP 8.0 with `WP_DEBUG` on, and no admin screen or public page raised a warning. The manual test pass covered:

- Public vote: a valid Turnstile token goes through. An invalid token, a second vote inside the per-IP interval (HTTP 429), a video outside the category and a closed vote all get rejected.
- Jury: drag-and-drop and arrow-button ordering, draft and submit, blocked access to unassigned categories, and a staged tie to check the points and the tie-break.
- Admin: the category limit forced through the browser, copying categories between editions, bulk invalidation showing up in the ranking, and all three CSV exports opening with correct accents.
- Mobile: a 375px layout with no horizontal scrolling.

The block theme fix described above came out of this test pass. The project has no automated tests.

## Stack

- PHP 7.4+ and WordPress 6.4+ (custom post type, taxonomies, REST API, Settings API)
- MySQL with custom tables created through `dbDelta`
- JavaScript with no build step, plus jQuery UI Sortable (bundled with WordPress) for the jury ballot
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

The admin interface is in Brazilian Portuguese. The steps below use the menu names as they appear, with translations in parentheses.

1. Copy the folder to `wp-content/plugins/` and activate the plugin. Activation creates the tables, the Jurado MVF (MVF Juror) role and the MVF Votação menu.
2. Under MVF Votação → Configurações (Settings), enter the Turnstile keys. Cloudflare's test keys (`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`) work for local runs.
3. Create an edition, its categories and videos, and assign jurors (users with the Jurado MVF role).
4. Add the shortcode to a page:

```
[mvf_votacao]                         active edition, all categories
[mvf_votacao categoria="slug"]        a single category
[mvf_votacao edicao="2026"]           another edition (slug or ID)
```

If the site uses page caching (WP Rocket, LiteSpeed, Cloudflare cache rules), exclude `/wp-json/mvf/*`. Without a persistent object cache, the per-IP limit writes a temporary row to `wp_options` on every vote, so consider Redis or Memcached during voting periods.

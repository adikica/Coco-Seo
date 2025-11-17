Coco SEO Suite

A modern SEO framework for WordPress, built with PHP 8.3+, namespaces, modular architecture, and native WordPress APIs.
Coco SEO Suite provides advanced meta management, dynamic sitemaps, robots.txt control, Google indexing insights, Bing verification, and IndexNow URL submission — all in one fast, efficient package.

🚀 Features
✔ Modern Architecture

Fully namespaced PHP 8.3+ codebase

Strong typing, strict mode, autoloaded classes

Modular subsystem design (meta, sitemap, robots, indexing, bing/indexnow)

Zero bloat — only essential SEO tooling

✔ SEO Meta Management

Custom meta title & description fields

Index / noindex, follow / nofollow per-post controls

Canonical URL overrides

Block Editor–native UI (no legacy metabox clutter)

✔ Google Indexing Check

One-click check for individual URLs (AJAX)

Bulk indexing check by post type (10/25/50 latest posts)

Smart simulation system until Search Console API mode is enabled

Saves results to postmeta for tracking

✔ XML Sitemap Engine

Automatic sitemap index: /sitemap.xml

Per-post-type sitemaps: /sitemap-post.xml, /sitemap-page.xml, custom CPTs

lastmod timestamps in ISO8601 (Google-friendly)

Auto-regenerates when content changes

Virtual rendering (no physical files required)

✔ Robots.txt Manager

Replaces WP’s default virtual robots

Automatically injects sitemap URLs

Can be extended via filters

Great for staging vs production environments

✔ Bing Webmaster Verification

One-field Bing msvalidate.01 code

Automatically injected into <head>

No theme editing required

✔ IndexNow Integration (Bing + others)

Auto-submits URLs on publish/update

JSON POST to https://api.indexnow.org/indexnow

Supports 3 key strategies:

filesystem → writes {key}.txt to root

virtual → serves indexnow-key.txt via rewrite

auto → prefers filesystem, falls back to virtual

Manual test submit from the admin

✔ SEO Status Dashboard

Indexing state per post

Post-age-aware indexing likelihood model

Bulk actions supported

Quickly identify what Google might not yet see

✔ Performance Optimized

Transient caching for sitemaps and indexing results

Minimal database lookups

No remote API calls unless explicitly triggered

Zero frontend impact unless meta overrides are used

📦 Requirements

WordPress 6.7+

PHP 8.3+

curl, json, and SimpleXML extensions

Recommended: ability to perform outbound HTTPS calls (IndexNow)

🔧 Installation
1. Composer (recommended)
composer require dnovogroup/coco-seo

2. Manual Installation

Download the plugin ZIP

Upload to /wp-content/plugins/

Run:

composer install


inside the plugin folder
4. Activate via WordPress admin

⚙️ Usage Guide
1. Quick Setup

Go to Coco SEO Suite → Settings

Configure:

Global meta defaults

Google API key (optional for simulation mode; required for Search Console API mode)

Bing verification code

IndexNow key & strategy

Enable SEO fields for desired post types

📑 SEO Meta Editing

Inside any post/page:

Edit SEO Title

Edit SEO Description

Toggle noindex / nofollow

Add canonical URL

All rendered cleanly in <head> with full escaping and fallbacks

🔍 Google Indexing Status
🔹 Check Individual URL

Found under Coco SEO Suite → Google Indexing

AJAX request

Nonce-protected

Provides “Indexed” or “Not Indexed”

Stores timestamp + status

🔹 Bulk Check

Runs via admin-post.php

Options: 10, 25, 50 recent posts

Results stored per-admin via transient

Redirects back to display results table

🗺 Sitemaps
Dynamic URLs
/sitemap.xml
/sitemap-post.xml
/sitemap-page.xml
/sitemap-{custom_post_type}.xml

Automatic generation on:

Publish

Update

Trash/delete

Developer Filters
add_filter('coco_sitemap_post_types', function ($types) {
    $types[] = 'portfolio';
    return $types;
});

🤖 Robots.txt

Virtual endpoint:

/robots.txt


Automatically includes:

Sitemap: https://example.com/sitemap.xml


Extendable via:

add_filter('coco_robots_rules', function ($rules) {
    $rules[] = 'Disallow: /private/';
    return $rules;
});

🪟 Bing Webmaster Integration
Add your code:
msvalidate.01


Plugin outputs:

<meta name="msvalidate.01" content="YOURCODE">


No theme editing required.

⚡ IndexNow Integration
Key Strategies
Strategy	Description
auto	Write key to root if possible, else virtual serve
filesystem	Always write {key}.txt
virtual	Never write to disk; serve via rewrite
URL Handling

Auto-submission on:

Publish new content

Update existing published content

JSON payload:

{
  "host": "example.com",
  "key": "yourIndexNowKey",
  "keyLocation": "https://example.com/YOURKEY.txt",
  "urlList": ["https://example.com/page/"]
}


Manual tools available inside plugin settings.

🧑‍💻 Developer Notes
REST Endpoints
POST /wp-json/coco/v1/indexnow
POST /wp-json/coco/v1/ping-sitemap


Capabilities required:

current_user_can('manage_options')

Cron Hook
coco_bing_indexnow_daily_ping

Rewrite Rules
^indexnow-([A-Za-z0-9]+)\.txt$
^sitemap(-.*)?\.xml$

❓ FAQ
✔ Where are my sitemaps?

Main index:

https://yoursite.com/sitemap.xml


Individual post types are linked from the index.

✔ Does this work with other SEO plugins?

Works technically, but you should avoid multiple sitemap generators.

✔ Why does “Bulk Check” redirect?

Because it uses secure admin-post.php actions to avoid WP’s
“Cannot load {slug}” admin-page errors.

📄 License

GPL v2 or later

👤 Author

Developed by Adi Kica
For website.com

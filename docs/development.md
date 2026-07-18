# Developing & testing this plugin

You don't need to install WordPress yourself. This repo uses **`wp-env`**, which
runs a throwaway WordPress inside Docker with the plugin already installed and
live. Edit code, refresh the browser, see the change.

## One-time setup

```bash
npm install          # JS deps + the wp-env / wp-scripts tooling
npm run env:start    # boots WordPress in Docker (first run pulls images, ~5 min)
```

That's it. You now have:

| URL | What |
|-----|------|
| http://localhost:8888 | The site (what visitors see) |
| http://localhost:8888/wp-admin | Admin dashboard — log in with **`admin`** / **`password`** |

A second copy runs on **:8889** for automated tests — ignore it for manual work.

## The daily loop

Open **two terminals** in the repo:

```bash
# Terminal 1 — rebuilds src/ → build/ automatically on every save
npm run start

# Terminal 2 — leave WordPress running (only needed once per session)
npm run env:start
```

Then:

- **Editing PHP** (`chainkit-bitcoin-payment-button.php`, `includes/`, `src/render.php`)
  → just **refresh the browser**. PHP is live-mounted; no build needed.
- **Editing JS or SCSS** (`src/edit.js`, `src/view.js`, `src/*.scss`)
  → `npm run start` rebuilds within a second; then **refresh the browser**.
  (If you're not running `start`, run `npm run build` manually.)

> Note: the editor bundle (`index.js`) is sometimes cached hard by the block
> editor. If a change to the editor UI doesn't show, do a hard refresh
> (Ctrl+Shift+R) in the editor tab.

## Trying the block

1. Go to **wp-admin → Pages → Add New**.
2. Click the **+** and search **"Bitcoin Payment Button"**. Add it.
3. In the right sidebar, paste a Bitcoin address and pick an amount mode. Publish,
   then **View** the page.

To try the shortcode instead, add a **Shortcode** block (or use the Classic
editor) and paste:

```
[chainkit_bitcoin_button address="bc1q..." amount_mode="fiat" amount_fiat="49" currency="EUR" label="My Store"]
```

## Faster: drive it from the command line

`wp-env run cli wp <...>` runs [WP-CLI](https://developer.wordpress.org/cli/)
inside the container — handy for creating test pages without clicking around:

```bash
# Create a published page with the block, print its ID
npx wp-env run cli wp post create --post_type=page --post_status=publish \
  --post_title="Test" \
  --post_content='<!-- wp:chainkit/bitcoin-payment-button {"address":"bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq","amountMode":"fiat","amountFiat":"49","currency":"EUR"} /-->' \
  --porcelain

# then open http://localhost:8888/?page_id=<ID>

npx wp-env run cli wp plugin list           # confirm the plugin is active
npx wp-env run cli wp transient delete chainkit_bpb_rates   # force a fresh rate fetch
```

## Tests & checks (same ones CI runs)

```bash
npm run build        # must compile clean
npm run test:unit    # BIP21 JS unit tests
npm run lint:css     # stylelint

# PHP tests need PHP + PHPUnit. Easiest via Docker:
docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c \
  'curl -fsSL https://phar.phpunit.de/phpunit-9.phar -o /tmp/p.phar && php /tmp/p.phar --no-configuration tests/php/Bip21Test.php'
```

> Heads-up: `npm run lint:js` currently crashes under Node 25 (a bundled
> ESLint/@typescript-eslint incompatibility, not our code). CI pins Node 20 where
> it passes. Locally, use Node 20 if you want to run it, or rely on `npm run start`
> catching real errors.

## What to know about the code

The block is **dynamic** (server-rendered). One function does all the rendering
and escaping — `chainkit_bpb_render()` in the main plugin file — and both the
block (`src/render.php`) and the `[chainkit_bitcoin_button]` shortcode call it, so
they always match.

The BIP21 URI logic exists **twice on purpose**: `src/lib/bip21.js` (used by the
editor preview + JS tests) and `includes/bip21.php` (used by the server render +
PHP tests). They're kept byte-for-byte identical and each has a unit suite that
pins the same known-good cases. If you touch one, touch the other and run both
test suites.

## Housekeeping

```bash
npm run env:stop         # stop the containers (keeps data)
npx wp-env destroy       # wipe everything and start fresh next time
npm run plugin-zip       # build the distributable ZIP (chainkit-bitcoin-payment-button.zip)
```

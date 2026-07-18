# Bitcoin Payment Button & QR — chainkit

A free WordPress plugin that adds a **"Pay with Bitcoin" button** and a **BIP21 QR code** to any page or post — as a Gutenberg block or a `[chainkit_bitcoin_button]` shortcode. The button is a real `bitcoin:` payment request that opens the visitor's wallet with the address (and optional amount, label, and message) pre-filled.

Funds go **straight to your own Bitcoin address**. No account, no custody, no API key. It works even with JavaScript disabled — JS only adds the QR code and the copy button.

This is a standalone tool from [**chainkit**](https://chainkit.dev), the non-custodial Bitcoin payment processor. It's the free, manual version of what chainkit automates: fiat-priced invoices with a rate locked at issuance, on-chain settlement to your wallet, and a webhook on confirmation. The plugin is a port of the [chainkit payment-link tool](https://chainkit.dev/tools/payment-link) for WordPress.

## Features

- **Gutenberg block** + **`[chainkit_bitcoin_button]` shortcode** (Classic editor / page builders).
- **Three amount modes:** payer decides, fixed BTC, or a **fiat amount** (USD/EUR/GBP/JPY/CAD) converted at the live rate.
- **Scannable QR code** (BIP21), a **copy-address** button, and a plain `bitcoin:` link that works without JavaScript.
- Optional **label** and **message** carried into the wallet.
- **Light / dark / auto** theme and left / center / right alignment.
- Honest fiat handling: a static button can't lock a rate, so fiat amounts are labelled **approximate** and funnel to chainkit invoices for the real thing.
- Translation-ready; no tracking; one disclosed, cached external request (fiat rates only).

## Shortcode

```
[chainkit_bitcoin_button
  address="bc1q..."
  amount_mode="fiat"      // none | btc | fiat
  amount_fiat="49"
  currency="EUR"          // USD | EUR | GBP | JPY | CAD
  label="My Store"
  message="Order #1042"
  button_text="Pay with Bitcoin"
  align="left"            // left | center | right
  theme="auto"            // auto | light | dark
  show_qr="true"
  show_powered="true"
]
```

The Gutenberg block exposes the same options in the block sidebar.

## Fiat rates & privacy

The only external request happens when a button uses **fiat** amount mode: the site fetches the BTC→fiat rate table from `https://api.chainkit.dev/v1/public/btc/rates`, **server-side**, and caches it in a WordPress transient for ~5 minutes. No visitor data is sent, and buttons that use "payer decides" or a fixed BTC amount make no external requests at all. If the endpoint is unreachable the button degrades to an address-only request. See the [`External services`](readme.txt) section of `readme.txt` for the full disclosure.

## Development

Requires Node 18+ and [Docker](https://www.docker.com/) (for the local WordPress).

```bash
npm install
npm run build          # compile src/ (TypeScript) → build/
npm run start          # watch mode
npm run type-check     # tsc --noEmit
npm run test:unit      # BIP21 unit tests
npm run lint:js        # ESLint
npm run lint:css       # stylelint

npm run env:start      # spin up WordPress at http://localhost:8888 (wp-env / Docker)
npm run env:stop
```

The frontend and editor are **TypeScript** (`src/*.ts`, `src/edit.tsx`), built by
`@wordpress/scripts`. The block is **dynamic** (server-rendered): all markup and
escaping lives in `chainkit_bpb_render()` in the main plugin file, and both
`src/render.php` (the block render) and the `[chainkit_bitcoin_button]` shortcode
delegate to it, so they render identically. The BIP21 URI logic is duplicated as
a TypeScript module (`src/lib/bip21.ts`, used by the editor + tests) and a
pure-PHP mirror (`includes/bip21.php`, used by the render + tests); the two are
kept byte-for-byte compatible and each has its own unit suite
(`tests/bip21.test.ts`, `tests/php/Bip21Test.php`).

## License

[GPL-2.0-or-later](LICENSE).

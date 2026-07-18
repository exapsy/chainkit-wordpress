=== Bitcoin Payment Button & QR — chainkit ===
Contributors: chainkit
Tags: bitcoin, cryptocurrency, payments, donations, qr-code
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a "Pay with Bitcoin" button and BIP21 QR code to any page or post. A real bitcoin: link that opens the visitor's wallet. No account, no custody.

== Description ==

Drop a **"Pay with Bitcoin" button** on any page, post, or sidebar — as a Gutenberg block or a `[chainkit_bitcoin_button]` shortcode. Visitors get a real `bitcoin:` payment request (BIP21) that opens their wallet with the address — and optionally the amount, a label, and a message — already filled in, plus a scannable QR code and a one-tap copy button.

Funds go **straight to your own Bitcoin address**. This plugin never touches your money, holds no keys, and requires no account or API key. You paste an address; it builds a standard payment request.

= Features =

* **Gutenberg block** and **`[chainkit_bitcoin_button]` shortcode** (Classic editor / page builders).
* **Three amount modes, each clearly distinct:**
  * **Fiat price** — you price in USD/EUR/GBP/JPY/CAD (e.g. €49); the button leads with your price and shows the BTC underneath.
  * **Fixed BTC** — you set an exact amount (e.g. 0.005 BTC); the button leads with BTC and shows a switchable local-currency reference.
  * **Payer decides** — an on-page amount picker: **preset buttons**, a **slider (range)**, and/or a **custom amount field**, in your currency or in BTC. Picking updates the button and QR live. Perfect for donations and tips.
* **Local-currency display:** in fixed-BTC mode the reference is guessed from the visitor's own browser language and is switchable — converted entirely in their browser, with nothing sent anywhere.
* **Global settings:** set your Bitcoin address (and default currency, theme, and button text) once under Settings → Bitcoin Payment Button; every button uses it unless overridden.
* **Scannable QR code** encoding the exact same payment request.
* **Copy-address button** and a plain `bitcoin:` link that **works even with JavaScript disabled**.
* Optional **label** and **message** carried into the wallet (e.g. your store name and an order number).
* **Light / dark / auto** theme and left / center / right alignment.
* Built as a standard block with `@wordpress/scripts`; translation-ready.

= Honest about fiat pricing =

A static button on a web page **cannot lock an exchange rate** the way a real invoice does. When you set a fiat amount, the button shows the BTC equivalent as **approximate** and clearly labels it "rate not locked." For a rate that is locked at issuance, watched on-chain, and settled straight to your wallet with a webhook when it confirms, use [chainkit](https://chainkit.dev) invoices — this plugin is the free, manual version of that.

= About chainkit =

[chainkit](https://chainkit.dev) is a non-custodial Bitcoin payment processor and open-source Go SDK. Merchants register an extended public key (xpub — never a seed); chainkit derives a fresh address per invoice, prices invoices in fiat, locks the BTC amount at issuance, watches the chain, and fires an HMAC-signed webhook on confirmation. Funds settle on-chain straight to the merchant's own wallet. This plugin is a free, standalone tool from the same team — it does **not** require a chainkit account.

== External services ==

This plugin connects to one external service, and only in one specific case.

**chainkit public rate API — https://api.chainkit.dev/v1/public/btc/rates**

* **When:** when a page containing a button is rendered, to power the fiat amount and the local-currency reference. The rate table is fetched **once and cached for ~5 minutes** in a WordPress transient, so at most a handful of requests are made per hour no matter how much traffic the page gets.
* **What is sent:** a plain HTTPS GET request for the current BTC→fiat rate table. **No visitor data, no personal information, and no page or IP data is sent by the plugin** beyond what any HTTP request inherently includes. The request carries no cookies and no identifiers.
* **Made server-side, not per visitor:** your WordPress server makes the request, not the visitor's browser. The visitor's browser never contacts chainkit. Currency switching happens in the visitor's browser using the already-fetched rate table, and the default currency is guessed from the browser's own language setting — no geolocation, no IP lookup, nothing sent per visitor.
* **If unreachable:** the button gracefully falls back to an address-only payment request and notes that the rate is unavailable.

Service provided by chainkit. Terms: https://chainkit.dev/terms — Privacy policy: https://chainkit.dev/privacy

No other external service is contacted. QR codes are generated locally in the browser; the `bitcoin:` link and address are rendered on your own server.

== Installation ==

1. Install and activate the plugin (from the Plugins screen, or upload the ZIP).
2. (Optional) Go to **Settings → Bitcoin Payment Button** and enter your Bitcoin address once. Every button then uses it by default.
3. In the block editor, add the **"Bitcoin Payment Button"** block. Or, in the Classic editor / a page builder, use the shortcode — as short as `[chainkit_bitcoin_button]` once your address is saved, or fully specified:
   `[chainkit_bitcoin_button address="bc1q..." amount_mode="fiat" amount_fiat="49" currency="EUR" label="My Store"]`
3. Publish. Visitors can now pay you in Bitcoin.

== Frequently Asked Questions ==

= Do I need a chainkit account? =

No. This plugin is fully standalone and free. You only need a Bitcoin address to receive funds.

= Does chainkit or this plugin hold my Bitcoin? =

No. The button builds a standard BIP21 payment request to **your** address. Funds go directly from the payer's wallet to yours. The plugin is non-custodial and stores no keys.

= What is BIP21? =

BIP21 is the standard `bitcoin:<address>?amount=&label=&message=` URI scheme that every major Bitcoin wallet understands. The button link and the QR code both encode the same BIP21 request.

= Can I lock the exchange rate for a fiat price? =

Not with a static button — no page-embedded button can. The fiat conversion is shown as approximate. For rate-locked, fiat-priced invoices with on-chain settlement and confirmation webhooks, use chainkit invoices at https://chainkit.dev.

= Which shortcode attributes are supported? =

`address`, `amount_mode` (`none` | `btc` | `fiat`), `amount_btc`, `amount_fiat`, `currency` (`USD`/`EUR`/`GBP`/`JPY`/`CAD`), `label`, `message`, `button_text`, `align` (`left`/`center`/`right`), `theme` (`auto`/`light`/`dark`), `show_qr` (`true`/`false`), `show_powered` (`true`/`false`).

For the payer-decides picker (`amount_mode="none"`): `picker_unit` (`fiat`/`btc`), `presets_enabled` (`true`/`false`), `preset_values` (e.g. `"1, 2, 5, 10"`), `range_enabled` (`true`/`false`), `range_min`, `range_max`, `free_enabled` (`true`/`false`).

= Does it work without JavaScript? =

Yes. The `bitcoin:` link and the copyable address are rendered server-side, so the button works with JavaScript disabled. JavaScript adds the QR code, the one-tap copy, the currency switcher, and the payer-decides amount picker — without it, a payer simply enters the amount in their wallet.

= Does it track my visitors? =

No. The plugin sets no cookies and sends no visitor data anywhere. The exchange rate is fetched by your server (not the visitor's browser) and cached. The local-currency default is read from the visitor's browser language and converted in their browser — there is no geolocation or IP lookup.

== Screenshots ==

1. The Bitcoin Payment Button rendered on a page — button, amount, address, and QR.
2. Configuring the block in the editor: address, amount mode, label, and theme.
3. The `[chainkit_bitcoin_button]` shortcode in the Classic editor.

== Changelog ==

= 1.0.0 =
* Initial release: Gutenberg block + `[chainkit_bitcoin_button]` shortcode, BIP21 link and QR, three distinct amount modes (fiat price / fixed BTC / payer-decides picker with presets, range, and custom input), server-side cached fiat conversion, in-browser local-currency switcher (no tracking), global settings screen, light/dark/auto themes, no-JS fallback.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

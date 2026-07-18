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
* **Three amount modes:** let the payer decide, a **fixed BTC amount**, or a **fiat amount** (USD, EUR, GBP, JPY, CAD) converted at the live rate.
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

* **When:** only when a button is configured with **fiat** amount mode. Buttons using "payer decides" or a fixed BTC amount make **no external requests**.
* **What is sent:** a plain HTTPS GET request for the current BTC→fiat rate table. **No visitor data, no personal information, no page or IP data is sent by the plugin** beyond what any HTTP request inherently includes. The request carries no cookies and no identifiers.
* **Why:** to convert your fiat price (e.g. €49) into the BTC amount shown on the button. The request is made **server-side from your WordPress site** (not from each visitor's browser) and the result is **cached for ~5 minutes** in a WordPress transient, so at most a handful of requests are made per hour regardless of traffic.
* **If unreachable:** the button gracefully falls back to an address-only payment request and notes that the rate is unavailable.

Service provided by chainkit. Terms: https://chainkit.dev/terms — Privacy policy: https://chainkit.dev/privacy

No other external service is contacted. QR codes are generated locally in the browser; the `bitcoin:` link and address are rendered on your own server.

== Installation ==

1. Install and activate the plugin (from the Plugins screen, or upload the ZIP).
2. In the block editor, add the **"Bitcoin Payment Button"** block and paste your Bitcoin address in the block settings. Or, in the Classic editor / a page builder, use the shortcode:
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

= Does it work without JavaScript? =

Yes. The `bitcoin:` link and the copyable address are rendered server-side, so the button works with JavaScript disabled. JavaScript only adds the QR code and the one-tap copy behaviour.

== Screenshots ==

1. The Bitcoin Payment Button rendered on a page — button, amount, address, and QR.
2. Configuring the block in the editor: address, amount mode, label, and theme.
3. The `[chainkit_bitcoin_button]` shortcode in the Classic editor.

== Changelog ==

= 1.0.0 =
* Initial release: Gutenberg block + `[chainkit_bitcoin_button]` shortcode, BIP21 link and QR, none/BTC/fiat amount modes, server-side cached fiat conversion, light/dark/auto themes, no-JS fallback.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

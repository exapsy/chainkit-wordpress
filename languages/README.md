# Translations

The plugin is translation-ready: every user-facing string uses the
`chainkit-bitcoin-payment-button` text domain and `load_plugin_textdomain()` is
called on `init`.

Generate the translation template with [WP-CLI](https://wp-cli.org/):

```bash
wp i18n make-pot . languages/chainkit-bitcoin-payment-button.pot
```

Once published, WordPress.org's [translate.wordpress.org](https://translate.wordpress.org/)
hosts community translations automatically — no `.po`/`.mo` files need to be
committed here.

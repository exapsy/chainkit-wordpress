/**
 * Ambient module declarations for non-code imports so `tsc --noEmit` can
 * type-check the source. Styles are handled by the webpack build, not TS.
 */
declare module '*.scss';
declare module '*.css';

// @wordpress/block-editor does not ship type declarations in this line; the
// build externalizes it to wp.blockEditor at runtime. Treat as untyped.
declare module '@wordpress/block-editor';

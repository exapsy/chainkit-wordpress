/**
 * Block registration entry point. The block is dynamic (server-rendered via
 * render.php), so `save` returns null and all frontend markup comes from PHP.
 */
import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import metadata from './block.json';
import Edit, { type Attributes } from './edit';
import './style.scss';
import './editor.scss';

// block.json is the source of truth for name/title/category/attributes; the
// cast just hands TS the typed shape it can't infer from the JSON import.
registerBlockType( metadata as unknown as BlockConfiguration< Attributes >, {
	edit: Edit,
	save: () => null,
} );

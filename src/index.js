/**
 * Block registration entry point. The block is dynamic (server-rendered via
 * render.php), so `save` returns null and all frontend markup comes from PHP.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );

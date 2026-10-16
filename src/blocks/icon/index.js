import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import blockIcon from './block-icon';
import '../../shared/editor.scss';

registerBlockType( metadata, {
	icon: blockIcon,
	edit: Edit,
	save,
	deprecated,
} );

/*
 * Allow Iconjet blocks inside the Navigation block. Navigation reads its
 * allowed child blocks from the block type's allowedBlocks (block.json),
 * so both blocks are appended there. The PHP side marks them as list items
 * for Navigation's server render (Blocks::navigation_listable_blocks).
 */
addFilter(
	'blocks.registerBlockType',
	'iconjet/navigation-children',
	( settings, name ) => {
		if (
			name !== 'core/navigation' ||
			! Array.isArray( settings.allowedBlocks )
		) {
			return settings;
		}
		return {
			...settings,
			allowedBlocks: [
				...new Set( [
					...settings.allowedBlocks,
					'iconjet/icon',
					'iconjet/social-icons',
				] ),
			],
		};
	}
);

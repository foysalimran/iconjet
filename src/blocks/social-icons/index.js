import { registerBlockType } from '@wordpress/blocks';

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

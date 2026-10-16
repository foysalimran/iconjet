/**
 * Icon picker modal used by the Icon block and the Shortcode Generator.
 */
import { __ } from '@wordpress/i18n';
import { Modal } from '@wordpress/components';

import IconBrowser from './icon-browser';

/**
 * @param {Object}   props
 * @param {Function} props.onSelect ( { ref, name, svg: { v, b, a } } ) => void
 * @param {Function} props.onClose  Close the modal.
 * @param {string}   props.value    Currently selected icon reference.
 */
export default function IconPickerModal( { onSelect, onClose, value } ) {
	return (
		<Modal
			title={ __( 'Choose an icon', 'iconjet' ) }
			onRequestClose={ onClose }
			className="iconjet-picker"
			size="large"
		>
			<IconBrowser
				value={ value }
				onSelect={ ( icon ) =>
					onSelect( {
						ref: icon.ref,
						name: icon.name,
						svg: { v: icon.v, b: icon.b, a: icon.a },
					} )
				}
			/>
		</Modal>
	);
}

/**
 * Button that copies text and confirms with a short "Copied" state.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';

/**
 * @param {Object} props
 * @param {string} props.text    Text to copy.
 * @param {string} props.label   Button text.
 * @param {string} props.variant Button variant.
 */
export default function CopyButton( { text, label, variant = 'secondary' } ) {
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => {
		setCopied( true );
		speak( __( 'Copied to clipboard.', 'iconjet' ) );
	} );

	useEffect( () => {
		if ( ! copied ) {
			return undefined;
		}
		const timer = setTimeout( () => setCopied( false ), 2000 );
		return () => clearTimeout( timer );
	}, [ copied ] );

	return (
		<Button
			ref={ ref }
			variant={ variant }
			__next40pxDefaultSize
			disabled={ ! text }
		>
			{ copied ? __( 'Copied!', 'iconjet' ) : label }
		</Button>
	);
}

/**
 * Iconjet admin app. One bundle for all Iconjet pages; the page comes from
 * the container's data-page attribute (set by includes/class-admin.php).
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import LibraryPage from './library';
import GeneratorPage from './generator';
import CustomIconsPage from './custom-icons';
import SettingsPage from './settings';
import './admin.scss';

const PAGES = {
	library: LibraryPage,
	generator: GeneratorPage,
	custom: CustomIconsPage,
	settings: SettingsPage,
};

domReady( () => {
	const root = document.getElementById( 'iconjet-admin' );
	if ( ! root ) {
		return;
	}
	const Page = PAGES[ root.dataset.page ] || LibraryPage;
	createRoot( root ).render( <Page /> );
} );

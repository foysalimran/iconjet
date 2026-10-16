import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

import IconBrowser from '../shared/icon-browser';
import IconSvg from '../shared/svg';
import CopyButton from '../shared/copy-button';

export default function LibraryPage() {
	const [ icon, setIcon ] = useState( null );
	const shortcode = icon ? `[iconjet name="${ icon.ref }"]` : '';
	const urls = ( window.iconjetAdmin && window.iconjetAdmin.urls ) || {};

	return (
		<div className="iconjet-library">
			<Card className="iconjet-library__browser">
				<CardBody>
					<IconBrowser
						value={ icon && icon.ref }
						onSelect={ setIcon }
						height={ 520 }
					/>
				</CardBody>
			</Card>

			<Card className="iconjet-library__details">
				<CardBody>
					{ ! icon && (
						<p className="description">
							{ __(
								'Select an icon to copy its shortcode or reference.',
								'iconjet'
							) }
						</p>
					) }
					{ icon && (
						<div aria-live="polite">
							<div className="iconjet-library__preview">
								<IconSvg icon={ icon } size={ 96 } />
							</div>
							<h2 className="iconjet-library__name">
								{ icon.label || icon.name }
							</h2>

							<p className="iconjet-library__label">
								{ __( 'Shortcode', 'iconjet' ) }
							</p>
							<code className="iconjet-library__code">
								{ shortcode }
							</code>
							<CopyButton
								text={ shortcode }
								variant="primary"
								label={ __( 'Copy shortcode', 'iconjet' ) }
							/>

							<p className="iconjet-library__label">
								{ __( 'Icon reference', 'iconjet' ) }
							</p>
							<code className="iconjet-library__code">
								{ icon.ref }
							</code>
							<CopyButton
								text={ icon.ref }
								label={ __( 'Copy reference', 'iconjet' ) }
							/>

							{ urls.generator && (
								<p>
									<a
										href={ addQueryArgs( urls.generator, {
											icon: icon.ref,
										} ) }
									>
										{ sprintf(
											/* translators: %s: icon name. */
											__(
												'Customize “%s” in the Shortcode Generator',
												'iconjet'
											),
											icon.label || icon.name
										) }
									</a>
								</p>
							) }
						</div>
					) }
				</CardBody>
			</Card>
		</div>
	);
}

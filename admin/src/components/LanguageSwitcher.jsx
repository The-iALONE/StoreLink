import { useState } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api, config } from '../api/client';

export default function LanguageSwitcher() {
	const [ locale, setLocale ] = useState( config.locale || config.defaultLocale || 'fa_IR' );
	const [ saving, setSaving ] = useState( false );

	const options = Object.entries( config.locales || {} ).map( ( [ value, label ] ) => ( {
		value,
		label,
	} ) );

	const handleChange = async ( value ) => {
		setSaving( true );
		try {
			await api.post( '/settings/locale', { locale: value } );
			window.location.reload();
		} catch ( error ) {
			setSaving( false );
		}
	};

	return (
		<div className="pricepilot-language-switcher">
			<SelectControl
				label={ __( 'Language', 'pricepilot' ) }
				value={ locale }
				options={ options }
				onChange={ handleChange }
				disabled={ saving }
			/>
		</div>
	);
}

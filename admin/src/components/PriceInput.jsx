import { TextControl } from '@wordpress/components';
import { formatNumberInput, getCurrencyLabel, parseFormattedNumber } from '../utils/price';

export default function PriceInput( { label, value, onChange, help } ) {
	const currency = getCurrencyLabel();
	const displayValue =
		value === '' || value === null || value === undefined
			? ''
			: formatNumberInput( value );

	const handleChange = ( input ) => {
		if ( input === '' ) {
			onChange( '' );
			return;
		}

		onChange( parseFormattedNumber( input ) );
	};

	return (
		<div className="pricepilot-price-input">
			<TextControl
				label={ label }
				value={ displayValue }
				onChange={ handleChange }
				help={ help }
				type="text"
				inputMode="decimal"
			/>
			{ currency && (
				<span className="pricepilot-price-input__currency">{ currency }</span>
			) }
		</div>
	);
}

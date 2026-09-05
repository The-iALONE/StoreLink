import { TextControl } from '@wordpress/components';

export default function PercentInput( { label, value, onChange } ) {
	return (
		<div className="pricepilot-percent-input">
			<TextControl
				label={ label }
				value={ value }
				onChange={ onChange }
				type="number"
				min="0"
				step="0.01"
			/>
			<span className="pricepilot-percent-input__suffix">%</span>
		</div>
	);
}

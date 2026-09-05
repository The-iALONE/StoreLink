const priceConfig = window.pricePilotData || {};

/**
 * Iranian / display-friendly grouping with comma every 3 digits.
 *
 * @param {number|string} value Numeric value.
 * @return {string}
 */
export function formatNumberInput( value ) {
	if ( value === '' || value === null || value === undefined ) {
		return '';
	}

	const cleaned = String( value ).replace( /,/g, '' ).replace( /[^\d.]/g, '' );
	if ( ! cleaned ) {
		return '';
	}

	const parts = cleaned.split( '.' );
	const intPart = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, ',' );

	if ( parts.length > 1 ) {
		return `${ intPart }.${ parts.slice( 1 ).join( '' ) }`;
	}

	return intPart;
}

/**
 * Parse a formatted price string to a number.
 *
 * @param {string|number} value Formatted input.
 * @return {number|string}
 */
export function parseFormattedNumber( value ) {
	if ( value === '' || value === null || value === undefined ) {
		return '';
	}

	const cleaned = String( value ).replace( /,/g, '' ).trim();
	if ( ! cleaned ) {
		return '';
	}

	const num = parseFloat( cleaned );
	return Number.isNaN( num ) ? '' : num;
}

/**
 * Currency context from WooCommerce settings.
 *
 * @return {{label:string,code:string,unit:string}}
 */
export function getCurrencyContext() {
	return {
		label: priceConfig.currencyLabel || priceConfig.currency || '',
		code: priceConfig.currencyCode || '',
		unit: priceConfig.currencyUnit || 'standard',
	};
}

/**
 * Currency label from WooCommerce settings.
 *
 * @return {string}
 */
export function getCurrencyLabel() {
	return getCurrencyContext().label;
}

/**
 * Format number with comma grouping (no currency).
 *
 * @param {number|string} value Price value.
 * @return {string}
 */
export function formatPriceAmount( value ) {
	if ( value === '' || value === null || value === undefined ) {
		return '';
	}

	const num = Number( value );
	if ( Number.isNaN( num ) ) {
		return String( value );
	}

	return formatNumberInput( num );
}

/**
 * Format a stored price for display.
 *
 * @param {number|string} value Price value.
 * @return {string}
 */
export function formatPriceDisplay( value ) {
	if ( value === '' || value === null || value === undefined ) {
		return '—';
	}

	const num = Number( value );
	if ( Number.isNaN( num ) ) {
		return String( value );
	}

	const formatted = formatPriceAmount( num );
	const currency = getCurrencyLabel();

	return currency ? `${ formatted } ${ currency }` : formatted;
}

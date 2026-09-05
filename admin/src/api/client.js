import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const config = window.pricePilotData || {};
const API_PREFIX = '/pricepilot/v1';

/**
 * Build a REST path under the plugin namespace.
 * Uses WordPress default apiFetch root (/wp-json/) — do not add a custom root middleware.
 */
function buildPath( path, query = {} ) {
	const normalized = path.startsWith( '/' ) ? path : `/${ path }`;
	const params = new URLSearchParams( query ).toString();
	return params ? `${ API_PREFIX }${ normalized }?${ params }` : `${ API_PREFIX }${ normalized }`;
}

export const api = {
	get: ( path, query = {} ) =>
		apiFetch( { path: buildPath( path, query ) } ).then( ( res ) => res.data ?? res ),
	post: ( path, data = {} ) =>
		apiFetch( { path: buildPath( path ), method: 'POST', data } ).then( ( res ) => res.data ?? res ),
};

export function parseError( error ) {
	if ( error?.message ) {
		const message = String( error.message );
		if ( message.includes( '<p>' ) || message.includes( '<html' ) ) {
			return __( 'A server error occurred while loading data. Please refresh the page.', 'pricepilot' );
		}
		return message;
	}
	return __( 'An unexpected error occurred.', 'pricepilot' );
}

export { formatPriceDisplay as formatPrice, formatPriceAmount } from '../utils/price';

export { config };

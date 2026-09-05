import { __, sprintf } from '@wordpress/i18n';
import { getCurrencyLabel } from '../utils/price';

export default function CurrencyNotice() {
	const label = getCurrencyLabel();

	if ( ! label ) {
		return null;
	}

	return (
		<p className="pricepilot-currency-notice">
			{ sprintf(
				__( 'All prices are based on %s (WooCommerce setting).', 'pricepilot' ),
				label
			) }
		</p>
	);
}

import { useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { api, parseError } from '../api/client';
import { getCurrencyLabel, parseFormattedNumber } from '../utils/price';
import PriceInput from './PriceInput';
import PercentInput from './PercentInput';
import PreviewModal from './PreviewModal';

const operations = [
	{ label: __( '+ Percent', 'pricepilot' ), value: 'increase_percent' },
	{ label: __( '- Percent', 'pricepilot' ), value: 'decrease_percent' },
	{ label: __( '+ Fixed Amount', 'pricepilot' ), value: 'increase_fixed' },
	{ label: __( '- Fixed Amount', 'pricepilot' ), value: 'decrease_fixed' },
	{ label: __( 'Set Price', 'pricepilot' ), value: 'set_price' },
	{ label: __( 'Remove Sale Price', 'pricepilot' ), value: 'remove_sale' },
];

const percentOperations = [ 'increase_percent', 'decrease_percent' ];
const moneyOperations = [ 'increase_fixed', 'decrease_fixed', 'set_price' ];

export default function BulkPanel( { selected, notify, onApplied } ) {
	const [ operationType, setOperationType ] = useState( 'increase_percent' );
	const [ value, setValue ] = useState( '10' );
	const [ targetField, setTargetField ] = useState( 'regular' );
	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const isPercent = percentOperations.includes( operationType );
	const isMoney = moneyOperations.includes( operationType );

	const handlePreview = async () => {
		if ( ! selected.length ) {
			notify( __( 'Select at least one product.', 'pricepilot' ), 'error' );
			return;
		}

		setLoading( true );
		try {
			const numericValue = isPercent
				? parseFloat( value ) || 0
				: parseFloat( parseFormattedNumber( value ) ) || 0;

			const result = await api.post( '/bulk/preview', {
				product_ids: selected,
				operation_type: operationType,
				value: numericValue,
				target_field: targetField,
			} );
			setPreview( result );
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		} finally {
			setLoading( false );
		}
	};

	const handleApply = async () => {
		if ( ! preview?.preview_token ) {
			return;
		}

		setLoading( true );
		try {
			const result = await api.post( '/bulk/apply', {
				preview_token: preview.preview_token,
			} );
			setPreview( null );
			const msg =
				result.error_count > 0
					? __( 'Applied with %1$d successes and %2$d errors.', 'pricepilot' )
							.replace( '%1$d', result.success_count )
							.replace( '%2$d', result.error_count )
					: __( 'Successfully updated %d products.', 'pricepilot' ).replace(
							'%d',
							result.success_count
					  );
			notify( msg );
			onApplied();
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		} finally {
			setLoading( false );
		}
	};

	return (
		<>
			<div className="pricepilot-bulk-panel">
				<SelectControl
					label={ __( 'Operation', 'pricepilot' ) }
					value={ operationType }
					options={ operations }
					onChange={ setOperationType }
				/>

				{ operationType !== 'remove_sale' && isPercent && (
					<PercentInput
						label={ __( 'Value (percent)', 'pricepilot' ) }
						value={ value }
						onChange={ setValue }
					/>
				) }

				{ operationType !== 'remove_sale' && isMoney && (
					<PriceInput
						label={ sprintf( __( 'Value (%s)', 'pricepilot' ), getCurrencyLabel() ) }
						value={ value }
						onChange={ setValue }
					/>
				) }

				{ operationType !== 'remove_sale' && (
					<SelectControl
						label={ __( 'Target', 'pricepilot' ) }
						value={ targetField }
						options={ [
							{ label: __( 'Regular Price', 'pricepilot' ), value: 'regular' },
							{ label: __( 'Sale Price', 'pricepilot' ), value: 'sale' },
							{ label: __( 'Both', 'pricepilot' ), value: 'both' },
						] }
						onChange={ setTargetField }
					/>
				) }

				<div className="pricepilot-bulk-panel__actions">
					<Button variant="primary" onClick={ handlePreview } disabled={ loading }>
						{ __( 'Preview Changes', 'pricepilot' ) }
					</Button>
					<span className="pricepilot-bulk-panel__selected">
						{ selected.length } { __( 'selected', 'pricepilot' ) }
					</span>
				</div>
			</div>

			{ preview && (
				<PreviewModal
					preview={ preview }
					loading={ loading }
					onCancel={ () => setPreview( null ) }
					onApply={ handleApply }
				/>
			) }
		</>
	);
}

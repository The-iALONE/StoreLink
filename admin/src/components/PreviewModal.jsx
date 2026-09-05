import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { formatPriceAmount } from '../api/client';
import { getCurrencyLabel } from '../utils/price';

function hasPrice( value ) {
	return value !== '' && value !== null && value !== undefined;
}

function formatCellAmount( value ) {
	if ( ! hasPrice( value ) ) {
		return null;
	}

	return formatPriceAmount( value );
}

function PriceChangeCell( { oldValue, newValue } ) {
	const currency = getCurrencyLabel();
	const oldAmount = formatCellAmount( oldValue );
	const newAmount = formatCellAmount( newValue );
	const changed = String( oldValue ?? '' ) !== String( newValue ?? '' );

	if ( ! oldAmount && ! newAmount ) {
		return <span className="pricepilot-muted">—</span>;
	}

	if ( ! changed ) {
		return (
			<span className="pricepilot-price-value">
				{ newAmount }
				{ currency && <span className="pricepilot-price-value__unit">{ currency }</span> }
			</span>
		);
	}

	return (
		<div className="pricepilot-price-change-vertical">
			<div className="pricepilot-price-change-vertical__row">
				<span className="pricepilot-price-change-vertical__label">
					{ __( 'Before', 'pricepilot' ) }
				</span>
				<span className="pricepilot-price-change-vertical__old">
					{ oldAmount || '—' }
					{ oldAmount && currency && (
						<span className="pricepilot-price-value__unit">{ currency }</span>
					) }
				</span>
			</div>
			<div className="pricepilot-price-change-vertical__row">
				<span className="pricepilot-price-change-vertical__label">
					{ __( 'After', 'pricepilot' ) }
				</span>
				<strong className="pricepilot-price-change-vertical__new">
					{ newAmount || '—' }
					{ newAmount && currency && (
						<span className="pricepilot-price-value__unit">{ currency }</span>
					) }
				</strong>
			</div>
		</div>
	);
}

function SalePriceCell( { oldValue, newValue, notice, changed } ) {
	if ( notice ) {
		return <span className="pricepilot-sale-note">{ notice }</span>;
	}

	if ( ! hasPrice( oldValue ) && ! hasPrice( newValue ) && ! changed ) {
		return (
			<span className="pricepilot-muted">{ __( 'No sale price', 'pricepilot' ) }</span>
		);
	}

	return (
		<PriceChangeCell oldValue={ oldValue } newValue={ newValue } />
	);
}

export default function PreviewModal( { preview, loading, onCancel, onApply } ) {
	const currency = getCurrencyLabel();

	return (
		<Modal title={ __( 'Preview Changes', 'pricepilot' ) } onRequestClose={ onCancel }>
			<ul className="pricepilot-preview-help">
				<li>
					{ __(
						'Regular price: the main price of the product.',
						'pricepilot'
					) }
				</li>
				<li>
					{ __(
						'Sale price: the discounted price when the product is on sale.',
						'pricepilot'
					) }
				</li>
				<li>
					{ __(
						'If sale price is empty, the customer pays the regular price.',
						'pricepilot'
					) }
				</li>
			</ul>

			<div className="pricepilot-table-wrap">
				<table className="wp-list-table widefat fixed striped pricepilot-preview-table">
					<thead>
						<tr>
							<th>{ __( 'Product', 'pricepilot' ) }</th>
							<th>
								{ __( 'Regular Price', 'pricepilot' ) }
								{ currency && (
									<span className="pricepilot-table-unit">({ currency })</span>
								) }
							</th>
							<th>
								{ __( 'Sale Price', 'pricepilot' ) }
								{ currency && (
									<span className="pricepilot-table-unit">({ currency })</span>
								) }
							</th>
							<th>{ __( 'Change', 'pricepilot' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ preview.items.map( ( row ) => (
							<tr key={ row.product_id }>
								<td>{ row.name }</td>
								<td>
									<PriceChangeCell
										oldValue={ row.old_regular }
										newValue={ row.new_regular }
									/>
								</td>
								<td>
									<SalePriceCell
										oldValue={ row.old_sale }
										newValue={ row.new_sale }
										notice={ row.sale_notice }
										changed={ row.sale_changed }
									/>
								</td>
								<td>{ row.change_label }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			{ preview.skipped?.length > 0 && (
				<p>
					{ __( 'Skipped:', 'pricepilot' ) } { preview.skipped.length }
				</p>
			) }

			<div className="pricepilot-preview-actions">
				<Button variant="secondary" onClick={ onCancel }>
					{ __( 'Cancel', 'pricepilot' ) }
				</Button>
				<Button variant="primary" onClick={ onApply } disabled={ loading }>
					{ __( 'Apply Changes', 'pricepilot' ) }
				</Button>
			</div>
		</Modal>
	);
}

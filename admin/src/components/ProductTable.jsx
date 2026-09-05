import { CheckboxControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { formatPrice } from '../api/client';

export default function ProductTable( {
	items,
	loading,
	selected,
	onSelect,
	onSelectAll,
} ) {
	if ( loading ) {
		return <Spinner />;
	}

	if ( ! items?.length ) {
		return (
			<p className="pricepilot-empty-state">
				{ __( 'No products found. Try clearing filters or publish a simple product in WooCommerce.', 'pricepilot' ) }
			</p>
		);
	}

	const selectable = items.filter( ( p ) => p.bulk_supported );
	const allSelected = selectable.length > 0 && selectable.every( ( p ) => selected.includes( p.id ) );

	return (
		<div className="pricepilot-table-wrap">
			<table className="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td style={ { width: 30 } }>
							<CheckboxControl
								checked={ allSelected }
								onChange={ ( checked ) => onSelectAll( checked, selectable ) }
								__nextHasNoMarginBottom
							/>
						</td>
						<th>{ __( 'ID', 'pricepilot' ) }</th>
						<th>{ __( 'Product', 'pricepilot' ) }</th>
						<th>{ __( 'SKU', 'pricepilot' ) }</th>
						<th>{ __( 'Regular', 'pricepilot' ) }</th>
						<th>{ __( 'Sale', 'pricepilot' ) }</th>
						<th>{ __( 'Stock', 'pricepilot' ) }</th>
						<th>{ __( 'Type', 'pricepilot' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ items.map( ( product ) => (
						<tr key={ product.id }>
							<td>
								{ product.bulk_supported ? (
									<CheckboxControl
										checked={ selected.includes( product.id ) }
										onChange={ () => onSelect( product.id ) }
										__nextHasNoMarginBottom
									/>
								) : (
									<span className="pricepilot-badge pricepilot-badge-warning">
										{ product.bulk_skip_reason || product.type }
									</span>
								) }
							</td>
							<td>{ product.id }</td>
							<td>
								<strong>{ product.name }</strong>
								{ product.sku && (
									<div style={ { fontSize: 12, color: '#646970' } }>
										{ __( 'SKU', 'pricepilot' ) }: { product.sku }
									</div>
								) }
								{ product.parent_name && (
									<div style={ { fontSize: 12, color: '#646970' } }>
										{ product.parent_name }
									</div>
								) }
							</td>
							<td>{ product.sku || '—' }</td>
							<td>{ formatPrice( product.regular_price ) }</td>
							<td>{ formatPrice( product.sale_price ) }</td>
							<td>{ product.stock_quantity ?? product.stock_status }</td>
							<td>{ product.type }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

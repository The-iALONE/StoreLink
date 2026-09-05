import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDashboardStats } from '../hooks/useApi';
import { api, formatPrice, parseError } from '../api/client';

export default function Dashboard( { notify } ) {
	const { stats, loading, error } = useDashboardStats();

	const handleUndo = async () => {
		try {
			const result = await api.post( '/bulk/undo' );
			notify(
				__( 'Undone %d products.', 'pricepilot' ).replace( '%d', result.success_count )
			);
			window.location.reload();
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		}
	};

	if ( loading ) {
		return <Spinner />;
	}

	if ( error ) {
		return <p>{ error }</p>;
	}

	const cards = [
		{ label: __( 'Total Products', 'pricepilot' ), value: stats.total_products },
		{ label: __( 'Products With Sale', 'pricepilot' ), value: stats.products_with_sale },
		{ label: __( 'Out Of Stock', 'pricepilot' ), value: stats.out_of_stock },
		{ label: __( 'Low Stock', 'pricepilot' ), value: stats.low_stock },
		{ label: __( 'Without Price', 'pricepilot' ), value: stats.products_without_price },
	];

	return (
		<div>
			<div className="pricepilot-kpi-grid">
				{ cards.map( ( card ) => (
					<div key={ card.label } className="pricepilot-kpi-card">
						<strong>{ card.value }</strong>
						<span>{ card.label }</span>
					</div>
				) ) }
			</div>

			{ stats.last_bulk_operation && (
				<div className="pricepilot-kpi-card" style={ { maxWidth: 480 } }>
					<h3>{ __( 'Last Bulk Operation', 'pricepilot' ) }</h3>
					<p>
						{ stats.last_bulk_operation.user } — { stats.last_bulk_operation.created_at }
					</p>
					<p>
						{ stats.last_bulk_operation.product_count } { __( 'products', 'pricepilot' ) } —{ ' ' }
						{ stats.last_bulk_operation.operation_type }{ ' ' }
						{ stats.last_bulk_operation.operation_value }
					</p>
					{ stats.last_bulk_operation.can_undo && (
						<Button variant="secondary" onClick={ handleUndo }>
							{ __( 'Undo Last Operation', 'pricepilot' ) }
						</Button>
					) }
				</div>
			) }

			{ stats.recently_updated?.length > 0 && (
				<div style={ { marginTop: 24 } }>
					<h3>{ __( 'Recently Updated', 'pricepilot' ) }</h3>
					<ul>
						{ stats.recently_updated.map( ( item ) => (
							<li key={ item.id }>
								{ item.name } — { item.date }
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
}

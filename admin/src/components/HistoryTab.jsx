import { useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useHistory } from '../hooks/useApi';
import { api, parseError } from '../api/client';

export default function HistoryTab( { notify } ) {
	const [ page, setPage ] = useState( 1 );
	const { items, total, loading, refresh } = useHistory( page );

	const handleUndo = async () => {
		try {
			const result = await api.post( '/bulk/undo' );
			notify(
				__( 'Undone %d products.', 'pricepilot' ).replace( '%d', result.success_count )
			);
			refresh();
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		}
	};

	const totalPages = Math.ceil( total / 20 ) || 1;

	return (
		<div>
			<Button variant="secondary" onClick={ handleUndo } style={ { marginBottom: 16 } }>
				{ __( 'Undo Last Operation', 'pricepilot' ) }
			</Button>

			{ loading ? (
				<Spinner />
			) : (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'User', 'pricepilot' ) }</th>
							<th>{ __( 'Date', 'pricepilot' ) }</th>
							<th>{ __( 'Operation', 'pricepilot' ) }</th>
							<th>{ __( 'Products', 'pricepilot' ) }</th>
							<th>{ __( 'Status', 'pricepilot' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( row ) => (
							<tr key={ row.id }>
								<td>{ row.user }</td>
								<td>{ row.created_at }</td>
								<td>
									{ row.operation_type } { row.operation_value } ({ row.target_field })
								</td>
								<td>{ row.product_count }</td>
								<td>{ row.status }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<div style={ { marginTop: 12, display: 'flex', gap: 8 } }>
				<Button disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
					{ __( 'Previous', 'pricepilot' ) }
				</Button>
				<span>{ page } / { totalPages }</span>
				<Button disabled={ page >= totalPages } onClick={ () => setPage( page + 1 ) }>
					{ __( 'Next', 'pricepilot' ) }
				</Button>
			</div>
		</div>
	);
}

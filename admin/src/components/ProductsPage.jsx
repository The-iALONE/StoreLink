import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FilterBar, { defaultFilters } from './FilterBar';
import ProductTable from './ProductTable';
import BulkPanel from './BulkPanel';
import CurrencyNotice from './CurrencyNotice';
import { useProducts } from '../hooks/useApi';

export default function ProductsPage( { notify } ) {
	const [ filters, setFilters ] = useState( defaultFilters );
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState( [] );
	const { items, total, loading, error, refresh } = useProducts( filters, page, 20 );

	const toggleSelect = ( id ) => {
		setSelected( ( prev ) =>
			prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ]
		);
	};

	const selectAll = ( checked, selectable ) => {
		setSelected( checked ? selectable.map( ( p ) => p.id ) : [] );
	};

	const totalPages = Math.ceil( total / 20 ) || 1;

	return (
		<div>
			<CurrencyNotice />
			<FilterBar filters={ filters } onChange={ ( f ) => { setFilters( f ); setPage( 1 ); } } />
			<BulkPanel
				selected={ selected }
				notify={ notify }
				onApplied={ () => { setSelected( [] ); refresh(); } }
			/>
			{ error && <p>{ error }</p> }
			<ProductTable
				items={ items }
				loading={ loading }
				selected={ selected }
				onSelect={ toggleSelect }
				onSelectAll={ selectAll }
			/>
			<div style={ { marginTop: 12, display: 'flex', gap: 8 } }>
				<Button disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
					{ __( 'Previous', 'pricepilot' ) }
				</Button>
				<span>
					{ page } / { totalPages } ({ total } { __( 'products', 'pricepilot' ) })
				</span>
				<Button disabled={ page >= totalPages } onClick={ () => setPage( page + 1 ) }>
					{ __( 'Next', 'pricepilot' ) }
				</Button>
			</div>
		</div>
	);
}

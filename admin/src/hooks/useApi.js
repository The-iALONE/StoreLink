import { useState, useEffect, useCallback } from '@wordpress/element';
import { api, parseError } from '../api/client';

export function useProducts( filters, page, perPage ) {
	const [ data, setData ] = useState( { items: [], total: 0 } );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const fetchProducts = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const result = await api.get( '/products', {
				...filters,
				page,
				per_page: perPage,
			} );
			setData( {
				items: result?.items ?? [],
				total: result?.total ?? 0,
			} );
		} catch ( err ) {
			setError( parseError( err ) );
		} finally {
			setLoading( false );
		}
	}, [ filters, page, perPage ] );

	useEffect( () => {
		fetchProducts();
	}, [ fetchProducts ] );

	return { ...data, loading, error, refresh: fetchProducts };
}

export function useDashboardStats() {
	const [ stats, setStats ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		api.get( '/dashboard/stats' )
			.then( setStats )
			.catch( ( err ) => setError( parseError( err ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	return { stats, loading, error };
}

export function useHistory( page ) {
	const [ data, setData ] = useState( { items: [], total: 0 } );
	const [ loading, setLoading ] = useState( false );

	const fetchHistory = useCallback( async () => {
		setLoading( true );
		try {
			const result = await api.get( '/history', { page, per_page: 20 } );
			setData( result );
		} finally {
			setLoading( false );
		}
	}, [ page ] );

	useEffect( () => {
		fetchHistory();
	}, [ fetchHistory ] );

	return { ...data, loading, refresh: fetchHistory };
}

export function useProductCategories() {
	const [ categories, setCategories ] = useState( [] );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		api.get( '/categories' )
			.then( ( result ) => setCategories( Array.isArray( result ) ? result : [] ) )
			.catch( () => setCategories( [] ) )
			.finally( () => setLoading( false ) );
	}, [] );

	return { categories, loading };
}

import { useMemo } from '@wordpress/element';
import { TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useProductCategories } from '../hooks/useApi';
import PriceInput from './PriceInput';

const defaultFilters = {
	search: '',
	sku: '',
	category: '',
	price_min: '',
	price_max: '',
	stock_status: '',
	on_sale: '',
	post_status: 'publish',
};

function buildCategoryOptions( categories ) {
	const options = [
		{ label: __( 'All', 'pricepilot' ), value: '' },
	];

	if ( ! categories.length ) {
		return options;
	}

	const byParent = {};

	categories.forEach( ( category ) => {
		const parentId = category.parent || 0;
		if ( ! byParent[ parentId ] ) {
			byParent[ parentId ] = [];
		}
		byParent[ parentId ].push( category );
	} );

	const walk = ( parentId, depth ) => {
		( byParent[ parentId ] || [] ).forEach( ( category ) => {
			const prefix = depth > 0 ? `${ '— '.repeat( depth ) }` : '';
			options.push( {
				label: `${ prefix }${ category.name }`,
				value: category.slug,
			} );
			walk( category.id, depth + 1 );
		} );
	};

	walk( 0, 0 );

	return options;
}

export default function FilterBar( { filters, onChange } ) {
	const update = ( key, value ) => onChange( { ...filters, [ key ]: value } );
	const { categories, loading } = useProductCategories();
	const categoryOptions = useMemo(
		() => buildCategoryOptions( categories ),
		[ categories ]
	);

	return (
		<div className="pricepilot-filters">
			<TextControl
				label={ __( 'Search', 'pricepilot' ) }
				value={ filters.search }
				onChange={ ( v ) => update( 'search', v ) }
			/>
			<TextControl
				label={ __( 'SKU', 'pricepilot' ) }
				value={ filters.sku }
				onChange={ ( v ) => update( 'sku', v ) }
			/>
			<SelectControl
				label={ __( 'Category', 'pricepilot' ) }
				value={ filters.category }
				options={ categoryOptions }
				onChange={ ( v ) => update( 'category', v ) }
				disabled={ loading }
			/>
			<PriceInput
				label={ __( 'Min Price', 'pricepilot' ) }
				value={ filters.price_min }
				onChange={ ( v ) => update( 'price_min', v ) }
			/>
			<PriceInput
				label={ __( 'Max Price', 'pricepilot' ) }
				value={ filters.price_max }
				onChange={ ( v ) => update( 'price_max', v ) }
			/>
			<SelectControl
				label={ __( 'Stock Status', 'pricepilot' ) }
				value={ filters.stock_status }
				options={ [
					{ label: __( 'All', 'pricepilot' ), value: '' },
					{ label: __( 'In Stock', 'pricepilot' ), value: 'instock' },
					{ label: __( 'Out Of Stock', 'pricepilot' ), value: 'outofstock' },
					{ label: __( 'On Backorder', 'pricepilot' ), value: 'onbackorder' },
				] }
				onChange={ ( v ) => update( 'stock_status', v ) }
			/>
			<SelectControl
				label={ __( 'On Sale', 'pricepilot' ) }
				value={ filters.on_sale }
				options={ [
					{ label: __( 'All', 'pricepilot' ), value: '' },
					{ label: __( 'On Sale', 'pricepilot' ), value: 'true' },
					{ label: __( 'Not On Sale', 'pricepilot' ), value: 'false' },
				] }
				onChange={ ( v ) => update( 'on_sale', v ) }
			/>
		</div>
	);
}

export { defaultFilters };

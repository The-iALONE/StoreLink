import { useState } from '@wordpress/element';
import { TabPanel, Snackbar } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Dashboard from './components/Dashboard';
import ProductsPage from './components/ProductsPage';
import HistoryTab from './components/HistoryTab';
import ImportExportTab from './components/ImportExportTab';
import LanguageSwitcher from './components/LanguageSwitcher';

export default function App() {
	const [ snackbar, setSnackbar ] = useState( null );

	const notify = ( message, type = 'success' ) => {
		setSnackbar( { message, type } );
	};

	return (
		<div className="pricepilot-app" dir={ window.pricePilotData?.isRtl ? 'rtl' : 'ltr' }>
			<div className="pricepilot-app-header">
				<div>
					<h1>{ __( 'PricePilot', 'pricepilot' ) }</h1>
					<p>{ __( 'Smart WooCommerce pricing and product operations.', 'pricepilot' ) }</p>
				</div>
				<LanguageSwitcher />
			</div>

			<TabPanel
				className="pricepilot-tabs"
				activeClass="is-active"
				tabs={ [
					{ name: 'dashboard', title: __( 'Dashboard', 'pricepilot' ) },
					{ name: 'products', title: __( 'Products & Pricing', 'pricepilot' ) },
					{ name: 'history', title: __( 'History', 'pricepilot' ) },
					{ name: 'import', title: __( 'Import / Export', 'pricepilot' ) },
				] }
			>
				{ ( tab ) => {
					if ( tab.name === 'dashboard' ) {
						return <Dashboard notify={ notify } />;
					}
					if ( tab.name === 'products' ) {
						return <ProductsPage notify={ notify } />;
					}
					if ( tab.name === 'history' ) {
						return <HistoryTab notify={ notify } />;
					}
					return <ImportExportTab notify={ notify } />;
				} }
			</TabPanel>

			{ snackbar && (
				<div className="pricepilot-toast-area">
					<Snackbar onRemove={ () => setSnackbar( null ) }>
						{ snackbar.message }
					</Snackbar>
				</div>
			) }
		</div>
	);
}

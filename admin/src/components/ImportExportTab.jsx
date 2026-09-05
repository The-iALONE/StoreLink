import { useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { config, parseError } from '../api/client';

export default function ImportExportTab( { notify } ) {
	const [ importing, setImporting ] = useState( false );
	const [ exportLoading, setExportLoading ] = useState( false );
	const [ importResult, setImportResult ] = useState( null );

	const handleExport = async () => {
		setExportLoading( true );
		try {
			const response = await apiFetch( { path: '/export' } );
			const data = response.data ?? response;
			const binary = atob( data.content );
			const bytes = new Uint8Array( binary.length );
			for ( let i = 0; i < binary.length; i++ ) {
				bytes[ i ] = binary.charCodeAt( i );
			}
			const blob = new Blob( [ bytes ], {
				type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			} );
			const url = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = data.filename || 'pricepilot-export.xlsx';
			link.click();
			URL.revokeObjectURL( url );
			notify( __( 'Export completed.', 'pricepilot' ) );
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		} finally {
			setExportLoading( false );
		}
	};

	const handleImport = async ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}

		setImporting( true );
		setImportResult( null );

		const formData = new FormData();
		formData.append( 'file', file );

		try {
			const response = await fetch( `${ config.apiUrl }/import`, {
				method: 'POST',
				headers: {
					'X-WP-Nonce': config.nonce,
				},
				body: formData,
				credentials: 'same-origin',
			} );
			const json = await response.json();
			if ( ! response.ok ) {
				throw json;
			}
			const data = json.data ?? json;
			setImportResult( data );
			notify(
				__( 'Import: %1$d success, %2$d errors.', 'pricepilot' )
					.replace( '%1$d', data.success_count )
					.replace( '%2$d', data.error_count )
			);
		} catch ( err ) {
			notify( parseError( err ), 'error' );
		} finally {
			setImporting( false );
			event.target.value = '';
		}
	};

	return (
		<div>
			<p>{ __( 'Export all products or import price updates from Excel.', 'pricepilot' ) }</p>

			<div style={ { display: 'flex', gap: 12, marginBottom: 24 } }>
				<Button variant="primary" onClick={ handleExport } disabled={ exportLoading }>
					{ exportLoading ? <Spinner /> : __( 'Export to Excel', 'pricepilot' ) }
				</Button>
				<label className="button button-secondary">
					{ importing ? <Spinner /> : __( 'Import from Excel', 'pricepilot' ) }
					<input
						type="file"
						accept=".xlsx,.xls"
						style={ { display: 'none' } }
						onChange={ handleImport }
						disabled={ importing }
					/>
				</label>
			</div>

			{ importResult && (
				<div className="pricepilot-kpi-card">
					<h3>{ __( 'Import Result', 'pricepilot' ) }</h3>
					<p>
						{ __( 'Success:', 'pricepilot' ) } { importResult.success_count } —{ ' ' }
						{ __( 'Errors:', 'pricepilot' ) } { importResult.error_count }
					</p>
					{ importResult.errors?.length > 0 && (
						<ul>
							{ importResult.errors.map( ( err, i ) => (
								<li key={ i }>
									{ __( 'Row', 'pricepilot' ) } { err.row }: { err.message }
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</div>
	);
}

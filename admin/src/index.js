import { render } from '@wordpress/element';
import App from './App';
import './style.scss';

const root = document.getElementById( 'pricepilot-root' );

if ( root ) {
	render( <App />, root );
}

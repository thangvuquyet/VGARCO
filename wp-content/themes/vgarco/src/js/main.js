import '../scss/main.scss';
import { initNav } from './modules/nav.js';

const pageModules = {
	home: () => import( './modules/home.js' ),
	projects: () => import( './modules/projects.js' ),
	services: () => import( './modules/services.js' ),
	contact: () => import( './modules/contact.js' ),
	article: () => import( './modules/article.js' ),
	careers: () => import( './modules/careers.js' ),
	apply: () => import( './modules/apply.js' ),
	project: () => import( './modules/project.js' ),
};

function initSearch() {
	const openers = [ ...document.querySelectorAll( '[data-vg-search-open]' ) ];
	if ( ! openers.length || ! document.querySelector( '[data-search-dialog]' ) ) {
		return;
	}

	let loaded = null;
	const load = () => ( loaded ??= import( './modules/search.js' ) );

	for ( const opener of openers ) {
		opener.setAttribute( 'role', 'button' );
		opener.setAttribute( 'aria-haspopup', 'dialog' );
		opener.addEventListener( 'pointerenter', load, { once: true } );
		opener.addEventListener( 'focus', load, { once: true } );
		opener.addEventListener( 'click', ( event ) => {
			if ( event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}
			event.preventDefault();
			load().then( ( module ) => module.openSearch( opener ) );
		} );
		opener.addEventListener( 'keydown', ( event ) => {
			if ( event.key === ' ' ) {
				event.preventDefault();
				opener.click();
			}
		} );
	}
}

function initLanguageNotice() {
	const KEY = 'vg-lang-missing';
	const notice = document.querySelector( '[data-lang-notice]' );

	document.addEventListener( 'click', ( event ) => {
		if ( event.target instanceof Element && event.target.closest( '[data-lang-missing]' ) ) {
			try {
				sessionStorage.setItem( KEY, String( Date.now() ) );
			} catch {
			}
		}
	} );

	if ( ! notice ) {
		return;
	}
	try {
		const flagged = Number( sessionStorage.getItem( KEY ) );
		sessionStorage.removeItem( KEY );
		if ( flagged && Date.now() - flagged < 30000 ) {
			notice.hidden = false;
		}
	} catch {
	}
	notice.querySelector( '[data-lang-notice-close]' )?.addEventListener( 'click', () => {
		notice.hidden = true;
	} );
}

function start() {
	initNav();
	initSearch();
	initLanguageNotice();

	const load = pageModules[ document.body.dataset.vgPage ];
	if ( load ) {
		load().then( ( module ) => module.default?.() );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start, { once: true } );
} else {
	start();
}

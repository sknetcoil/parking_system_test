import AuthService from '../services/AuthService';

export default class Router {
	constructor(routes, rootElement) {
		this.routes = routes;
		this.rootElement = rootElement;
		this.currentPage = null;

		window.addEventListener('popstate', () => {
			this.navigate(window.location.pathname, false);
		});
	}

	navigate(path, pushState = true) {
		if (path === '/slots' && !AuthService.isLoggedIn()) {
			return this.navigate('/login', pushState);
		}

		if (path === '/login' && AuthService.isLoggedIn()) {
			return this.navigate('/slots', pushState);
		}

		const PageClass = this.routes[path];

		if (!PageClass) return;

		this.currentPage = new PageClass(this.rootElement, this);
		this.currentPage.render();

		if (pushState) {
			window.history.pushState({}, '', path);
		}
	}
}

const STORAGE_KEY = 'parking_auth_token';
const USER_KEY = 'parking_auth_email';

export default {
	async login(username, password) {
		try {
			const response = await fetch(`${import.meta.env.VITE_API_URL}/login`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: username, password })
			});

			if (!response.ok) {
				const errorData = await response.json();
				throw new Error(errorData.error || 'Invalid credentials');
			}

			const data = await response.json();
			sessionStorage.setItem(STORAGE_KEY, data.token);
			sessionStorage.setItem(USER_KEY, username);
			return { token: data.token, username };
		} catch (error) {
			throw new Error(error.message || 'Network error occurred');
		}
	},

	logout() {
		sessionStorage.removeItem(STORAGE_KEY);
		sessionStorage.removeItem(USER_KEY);
	},

	isLoggedIn() {
		return !!sessionStorage.getItem(STORAGE_KEY);
	},

	getCurrentUser() {
		return sessionStorage.getItem(USER_KEY);
	},

	getCurrentToken() {
		return sessionStorage.getItem(STORAGE_KEY);
	}
};

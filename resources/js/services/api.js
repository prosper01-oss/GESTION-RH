import axios from 'axios';

// Sanctum en mode SPA : cookie de session et jeton XSRF envoyés à chaque requête.
const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

export function csrfCookie() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export default api;

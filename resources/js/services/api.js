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

/**
 * Erreurs de validation (422) au format { champ: 'premier message' }.
 */
export function validationErrors(error) {
    const errors = error.response?.status === 422 ? error.response.data.errors ?? {} : {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
}

/**
 * Message lisible pour toute erreur d'API.
 */
export function errorMessage(error) {
    if (!error.response) {
        return 'Le serveur est injoignable. Vérifiez le réseau.';
    }

    return error.response.data?.message || 'Une erreur est survenue.';
}

export default api;

/**
 * Client API : gestion du jeton Bearer, requêtes JSON/multipart,
 * redirection automatique vers login si 401.
 */
const Auth = {
    KEY: 'transport_token',

    token() {
        return localStorage.getItem(this.KEY);
    },
    save(token) {
        localStorage.setItem(this.KEY, token);
    },
    clear() {
        localStorage.removeItem(this.KEY);
    },
    isLoggedIn() {
        return !!this.token();
    },
    logout() {
        this.clear();
        window.location.href = 'login.html';
    }
};

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

const api = {
    /**
     * @param {string} method  GET|POST|PUT|DELETE
     * @param {string} path    ex. '/api/colis/mine'
     * @param {object|FormData|null} body
     */
    async request(method, path, body = null) {
        const headers = {};
        const token = Auth.token();
        if (token) headers['Authorization'] = 'Bearer ' + token;

        let payload = null;
        if (body instanceof FormData) {
            payload = body; // Content-Type défini automatiquement par le navigateur
        } else if (body !== null) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }

        let res;
        try {
            res = await fetch(window.API_URL + path, { method, headers, body: payload });
        } catch (e) {
            throw new ApiError('Serveur injoignable. Vérifiez que l\'API est démarrée.', 0);
        }

        const text = await res.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch (e) { /* réponse non-JSON */ }

        if (!res.ok) {
            // Jeton présent mais rejeté (401) : session expirée/révoquée.
            // On efface le jeton puis on renvoie vers la page de connexion du
            // contexte courant (admin/login.html depuis /admin/, sinon login.html).
            if (res.status === 401 && Auth.isLoggedIn()) {
                Auth.clear();
                window.location.href = 'login.html';
                throw new ApiError('Session expirée', 401);
            }
            throw new ApiError((data && data.error) || ('Erreur HTTP ' + res.status), res.status);
        }
        return data && data.data !== undefined ? data.data : data;
    },

    // L'API n'expose que GET/POST/DELETE (les modifications passent par POST).
    get(path)            { return this.request('GET', path); },
    post(path, body)     { return this.request('POST', path, body); },
    del(path)            { return this.request('DELETE', path); },
    upload(path, fields, files = {}) {
        const fd = new FormData();
        Object.entries(fields || {}).forEach(([k, v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
        Object.entries(files).forEach(([k, f]) => { if (f) fd.append(k, f); });
        return this.request('POST', path, fd);
    }
};

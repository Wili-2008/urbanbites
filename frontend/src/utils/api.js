// Entrar al router mediante PATH_INFO evita el 400 que WAMP devuelve al
// reescribir endpoints bajo el Alias del VirtualHost.
const apiUrl = new URL('../../../backend/routes/api.php/', import.meta.url);

const buildUrl = (endpoint) => new URL(String(endpoint).replace(/^\/+/, ''), apiUrl);

const request = async (endpoint, options = {}) => {
    let response;
    const token = (() => {
        try { return JSON.parse(localStorage.getItem('urban-bites.session') || 'null')?.token; }
        catch { return null; }
    })();
    const headers = new Headers(options.headers || {});
    if (token) headers.set('Authorization', `Bearer ${token}`);

    try {
        response = await fetch(buildUrl(endpoint), {
            ...options,
            headers,
            credentials: 'same-origin',
        });
    } catch (error) {
        if (error instanceof TypeError) {
            throw new Error('No se pudo comunicar con el servidor. Verifica que el sitio y el API estén disponibles.');
        }
        throw error;
    }

    const responseText = await response.text();
    let data = null;

    if (responseText) {
        try {
            data = JSON.parse(responseText);
        } catch {
            if (response.ok) {
                throw new Error('El API devolvió una respuesta que no es JSON válido.');
            }
        }
    }

    if (!response.ok) {
        throw new Error(data?.message || data?.error || `Error HTTP: ${response.status}`);
    }

    return data;
};

export const api = {
    get: (endpoint) => request(endpoint),

    post: (endpoint, payload) => request(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }),

    postFormData: (endpoint, payload) => {
        const form = new FormData();
        Object.entries(payload).forEach(([key, value]) => form.append(key, value));
        return request(endpoint, { method: 'POST', body: form });
    },

    put: (endpoint, payload) => request(endpoint, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }),

    del: (endpoint, payload) => request(endpoint, {
        method: 'DELETE',
        headers: payload ? { 'Content-Type': 'application/json' } : {},
        ...(payload ? { body: JSON.stringify(payload) } : {}),
    }),
};

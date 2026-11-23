/**
 * Thin wrapper around the Laravel JSON API (/api/v1).
 * Non-2xx responses throw ApiError so callers can show field errors (422)
 * or a general message (404, 500, network).
 */
export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message ?? `Request failed (${status})`);
        this.status = status;
        this.errors = body?.errors ?? {};
    }
}

async function request(method, path, body) {
    let response;
    try {
        response = await fetch(`/api/v1${path}`, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, { message: 'Cannot reach the server. Is it running?' });
    }

    if (response.status === 204) {
        return null;
    }

    const data = await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(response.status, data);
    }

    return data.data;
}

export const api = {
    listDesigns: () => request('GET', '/designs'),
    getDesign: (id) => request('GET', `/designs/${id}`),
    createDesign: (payload) => request('POST', '/designs', payload),
    updateDesign: (id, payload) => request('PUT', `/designs/${id}`, payload),
    deleteDesign: (id) => request('DELETE', `/designs/${id}`),
    calculate: (payload) => request('POST', '/calculate', payload),
};

const TIMEOUT_MS = 5000;

export class ApiError extends Error {
    constructor(message, { status = 0, errors = null }  = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

export async function request(baseUrl, path, { method = 'GET', body} = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

    try {
        const response = await fetch(`${baseUrl}${path}`, {
            method,
            headers: {
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
            signal: controller.signal
        });

        const payload = await response.json().catch(() => null)
        
        if (!response.ok) {
            throw new ApiError(payload?.message ?? `Permintaan gagal (${response.status})`, {
                status: response.status,
                errors: payload?.errors ?? null
            });
        }

        return payload;
        
    } catch (error) {
        if (error instanceof ApiError) throw error
        if (error.name === 'AbortError')throw new ApiError('server terlalu lama menjawab')
        throw new ApiError('Tidak dapat terhubung ke server')
    } finally {
        clearTimeout(timer);
    }

}
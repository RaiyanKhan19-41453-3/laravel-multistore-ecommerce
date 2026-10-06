const USER_KEY = 'store_user';

import { getGuestToken } from '@/lib/guest-token';

export interface StoreUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
}

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));

    if (!match) {
        return null;
    }

    try {
        return decodeURIComponent(match[2]);
    } catch {
        return match[2];
    }
}

export function getUser(): StoreUser | null {
    const raw = localStorage.getItem(USER_KEY);

    if (!raw) return null;

    try {
        return JSON.parse(raw) as StoreUser;
    } catch {
        return null;
    }
}

export function setAuth(user: StoreUser): void {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function clearAuth(): void {
    localStorage.removeItem(USER_KEY);
}

export async function apiStore<T = unknown>(
    path: string,
    options: { method?: string; body?: unknown; headers?: Record<string, string> } = {},
): Promise<{ ok: boolean; data: T | null; message?: string; errors?: Record<string, string[]> }> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers ?? {}),
    };

    const csrf = getCookie('XSRF-TOKEN');

    if (csrf) {
        headers['X-XSRF-TOKEN'] = csrf;
    }

    headers['X-Guest-Token'] = getGuestToken();

    let res: Response;

    try {
        res = await fetch(`/api${path}`, {
            method: options.method ?? (options.body ? 'POST' : 'GET'),
            headers,
            credentials: 'same-origin',
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
    } catch {
        return { ok: false, data: null, message: 'Network error. Please check your connection and try again.' };
    }

    if (res.status === 401) {
        clearAuth();
    }

    let json: Record<string, unknown> | null = null;

    try {
        json = (await res.json()) as Record<string, unknown>;
    } catch {
        json = null;
    }

    const data = (json?.data as T) ?? null;

    return {
        ok: res.ok,
        data,
        message: json?.message as string | undefined,
        errors: json?.errors as Record<string, string[]> | undefined,
    };
}

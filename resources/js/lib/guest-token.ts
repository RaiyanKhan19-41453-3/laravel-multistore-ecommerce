const GUEST_TOKEN_KEY = 'store_guest_token';

function generateUuid(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}

export function getGuestToken(): string {
    let token = localStorage.getItem(GUEST_TOKEN_KEY);

    if (!token) {
        token = generateUuid();
        localStorage.setItem(GUEST_TOKEN_KEY, token);
    }

    return token;
}

const storageKey = 'urban-bites.session';

export const getSession = () => {
    try {
        const session = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (!session?.token || !session?.user?.id_usuario || !['administrador', 'vendedor'].includes(session.user.rol)) {
            return null;
        }
        return session;
    } catch {
        localStorage.removeItem(storageKey);
        return null;
    }
};

export const saveSession = (user, token) => {
    if (!user?.id_usuario || !['administrador', 'vendedor'].includes(user.rol) || !token) {
        throw new Error('La respuesta no contiene un usuario o token válido.');
    }
    const session = { user, token: String(token) };
    localStorage.setItem(storageKey, JSON.stringify(session));
    return session;
};

export const clearSession = () => localStorage.removeItem(storageKey);

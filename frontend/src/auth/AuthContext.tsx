import { createContext, ReactNode, useCallback, useContext, useEffect, useState } from 'react';
import { api, authApi, getSessionToken, setSessionToken } from '../api';
import type { AppConfig, AuthUser, ProtectionType } from '../types';

interface AuthContextValue {
  user: AuthUser | null;
  config: AppConfig | null;
  ready: boolean;
  protectionTypes: ProtectionType[];
  loginWithGoogleToken: (idToken: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [config, setConfig] = useState<AppConfig | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const cfg = await api.config();
        if (active) setConfig(cfg);
      } catch {
        // config endpoint optional; login simply won't be available
      }
      if (getSessionToken()) {
        try {
          const me = await authApi.me();
          if (active) setUser(me);
        } catch {
          setSessionToken('');
        }
      }
      if (active) setReady(true);
    })();
    return () => {
      active = false;
    };
  }, []);

  const loginWithGoogleToken = useCallback(async (idToken: string) => {
    const { token, user: u } = await authApi.loginWithGoogle(idToken);
    setSessionToken(token);
    setUser(u);
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // ignore network errors on logout
    }
    setSessionToken('');
    setUser(null);
    window.google?.accounts.id.disableAutoSelect();
  }, []);

  return (
    <AuthContext.Provider
      value={{
        user,
        config,
        ready,
        protectionTypes: config?.protectionTypes ?? ['kruh', 'uzel', 'hodiny', 'hrot', 'strom', 'jine'],
        loginWithGoogleToken,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

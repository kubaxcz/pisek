import { useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthContext';

const GIS_SRC = 'https://accounts.google.com/gsi/client';

function loadGisScript(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (window.google?.accounts?.id) {
      resolve();
      return;
    }
    const existing = document.querySelector(`script[src="${GIS_SRC}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve());
      existing.addEventListener('error', () => reject(new Error('GIS load failed')));
      return;
    }
    const script = document.createElement('script');
    script.src = GIS_SRC;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('GIS load failed'));
    document.head.appendChild(script);
  });
}

/**
 * Renders Google's official sign-in button and wires its credential into auth.
 */
export function GoogleLoginButton() {
  const { config, loginWithGoogleToken } = useAuth();
  const ref = useRef<HTMLDivElement>(null);
  const [error, setError] = useState<string | null>(null);

  const clientId = config?.googleClientId ?? '';

  useEffect(() => {
    if (!clientId || !ref.current) return;
    let cancelled = false;

    loadGisScript()
      .then(() => {
        if (cancelled || !ref.current || !window.google) return;
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: (response) => {
            loginWithGoogleToken(response.credential).catch((e: unknown) =>
              setError(e instanceof Error ? e.message : 'Přihlášení selhalo.'),
            );
          },
        });
        window.google.accounts.id.renderButton(ref.current, {
          type: 'standard',
          theme: 'outline',
          size: 'large',
          text: 'signin_with',
          shape: 'pill',
        });
      })
      .catch(() => setError('Nelze načíst Google přihlášení.'));

    return () => {
      cancelled = true;
    };
  }, [clientId, loginWithGoogleToken]);

  if (!clientId) {
    return <span className="muted">Přihlášení Google není nakonfigurováno.</span>;
  }

  return (
    <div>
      <div ref={ref} />
      {error ? <div className="error">{error}</div> : null}
    </div>
  );
}

import { FormEvent, useState } from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';

export function Layout() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');

  function onSearch(e: FormEvent) {
    e.preventDefault();
    const q = query.trim();
    if (q.length >= 2) {
      navigate(`/hledat?q=${encodeURIComponent(q)}`);
    }
  }

  return (
    <>
      <header className="app-header">
        <div className="app-header__bar">
          <Link to="/" className="app-header__title">
            🧗 Piskari
          </Link>
          <span className="app-header__spacer" />
          <Link to="/admin" className="nav-link">
            Admin
          </Link>
        </div>
        <form className="search-box" onSubmit={onSearch} role="search">
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Hledat skálu nebo cestu…"
            aria-label="Hledat skálu nebo cestu"
          />
          <button type="submit">Hledat</button>
        </form>
      </header>
      <main>
        <Outlet />
      </main>
    </>
  );
}

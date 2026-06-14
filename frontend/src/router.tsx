import { createBrowserRouter } from 'react-router-dom';
import { Layout } from './components/Layout';
import { HomePage } from './pages/HomePage';
import { SectorPage } from './pages/SectorPage';
import { RockPage } from './pages/RockPage';
import { SearchPage } from './pages/SearchPage';
import { AdminPage } from './pages/AdminPage';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'sektor/:sectorId', element: <SectorPage /> },
      { path: 'skala/:rockId', element: <RockPage /> },
      { path: 'hledat', element: <SearchPage /> },
      { path: 'admin', element: <AdminPage /> },
    ],
  },
]);

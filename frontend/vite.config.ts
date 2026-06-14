import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// During development the API is proxied to the PHP backend on :8080.
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8080',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
  },
});

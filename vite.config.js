import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'public/build',
    manifest: 'manifest.json',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
  },
});

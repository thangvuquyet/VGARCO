import { defineConfig } from 'vite';

export default defineConfig( {
	base: './',
	build: {
		outDir: 'dist',
		emptyOutDir: true,
		manifest: true,
		sourcemap: false,
		target: 'es2020',
		modulePreload: { polyfill: false },
		rollupOptions: {
			input: 'src/js/main.js',
		},
	},
} );

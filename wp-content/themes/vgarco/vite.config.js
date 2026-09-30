import { defineConfig } from 'vite';

// Không dùng máy chủ dev/HMR: WordPress phục vụ trang, Vite chỉ build ra dist/.
//   npm run build  → build một lần (dùng khi triển khai)
//   npm run dev    → build lại mỗi khi sửa tệp trong src/
export default defineConfig( {
	// Đường dẫn tương đối: CSS tìm phông chữ và JS tìm module con cạnh chính nó,
	// nên chạy được dưới mọi đường dẫn (kể cả khi đổi tên thư mục theme).
	base: './',
	build: {
		outDir: 'dist',
		emptyOutDir: true,
		manifest: true, // PHP đọc dist/.vite/manifest.json để lấy tên tệp có mã băm (inc/enqueue.php).
		sourcemap: false,
		target: 'es2020',
		modulePreload: { polyfill: false },
		rollupOptions: {
			input: 'src/js/main.js',
		},
	},
} );

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      "@": resolve(__dirname, "src")
    }
  },
  build: {
    lib: {
      entry: resolve(__dirname, "src/index.ts"),
      name: "NoticePluginUser",
      formats: ["iife"],
      fileName: () => "notice-plugin.iife.js"
    },
    outDir: "dist",
    rollupOptions: {
      external: ["vue", "vue-router", "element-plus", "pinia"],
      output: {
        globals: {
          vue: "__deps.Vue",
          "vue-router": "__deps.VueRouter",
          "element-plus": "__deps.ElementPlus",
          pinia: "__deps.Pinia"
        }
      }
    }
  }
});

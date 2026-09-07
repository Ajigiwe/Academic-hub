import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Server-side PDF pipeline: native/cjs packages that must not be
  // bundled — they load from node_modules at runtime instead.
  serverExternalPackages: ["pdfjs-dist", "@napi-rs/canvas", "pdf-lib"],
  // Self-hosted deployment: build a standalone server (deploy/Dockerfile).
  output: "standalone",
  // Bulk PDF uploads travel through server actions; Next's default 1 MB
  // cap rejects real papers. 100 MB suits real scanned PDFs on a VPS
  // (nginx client_max_body_size must match). On Vercel the platform's
  // 4.5 MB function body cap applies regardless of this setting.
  experimental: {
    serverActions: {
      bodySizeLimit: "100mb",
    },
  },
};

export default nextConfig;

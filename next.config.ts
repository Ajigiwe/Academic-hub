import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Server-side PDF pipeline: native/cjs packages that must not be
  // bundled — they load from node_modules at runtime instead.
  serverExternalPackages: ["pdfjs-dist", "@napi-rs/canvas", "pdf-lib"],
  // Bulk PDF uploads travel through server actions; Next's default 1 MB
  // cap rejects real papers. Note Vercel's platform hard-caps function
  // request bodies at 4.5 MB, so deployed uploads are still limited to
  // that regardless of this setting.
  experimental: {
    serverActions: {
      bodySizeLimit: "25mb",
    },
  },
};

export default nextConfig;

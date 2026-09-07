import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Server-side PDF pipeline: native/cjs packages that must not be
  // bundled — they load from node_modules at runtime instead.
  serverExternalPackages: ["pdfjs-dist", "@napi-rs/canvas", "pdf-lib"],
};

export default nextConfig;

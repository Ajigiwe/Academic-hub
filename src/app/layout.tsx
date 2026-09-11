import type { Metadata, Viewport } from "next";
import localFont from "next/font/local";
import "./globals.css";

// Fraunces — academic display serif (variable, optical sizing). Vendored
// locally so builds never depend on Google Fonts being reachable.
const fraunces = localFont({
  src: "./fonts/fraunces-var.woff2",
  variable: "--font-fraunces",
  display: "swap",
});
import { getCurrentUser } from "@/lib/auth";
import { SiteHeader } from "@/components/site-header";
import { MobileNav } from "@/components/mobile-nav";
import { RegisterServiceWorker } from "@/components/register-sw";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Academic Resource Hub";

export const metadata: Metadata = {
  title: {
    default: `${appName} — Find. Purchase. Study.`,
    template: `%s · ${appName}`,
  },
  description:
    "Ghana's digital academic library. Find solved and unsolved past questions, course materials, slides, and notes — everything you need to study smarter.",
  manifest: "/manifest.webmanifest",
  icons: {
    icon: [
      { url: "/icons/icon.svg", type: "image/svg+xml" },
      { url: "/icons/icon-192.png", sizes: "192x192", type: "image/png" },
      { url: "/icons/icon-512.png", sizes: "512x512", type: "image/png" },
    ],
    apple: [{ url: "/icons/apple-touch-icon.png", sizes: "180x180" }],
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: "Academic Hub",
  },
};

export const viewport: Viewport = {
  themeColor: "#0b2d5b",
  width: "device-width",
  initialScale: 1,
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const user = await getCurrentUser();

  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: `try{var t=localStorage.getItem('arh-theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark')}catch(e){}`,
          }}
        />
      </head>
      <body className={`${fraunces.variable} flex min-h-screen flex-col`}>
        <RegisterServiceWorker />
        <SiteHeader user={user} />
        <main className="flex-1 pb-20 md:pb-0">{children}</main>
        <MobileNav isAuthed={user !== null} isAdmin={user?.role === "ADMIN"} />
      </body>
    </html>
  );
}

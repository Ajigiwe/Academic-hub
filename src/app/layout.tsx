import type { Metadata, Viewport } from "next";
import "./globals.css";
import { getCurrentUser } from "@/lib/auth";
import { SiteHeader } from "@/components/site-header";
import { SiteFooter } from "@/components/site-footer";
import { MobileNav } from "@/components/mobile-nav";
import { RegisterServiceWorker } from "@/components/register-sw";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Academic Resource Hub";

export const metadata: Metadata = {
  title: {
    default: `${appName} — Find. Purchase. Study.`,
    template: `%s · ${appName}`,
  },
  description:
    "Ghana's digital academic library. Find, purchase, and study past questions and academic resources — Learn · Revise · Excel.",
  manifest: "/manifest.webmanifest",
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: appName,
  },
};

export const viewport: Viewport = {
  themeColor: "#027a48",
  width: "device-width",
  initialScale: 1,
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const user = await getCurrentUser();

  return (
    <html lang="en">
      <body className="flex min-h-screen flex-col">
        <RegisterServiceWorker />
        <SiteHeader user={user} />
        <main className="flex-1 pb-20 md:pb-0">{children}</main>
        <SiteFooter />
        <MobileNav isAuthed={user !== null} isAdmin={user?.role === "ADMIN"} />
      </body>
    </html>
  );
}

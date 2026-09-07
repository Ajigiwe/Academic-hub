import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { hasEntitlement } from "@/lib/entitlements";
import { DocumentViewer } from "@/components/document-viewer";

interface Params {
  slug: string;
}

export const metadata: Metadata = { title: "Secure Viewer" };

export default async function ViewerPage({ params }: { params: Promise<Params> }) {
  const { slug } = await params;

  const user = await getCurrentUser();
  if (!user) redirect(`/login?next=/viewer/${slug}`);

  const resource = await prisma.resource.findUnique({
    where: { slug },
    select: {
      id: true,
      title: true,
      slug: true,
      status: true,
      pageCount: true,
    },
  });
  if (!resource) notFound();

  const entitled = await hasEntitlement(user, resource.id);
  if (!entitled) redirect(`/resources/${resource.slug}`);

  if (resource.status === "ARCHIVED") notFound();

  // Resolve the page count: DB value first, PDF probe as fallback.
  let pageCount = resource.pageCount ?? 0;
  if (!pageCount) {
    const file = await prisma.resourceFile.findFirst({
      where: { resourceId: resource.id, isCurrent: true },
      orderBy: { createdAt: "desc" },
      select: { storageKey: true },
    });
    if (file) {
      const { getObjectBuffer } = await import("@/lib/storage");
      const { probePdf } = await import("@/lib/pdf-render");
      const bytes = await getObjectBuffer(file.storageKey);
      pageCount = (await probePdf(bytes)).pageCount;
    }
  }

  return (
    <DocumentViewer
      slug={resource.slug}
      title={resource.title}
      pageCount={pageCount || 1}
    />
  );
}

import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { getObjectStream } from "@/lib/storage";
import { slugify } from "@/lib/resource-admin";

interface Params {
  params: Promise<{ slug: string }>;
}

/**
 * Public download route for FREE materials only. Paid papers never come
 * through here — they are gated by the tokenised viewer. A resource is
 * downloadable when it has NO bundle (bundleId null) and is PUBLISHED,
 * which is exactly what the admin free-material form creates.
 */
export async function GET(_req: Request, { params }: Params) {
  const { slug } = await params;

  const resource = await prisma.resource.findFirst({
    where: { slug, bundleId: null, status: "PUBLISHED" },
    include: {
      course: { select: { code: true, title: true } },
      files: {
        where: { isCurrent: true },
        orderBy: { createdAt: "desc" },
        take: 1,
      },
    },
  });

  if (!resource || !resource.files[0]) {
    return NextResponse.json({ error: "Material not found." }, { status: 404 });
  }

  const file = resource.files[0];
  const { stream, contentLength } = await getObjectStream(file.storageKey);

  // Safe download filename: course code + slug, never the original name.
  const filename = `${resource.course.code.replace(/\s+/g, "-")}-${slugify(resource.title)}.pdf`;

  // Best-effort download counter — never blocks the response.
  prisma.resource
    .update({ where: { id: resource.id }, data: { downloadCount: { increment: 1 } } })
    .catch(() => undefined);

  return new Response(stream as unknown as BodyInit, {
    headers: {
      "Content-Type": "application/pdf",
      "Content-Disposition": `attachment; filename="${filename}"`,
      ...(contentLength ? { "Content-Length": String(contentLength) } : {}),
      "Cache-Control": "private, max-age=3600",
    },
  });
}
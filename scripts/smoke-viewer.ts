/**
 * Viewer pipeline smoke test (no browser): pulls the demo paper's PDF
 * from storage and renders page 1 through the real watermark pipeline.
 *
 * Run: npx tsx --env-file=.env scripts/smoke-viewer.ts
 */
import { prisma } from "@/lib/db";
import { getObjectBuffer } from "@/lib/storage";
import { probePdf, renderPageToPng } from "@/lib/pdf-render";

async function loadBytes(key: string): Promise<Uint8Array> {
  return getObjectBuffer(key);
}

async function main() {
  const file = await prisma.resourceFile.findFirst({
    where: { isCurrent: true },
    orderBy: { createdAt: "desc" },
    select: { id: true, storageKey: true, resource: { select: { title: true, slug: true } } },
  });
  if (!file) {
    throw new Error("No resource file in DB — run make-demo-draft first.");
  }
  console.log("file:", file.resource.title);

  const bytes = await loadBytes(file.storageKey);

  const probe = await probePdf(bytes);
  console.log("probe:", probe);
  if (probe.pageCount < 1 || probe.encrypted) throw new Error("probe failed");

  const identity = {
    name: "Ama Mensah",
    accountId: "A1B2C3",
    orderRef: "PQ-SMOKE99",
  };

  const { png } = await renderPageToPng(bytes, 1, identity);
  console.log("rendered page 1:", png.length, "bytes");
  if (png.length < 10_000) throw new Error("rendered PNG suspiciously small");

  // PNG magic bytes check
  if (png[0] !== 0x89 || png[1] !== 0x50 || png[2] !== 0x4e || png[3] !== 0x47) {
    throw new Error("output is not a PNG");
  }

  // Out-of-range page must fail cleanly
  let rejected = false;
  try {
    await renderPageToPng(bytes, probe.pageCount + 5, identity);
  } catch {
    rejected = true;
  }
  if (!rejected) throw new Error("out-of-range page did not throw");

  console.log("\nSMOKE TEST PASSED ✓");
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

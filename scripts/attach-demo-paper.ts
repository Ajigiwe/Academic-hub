/**
 * One-off: attach a real generated PDF to ONE seeded bundle paper so the
 * public first-page preview can be exercised end to end locally (seeded
 * bundles normally have no files — the demo-prep script is the full-data
 * path). Idempotent: skips if the paper already has a current file.
 *
 * Run: npx tsx --env-file=.env scripts/attach-demo-paper.ts
 */
import { prisma } from "@/lib/db";
import { putObject, getObjectHead } from "@/lib/storage";
import { makePdf } from "./pdf-factory";

const TARGET_TITLE = "BUS 101 — Introduction to Business · Semester 1 Exam";

async function main() {
  const paper =
    (
      await prisma.resource.findMany({
        where: { title: { contains: "BUS 101" } },
        orderBy: { title: "asc" },
        select: {
          id: true,
          title: true,
          bundleId: true,
          pageCount: true,
          files: { where: { isCurrent: true }, select: { id: true } },
        },
      })
    ).find((r) => r.bundleId) ?? null;
  if (!paper) throw new Error("No published bundle paper matching BUS 101 found.");

  if (paper.files.length > 0) {
    console.log("already has a current file — nothing to do");
    return;
  }

  const buf = makePdf(
    paper.pageCount ?? 18,
    "BUS 101 - Introduction to Business",
    [
      "Answer ALL questions.",
      "1. Define the term 'business organisation' and state its purpose. (5 marks)",
      "2. Explain three forms of business ownership with examples. (15 marks)",
      "3. Discuss the effect of supply and demand on pricing. (10 marks)",
      "4. Outline the functions of management. (10 marks)",
    ],
  );
  const key = `private/resources/demo/bus-101-sem1-exam.pdf`;
  await putObject(key, buf, "application/pdf");
  const head = await getObjectHead(key);
  if ((head.contentLength ?? 0) !== buf.length) {
    throw new Error("storage verification failed");
  }

  await prisma.resourceFile.create({
    data: {
      resourceId: paper.id,
      storageKey: key,
      mimeType: "application/pdf",
      sizeBytes: buf.length,
      originalName: "bus-101-sem1-exam.pdf",
      isCurrent: true,
    },
  });
  console.log(`attached ${buf.length}-byte PDF to: ${paper.title}`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

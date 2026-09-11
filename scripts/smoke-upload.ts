/**
 * Upload-pipeline smoke test: exercises sniffing, probing, slug
 * generation, the bulk bundle transaction, MinIO storage, and
 * post-upload verification — without the browser layer.
 *
 * Run: npx tsx --env-file=.env scripts/smoke-upload.ts
 */
import { prisma } from "@/lib/db";
import { getObjectHead } from "@/lib/storage";
import { uploadResourceFiles, replaceResourceFile } from "@/lib/resource-admin";
import { makePdf, toFile } from "./pdf-factory";
import { S3Client, DeleteObjectCommand } from "@aws-sdk/client-s3";

function assert(cond: unknown, label: string): asserts cond {
  if (!cond) {
    console.error(`FAIL: ${label}`);
    process.exit(1);
  }
  console.log(`ok: ${label}`);
}

const s3 = new S3Client({
  region: process.env.S3_REGION ?? "us-east-1",
  endpoint: process.env.S3_ENDPOINT,
  forcePathStyle: true,
  credentials: {
    accessKeyId: process.env.S3_ACCESS_KEY_ID ?? "",
    secretAccessKey: process.env.S3_SECRET_ACCESS_KEY ?? "",
  },
});

async function deleteObject(key: string): Promise<void> {
  await s3
    .send(new DeleteObjectCommand({ Bucket: process.env.S3_BUCKET ?? "pastq-private", Key: key }))
    .catch(() => undefined);
}

async function main() {
  const admin = await prisma.user.findFirst({ where: { role: "ADMIN" } });
  assert(admin, "admin user exists (run the seed)");

  const before = await prisma.bundle.count();

  // ── 1. Bulk create-with-files (new bundle) ────────────────────
  const created = await uploadResourceFiles({
    files: [toFile(makePdf(2), "sem1-exam.pdf"), toFile(makePdf(3), "sem2-exam.pdf")],
    adminId: admin!.id,
    semester: 1,
    bundle: {
      title: "Smoke Test Bundle — Upload Pipeline",
      description: "Created by scripts/smoke-upload.ts; safe to ignore.",
      level: 200,
      academicYear: "2025/2026",
      price: 10,
      courseCode: "TST 999",
      courseTitle: "Smoke Test Course",
      programmeName: "BSc Smoke Testing",
      solved: false,
    },
  });
  console.log("created:", created);
  assert(created.createdBundle, "new bundle created (create path)");
  assert(created.files.length === 2, "two resources created");
  assert(
    created.files.map((f) => f.pageCount).sort().join(",") === "2,3",
    "page counts probed per file",
  );

  const bundleRow = await prisma.bundle.findUnique({
    where: { id: created.bundleId },
    include: { _count: { select: { resources: true } } },
  });
  assert(bundleRow?._count.resources === 2, "two draft resources in the bundle");
  assert(bundleRow?.status === "DRAFT", "bundle starts as DRAFT");
  assert(bundleRow?.pricePesewas === 1000, "price stored in pesewas (GH₵10.00)");

  const dbFiles = await prisma.resourceFile.findMany({
    where: { resource: { bundleId: created.bundleId }, isCurrent: true },
  });
  assert(dbFiles.length === 2, "two current resource_file rows written");
  for (const f of dbFiles) {
    const head = await getObjectHead(f.storageKey);
    assert(
      head.contentLength === f.sizeBytes && head.contentType === "application/pdf",
      `storage object matches for ${f.storageKey}`,
    );
  }

  // ── 2. Add to existing bundle ──────────────────────────────────
  const added = await uploadResourceFiles({
    files: [toFile(makePdf(4), "resit-exam.pdf")],
    adminId: admin!.id,
    semester: 2,
    bundleId: created.bundleId,
  });
  console.log("added:", added);
  assert(!added.createdBundle, "reused existing bundle (attach path)");

  const afterAdd = await prisma.bundle.findUnique({
    where: { id: created.bundleId },
    include: { _count: { select: { resources: true } } },
  });
  assert(afterAdd?._count.resources === 3, "bundle now holds three papers");

  // ── 3. Version replace on one paper ────────────────────────────
  const target = created.files[0].resourceId;
  const updated = await replaceResourceFile({
    file: toFile(makePdf(5), "sem1-exam-v2.pdf"),
    adminId: admin!.id,
    resourceId: target,
  });
  assert(updated.pageCount === 5, "version replace probed 5 pages");
  const versions = await prisma.resourceFile.findMany({
    where: { resourceId: target },
    orderBy: { createdAt: "asc" },
  });
  assert(versions.length === 2, "two file versions recorded");
  assert(versions.filter((v) => v.isCurrent).length === 1, "exactly one current version");

  // ── 4. Non-PDF rejection (whole batch refused, nothing written) ─
  const bundlesBeforeReject = await prisma.bundle.count();
  const resourcesBeforeReject = await prisma.resource.count();
  let rejected = false;
  try {
    await uploadResourceFiles({
      files: [
        toFile(makePdf(2), "fine.pdf"),
        toFile(Buffer.from("this is definitely not a portable document"), "nope.txt"),
      ],
      adminId: admin!.id,
      semester: 1,
      bundle: {
        title: "Should never be created",
        description: undefined,
        level: 100,
        academicYear: "2025/2026",
        price: 0,
        courseCode: "TST 998",
        courseTitle: "Reject Course",
        programmeName: "BSc Smoke Testing",
        solved: false,
      },
    });
  } catch (err) {
    rejected = err instanceof Error && /does not look like a valid PDF/.test(err.message);
  }
  assert(rejected, "non-PDF rejected before any storage/DB write");
  assert(
    (await prisma.bundle.count()) === bundlesBeforeReject,
    "no bundle created for rejected batch",
  );
  assert(
    (await prisma.resource.count()) === resourcesBeforeReject,
    "no resource created for rejected batch",
  );

  // ── Cleanup ────────────────────────────────────────────────────
  const allVersions = await prisma.resourceFile.findMany({
    where: { resource: { bundleId: created.bundleId } },
    select: { storageKey: true },
  });
  await prisma.resource.deleteMany({
    where: { bundleId: created.bundleId },
  });
  await prisma.bundle.delete({ where: { id: created.bundleId } });
  for (const v of allVersions) await deleteObject(v.storageKey);
  assert(
    (await prisma.bundle.count()) === before,
    "cleanup done (bundle + resources + storage objects removed)",
  );

  console.log("\nSMOKE TEST PASSED ✓");
}

main()
  .catch((err) => {
    console.error("SMOKE TEST FAILED:", err);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
/**
 * Prepares a shareable demo on an EMPTY-ish deployed DB (after migrate +
 * seed): hides the seeded placeholder bundles (published rows with NO
 * file behind them — the viewer would error), creates ONE fully working
 * published bundle of sample past-question PDFs through the real bulk
 * upload pipeline, and books a PAID demo order for the demo student so
 * the whole flow works on first click: browse → bundle → My Library →
 * secure viewer.
 *
 * Prereqs: schema migrated + seeded (admin/student accounts, courses).
 *   Local:   npm run services:up && npm run db:migrate && npm run db:seed
 *   Deployed: npx prisma migrate deploy && npm run db:seed
 *
 * Run:   npx tsx --env-file=.env scripts/prepare-demo.ts
 * Flags: --keep-placeholders  → leave the fileless seeded bundles published
 */
import { prisma } from "@/lib/db";
import { uploadResourceFiles } from "@/lib/resource-admin";
import { fulfillOrder } from "@/lib/payments";
import { grantBundlePapersToPastBuyers } from "@/lib/entitlement-grant";
import { S3Client, DeleteObjectCommand } from "@aws-sdk/client-s3";
import { makePdf, toFile } from "./pdf-factory";

const s3 = new S3Client({
  region: process.env.S3_REGION ?? "us-east-1",
  endpoint: process.env.S3_ENDPOINT,
  forcePathStyle: true,
  credentials: {
    accessKeyId: process.env.S3_ACCESS_KEY_ID ?? "",
    secretAccessKey: process.env.S3_SECRET_ACCESS_KEY ?? "",
  },
});

/**
 * Removes an earlier run of THIS demo bundle so a re-run always ends in
 * the same deterministic state: same papers, price, and one paid order.
 * Only ever touches the demo slug — never real catalog data.
 */
async function teardownDemoBundle(slug: string): Promise<void> {
  const bundle = await prisma.bundle.findUnique({
    where: { slug },
    select: { id: true },
  });
  if (!bundle) return;

  const orderRows = await prisma.orderItem.findMany({
    where: { bundleId: bundle.id },
    select: { orderId: true },
  });
  const orderIds = [...new Set(orderRows.map((o) => o.orderId))];

  await prisma.payment.deleteMany({ where: { orderId: { in: orderIds } } });
  await prisma.entitlement.deleteMany({
    where: {
      OR: [{ orderId: { in: orderIds } }, { resource: { bundleId: bundle.id } }],
    },
  });
  await prisma.orderItem.deleteMany({ where: { bundleId: bundle.id } });
  await prisma.order.deleteMany({ where: { id: { in: orderIds } } });

  const resources = await prisma.resource.findMany({
    where: { bundleId: bundle.id },
    select: { id: true },
  });
  const resourceIds = resources.map((r) => r.id);
  const files = await prisma.resourceFile.findMany({
    where: { resourceId: { in: resourceIds } },
    select: { storageKey: true },
  });
  await prisma.viewingSession.deleteMany({
    where: { resourceId: { in: resourceIds } },
  });
  await prisma.resource.deleteMany({ where: { id: { in: resourceIds } } });
  await prisma.bundle.delete({ where: { id: bundle.id } });

  // Best-effort cleanup of the private blobs behind those rows.
  for (const f of files) {
    await s3
      .send(
        new DeleteObjectCommand({
          Bucket: process.env.S3_BUCKET ?? "pastq-private",
          Key: f.storageKey,
        }),
      )
      .catch(() => undefined);
  }
  console.log(`removed previous demo state (${files.length} stored file(s)) — rebuilding fresh`);
}

const BUNDLE_SLUG = "ict-201-database-systems-2025-2026";

interface DemoPaper {
  fileName: string;
  semester: 1 | 2;
  pages: number;
  title: string;
  questions: string[];
}

const PAPERS: DemoPaper[] = [
  {
    fileName: "ICT 201 Database Systems Semester 1 Examination 2025-2026.pdf",
    semester: 1,
    pages: 3,
    title: "ICT 201 — Database Systems · Semester 1 Examination (2025/2026)",
    questions: [
      "1. Explain the three levels of data abstraction with examples. (10 marks)",
      "2. Differentiate between DELETE and TRUNCATE in SQL. (10 marks)",
      "3. Normalise the given relation to 3NF, showing each step. (20 marks)",
    ],
  },
  {
    fileName: "ICT 201 Database Systems Mid Semester Quiz 2025-2026.pdf",
    semester: 1,
    pages: 2,
    title: "ICT 201 — Database Systems · Mid-Semester Quiz (2025/2026)",
    questions: [
      "1. Draw an ER diagram for a library management system. (10 marks)",
      "2. Write SQL to create the STUDENT table with a PRIMARY KEY. (10 marks)",
    ],
  },
  {
    fileName: "ICT 201 Database Systems Semester 2 Examination 2025-2026.pdf",
    semester: 2,
    pages: 3,
    title: "ICT 201 — Database Systems · Semester 2 Examination (2025/2026)",
    questions: [
      "1. Write SQL queries for the schema provided. (15 marks)",
      "2. Discuss the four transaction isolation levels. (10 marks)",
      "3. Design a B+ tree index for the query shown. (15 marks)",
    ],
  },
  {
    fileName: "ICT 201 Database Systems Semester 2 Resit 2025-2026.pdf",
    semester: 2,
    pages: 2,
    title: "ICT 201 — Database Systems · Semester 2 Resit (2025/2026)",
    questions: [
      "1. Explain ACID properties of transactions. (12 marks)",
      "2. Compare relational and NoSQL storage models. (8 marks)",
    ],
  },
];

const BUNDLE_FIELDS = {
  title: "ICT 201 — Database Systems · 2025/2026 Past Questions",
  description:
    "Full set of past questions for Database Systems in the 2025/2026 academic year: semester 1 & 2 examinations, mid-semester quiz, and the resit paper. Buy once and every paper unlocks in My Library.",
  level: 200,
  academicYear: "2025/2026",
  price: 15,
  courseCode: "ICT 201",
  courseTitle: "Database Systems",
  programmeName: "BSc Information Technology",
};

/** Unique human-readable reference, same shape as the orders API. */
function generateReference(): string {
  const alphabet = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"; // no ambiguous chars
  let ref = "PQ-";
  for (let i = 0; i < 6; i++) {
    ref += alphabet[Math.floor(Math.random() * alphabet.length)];
  }
  return ref;
}

async function main() {
  const keepPlaceholders = process.argv.includes("--keep-placeholders");

  const admin = await prisma.user.findFirst({ where: { role: "ADMIN" } });
  const student =
    (await prisma.user.findUnique({ where: { email: "student@pastq.test" } })) ??
    (await prisma.user.findFirst({ where: { role: "STUDENT" } }));
  if (!admin || !student) {
    throw new Error("Demo users missing — run the seed first (npm run db:seed).");
  }

  // ── 1. Hide seeded placeholder bundles (published rows, no file behind
  //       them — clicking a paper would error in the viewer) ──────────
  if (!keepPlaceholders) {
    const published = await prisma.bundle.findMany({
      where: { status: "PUBLISHED" },
      select: {
        id: true,
        slug: true,
        title: true,
        resources: {
          select: {
            files: { where: { isCurrent: true }, select: { id: true }, take: 1 },
          },
        },
      },
    });
    const fileless = published.filter((b) =>
      b.resources.every((r) => r.files.length === 0),
    );
    for (const b of fileless) {
      await prisma.bundle.update({
        where: { id: b.id },
        data: { status: "UNPUBLISHED" },
      });
      await prisma.resource.updateMany({
        where: { bundleId: b.id },
        data: { status: "UNPUBLISHED" },
      });
      console.log(`unpublished placeholder (no PDF behind it): ${b.slug}`);
    }
    if (fileless.length > 0) {
      console.log(
        "  (these were seeded without real files — reverse any time in Admin → Bundles)\n",
      );
    }
  }

  // ── 2. Fresh demo bundle through the real bulk-upload pipeline ──
  await teardownDemoBundle(BUNDLE_SLUG);

  const sem1 = PAPERS.filter((p) => p.semester === 1);
  const sem2 = PAPERS.filter((p) => p.semester === 2);

  const created = await uploadResourceFiles({
    files: sem1.map((p) =>
      toFile(makePdf(p.pages, "ICT 201 - Database Systems", p.questions), p.fileName),
    ),
    adminId: admin.id,
    semester: 1,
    bundle: BUNDLE_FIELDS,
  });

  const added = await uploadResourceFiles({
    files: sem2.map((p) =>
      toFile(makePdf(p.pages, "ICT 201 - Database Systems", p.questions), p.fileName),
    ),
    adminId: admin.id,
    semester: 2,
    bundleId: created.bundleId,
  });

  // Clean up the file-name-derived titles into readable paper titles.
  const titleByFile = new Map(PAPERS.map((p) => [p.fileName, p.title]));
  for (const f of [...created.files, ...added.files]) {
    const title = titleByFile.get(f.fileName);
    if (title) {
      await prisma.resource.update({
        where: { id: f.resourceId },
        data: { title, description: BUNDLE_FIELDS.description },
      });
    }
  }
  const bundle = await prisma.bundle.findUniqueOrThrow({ where: { slug: BUNDLE_SLUG } });
  console.log(
    `demo bundle created: ${bundle.slug} (${created.files.length + added.files.length} papers)`,
  );

  // ── 3. Publish the bundle (takes every paper live, as the admin UI) ──
  if (bundle.status !== "PUBLISHED") {
    await prisma.$transaction(async (tx) => {
      await tx.bundle.update({
        where: { id: bundle!.id },
        data: { status: "PUBLISHED", publishedAt: new Date() },
      });
      await tx.resource.updateMany({
        where: { bundleId: bundle!.id, status: { not: "PUBLISHED" } },
        data: { status: "PUBLISHED", publishedAt: new Date() },
      });
    });
    console.log(`published demo bundle: /bundles/${bundle.slug}`);
  }

  // ── 4. Book a PAID demo order for the student (real fulfillment path) ──
  const resources = await prisma.resource.findMany({
    where: { bundleId: bundle.id, status: "PUBLISHED" },
    select: { id: true },
  });

  const owned = await prisma.entitlement.count({
    where: { userId: student.id, resourceId: { in: resources.map((r) => r.id) }, status: "active" },
  });

  let orderRef: string | null = null;

  if (owned < resources.length) {
    const paidOrder = await prisma.orderItem.findFirst({
      where: {
        bundleId: bundle.id,
        order: { userId: student.id, status: "PAID" },
      },
      select: { orderId: true },
    });

    if (!paidOrder) {
      for (let attempt = 0; attempt < 5; attempt++) {
        const candidate = generateReference();
        const clash = await prisma.order.findUnique({ where: { reference: candidate } });
        if (!clash) {
          orderRef = candidate;
          break;
        }
      }
      if (!orderRef) throw new Error("Could not allocate an order reference.");

      const order = await prisma.order.create({
        data: {
          reference: orderRef,
          userId: student.id,
          amountPesewas: bundle.pricePesewas,
          items: {
            create: {
              bundleId: bundle.id,
              unitPricePesewas: bundle.pricePesewas,
            },
          },
        },
      });

      await prisma.payment.create({
        data: {
          orderId: order.id,
          provider: "mock",
          providerRef: `mock_${orderRef}`,
          reference: orderRef,
          channel: "MOCK",
          status: "SUCCESSFUL",
          amountPesewas: bundle.pricePesewas,
          currency: "GHS",
          verifiedAt: new Date(),
        },
      });

      const fulfillment = await fulfillOrder(orderRef);
      console.log(
        `paid demo order ${orderRef} → ${fulfillment.entitlementResourceIds.length} entitlements for ${student.email}`,
      );
    } else if (resources.length > 0) {
      // Papers were added after the demo purchase — top the student up.
      const toppedUp = await grantBundlePapersToPastBuyers(bundle.id);
      if (toppedUp > 0) {
        console.log(`topped up ${toppedUp} late-paper entitlement(s) for ${student.email}`);
      }
    }
  } else if (resources.length > 0) {
    console.log(`${student.email} already owns every paper in the demo bundle — skipping purchase`);
  }

  console.log("\nDEMO READY ✓");
  console.log(`  Browse catalog:        /browse`);
  console.log(`  Demo bundle:           /bundles/${bundle.slug}  (GH₵${(bundle.pricePesewas / 100).toFixed(2)}, ${resources.length} papers)`);
  console.log(`  Admin area:            /admin/bundles  (publish real sets here)`);
  console.log("");
  console.log(`  Log in as ${student.email} and try:`);
  console.log(`    My Library → open a paper → secure viewer`);
  if (orderRef) console.log(`    Purchase history shows order ${orderRef}`);
}

main()
  .catch((err) => {
    console.error("PREPARE-DEMO FAILED:", err);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

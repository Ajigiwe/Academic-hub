/**
 * One-off migration: replace the old award-type "programmes" (BTECH /
 * Dip Tech / HND) with the real programme tracks (Procurement and Supply,
 * Marketing, Accounting, Secretaryship and Management).
 *
 * Safe because the catalog is demo-only data: 0 orders, 0 entitlements.
 * Users are preserved. Run `npm run db:seed` afterwards to rebuild the
 * demo catalog under the new programmes.
 */
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

const NEW_PROGRAMMES = [
  { name: "Procurement and Supply", slug: "procurement-and-supply" },
  { name: "Marketing", slug: "marketing" },
  { name: "Accounting", slug: "accounting" },
  { name: "Secretaryship and Management", slug: "secretaryship-and-management" },
];

const OLD_SLUGS = ["btech", "dip-tech", "hnd"];

async function main() {
  // Guard: refuse to run against a database with real purchases.
  const [orders, entitlements] = await Promise.all([
    prisma.order.count(),
    prisma.entitlement.count(),
  ]);
  if (orders > 0 || entitlements > 0) {
    throw new Error(
      `Refusing to migrate: ${orders} orders / ${entitlements} entitlements exist. This script only runs against demo data.`,
    );
  }

  // 1. Wipe demo catalog (order matters: resources reference bundles).
  const res = await prisma.resource.deleteMany({});
  const bundles = await prisma.bundle.deleteMany({});
  console.log(`deleted ${res.count} resources, ${bundles.count} bundles`);

  // 2. Remove the old award-type programmes and their settings rows.
  const progs = await prisma.programme.deleteMany({
    where: { slug: { in: OLD_SLUGS } },
  });
  const settings = await prisma.setting.deleteMany({
    where: { key: { in: OLD_SLUGS.map((s) => `programme.${s}.enabled`) } },
  });
  console.log(`deleted ${progs.count} old programmes, ${settings.count} settings rows`);

  // 3. Create the four real programmes.
  for (const p of NEW_PROGRAMMES) {
    await prisma.programme.upsert({
      where: { slug: p.slug },
      update: { name: p.name },
      create: p,
    });
    await prisma.setting.upsert({
      where: { key: `programme.${p.slug}.enabled` },
      update: { value: "true" },
      create: { key: `programme.${p.slug}.enabled`, value: "true" },
    });
  }
  console.log(`created ${NEW_PROGRAMMES.length} programmes (all enabled)`);

  console.log("Migration complete — now run: npm run db:seed");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

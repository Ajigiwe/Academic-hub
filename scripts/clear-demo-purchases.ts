/**
 * One-off cleanup for DEMO databases: remove purchase records created by
 * mock/test payments so demo catalog rows become deletable again (the
 * delete actions protect anything an order or entitlement references).
 *
 * Guards:
 *  1. Refuses to run if ANY order has a real (non-mock) payment — this
 *     script is only for mock-checkout demo data, never real revenue.
 *  2. Dry-run by default: prints what it would delete and exits unless
 *     `--yes` is passed.
 *
 * Run against the target database (the one DATABASE_URL points at):
 *   npx tsx scripts/clear-demo-purchases.ts          # dry run
 *   npx tsx scripts/clear-demo-purchases.ts --yes    # execute
 *
 * Afterwards the delete actions unlock, or re-converge the demo catalog:
 *   npx tsx scripts/migrate-programmes.ts && npm run db:seed
 */
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();
const confirmed = process.argv.includes("--yes");

async function main() {
  const orders = await prisma.order.findMany({
    select: {
      id: true,
      reference: true,
      status: true,
      payments: { select: { provider: true, channel: true, status: true } },
    },
  });

  if (orders.length === 0) {
    console.log("No orders found — nothing to clear.");
    return;
  }

  const real = orders.filter((o) => o.payments.some((p) => p.provider !== "mock"));
  if (real.length > 0) {
    throw new Error(
      `Refusing: ${real.length} order(s) have non-mock payments ` +
        `(${real.map((o) => o.reference).join(", ")}). ` +
        "This script only clears mock-checkout demo data.",
    );
  }

  const entitlements = await prisma.entitlement.count();
  const payments = await prisma.payment.count();

  console.log(
    `Found ${orders.length} mock order(s), ${payments} payment(s), ${entitlements} entitlement(s).`,
  );

  if (!confirmed) {
    console.log("Dry run — nothing deleted. Re-run with --yes to execute.");
    return;
  }

  // Entitlements and payments restrict order deletion, so they go first;
  // order items cascade with their order.
  const ent = await prisma.entitlement.deleteMany({});
  const pay = await prisma.payment.deleteMany({});
  const ord = await prisma.order.deleteMany({});
  console.log(
    `Deleted ${ord.count} order(s), ${pay.count} payment(s), ${ent.count} entitlement(s).`,
  );

  console.log(
    "Done. Delete buttons are now unlocked, or run " +
      "`npx tsx scripts/migrate-programmes.ts && npm run db:seed` " +
      "to converge the demo catalog on the current seed manifest.",
  );
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

/**
 * One-off cleanup for DEMO databases: remove purchase records created by
 * mock/test payments so demo catalog rows become deletable again (the
 * delete actions protect anything an order or entitlement references).
 *
 * Guards:
 *  1. Only mock-provider orders are deletable implicitly. Orders with
 *     real (e.g. Paystack) payments are refused UNLESS their references
 *     are passed explicitly via --ref (each an informed confirmation):
 *       --ref=PQ-ABC123 --ref=PQ-DEF456
 *  2. Dry-run by default: prints what it would delete and exits unless
 *     `--yes` is passed.
 *
 * Run against the target database (the one DATABASE_URL points at):
 *   npx tsx scripts/clear-demo-purchases.ts          # dry run
 *   npx tsx scripts/clear-demo-purchases.ts --yes    # execute
 *   npx tsx scripts/clear-demo-purchases.ts --yes \
 *     --ref=PQ-X9K3DV --ref=PQ-AY4SSM                # include test sales
 *
 * Afterwards the delete actions unlock, or re-converge the demo catalog:
 *   npx tsx scripts/migrate-programmes.ts && npm run db:seed
 */
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();
const confirmed = process.argv.includes("--yes");

async function main() {
  const wantedRefs = process.argv
    .filter((a) => a.startsWith("--ref="))
    .map((a) => a.slice("--ref=".length))
    .filter(Boolean);

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

  // Orders with real payments are only included when explicitly listed.
  const target = orders.filter(
    (o) =>
      o.payments.every((p) => p.provider === "mock") ||
      wantedRefs.includes(o.reference),
  );
  const refused = orders.filter((o) => !target.includes(o));
  if (refused.length > 0) {
    console.log(
      `Skipping ${refused.length} order(s) with non-mock payments not listed via --ref: ` +
        refused.map((o) => o.reference).join(", "),
    );
  }
  const missing = wantedRefs.filter(
    (r) => !orders.some((o) => o.reference === r),
  );
  if (missing.length > 0) {
    console.log(`Note: --ref not found (ignored): ${missing.join(", ")}`);
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
  // order items cascade with their order. Scoped to the selected orders.
  const targetIds = target.map((o) => o.id);
  const ent = await prisma.entitlement.deleteMany({
    where: { orderId: { in: targetIds } },
  });
  const pay = await prisma.payment.deleteMany({
    where: { orderId: { in: targetIds } },
  });
  const ord = await prisma.order.deleteMany({
    where: { id: { in: targetIds } },
  });
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

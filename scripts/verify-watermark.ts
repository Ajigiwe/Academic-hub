/**
 * Proves the watermark is burned into the PIXELS: renders the same page
 * for two identities and diffs the bytes.
 * Run: npx tsx --env-file=.env scripts/verify-watermark.ts
 */
import { prisma } from "@/lib/db";
import { getObjectBuffer } from "@/lib/storage";
import { renderPageToPng } from "@/lib/pdf-render";
import { createCanvas, loadImage } from "@napi-rs/canvas";

async function main() {
  const file = await prisma.resourceFile.findFirst({
    where: { isCurrent: true },
    orderBy: { createdAt: "desc" },
    select: { storageKey: true },
  });
  if (!file) throw new Error("no file");
  const bytes = await getObjectBuffer(file.storageKey);

  const a = await renderPageToPng(bytes, 1, {
    name: "Ama Mensah",
    accountId: "AAA111",
    orderRef: "PQ-AAA",
  });
  const b = await renderPageToPng(bytes, 1, {
    name: "Kofi Boateng",
    accountId: "BBB222",
    orderRef: "PQ-BBB",
  });

  console.log("sizes:", a.png.length, b.png.length);
  if (a.png.equals(b.png)) throw new Error("outputs identical — watermark NOT burned in");

  // Decode both and count differing pixels in a center band.
  const ia = await loadImage(a.png);
  const ib = await loadImage(b.png);
  const ca = createCanvas(ia.width, ia.height);
  const cb = createCanvas(ib.width, ib.height);
  ca.getContext("2d").drawImage(ia, 0, 0);
  cb.getContext("2d").drawImage(ib, 0, 0);
  const da = ca.getContext("2d").getImageData(0, Math.floor(ia.height * 0.4), ia.width, Math.floor(ia.height * 0.2)).data;
  const db = cb.getContext("2d").getImageData(0, Math.floor(ib.height * 0.4), ib.width, Math.floor(ib.height * 0.2)).data;

  let diff = 0;
  for (let i = 0; i < da.length; i += 4) {
    if (Math.abs(da[i] - db[i]) > 8) diff++;
  }
  const pct = ((diff / (da.length / 4)) * 100).toFixed(2);
  console.log(`differing pixels in center band: ${diff} (${pct}%)`);
  if (diff < 500) throw new Error("watermark delta too small");

  console.log("\nWATERMARK VERIFIED IN PIXELS ✓");
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

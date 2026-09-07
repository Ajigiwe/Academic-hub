/**
 * Creates one realistic DRAFT bundle (with two papers) through the real
 * bulk-upload pipeline, for admin-UI testing and as a demo catalog item.
 *
 * Run: npx tsx --env-file=.env scripts/make-demo-draft.ts
 */
import { prisma } from "@/lib/db";
import { uploadResourceFiles } from "@/lib/resource-admin";

function makePdf(pageCount: number, heading: string, lines: string[]): Buffer {
  const enc = (s: string) => Buffer.from(s, "latin1");
  const chunks: Buffer[] = [];
  let offset = 0;
  const offsets: number[] = [];
  const push = (s: string) => {
    const b = enc(s);
    chunks.push(b);
    offset += b.length;
  };
  const beginObj = (n: number) => {
    offsets[n] = offset;
    push(`${n} 0 obj\n`);
  };

  push("%PDF-1.4\n");
  const kids = Array.from({ length: pageCount }, (_, i) => `${3 + i * 2} 0 R`).join(" ");
  const fontObjNum = 3 + pageCount * 2;

  beginObj(1);
  push(`<< /Type /Catalog /Pages 2 0 R >>\nendobj\n`);
  beginObj(2);
  push(`<< /Type /Pages /Kids [ ${kids} ] /Count ${pageCount} >>\nendobj\n`);

  for (let i = 0; i < pageCount; i++) {
    const contentNum = 4 + i * 2;
    beginObj(3 + i * 2);
    push(
      `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents ${contentNum} 0 R ` +
        `/Resources << /Font << /F1 ${fontObjNum} 0 R >> >> >>\nendobj\n`,
    );
    const pageLines = [
      heading,
      "End of Semester Examination - 2025/2026",
      "",
      `Page ${i + 1} of ${pageCount}`,
      "",
      "Answer ALL questions. Time allowed: 2 hours.",
      ...lines,
    ];
    const text = pageLines
      .map((l, idx) => `BT /F1 ${idx === 0 ? 18 : 12} Tf 72 ${720 - idx * 22} Td (${l}) Tj ET`)
      .join("\n");
    beginObj(contentNum);
    push(`<< /Length ${text.length} >>\nstream\n${text}\nendstream\nendobj\n`);
  }

  beginObj(fontObjNum);
  push(`<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n`);

  const xrefStart = offset;
  let xref = `xref\n0 ${fontObjNum + 1}\n0000000000 65535 f \n`;
  for (let n = 1; n <= fontObjNum; n++) {
    xref += `${String(offsets[n]).padStart(10, "0")} 00000 n \n`;
  }
  push(xref);
  push(
    `trailer\n<< /Size ${fontObjNum + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`,
  );
  return Buffer.concat(chunks);
}

/** File parts need a plain ArrayBuffer (not a Node Buffer view). */
function toFile(buf: Buffer, name: string): File {
  const ab = new ArrayBuffer(buf.length);
  new Uint8Array(ab).set(buf);
  return new File([ab], name, { type: "application/pdf" });
}

async function main() {
  const admin = await prisma.user.findFirstOrThrow({ where: { role: "ADMIN" } });

  const result = await uploadResourceFiles({
    files: [
      toFile(
        makePdf(2, "ICT 201 - Database Systems", [
          "1. Explain the three levels of data abstraction. (10 marks)",
          "2. Differentiate between DELETE and TRUNCATE. (10 marks)",
          "3. Normalise the relation below to 3NF. (20 marks)",
        ]),
        "ICT 201 Semester 1 Exam - 2025-2026.pdf",
      ),
      toFile(
        makePdf(2, "ICT 201 - Database Systems", [
          "1. Write SQL for the schema given. (15 marks)",
          "2. Discuss transaction isolation levels. (10 marks)",
          "3. Design an index for the query shown. (15 marks)",
        ]),
        "ICT 201 Semester 2 Exam - 2025-2026.pdf",
      ),
    ],
    adminId: admin.id,
    semester: 1,
    bundle: {
      title: "ICT 201 — Database Systems · 2025/2026 Past Questions",
      description:
        "Full end-of-semester exam papers for Database Systems, 2025/2026 academic year. DRAFT — publish the bundle when the set is complete.",
      level: 200,
      academicYear: "2025/2026",
      price: 25,
      courseCode: "ICT 201",
      courseTitle: "Database Systems",
      programmeName: "BSc Information Technology",
    },
  });
  console.log("draft bundle ready:", result);
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
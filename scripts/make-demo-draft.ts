/**
 * Creates one realistic DRAFT bundle (with two papers) through the real
 * bulk-upload pipeline, for admin-UI testing and as a demo catalog item.
 *
 * Run: npx tsx --env-file=.env scripts/make-demo-draft.ts
 */
import { prisma } from "@/lib/db";
import { uploadResourceFiles } from "@/lib/resource-admin";
import { makePdf, toFile } from "./pdf-factory";

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
      solved: false,
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
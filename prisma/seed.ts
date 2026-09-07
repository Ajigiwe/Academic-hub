import { PrismaClient, ResourceType } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

// Sensible defaults for the single-institution MVP (spec: fixed institution).
const PROGRAMMES = [
  { name: "BSc Information Technology", slug: "bsc-information-technology" },
  { name: "BSc Computer Science", slug: "bsc-computer-science" },
  { name: "BSc Business Administration", slug: "bsc-business-administration" },
  { name: "BSc Statistics", slug: "bsc-statistics" },
];

const COURSES = [
  { code: "ICT 201", title: "Database Systems", slug: "ict-201-database-systems" },
  { code: "ICT 401", title: "Advanced Database Systems", slug: "ict-401-advanced-database-systems" },
  { code: "CS 305", title: "Database Management", slug: "cs-305-database-management" },
  { code: "STAT 202", title: "Statistics II", slug: "stat-202-statistics-ii" },
  { code: "BUS 101", title: "Introduction to Business", slug: "bus-101-introduction-to-business" },
];

// Bundles are the unit of sale — one per course + academic year, priced as a
// whole. Papers inside a bundle are never sold individually.
const BUNDLES = [
  {
    slug: "ict-201-database-systems-2024-2025",
    title: "ICT 201 — Database Systems · 2024/2025 Past Questions",
    course: "ICT 201",
    programme: "bsc-information-technology",
    level: 200,
    academicYear: "2024/2025",
    pricePesewas: 2500,
    description:
      "Complete past questions covering major topics in Database Systems: ER modelling, normalisation, SQL, transactions, and indexing. Includes both semester papers.",
    papers: [
      {
        slug: "ict-201-database-systems-2024-2025-sem1-exam",
        title: "ICT 201 — Database Systems · Semester 1 Exam",
        semester: 1,
        pageCount: 28,
      },
      {
        slug: "ict-201-database-systems-2024-2025-sem2-exam",
        title: "ICT 201 — Database Systems · Semester 2 Exam",
        semester: 2,
        pageCount: 24,
      },
    ],
  },
  {
    slug: "stat-202-statistics-ii-2023-2024",
    title: "STAT 202 — Statistics II · 2023/2024 Past Questions",
    course: "STAT 202",
    programme: "bsc-statistics",
    level: 200,
    academicYear: "2023/2024",
    pricePesewas: 2000,
    description:
      "Past questions on hypothesis testing, regression, ANOVA, and probability distributions — both semesters included.",
    papers: [
      {
        slug: "stat-202-statistics-ii-2023-2024-sem2-exam",
        title: "STAT 202 — Statistics II · Semester 2 Exam",
        semester: 2,
        pageCount: 22,
      },
    ],
  },
  {
    slug: "ict-401-advanced-database-systems-2024-2025",
    title: "ICT 401 — Advanced Database Systems · 2024/2025 Past Questions",
    course: "ICT 401",
    programme: "bsc-information-technology",
    level: 400,
    academicYear: "2024/2025",
    pricePesewas: 3000,
    description:
      "Distributed databases, query optimisation, concurrency control, and NoSQL storage models — full exam coverage.",
    papers: [
      {
        slug: "ict-401-advanced-database-systems-2024-2025-sem2-exam",
        title: "ICT 401 — Advanced Database Systems · Semester 2 Exam",
        semester: 2,
        pageCount: 31,
      },
    ],
  },
  {
    slug: "bus-101-introduction-to-business-2025-2026",
    title: "BUS 101 — Introduction to Business · 2025/2026 Past Questions",
    course: "BUS 101",
    programme: "bsc-business-administration",
    level: 100,
    academicYear: "2025/2026",
    pricePesewas: 1500,
    description:
      "Business environment, forms of ownership, management functions, and marketing basics — the full first-year paper set.",
    papers: [
      {
        slug: "bus-101-introduction-to-business-2025-2026-sem1-exam",
        title: "BUS 101 — Introduction to Business · Semester 1 Exam",
        semester: 1,
        pageCount: 18,
      },
    ],
  },
];

async function main() {
  console.log("Seeding…");

  const adminPassword = await bcrypt.hash("Admin@12345", 12);
  const studentPassword = await bcrypt.hash("Student@123", 12);

  await prisma.user.upsert({
    where: { email: "admin@pastq.test" },
    update: {},
    create: {
      email: "admin@pastq.test",
      passwordHash: adminPassword,
      firstName: "Platform",
      lastName: "Admin",
      role: "ADMIN",
    },
  });

  await prisma.user.upsert({
    where: { email: "student@pastq.test" },
    update: {},
    create: {
      email: "student@pastq.test",
      passwordHash: studentPassword,
      firstName: "Ama",
      lastName: "Mensah",
      role: "STUDENT",
    },
  });

  for (const p of PROGRAMMES) {
    await prisma.programme.upsert({
      where: { slug: p.slug },
      update: {},
      create: p,
    });
  }

  for (const c of COURSES) {
    await prisma.course.upsert({
      where: { code: c.code },
      update: {},
      create: c,
    });
  }

  const programmes = await prisma.programme.findMany();
  const courses = await prisma.course.findMany();
  const programmeBySlug = new Map(programmes.map((p) => [p.slug, p.id]));
  const courseByCode = new Map(courses.map((c) => [c.code, c.id]));

  for (const b of BUNDLES) {
    const programmeId = programmeBySlug.get(b.programme);
    const courseId = courseByCode.get(b.course);
    if (!programmeId || !courseId) throw new Error(`Missing relation for ${b.slug}`);

    const existing = await prisma.bundle.findUnique({ where: { slug: b.slug } });
    if (existing) {
      console.log(`  bundle exists, skipping: ${b.slug}`);
      continue;
    }

    await prisma.bundle.create({
      data: {
        slug: b.slug,
        title: b.title,
        description: b.description,
        status: "PUBLISHED",
        level: b.level,
        academicYear: b.academicYear,
        pricePesewas: b.pricePesewas,
        publishedAt: new Date(),
        programmeId,
        courseId,
        resources: {
          create: b.papers.map((p) => ({
            slug: p.slug,
            title: p.title,
            description: b.description,
            type: ResourceType.PAST_QUESTION,
            status: "PUBLISHED",
            level: b.level,
            semester: p.semester,
            academicYear: b.academicYear,
            pageCount: p.pageCount,
            previewPages: 2,
            publishedAt: new Date(),
            programmeId,
            courseId,
          })),
        },
      },
    });
    console.log(`  bundle created: ${b.slug} (${b.papers.length} papers)`);
  }

  console.log("Seed complete.");
  console.log("  Admin login:   admin@pastq.test / Admin@12345");
  console.log("  Student login: student@pastq.test / Student@123");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
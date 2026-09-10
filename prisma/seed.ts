import { PrismaClient, ResourceType } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

// Sensible defaults for the single-institution MVP: the four programme
// (department) tracks, levels 100–400.
const PROGRAMMES = [
  { name: "Procurement and Supply", slug: "procurement-and-supply" },
  { name: "Marketing", slug: "marketing" },
  { name: "Accounting", slug: "accounting" },
  { name: "Secretaryship and Management", slug: "secretaryship-and-management" },
];

const COURSES = [
  { code: "ACC 102", title: "Introduction to Financial Accounting", slug: "acc-102-introduction-to-financial-accounting" },
  { code: "ACC 201", title: "Financial Reporting", slug: "acc-201-financial-reporting" },
  { code: "MKT 203", title: "Principles of Marketing", slug: "mkt-203-principles-of-marketing" },
  { code: "PSM 201", title: "Procurement Principles", slug: "psm-201-procurement-principles" },
  { code: "PSM 305", title: "Public Procurement", slug: "psm-305-public-procurement" },
  { code: "SMG 204", title: "Office Administration", slug: "smg-204-office-administration" },
];

// Bundles are the unit of sale — one per course + academic year, priced as a
// whole. Papers inside a bundle are never sold individually. Each bundle is
// tagged with its programme track and level; papers carry the semester.
const BUNDLES = [
  {
    slug: "acc-102-introduction-to-financial-accounting-2024-2025",
    title: "ACC 102 — Introduction to Financial Accounting · 2024/2025 Past Questions",
    course: "ACC 102",
    programme: "accounting",
    level: 100,
    academicYear: "2024/2025",
    pricePesewas: 1500,
    description:
      "Complete past questions covering the accounting cycle, journal entries, ledgers, trial balance, and basic financial statements — both semester papers included.",
    papers: [
      {
        slug: "acc-102-introduction-to-financial-accounting-2024-2025-sem1-exam",
        title: "ACC 102 — Introduction to Financial Accounting · Semester 1 Exam",
        semester: 1,
        pageCount: 20,
      },
      {
        slug: "acc-102-introduction-to-financial-accounting-2024-2025-sem2-exam",
        title: "ACC 102 — Introduction to Financial Accounting · Semester 2 Exam",
        semester: 2,
        pageCount: 18,
      },
    ],
  },
  {
    slug: "acc-201-financial-reporting-2024-2025",
    title: "ACC 201 — Financial Reporting · 2024/2025 Past Questions",
    course: "ACC 201",
    programme: "accounting",
    level: 200,
    academicYear: "2024/2025",
    pricePesewas: 2500,
    description:
      "Past questions on IFRS-based reporting, preparation of company financial statements, consolidation basics, and cash-flow statements.",
    papers: [
      {
        slug: "acc-201-financial-reporting-2024-2025-sem2-exam",
        title: "ACC 201 — Financial Reporting · Semester 2 Exam",
        semester: 2,
        pageCount: 26,
      },
    ],
  },
  {
    slug: "mkt-203-principles-of-marketing-2024-2025",
    title: "MKT 203 — Principles of Marketing · 2024/2025 Past Questions",
    course: "MKT 203",
    programme: "marketing",
    level: 200,
    academicYear: "2024/2025",
    pricePesewas: 2000,
    description:
      "The marketing mix, segmentation and targeting, consumer behaviour, branding, and digital marketing — both semesters included.",
    papers: [
      {
        slug: "mkt-203-principles-of-marketing-2024-2025-sem1-exam",
        title: "MKT 203 — Principles of Marketing · Semester 1 Exam",
        semester: 1,
        pageCount: 22,
      },
    ],
  },
  {
    slug: "psm-201-procurement-principles-2024-2025",
    title: "PSM 201 — Procurement Principles · 2024/2025 Past Questions",
    course: "PSM 201",
    programme: "procurement-and-supply",
    level: 200,
    academicYear: "2024/2025",
    pricePesewas: 2200,
    description:
      "Procurement cycle, sourcing and supplier selection, tendering, contract management, and ethics in procurement — both semesters included.",
    papers: [
      {
        slug: "psm-201-procurement-principles-2024-2025-sem1-exam",
        title: "PSM 201 — Procurement Principles · Semester 1 Exam",
        semester: 1,
        pageCount: 24,
      },
      {
        slug: "psm-201-procurement-principles-2024-2025-sem2-exam",
        title: "PSM 201 — Procurement Principles · Semester 2 Exam",
        semester: 2,
        pageCount: 21,
      },
    ],
  },
  {
    slug: "psm-305-public-procurement-2024-2025",
    title: "PSM 305 — Public Procurement · 2024/2025 Past Questions",
    course: "PSM 305",
    programme: "procurement-and-supply",
    level: 300,
    academicYear: "2024/2025",
    pricePesewas: 3000,
    description:
      "The Public Procurement Act, procurement planning, methods of procurement, evaluation criteria, and dispute resolution — full exam coverage.",
    papers: [
      {
        slug: "psm-305-public-procurement-2024-2025-sem1-exam",
        title: "PSM 305 — Public Procurement · Semester 1 Exam",
        semester: 1,
        pageCount: 28,
      },
    ],
  },
  {
    slug: "smg-204-office-administration-2023-2024",
    title: "SMG 204 — Office Administration · 2023/2024 Past Questions",
    course: "SMG 204",
    programme: "secretaryship-and-management",
    level: 200,
    academicYear: "2023/2024",
    pricePesewas: 1800,
    description:
      "Office procedures, records management, business communication, meeting documentation, and office technology questions.",
    papers: [
      {
        slug: "smg-204-office-administration-2023-2024-sem2-exam",
        title: "SMG 204 — Office Administration · Semester 2 Exam",
        semester: 2,
        pageCount: 19,
      },
    ],
  },
];

// Default visibility flags — only Accounting is live for now; the other
// tracks can be switched on later from Admin → Settings.
const PROGRAMME_SETTINGS = [
  { key: "programme.procurement-and-supply.enabled", value: "true" },
  { key: "programme.marketing.enabled", value: "true" },
  { key: "programme.accounting.enabled", value: "true" },
  { key: "programme.secretaryship-and-management.enabled", value: "true" },
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

  for (const s of PROGRAMME_SETTINGS) {
    await prisma.setting.upsert({
      where: { key: s.key },
      update: {},
      create: s,
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
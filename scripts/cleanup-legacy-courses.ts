/**
 * One-off cleanup: remove the legacy BTECH-era demo courses (BUS 101,
 * CS 305, ICT 201/205/401, STAT 202) left behind when the catalogue moved
 * to the department programmes. migrate-programmes.ts wiped bundles and
 * resources but kept the Course table, and the seed only upserts, so the
 * old rows linger in the admin course picker.
 *
 * Guarded: a legacy course that somehow has bundles or resources attached
 * (e.g. an admin uploaded new content under it) is kept and reported.
 */
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

const LEGACY_CODES = [
  "BUS 101",
  "CS 305",
  "ICT 201",
  "ICT 205",
  "ICT 401",
  "STAT 202",
];

async function main() {
  let deleted = 0;
  let kept = 0;

  for (const code of LEGACY_CODES) {
    const course = await prisma.course.findUnique({
      where: { code },
      select: {
        id: true,
        code: true,
        title: true,
        _count: { select: { bundles: true, resources: true } },
      },
    });
    if (!course) continue;

    if (course._count.bundles > 0 || course._count.resources > 0) {
      kept++;
      console.log(
        `kept (has content): ${course.code} — ${course.title} ` +
          `(${course._count.bundles} bundles, ${course._count.resources} resources)`,
      );
      continue;
    }

    await prisma.course.delete({ where: { id: course.id } });
    deleted++;
    console.log(`deleted: ${course.code} — ${course.title}`);
  }

  console.log(`Done. ${deleted} deleted, ${kept} kept, ${LEGACY_CODES.length - deleted - kept} not found.`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());

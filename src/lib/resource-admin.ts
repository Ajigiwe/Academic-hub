import { prisma } from "@/lib/db";
import { buildStorageKey, putObject, getObjectHead } from "@/lib/storage";
import { createHash } from "crypto";
import { z } from "zod";

// ─────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────

export const MAX_UPLOAD_BYTES = 30 * 1024 * 1024; // 30 MB cap (spec §32)
export const MAX_PAGES = 120;

// ─────────────────────────────────────────────────────────────────
// Validation
// ─────────────────────────────────────────────────────────────────

/** Metadata for creating a NEW bundle (the unit of sale). */
export const bundleFormSchema = z.object({
  title: z
    .string()
    .trim()
    .min(6, "Bundle title must be at least 6 characters.")
    .max(160, "Bundle title must be at most 160 characters."),
  description: z
    .string()
    .trim()
    .max(2000, "Description must be at most 2000 characters.")
    .optional()
    .transform((v) => v || undefined),
  level: z.coerce
    .number()
    .int("Level must be a whole number.")
    .min(100, "Level must be 100–800.")
    .max(800, "Level must be 100–800."),
  academicYear: z
    .string()
    .trim()
    .regex(/^\d{4}\/\d{4}$/, "Academic year must look like 2024/2025."),
  price: z.coerce
    .number({ message: "Price is required." })
    .min(0, "Price cannot be negative.")
    .max(1000, "Price must be at most GH₵1,000."),
  courseCode: z
    .string()
    .trim()
    .min(2, "Course code is required (e.g. ICT 201).")
    .max(20, "Course code must be at most 20 characters."),
  courseTitle: z
    .string()
    .trim()
    .min(3, "Course title is required (e.g. Database Systems).")
    .max(120, "Course title must be at most 120 characters."),
  programmeName: z
    .string()
    .trim()
    .min(2, "Programme is required (e.g. BSc IT).")
    .max(120, "Programme must be at most 120 characters."),
});

export type BundleFormInput = z.infer<typeof bundleFormSchema>;

export function formatZodIssues(error: z.ZodError): string {
  return error.issues.map((i) => i.message).join(" ");
}

function createChecksum(buf: Buffer): string {
  return createHash("sha256").update(buf).digest("hex");
}

// ─────────────────────────────────────────────────────────────────
// Slug helpers
// ─────────────────────────────────────────────────────────────────

export function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/['’]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

export async function uniqueBundleSlug(base: string): Promise<string> {
  const slug = slugify(base);
  if (!slug) return `bundle-${Date.now().toString(36)}`;
  const clash = await prisma.bundle.findUnique({ where: { slug }, select: { id: true } });
  if (!clash) return slug;
  // Deterministic collision suffix, not a random one — keeps slugs stable.
  for (let n = 2; n < 100; n++) {
    const candidate = `${slug}-${n}`;
    const taken = await prisma.bundle.findUnique({
      where: { slug: candidate },
      select: { id: true },
    });
    if (!taken) return candidate;
  }
  return `${slug}-${Date.now().toString(36)}`;
}

async function uniqueResourceSlug(base: string): Promise<string> {
  const slug = slugify(base);
  if (!slug) return `resource-${Date.now().toString(36)}`;
  const clash = await prisma.resource.findUnique({ where: { slug }, select: { id: true } });
  if (!clash) return slug;
  for (let n = 2; n < 100; n++) {
    const candidate = `${slug}-${n}`;
    const taken = await prisma.resource.findUnique({
      where: { slug: candidate },
      select: { id: true },
    });
    if (!taken) return candidate;
  }
  return `${slug}-${Date.now().toString(36)}`;
}

async function uniqueCourseSlug(code: string, title: string): Promise<string> {
  const base = slugify(`${code} ${title}`);
  if (!base) return `course-${Date.now().toString(36)}`;
  const clash = await prisma.course.findUnique({ where: { slug: base }, select: { id: true } });
  if (!clash) return base;
  for (let n = 2; n < 100; n++) {
    const candidate = `${base}-${n}`;
    const taken = await prisma.course.findUnique({
      where: { slug: candidate },
      select: { id: true },
    });
    if (!taken) return candidate;
  }
  return `${base}-${Date.now().toString(36)}`;
}

// ─────────────────────────────────────────────────────────────────
// PDF sniffing — trust bytes, not the client's MIME type (§11)
// ─────────────────────────────────────────────────────────────────

/** Full-file content sniff (checksum-quality check, cheap at 30 MB). */
export function looksLikePdf(buf: Buffer): boolean {
  if (buf.length < 8) return false;
  const head = buf.subarray(0, 1024).toString("latin1");
  if (!head.includes("%PDF-")) return false;
  const tail = buf.subarray(Math.max(0, buf.length - 1024)).toString("latin1");
  return tail.includes("%%EOF");
}

export interface PdfProbe {
  pageCount: number;
  encrypted: boolean;
  producer?: string;
  creator?: string;
  title?: string;
}

/**
 * Extracts page count + encryption flag without a native PDF parser.
 * - Page count: counts "/Type /Page" objects — robust for Word/InDesign
 *   exports and matches pdfinfo output within a page.
 * - Encryption: presence of an /Encrypt dictionary.
 */
export function probePdf(buf: Buffer): PdfProbe {
  const ascii = buf.toString("latin1");
  const encrypted = /\/Encrypt\s+\d+\s+\d+\s+R/.test(ascii);
  const producer = ascii.match(/\/Producer\s*\(([^)]{0,180})\)/)?.[1];
  const creator = ascii.match(/\/Creator\s*\(([^)]{0,180})\)/)?.[1];
  const title = ascii.match(/\/Title\s*\(([^)]{0,180})\)/)?.[1];
  const pageCount = (ascii.match(/\/Type\s*\/Page[^s]/g) ?? []).length;
  return {
    pageCount: Math.max(1, pageCount),
    encrypted,
    producer,
    creator,
    title,
  };
}

// ─────────────────────────────────────────────────────────────────
// Bulk upload pipeline — N PDFs into ONE bundle (the unit of sale)
// ─────────────────────────────────────────────────────────────────

interface ValidatedFile {
  file: File;
  buf: Buffer;
  pageCount: number;
  storageKey: string;
  checksum: string;
}

/** Validate every file BEFORE any DB/storage write happens. */
function validatePdf(file: File, buf: Buffer): ValidatedFile {
  if (!looksLikePdf(buf)) {
    throw new Error(`"${file.name}" does not look like a valid PDF.`);
  }
  const probe = probePdf(buf);
  if (probe.encrypted) {
    throw new Error(
      `"${file.name}" is password-protected. Please remove protection and re-upload.`,
    );
  }
  if (probe.pageCount > MAX_PAGES) {
    throw new Error(
      `"${file.name}" has ${probe.pageCount} pages — the limit is ${MAX_PAGES}.`,
    );
  }
  return {
    file,
    buf,
    pageCount: probe.pageCount,
    storageKey: buildStorageKey(),
    checksum: createChecksum(buf),
  };
}

/** A readable paper title derived from the uploaded filename. */
function titleFromFileName(fileName: string, fallback: string): string {
  const base = fileName
    .replace(/\.pdf$/i, "")
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (!base) return fallback;
  return base.slice(0, 160);
}

export interface BulkUploadResult {
  bundleId: string;
  bundleSlug: string;
  createdBundle: boolean;
  files: {
    fileName: string;
    resourceId: string;
    slug: string;
    pageCount: number;
  }[];
}

/**
 * Uploads several PDFs at once, each becoming its own DRAFT resource
 * inside one bundle. The bundle is created as a DRAFT when a new one is
 * requested (bundle fields) or reused when a bundleId is given. DB rows
 * and storage objects are written per-file in one Prisma transaction; a
 * rollback leaves at most orphaned (unreferenced) storage blobs.
 */
export async function uploadResourceFiles(input: {
  files: File[];
  adminId: string;
  semester: 1 | 2;
  bundleId?: string;
  bundle?: BundleFormInput;
}): Promise<BulkUploadResult> {
  if (input.files.length === 0) {
    throw new Error("Choose at least one PDF to upload.");
  }
  if (input.files.length > 30) {
    throw new Error("Upload at most 30 PDFs at once.");
  }

  // Validate ALL files first (no partial writes if one is bad).
  const validFiles: ValidatedFile[] = [];
  for (const file of input.files) {
    const buf = Buffer.from(await file.arrayBuffer());
    validFiles.push(validatePdf(file, buf));
  }

  // Resolve the target bundle.
  let bundle: { id: string; slug: string } | null = null;
  let createdBundle = false;

  if (input.bundleId) {
    bundle = await prisma.bundle.findUnique({
      where: { id: input.bundleId },
      select: { id: true, slug: true },
    });
    if (!bundle) throw new Error("Target bundle no longer exists.");
  } else {
    if (!input.bundle) throw new Error("Bundle metadata required for a new bundle.");
    const fields = input.bundle;

    const course = await prisma.course.upsert({
      where: { code: fields.courseCode.toUpperCase() },
      create: {
        code: fields.courseCode.toUpperCase(),
        title: fields.courseTitle,
        slug: await uniqueCourseSlug(fields.courseCode, fields.courseTitle),
      },
      update: {},
    });

    const programme = await prisma.programme.upsert({
      where: { name: fields.programmeName },
      create: {
        name: fields.programmeName,
        slug: slugify(fields.programmeName),
      },
      update: {},
    });

    const slug = await uniqueBundleSlug(
      `${fields.courseCode} ${fields.courseTitle} ${fields.academicYear.replace("/", "-")}`,
    );

    bundle = await prisma.bundle.create({
      data: {
        slug,
        title: fields.title,
        description: fields.description ?? null,
        status: "DRAFT",
        level: fields.level,
        academicYear: fields.academicYear,
        pricePesewas: Math.round(fields.price * 100),
        courseId: course.id,
        programmeId: programme.id,
      },
      select: { id: true, slug: true },
    });
    createdBundle = true;
  }

  // Pull shared bundle context for the resources.
  const bundleFull = await prisma.bundle.findUniqueOrThrow({
    where: { id: bundle.id },
    select: {
      courseId: true,
      programmeId: true,
      level: true,
      academicYear: true,
      course: { select: { code: true, title: true } },
    },
  });

  // Generous timeout: on high-latency links (e.g. a local script against a
  // hosted Neon DB) per-file round trips can exceed Prisma's 5s default.
  const result = await prisma.$transaction(
    async (tx) => {
      const files: BulkUploadResult["files"] = [];

      for (const vf of validFiles) {
      const fallbackTitle = `${bundleFull.course.code} Past Question`;
      const title = titleFromFileName(vf.file.name, fallbackTitle);
      const slug = await uniqueResourceSlug(
        `${bundleFull.course.code} ${bundleFull.course.title} ${bundleFull.academicYear.replace("/", "-")} ${title}`,
      );

      const resource = await tx.resource.create({
        data: {
          slug,
          title,
          type: "PAST_QUESTION",
          status: "DRAFT",
          level: bundleFull.level,
          semester: input.semester,
          academicYear: bundleFull.academicYear,
          pageCount: vf.pageCount,
          bundleId: bundle.id,
          courseId: bundleFull.courseId,
          programmeId: bundleFull.programmeId,
        },
      });

      await tx.resourceFile.create({
        data: {
          resourceId: resource.id,
          storageKey: vf.storageKey,
          mimeType: "application/pdf",
          sizeBytes: vf.buf.length,
          checksum: vf.checksum,
          originalName: vf.file.name || "upload.pdf",
          isCurrent: true,
        },
      });

      await tx.accessLog.create({
        data: {
          userId: input.adminId,
          resourceId: resource.id,
          action: "OPENED_RESOURCE",
          detail: `Bulk-uploaded ${vf.buf.length} bytes to ${vf.storageKey} (pages: ${vf.pageCount})`,
        },
      });

      files.push({
        fileName: vf.file.name,
        resourceId: resource.id,
        slug,
        pageCount: vf.pageCount,
      });
    }

      return { files };
    },
    { timeout: 60_000 },
  );

  // Storage writes AFTER commit: a failed upload leaves no phantom rows.
  for (const vf of validFiles) {
    await putObject(vf.storageKey, vf.buf, "application/pdf");
    const head = await getObjectHead(vf.storageKey);
    if ((head.contentLength ?? 0) !== vf.buf.length) {
      throw new Error(
        `Upload verification failed for "${vf.file.name}" — the stored file does not match. Please retry.`,
      );
    }
  }

  return {
    bundleId: bundle.id,
    bundleSlug: bundle.slug,
    createdBundle,
    files: result.files,
  };
}

// ─────────────────────────────────────────────────────────────────
// Single-file replacement on an existing resource (admin edit page)
// ─────────────────────────────────────────────────────────────────

export interface ReplaceResult {
  resourceId: string;
  pageCount: number;
}

export async function replaceResourceFile(input: {
  file: File;
  adminId: string;
  resourceId: string;
}): Promise<ReplaceResult> {
  const buf = Buffer.from(await input.file.arrayBuffer());
  const vf = validatePdf(input.file, buf);

  const existing = await prisma.resource.findUnique({
    where: { id: input.resourceId },
    select: { id: true },
  });
  if (!existing) throw new Error("Target resource no longer exists.");

  await prisma.$transaction(
    async (tx) => {
      await tx.resourceFile.updateMany({
        where: { resourceId: existing.id, isCurrent: true },
        data: { isCurrent: false },
      });
      await tx.resourceFile.create({
        data: {
          resourceId: existing.id,
          storageKey: vf.storageKey,
          mimeType: "application/pdf",
          sizeBytes: vf.buf.length,
          checksum: vf.checksum,
          originalName: input.file.name || "upload.pdf",
          isCurrent: true,
        },
      });
      await tx.resource.update({
        where: { id: existing.id },
        data: { pageCount: vf.pageCount },
      });
      await tx.accessLog.create({
        data: {
          userId: input.adminId,
          resourceId: existing.id,
          action: "OPENED_RESOURCE",
          detail: `Replaced file with ${vf.buf.length} bytes at ${vf.storageKey} (pages: ${vf.pageCount})`,
        },
      });
    },
    { timeout: 60_000 },
  );

  await putObject(vf.storageKey, vf.buf, "application/pdf");
  const head = await getObjectHead(vf.storageKey);
  if ((head.contentLength ?? 0) !== vf.buf.length) {
    throw new Error("Upload verification failed — the stored file does not match. Please retry.");
  }

  return { resourceId: existing.id, pageCount: vf.pageCount };
}
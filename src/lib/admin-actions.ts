"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import {
  uploadResourceFiles,
  replaceResourceFile,
  bundleFormSchema,
  formatZodIssues,
  MAX_UPLOAD_BYTES,
  slugify,
} from "@/lib/resource-admin";
import { grantBundlePapersToPastBuyers } from "@/lib/entitlement-grant";

/**
 * Guard for every admin action. Server actions are ordinary HTTP
 * endpoints — the admin UI is never the access control (§11).
 */
async function requireAdminUser() {
  const user = await getCurrentUser();
  if (!user || user.role !== "ADMIN") return null;
  return user;
}

export interface UploadState {
  ok?: boolean;
  message?: string;
  /** Slug of the bundle just created/added to — used to link to it. */
  bundleSlug?: string;
}

/**
 * Bulk upload: several PDFs at once, each becomes a DRAFT paper inside
 * one bundle (new or existing). Bundles are the unit of sale — students
 * pay once and unlock every paper inside.
 */
export async function uploadBundleFilesAction(
  _prev: UploadState,
  formData: FormData,
): Promise<UploadState> {
  const admin = await requireAdminUser();
  if (!admin) return { ok: false, message: "Admin access required." };

  const files = formData.getAll("files").filter((f): f is File => f instanceof File && f.size > 0);
  if (files.length === 0) {
    return { ok: false, message: "Choose at least one PDF file to upload." };
  }
  for (const file of files) {
    if (file.size > MAX_UPLOAD_BYTES) {
      return {
        ok: false,
        message: `"${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(1)} MB) — the limit is 30 MB.`,
      };
    }
  }

  const bundleId = String(formData.get("bundleId") ?? "").trim();
  const semesterRaw = Number(formData.get("semester"));
  if (semesterRaw !== 1 && semesterRaw !== 2) {
    return { ok: false, message: "Semester must be 1 or 2." };
  }

  let bundleFields: z.infer<typeof bundleFormSchema> | undefined;
  if (!bundleId) {
    const parsed = bundleFormSchema.safeParse({
      title: formData.get("title"),
      description: formData.get("description"),
      level: formData.get("level"),
      academicYear: formData.get("academicYear"),
      price: formData.get("price"),
      courseCode: formData.get("courseCode"),
      courseTitle: formData.get("courseTitle"),
      programmeName: formData.get("programmeName"),
    });
    if (!parsed.success) {
      return { ok: false, message: formatZodIssues(parsed.error) };
    }
    bundleFields = parsed.data;
  }

  try {
    const result = await uploadResourceFiles({
      files,
      adminId: admin.id,
      semester: semesterRaw as 1 | 2,
      bundleId: bundleId || undefined,
      bundle: bundleFields,
    });

    revalidatePath("/admin/resources");
    revalidatePath("/admin/bundles");
    revalidatePath("/browse");
    revalidatePath("/");

    return {
      ok: true,
      bundleSlug: result.bundleSlug,
      message: result.createdBundle
        ? `Bundle created with ${result.files.length} paper${result.files.length === 1 ? "" : "s"} as drafts. Publish the bundle when ready.`
        : `${result.files.length} paper${result.files.length === 1 ? "" : "s"} added to the bundle as drafts. Publish them from the table below.`,
    };
  } catch (err) {
    console.error("Bulk upload failed:", err);
    return {
      ok: false,
      message: err instanceof Error ? err.message : "Upload failed — please retry.",
    };
  }
}

// ─────────────────────────────────────────────────────────────────
// Bundle management (admin/bundles)
// ─────────────────────────────────────────────────────────────────

export async function setBundleStatusAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const bundleId = String(formData.get("bundleId") ?? "");
  const next = String(formData.get("next") ?? "");
  if (!bundleId) return;

  const bundle = await prisma.bundle.findUnique({
    where: { id: bundleId },
    select: { id: true, status: true, slug: true, _count: { select: { resources: true } } },
  });
  if (!bundle) return;

  if (next === "PUBLISHED") {
    // Cannot publish an empty bundle — buying it would grant nothing.
    if (bundle._count.resources === 0) return;
    await prisma.bundle.update({
      where: { id: bundleId },
      data: { status: "PUBLISHED", publishedAt: new Date() },
    });
    // The bulk model: publishing the bundle takes all its papers live.
    await prisma.resource.updateMany({
      where: { bundleId, status: { not: "PUBLISHED" } },
      data: { status: "PUBLISHED", publishedAt: new Date() },
    });
    // Papers added since the last publish reach everyone who already
    // bought this bundle (late uploads unlock automatically).
    await grantBundlePapersToPastBuyers(bundleId);
  } else if (next === "UNPUBLISHED" && bundle.status === "PUBLISHED") {
    await prisma.bundle.update({
      where: { id: bundleId },
      data: { status: "UNPUBLISHED" },
    });
  }

  revalidatePath("/admin/bundles");
  revalidatePath("/admin/resources");
  revalidatePath("/browse");
  revalidatePath("/");
  if (bundle.slug) revalidatePath(`/bundles/${bundle.slug}`);
}

export async function updateBundlePriceAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const bundleId = String(formData.get("bundleId") ?? "").trim();
  if (!bundleId) return;

  const price = z.coerce
    .number({ message: "Price is required." })
    .min(0, "Price cannot be negative.")
    .max(1000, "Price must be at most GH₵1,000.")
    .safeParse(formData.get("price"));
  if (!price.success) return;

  await prisma.bundle.update({
    where: { id: bundleId },
    data: { pricePesewas: Math.round(price.data * 100) },
  });

  revalidatePath("/admin/bundles");
  revalidatePath("/browse");
  revalidatePath("/");
}

// ─────────────────────────────────────────────────────────────────
// Per-paper actions (admin/resources)
// ─────────────────────────────────────────────────────────────────

export interface EditResourceState {
  ok?: boolean;
  message?: string;
}

/** Validation for the subset of metadata editable after creation. */
const resourceEditSchema = z.object({
  description: z
    .string()
    .trim()
    .max(2000, "Description must be at most 2000 characters.")
    .transform((v) => v || null),
  type: z.enum(["PAST_QUESTION", "LECTURE_NOTES", "REVISION", "PRACTICE"]),
  programmeName: z
    .string()
    .trim()
    .min(2, "Programme is required (e.g. BSc IT).")
    .max(120, "Programme must be at most 120 characters."),
});

export async function updateResourceAction(
  _prev: EditResourceState,
  formData: FormData,
): Promise<EditResourceState> {
  const admin = await requireAdminUser();
  if (!admin) return { ok: false, message: "Admin access required." };

  const resourceId = String(formData.get("resourceId") ?? "").trim();
  if (!resourceId) return { ok: false, message: "Missing resource." };

  const parsed = resourceEditSchema.safeParse({
    description: formData.get("description") ?? "",
    type: formData.get("type"),
    programmeName: formData.get("programmeName"),
  });
  if (!parsed.success) {
    return { ok: false, message: formatZodIssues(parsed.error) };
  }

  const fields = parsed.data;
  const programme = await prisma.programme.upsert({
    where: { name: fields.programmeName },
    create: { name: fields.programmeName, slug: slugify(fields.programmeName) },
    update: {},
  });

  await prisma.resource.update({
    where: { id: resourceId },
    data: {
      description: fields.description,
      type: fields.type,
      programmeId: programme.id,
    },
  });

  revalidatePath("/admin/resources");
  revalidatePath(`/admin/resources/${resourceId}`);
  revalidatePath("/browse");
  revalidatePath("/");
  const resource = await prisma.resource.findUnique({
    where: { id: resourceId },
    select: { slug: true },
  });
  if (resource) revalidatePath(`/resources/${resource.slug}`);

  return { ok: true, message: "Resource updated." };
}

export async function replaceResourceFileAction(
  _prev: EditResourceState,
  formData: FormData,
): Promise<EditResourceState> {
  const admin = await requireAdminUser();
  if (!admin) return { ok: false, message: "Admin access required." };

  const resourceId = String(formData.get("resourceId") ?? "").trim();
  if (!resourceId) return { ok: false, message: "Missing resource." };

  const file = formData.get("file");
  if (!(file instanceof File) || file.size === 0) {
    return { ok: false, message: "Choose a PDF file to upload." };
  }
  if (file.size > MAX_UPLOAD_BYTES) {
    return {
      ok: false,
      message: `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB) — the limit is 30 MB.`,
    };
  }

  try {
    const result = await replaceResourceFile({
      file,
      adminId: admin.id,
      resourceId,
    });

    revalidatePath("/admin/resources");
    revalidatePath(`/admin/resources/${resourceId}`);
    revalidatePath("/browse");
    const resource = await prisma.resource.findUnique({
      where: { id: resourceId },
      select: { slug: true },
    });
    if (resource) revalidatePath(`/resources/${resource.slug}`);

    return {
      ok: true,
      message: `New version attached (${result.pageCount} pages) — the previous file is now superseded.`,
    };
  } catch (err) {
    console.error("File replacement failed:", err);
    return {
      ok: false,
      message: err instanceof Error ? err.message : "Upload failed — please retry.",
    };
  }
}

export async function setResourceStatusAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const resourceId = String(formData.get("resourceId") ?? "");
  const next = String(formData.get("next") ?? "");
  if (!resourceId) return;

  const resource = await prisma.resource.findUnique({
    where: { id: resourceId },
    select: {
      id: true,
      status: true,
      bundleId: true,
      _count: { select: { files: true } },
    },
  });
  if (!resource) return;

  if (next === "PUBLISHED") {
    // Cannot publish an empty resource — the viewer would 404 on content.
    if (resource._count.files === 0) return;
    await prisma.resource.update({
      where: { id: resourceId },
      data: { status: "PUBLISHED", publishedAt: new Date() },
    });
    // Publishing a paper inside a bundle everyone already bought reaches
    // those past buyers (late uploads unlock automatically).
    if (resource.bundleId) await grantBundlePapersToPastBuyers(resource.bundleId);
  } else if (next === "UNPUBLISHED" && resource.status === "PUBLISHED") {
    await prisma.resource.update({
      where: { id: resourceId },
      data: { status: "UNPUBLISHED" },
    });
  }

  revalidatePath("/admin/resources");
  revalidatePath("/browse");
  revalidatePath("/");
}

export async function deleteDraftAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const resourceId = String(formData.get("resourceId") ?? "");
  if (!resourceId) return;

  // Only DRAFT resources are deletable — PAID orders reference the rest.
  await prisma.resource.deleteMany({
    where: { id: resourceId, status: "DRAFT" },
  });

  revalidatePath("/admin/resources");
}
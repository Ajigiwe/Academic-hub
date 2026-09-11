"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import {
  uploadResourceFiles,
  uploadFreeMaterial,
  replaceResourceFile,
  bundleFormSchema,
  freeMaterialFormSchema,
  formatZodIssues,
  MAX_UPLOAD_BYTES,
  slugify,
  formString,
  formNumber,
} from "@/lib/resource-admin";
import { grantBundlePapersToPastBuyers } from "@/lib/entitlement-grant";
import { programmeSettingKey, getAllProgrammesWithVisibility } from "@/lib/settings";

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
      title: formString(formData, "title"),
      description: formString(formData, "description"),
      level: formNumber(formData, "level"),
      academicYear: formString(formData, "academicYear"),
      price: formNumber(formData, "price"),
      courseCode: formString(formData, "courseCode"),
      courseTitle: formString(formData, "courseTitle"),
      programmeName: formString(formData, "programmeName"),
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

export interface FreeUploadState {
  ok?: boolean;
  message?: string;
  resourceSlug?: string;
}

/**
 * Upload ONE free material (slides / notes / revision pack) — not part
 * of any sale bundle, published immediately, downloadable by anyone.
 */
export async function uploadFreeMaterialAction(
  _prev: FreeUploadState,
  formData: FormData,
): Promise<FreeUploadState> {
  const admin = await requireAdminUser();
  if (!admin) return { ok: false, message: "Admin access required." };

  const file = formData.get("file");
  if (!(file instanceof File) || file.size === 0) {
    return { ok: false, message: "Choose a PDF file to upload." };
  }
  if (file.size > MAX_UPLOAD_BYTES) {
    return {
      ok: false,
      message: `"${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(1)} MB) — the limit is 30 MB.`,
    };
  }

  const parsed = freeMaterialFormSchema.safeParse({
    title: formString(formData, "title"),
    description: formString(formData, "description"),
    type: formString(formData, "type"),
    level: formNumber(formData, "level"),
    semester: formNumber(formData, "semester"),
    courseCode: formString(formData, "courseCode"),
    courseTitle: formString(formData, "courseTitle"),
    programmeName: formString(formData, "programmeName"),
  });
  if (!parsed.success) {
    return { ok: false, message: formatZodIssues(parsed.error) };
  }

  try {
    const result = await uploadFreeMaterial({
      file,
      adminId: admin.id,
      material: parsed.data,
    });

    revalidatePath("/admin/resources");
    revalidatePath("/courses");
    revalidatePath("/search");
    revalidatePath("/");

    return {
      ok: true,
      resourceSlug: result.slug,
      message: `“${result.title}” published as a free download.`,
    };
  } catch (err) {
    console.error("Free material upload failed:", err);
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
    description: formString(formData, "description"),
    type: formString(formData, "type"),
    programmeName: formString(formData, "programmeName"),
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

// ─────────────────────────────────────────────────────────────────
// Deleting papers & bundles
// ─────────────────────────────────────────────────────────────────

const ADMIN_RESOURCES_PATH = "/admin/resources";
const ADMIN_BUNDLES_PATH = "/admin/bundles";

function deleteNoticeUrl(
  base: string,
  kind: "paper" | "bundle",
  outcome: "deleted" | "blocked" | "denied" | "missing",
  opts?: { name?: string; sold?: number },
): string {
  const params = new URLSearchParams({ notice: outcome, kind });
  if (opts?.name) params.set("name", opts.name);
  if (opts?.sold !== undefined) params.set("sold", String(opts.sold));
  return `${base}?${params.toString()}`;
}

/**
 * Delete ONE paper (bundle paper or free material). Anything a paid order
 * references is protected — buyers keep access to what they paid for —
 * but unsold papers can be removed outright. Always ends with a redirect
 * carrying a notice so the outcome is never a silent no-op; an already-
 * deleted id (double submit) reports success.
 */
export async function deleteResourceAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) redirect(deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "denied"));

  const resourceId = String(formData.get("resourceId") ?? "").trim();
  if (!resourceId) redirect(deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "missing"));

  const resource = await prisma.resource.findUnique({
    where: { id: resourceId },
    select: { id: true, title: true, slug: true, _count: { select: { entitlements: true } } },
  });
  if (!resource) redirect(deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "deleted"));
  if (resource._count.entitlements > 0) {
    redirect(
      deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "blocked", {
        name: resource.title,
        sold: resource._count.entitlements,
      }),
    );
  }

  try {
    // ResourceFile rows cascade; entitlements are zero by the guard above.
    await prisma.resource.delete({ where: { id: resource.id } });
  } catch (err) {
    console.error("Failed to delete resource:", err);
    redirect(
      deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "blocked", {
        name: resource.title,
        sold: 0,
      }),
    );
  }

  revalidatePath("/admin/resources");
  revalidatePath("/admin/bundles");
  revalidatePath("/browse");
  revalidatePath("/materials");
  revalidatePath("/search");
  revalidatePath("/");
  if (resource.slug) revalidatePath(`/resources/${resource.slug}`);
  redirect(
    deleteNoticeUrl(ADMIN_RESOURCES_PATH, "paper", "deleted", { name: resource.title }),
  );
}

/**
 * Delete a WHOLE bundle with all papers inside it. Bundles that appear on
 * any order are permanent purchase records and cannot be deleted —
 * unpublish or archive them instead. Papers inside a deletable bundle are
 * by definition unsold (per-paper entitlements only come from purchases),
 * so removing them loses no buyer access.
 */
export async function deleteBundleAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) redirect(deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "denied"));

  const bundleId = String(formData.get("bundleId") ?? "").trim();
  if (!bundleId) redirect(deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "missing"));

  const bundle = await prisma.bundle.findUnique({
    where: { id: bundleId },
    select: {
      id: true,
      title: true,
      slug: true,
      _count: { select: { resources: true, orderItems: true } },
    },
  });
  if (!bundle) redirect(deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "deleted"));
  if (bundle._count.orderItems > 0) {
    redirect(
      deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "blocked", {
        name: bundle.title,
        sold: bundle._count.orderItems,
      }),
    );
  }

  try {
    await prisma.$transaction([
      prisma.resource.deleteMany({ where: { bundleId: bundle.id } }),
      prisma.bundle.delete({ where: { id: bundle.id } }),
    ]);
  } catch (err) {
    console.error("Failed to delete bundle:", err);
    redirect(
      deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "blocked", {
        name: bundle.title,
        sold: 0,
      }),
    );
  }

  revalidatePath("/admin/bundles");
  revalidatePath("/admin/resources");
  revalidatePath("/browse");
  revalidatePath("/");
  if (bundle.slug) revalidatePath(`/bundles/${bundle.slug}`);
  redirect(
    deleteNoticeUrl(ADMIN_BUNDLES_PATH, "bundle", "deleted", {
      name: bundle.title,
    }),
  );
}

// ─────────────────────────────────────────────────────────────────
// Platform settings (admin/settings)
// ─────────────────────────────────────────────────────────────────

/**
 * Persist programme visibility. A checked box means the programme is
 * live for students; unchecked hides it from browsing, filters, and
 * search (content stays in the catalogue). Re-enabling restores it.
 * Covers every programme row in the DB, not just the seeded defaults.
 */
export async function saveProgrammeSettingsAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const programmes = await getAllProgrammesWithVisibility();

  await prisma.$transaction(
    programmes.map((p) => {
      const key = programmeSettingKey(p.slug);
      const value = formData.get(key) !== null ? "true" : "false";
      return prisma.setting.upsert({
        where: { key },
        update: { value },
        create: { key, value },
      });
    }),
  );

  revalidatePath("/admin/settings");
  revalidatePath("/");
  revalidatePath("/courses");
  revalidatePath("/materials");
  revalidatePath("/search");
}

/**
 * Add a new programme track. The slug is generated from the name; the
 * track starts ENABLED so it appears in the student browse flow right
 * away (and can be toggled off like any other).
 */
export async function addProgrammeAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) return;

  const name = String(formData.get("name") ?? "").trim();
  if (name.length < 2 || name.length > 120) return;

  const slug = slugify(name);
  if (!slug) return;

  // Name and slug are both unique — upsert keeps repeated submits safe.
  await prisma.programme.upsert({
    where: { slug },
    update: { name },
    create: { name, slug },
  });
  await prisma.setting.upsert({
    where: { key: programmeSettingKey(slug) },
    update: { value: "true" },
    create: { key: programmeSettingKey(slug), value: "true" },
  });

  revalidatePath("/admin/settings");
  revalidatePath("/");
  revalidatePath("/courses");
  revalidatePath("/materials");
  revalidatePath("/search");
}

/**
 * Delete a programme track. Only allowed when it has no bundles or
 * resources attached — hiding content is what the visibility toggle is
 * for. Always ends with a redirect back to the settings page carrying a
 * notice, so the outcome is never a silent no-op (deleted rows vanish
 * from the page even when the caller is a stale render).
 */
export async function deleteProgrammeAction(formData: FormData): Promise<void> {
  const admin = await requireAdminUser();
  if (!admin) {
    redirect("/admin/settings?notice=denied");
  }

  const slug = String(formData.get("slug") ?? "").trim();
  if (!slug) {
    redirect("/admin/settings?notice=missing");
  }

  const programme = await prisma.programme.findUnique({
    where: { slug },
    select: { id: true, name: true, _count: { select: { bundles: true, resources: true } } },
  });
  if (!programme) {
    // Already gone (e.g. double submit) — nothing left to do.
    redirect("/admin/settings?notice=deleted");
  }
  if (programme._count.bundles > 0 || programme._count.resources > 0) {
    redirect(
      `/admin/settings?notice=blocked&name=${encodeURIComponent(programme.name)}` +
        `&bundles=${programme._count.bundles}&resources=${programme._count.resources}`,
    );
  }

  await prisma.$transaction([
    prisma.setting.deleteMany({ where: { key: programmeSettingKey(slug) } }),
    prisma.programme.delete({ where: { id: programme.id } }),
  ]);

  revalidatePath("/admin/settings");
  revalidatePath("/");
  revalidatePath("/courses");
  revalidatePath("/materials");
  revalidatePath("/search");
  redirect(
    `/admin/settings?notice=deleted&name=${encodeURIComponent(programme.name)}`,
  );
}
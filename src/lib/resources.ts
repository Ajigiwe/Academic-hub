import { prisma } from "@/lib/db";
import type { Prisma } from "@prisma/client";
import { PROGRAMME_SLUGS } from "@/lib/programmes";

/**
 * Catalog queries for BUNDLES — the unit of sale. Students buy one
 * bundle per course + academic year (e.g. "ICT 201 · 2024/2025") and
 * unlock every paper inside it; individual resources are never sold.
 */

export interface SearchFilters {
  q?: string;
  programme?: string; // programme slug
  level?: number;
  semester?: number;
  year?: string;
  sort?: "relevance" | "newest" | "popular" | "price_asc" | "price_desc";
  page?: number;
  perPage?: number;
}

export const SEARCH_PER_PAGE = 12;

const PUBLISHED_RESOURCE: Prisma.ResourceWhereInput = { status: "PUBLISHED" };

export async function searchBundles(filters: SearchFilters) {
  const page = Math.max(1, filters.page ?? 1);
  const perPage = filters.perPage ?? SEARCH_PER_PAGE;

  const where: Prisma.BundleWhereInput = { status: "PUBLISHED" };

  if (filters.q?.trim()) {
    const q = filters.q.trim();
    where.OR = [
      { title: { contains: q, mode: "insensitive" } },
      { description: { contains: q, mode: "insensitive" } },
      { course: { is: { code: { contains: q, mode: "insensitive" } } } },
      { course: { is: { title: { contains: q, mode: "insensitive" } } } },
      { programme: { is: { name: { contains: q, mode: "insensitive" } } } },
      { academicYear: { contains: q } },
    ];
  }
  if (filters.programme) where.programme = { is: { slug: filters.programme } };
  if (filters.level) where.level = filters.level;
  if (filters.year) where.academicYear = filters.year;
  // A bundle counts as belonging to a semester when at least one of its
  // published papers does.
  if (filters.semester) {
    where.resources = { some: { semester: filters.semester, status: "PUBLISHED" } };
  }

  const orderBy: Prisma.BundleOrderByWithRelationInput[] =
    filters.sort === "newest"
      ? [{ publishedAt: "desc" }]
      : filters.sort === "price_asc"
        ? [{ pricePesewas: "asc" }]
        : filters.sort === "price_desc"
          ? [{ pricePesewas: "desc" }]
          : filters.sort === "popular"
            ? [{ orderItems: { _count: "desc" } }, { publishedAt: "desc" }]
            : // relevance: newest as a stable baseline (Post FTS upgrades later)
              [{ publishedAt: "desc" }];

  const [total, items] = await Promise.all([
    prisma.bundle.count({ where }),
    prisma.bundle.findMany({
      where,
      orderBy,
      skip: (page - 1) * perPage,
      take: perPage,
      include: {
        course: true,
        programme: true,
        _count: {
          select: {
            resources: { where: PUBLISHED_RESOURCE },
            orderItems: true,
          },
        },
      },
    }),
  ]);

  return { total, page, perPage, pages: Math.max(1, Math.ceil(total / perPage)), items };
}

export type SearchBundleItem = Awaited<ReturnType<typeof searchBundles>>["items"][number];

export async function getPopularBundles(limit = 6) {
  return prisma.bundle.findMany({
    where: { status: "PUBLISHED" },
    orderBy: [{ orderItems: { _count: "desc" } }, { publishedAt: "desc" }],
    take: limit,
    include: {
      course: true,
      programme: true,
      _count: { select: { resources: { where: PUBLISHED_RESOURCE } } },
    },
  });
}

export async function getRecentBundles(limit = 6) {
  return prisma.bundle.findMany({
    where: { status: "PUBLISHED" },
    orderBy: { publishedAt: "desc" },
    take: limit,
    include: {
      course: true,
      programme: true,
      _count: { select: { resources: { where: PUBLISHED_RESOURCE } } },
    },
  });
}

export async function getPublishedBundleBySlug(slug: string) {
  return prisma.bundle.findFirst({
    where: { slug, status: { in: ["PUBLISHED", "UNPUBLISHED"] } },
    include: {
      course: true,
      programme: true,
      resources: {
        where: { status: "PUBLISHED" },
        orderBy: [{ semester: "asc" }, { title: "asc" }],
      },
      _count: { select: { resources: { where: PUBLISHED_RESOURCE } } },
    },
  });
}

export async function getPublishedResourceBySlug(slug: string) {
  return prisma.resource.findFirst({
    where: { slug, status: { in: ["PUBLISHED", "UNPUBLISHED"] } },
    include: {
      course: true,
      programme: true,
      bundle: { select: { id: true, title: true, slug: true, pricePesewas: true } },
      files: {
        where: { isCurrent: true },
        select: { sizeBytes: true },
        take: 1,
      },
    },
  });
}

export async function getProgrammes() {
  return prisma.programme.findMany({
    where: { slug: { in: PROGRAMME_SLUGS } },
    orderBy: { name: "asc" },
    include: { _count: { select: { bundles: { where: { status: "PUBLISHED" } } } } },
  });
}

export async function getFilterYears(): Promise<string[]> {
  const rows = await prisma.bundle.findMany({
    where: { status: "PUBLISHED" },
    distinct: ["academicYear"],
    select: { academicYear: true },
    orderBy: { academicYear: "desc" },
  });
  return rows.map((r) => r.academicYear);
}

/**
 * Free downloadable materials (slides, notes, revision packs) — published
 * resources that belong to NO sale bundle. Optionally filtered to a
 * programme track + level + semester, matching the guided browse flow.
 */
export async function getFreeMaterials(filters: {
  programme?: string;
  level?: number;
  semester?: number;
  q?: string;
}) {
  const where: Prisma.ResourceWhereInput = {
    status: "PUBLISHED",
    bundleId: null,
  };
  if (filters.programme) where.programme = { is: { slug: filters.programme } };
  if (filters.level) where.level = filters.level;
  if (filters.semester) where.semester = filters.semester;
  if (filters.q?.trim()) {
    const q = filters.q.trim();
    where.OR = [
      { title: { contains: q, mode: "insensitive" } },
      { description: { contains: q, mode: "insensitive" } },
      { course: { is: { code: { contains: q, mode: "insensitive" } } } },
      { course: { is: { title: { contains: q, mode: "insensitive" } } } },
      { programme: { is: { name: { contains: q, mode: "insensitive" } } } },
    ];
  }

  return prisma.resource.findMany({
    where,
    orderBy: [{ course: { code: "asc" } }, { semester: "asc" }, { title: "asc" }],
    include: {
      course: true,
      programme: true,
      files: {
        where: { isCurrent: true },
        select: { id: true, sizeBytes: true },
        take: 1,
      },
    },
  });
}

export type FreeMaterial = Awaited<ReturnType<typeof getFreeMaterials>>[number];

export function formatPrice(pesewas: number): string {
  return `GH₵${(pesewas / 100).toFixed(2)}`;
}

export function formatBytes(bytes: number | null | undefined): string {
  if (!bytes) return "";
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
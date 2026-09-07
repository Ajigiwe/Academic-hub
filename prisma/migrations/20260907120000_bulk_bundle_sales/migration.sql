-- Bulk bundle sales: bundles become the unit of sale (course + academic
-- year). Backfills bundles from existing resources so no dev data is lost.

-- CreateTable
CREATE TABLE "bundles" (
    "id" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT,
    "status" "ResourceStatus" NOT NULL DEFAULT 'DRAFT',
    "level" INTEGER NOT NULL,
    "academic_year" TEXT NOT NULL,
    "price_pesewas" INTEGER NOT NULL,
    "published_at" TIMESTAMP(3),
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,
    "programme_id" TEXT NOT NULL,
    "course_id" TEXT NOT NULL,

    CONSTRAINT "bundles_pkey" PRIMARY KEY ("id")
);

-- Backfill: one bundle per (course, programme, level, academic year).
INSERT INTO "bundles" (
    "id", "slug", "title", "description", "status", "level", "academic_year",
    "price_pesewas", "published_at", "created_at", "updated_at",
    "programme_id", "course_id"
)
SELECT DISTINCT ON (r."course_id", r."programme_id", r."level", r."academic_year")
    'bundle_' || md5(r."course_id" || r."programme_id" || r."level"::text || r."academic_year"),
    c."slug" || '-' || replace(r."academic_year", '/', '-'),
    upper(c."code") || ' — ' || c."title" || ' · ' || r."academic_year" || ' Past Questions',
    'All past questions for ' || upper(c."code") || ' — ' || r."academic_year" || '.',
    'PUBLISHED',
    r."level",
    r."academic_year",
    r."price_pesewas",
    r."published_at",
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP,
    r."programme_id",
    r."course_id"
FROM "resources" r
JOIN "courses" c ON c."id" = r."course_id"
ORDER BY r."course_id", r."programme_id", r."level", r."academic_year";

-- Resources belong to exactly one bundle.
ALTER TABLE "resources" ADD COLUMN "bundle_id" TEXT;

UPDATE "resources" r
SET "bundle_id" = b."id"
FROM "bundles" b
WHERE b."course_id" = r."course_id"
  AND b."programme_id" = r."programme_id"
  AND b."level" = r."level"
  AND b."academic_year" = r."academic_year";

ALTER TABLE "resources" ALTER COLUMN "bundle_id" SET NOT NULL;

-- Order items now reference the purchased bundle instead of one resource.
ALTER TABLE "order_items" ADD COLUMN "bundle_id" TEXT;

UPDATE "order_items" oi
SET "bundle_id" = r."bundle_id"
FROM "resources" r
WHERE r."id" = oi."resource_id";

ALTER TABLE "order_items" ALTER COLUMN "bundle_id" SET NOT NULL;

-- DropForeignKey
ALTER TABLE "order_items" DROP CONSTRAINT "order_items_resource_id_fkey";

-- DropIndex
DROP INDEX "order_items_resource_id_idx";

-- DropIndex (created as a unique index by the init migration)
DROP INDEX "order_items_order_id_resource_id_key";

-- DropColumn
ALTER TABLE "order_items" DROP COLUMN "resource_id";

-- Resource-level price is gone: the bundle carries the only price.
ALTER TABLE "resources" DROP COLUMN "price_pesewas";

-- CreateIndex
CREATE UNIQUE INDEX "bundles_slug_key" ON "bundles"("slug");

-- CreateIndex
CREATE INDEX "bundles_status_published_at_idx" ON "bundles"("status", "published_at" DESC);

-- CreateIndex
CREATE INDEX "bundles_course_id_idx" ON "bundles"("course_id");

-- CreateIndex
CREATE INDEX "bundles_programme_id_idx" ON "bundles"("programme_id");

-- CreateIndex
CREATE INDEX "bundles_academic_year_idx" ON "bundles"("academic_year");

-- CreateIndex
CREATE INDEX "resources_bundle_id_idx" ON "resources"("bundle_id");

-- CreateIndex
CREATE UNIQUE INDEX "order_items_order_id_bundle_id_key" ON "order_items"("order_id", "bundle_id");

-- CreateIndex
CREATE INDEX "order_items_bundle_id_idx" ON "order_items"("bundle_id");

-- AddForeignKey
ALTER TABLE "bundles" ADD CONSTRAINT "bundles_programme_id_fkey" FOREIGN KEY ("programme_id") REFERENCES "programmes"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "bundles" ADD CONSTRAINT "bundles_course_id_fkey" FOREIGN KEY ("course_id") REFERENCES "courses"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "resources" ADD CONSTRAINT "resources_bundle_id_fkey" FOREIGN KEY ("bundle_id") REFERENCES "bundles"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "order_items" ADD CONSTRAINT "order_items_bundle_id_fkey" FOREIGN KEY ("bundle_id") REFERENCES "bundles"("id") ON DELETE RESTRICT ON UPDATE CASCADE;
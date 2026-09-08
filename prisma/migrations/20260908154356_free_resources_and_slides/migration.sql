-- AlterEnum
ALTER TYPE "ResourceType" ADD VALUE 'SLIDES';

-- DropForeignKey
ALTER TABLE "resources" DROP CONSTRAINT "resources_bundle_id_fkey";

-- AlterTable
ALTER TABLE "resources" ALTER COLUMN "bundle_id" DROP NOT NULL;

-- AddForeignKey
ALTER TABLE "resources" ADD CONSTRAINT "resources_bundle_id_fkey" FOREIGN KEY ("bundle_id") REFERENCES "bundles"("id") ON DELETE SET NULL ON UPDATE CASCADE;

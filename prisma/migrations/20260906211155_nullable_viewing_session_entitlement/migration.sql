-- DropForeignKey
ALTER TABLE "viewing_sessions" DROP CONSTRAINT "viewing_sessions_entitlement_id_fkey";

-- AlterTable
ALTER TABLE "viewing_sessions" ALTER COLUMN "entitlement_id" DROP NOT NULL;

-- AddForeignKey
ALTER TABLE "viewing_sessions" ADD CONSTRAINT "viewing_sessions_entitlement_id_fkey" FOREIGN KEY ("entitlement_id") REFERENCES "entitlements"("id") ON DELETE SET NULL ON UPDATE CASCADE;

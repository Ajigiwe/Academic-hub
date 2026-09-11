-- DropForeignKey
ALTER TABLE "viewing_sessions" DROP CONSTRAINT "viewing_sessions_resource_id_fkey";

-- DropForeignKey
ALTER TABLE "viewing_sessions" DROP CONSTRAINT "viewing_sessions_user_id_fkey";

-- AlterTable
ALTER TABLE "settings" ALTER COLUMN "updated_at" DROP DEFAULT;

-- AddForeignKey
ALTER TABLE "viewing_sessions" ADD CONSTRAINT "viewing_sessions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "viewing_sessions" ADD CONSTRAINT "viewing_sessions_resource_id_fkey" FOREIGN KEY ("resource_id") REFERENCES "resources"("id") ON DELETE CASCADE ON UPDATE CASCADE;

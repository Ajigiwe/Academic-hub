-- CreateTable
CREATE TABLE "settings" (
    "key" TEXT NOT NULL,
    "value" TEXT NOT NULL,
    "updated_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "settings_pkey" PRIMARY KEY ("key")
);

-- Default visibility: only BTECH is live for now. The other tracks are
-- switched on later from Admin → Settings (key format: programme.<slug>.enabled).
INSERT INTO "settings" ("key", "value") VALUES
    ('programme.btech.enabled', 'true'),
    ('programme.dip-tech.enabled', 'false'),
    ('programme.hnd.enabled', 'false')
ON CONFLICT ("key") DO NOTHING;
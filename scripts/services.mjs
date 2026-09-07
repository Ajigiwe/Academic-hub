/**
 * Terminal-managed local services: portable PostgreSQL + MinIO.
 * Replaces docker-compose — `npm run services:up` is all you need.
 *
 *   node scripts/services.mjs up|down|status
 *
 * Data lives in ./services/data (gitignored). Credentials are dev-only
 * and must match .env (they do by default).
 */
import { spawn } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const SERVICES_DIR = path.join(ROOT, "services");
const DATA_DIR = path.join(SERVICES_DIR, "data");
const PG_DATA = path.join(DATA_DIR, "postgres");
const PG_BIN = path.join(ROOT, "node_modules", "@embedded-postgres", "windows-x64", "native", "bin");
const MINIO_EXE = path.join(SERVICES_DIR, "minio", "minio.exe");
const PID_DIR = path.join(DATA_DIR, "pids");

const PG_PORT = 5432;
const S3_PORT = 9000;
const PG_USER = "pastq";
const PG_PASSWORD = "pastq-dev-password";
const PG_DB = "pastq";
const MINIO_ROOT_USER = "pastq-dev-key";
const MINIO_ROOT_PASSWORD = "pastq-dev-secret";
const BUCKET = "pastq-private";

const log = (...m) => console.log("[services]", ...m);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function portOpen(port) {
  return new Promise((resolve) => {
    const sock = net.connect({ port, host: "127.0.0.1" });
    sock.setTimeout(1500);
    sock.on("connect", () => { sock.destroy(); resolve(true); });
    sock.on("timeout", () => { sock.destroy(); resolve(false); });
    sock.on("error", () => resolve(false));
  });
}

function readPid(name) {
  try {
    const pid = Number(fs.readFileSync(path.join(PID_DIR, `${name}.pid`), "utf8").trim());
    return Number.isInteger(pid) && pid > 0 ? pid : null;
  } catch {
    return null;
  }
}

function alive(pid) {
  if (!pid) return false;
  try { return process.kill(pid, 0); } catch (e) { return e.code === "EPERM"; }
}

/** taskkill /T for a reliable Windows tree-kill; falls back to SIGTERM. */
function killTree(pid) {
  if (process.platform === "win32") {
    try {
      spawn("taskkill", ["/F", "/T", "/PID", String(pid)], { stdio: "ignore" }).on("error", () => process.kill(pid));
    } catch {
      try { process.kill(pid); } catch { /* already gone */ }
    }
  } else {
    try { process.kill(pid, "SIGTERM"); } catch { /* already gone */ }
  }
}

/** Spawn fully detached so children outlive this npm command. */
function spawnDetached(cmd, args, opts = {}) {
  const child = spawn(cmd, args, {
    cwd: ROOT,
    stdio: ["ignore", "ignore", "ignore"],
    detached: true,
    windowsHide: true,
    ...opts,
  });
  child.unref();
  return child;
}

function ensureDirs() {
  fs.mkdirSync(PG_DATA, { recursive: true });
  fs.mkdirSync(path.join(DATA_DIR, "postgres-sock"), { recursive: true }); // AF_UNIX lock dir on Windows PG17
  fs.mkdirSync(PID_DIR, { recursive: true });
}

async function initPgIfNeeded() {
  if (fs.existsSync(path.join(PG_DATA, "PG_VERSION"))) return;
  log("initialising PostgreSQL data directory (first run)…");
  fs.mkdirSync(DATA_DIR, { recursive: true });
  const pwfile = path.join(DATA_DIR, "pg_pwfile.txt");
  fs.writeFileSync(pwfile, PG_PASSWORD);
  await new Promise((resolve, reject) => {
    const p = spawn(path.join(PG_BIN, "initdb.exe"), [
      "-D", PG_DATA,
      "-U", PG_USER,
      "-A", "scram-sha-256",
      "--pwfile", pwfile,
      "-E", "UTF8",
    ], { stdio: ["ignore", "inherit", "inherit"], windowsHide: true });
    p.on("exit", (code) => (code === 0 ? resolve() : reject(new Error(`initdb exited ${code}`))));
    p.on("error", reject);
  });
  fs.rmSync(pwfile, { force: true });
}

function writePgHba() {
  // Single-user dev machine: trust local loopback connections.
  const hba = `# managed by scripts/services.mjs
host all all 127.0.0.1/32 trust
host all all ::1/128 trust
`;
  fs.writeFileSync(path.join(PG_DATA, "pg_hba.conf"), hba);
}

function pgConfLines() {
  return `
listen_addresses = '127.0.0.1'
port = ${PG_PORT}
unix_socket_directories = '${DATA_DIR.replace(/\\/g, "/")}/postgres-sock'
`;
}

async function startPostgres() {
  await initPgIfNeeded();
  writePgHba();
  const confPath = path.join(PG_DATA, "pastq.conf");
  fs.appendFileSync(confPath, pgConfLines().startsWith("\n") ? pgConfLines() : "\n" + pgConfLines());
  if (!fs.readFileSync(path.join(PG_DATA, "postgresql.conf"), "utf8").includes("pastq.conf")) {
    fs.appendFileSync(path.join(PG_DATA, "postgresql.conf"), `include = 'pastq.conf'\n`);
  }
  log("starting PostgreSQL…");
  // pg_ctl owns the logfile via -l; keep our stdio out of the file.
  const pg = spawn(path.join(PG_BIN, "pg_ctl.exe"), [
    "-D", PG_DATA,
    "-l", path.join(DATA_DIR, "postgres.log"),
    "start",
  ], { stdio: ["ignore", "ignore", "ignore"], detached: true, windowsHide: true });
  pg.unref();
  for (let i = 0; i < 60; i++) {
    if (await portOpen(PG_PORT)) {
      log(`PostgreSQL ready on :${PG_PORT}`);
      return;
    }
    await sleep(500);
  }
  throw new Error("PostgreSQL did not become ready in 30s — see services/data/postgres.log");
}

async function startMinio() {
  if (!fs.existsSync(MINIO_EXE)) {
    throw new Error(`MinIO binary missing at ${MINIO_EXE} — download it to services/minio/minio.exe`);
  }
  log("starting MinIO…");
  const child = spawnDetached(MINIO_EXE, ["server", path.join(DATA_DIR, "minio"), "--address", `127.0.0.1:${S3_PORT}`, "--console-address", `127.0.0.1:9001`], {
    env: {
      ...process.env,
      MINIO_ROOT_USER,
      MINIO_ROOT_PASSWORD,
    },
  });
  fs.mkdirSync(PID_DIR, { recursive: true });
  fs.writeFileSync(path.join(PID_DIR, "minio.pid"), String(child.pid));
  for (let i = 0; i < 60; i++) {
    if (await portOpen(S3_PORT)) {
      log(`MinIO ready on :${S3_PORT} (console :9001)`);
      return;
    }
    await sleep(500);
  }
  throw new Error("MinIO did not become ready in 30s — see services/data/minio.log");
}

async function ensureBucket() {
  const endpoint = `http://127.0.0.1:${S3_PORT}`;
  const { S3Client, HeadBucketCommand, CreateBucketCommand } = await import("@aws-sdk/client-s3");
  const s3 = new S3Client({ region: "us-east-1", endpoint, forcePathStyle: true, credentials: { accessKeyId: MINIO_ROOT_USER, secretAccessKey: MINIO_ROOT_PASSWORD } });
  try {
    await s3.send(new HeadBucketCommand({ Bucket: BUCKET }));
    log(`bucket "${BUCKET}" exists`);
  } catch {
    await s3.send(new CreateBucketCommand({ Bucket: BUCKET }));
    log(`bucket "${BUCKET}" created`);
  }
}

async function up() {
  ensureDirs();
  const pgRunning = await portOpen(PG_PORT);
  const s3Running = await portOpen(S3_PORT);

  if (!pgRunning) await startPostgres();
  else log("PostgreSQL already running");
  if (!s3Running) await startMinio();
  else log("MinIO already running");

  await ensureBucket();
  // Note: the `pastq` database itself is created by `npm run db:migrate`
  // (Prisma creates the target DB when missing).
  log("all services up ✔");
}

async function down() {
  for (const name of ["minio", "postgres"]) {
    const pid = readPid(name);
    if (pid && alive(pid)) {
      log(`stopping ${name} (pid ${pid})…`);
      killTree(pid);
    }
  }
  // pg_ctl stop is the graceful path for postgres (it may have been started by another session)
  if (await portOpen(PG_PORT)) {
    try {
      spawn(path.join(PG_BIN, "pg_ctl.exe"), ["-D", PG_DATA, "-m", "fast", "stop"], { stdio: "ignore", windowsHide: true });
      log("sent pg_ctl stop");
    } catch { /* best effort */ }
  }
  for (let i = 0; i < 20; i++) {
    if (!(await portOpen(PG_PORT)) && !(await portOpen(S3_PORT))) break;
    await sleep(500);
  }
  log("services down ✔");
}

async function status() {
  const pg = await portOpen(PG_PORT);
  const s3 = await portOpen(S3_PORT);
  log(`PostgreSQL :${PG_PORT} ${pg ? "UP" : "down"}`);
  log(`MinIO      :${S3_PORT} ${s3 ? "UP" : "down"} (console :9001)`);
  if (!pg || !s3) process.exitCode = 1;
}

const cmd = process.argv[2] || "up";
if (cmd === "up") await up();
else if (cmd === "down") await down();
else if (cmd === "status") await status();
else {
  console.error("usage: node scripts/services.mjs up|down|status");
  process.exit(1);
}

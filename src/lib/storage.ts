import {
  S3Client,
  PutObjectCommand,
  GetObjectCommand,
  HeadObjectCommand,
  HeadBucketCommand,
} from "@aws-sdk/client-s3";
import { createHash, randomBytes } from "crypto";

const endpoint = process.env.S3_ENDPOINT;
const region = process.env.S3_REGION ?? "us-east-1";
const bucket = process.env.S3_BUCKET ?? "pastq-private";

export const storageConfigured = Boolean(
  endpoint && process.env.S3_ACCESS_KEY_ID && process.env.S3_SECRET_ACCESS_KEY,
);

const s3 = new S3Client({
  region,
  endpoint, // omitted → real AWS S3
  forcePathStyle: true, // required for MinIO / most S3-compatible stores
  credentials: {
    accessKeyId: process.env.S3_ACCESS_KEY_ID ?? "",
    secretAccessKey: process.env.S3_SECRET_ACCESS_KEY ?? "",
  },
});

/** Content-disposition inline + browser PDF type; keys leak nothing. */
const RESPONSE_CONTENT_DISPOSITION = "inline";

/**
 * Content-hash sharded key (spec §32):
 *   private/resources/8f/4c/93b1c2e8.bin
 * Original filenames never appear in storage paths.
 */
export function buildStorageKey(): string {
  const bytes = randomBytes(16);
  const hex = createHash("sha256").update(bytes).digest("hex");
  return `private/resources/${hex.slice(0, 2)}/${hex.slice(2, 4)}/${hex.slice(4)}.bin`;
}

export async function ensureBucket(): Promise<void> {
  try {
    await s3.send(new HeadBucketCommand({ Bucket: bucket }));
  } catch {
    const { CreateBucketCommand } = await import("@aws-sdk/client-s3");
    await s3
      .send(new CreateBucketCommand({ Bucket: bucket }))
      .catch(() => undefined); // races with compose bootstrap are fine
  }
}

export async function putObject(
  key: string,
  body: Buffer,
  mimeType: string,
): Promise<void> {
  await s3.send(
    new PutObjectCommand({
      Bucket: bucket,
      Key: key,
      Body: body,
      ContentType: mimeType,
      ContentDisposition: RESPONSE_CONTENT_DISPOSITION,
    }),
  );
}

/** Post-upload verification: confirms the object actually landed. */
export async function getObjectHead(
  key: string,
): Promise<{ contentLength?: number; contentType?: string }> {
  const res = await s3.send(new HeadObjectCommand({ Bucket: bucket, Key: key }));
  return { contentLength: res.ContentLength, contentType: res.ContentType };
}

export async function getObjectStream(
  key: string,
  range?: string,
): Promise<{
  stream: ReadableStream<Uint8Array>;
  contentLength?: number;
  contentRange?: string;
}> {
  const res = await s3.send(
    new GetObjectCommand({ Bucket: bucket, Key: key, Range: range }),
  );
  return {
    stream: res.Body as ReadableStream<Uint8Array>,
    contentLength: res.ContentLength,
    contentRange: res.ContentRange,
  };
}

/**
 * Whole-object read as bytes. Handles every SDK Body shape (Node
 * Readable, web ReadableStream, Blob) — used by the PDF pipeline.
 */
export async function getObjectBuffer(key: string): Promise<Uint8Array> {
  const res = await s3.send(new GetObjectCommand({ Bucket: bucket, Key: key }));
  const body = res.Body as unknown as {
    transformToByteArray?: () => Promise<Uint8Array>;
  };
  if (typeof body?.transformToByteArray === "function") {
    return body.transformToByteArray();
  }
  const webStream = res.Body as unknown as ReadableStream<Uint8Array> | null;
  if (webStream && typeof webStream.getReader === "function") {
    const reader = webStream.getReader();
    const parts: Uint8Array[] = [];
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      if (value) parts.push(value);
    }
    const total = parts.reduce((n, p) => n + p.length, 0);
    const out = new Uint8Array(total);
    let off = 0;
    for (const p of parts) {
      out.set(p, off);
      off += p.length;
    }
    return out;
  }
  const nodeStream = res.Body as unknown as AsyncIterable<Uint8Array> | null;
  if (nodeStream && typeof (nodeStream as AsyncIterable<Uint8Array>)[Symbol.asyncIterator] === "function") {
    const parts: Uint8Array[] = [];
    for await (const chunk of nodeStream) {
      parts.push(chunk);
    }
    const total = parts.reduce((n, p) => n + p.length, 0);
    const out = new Uint8Array(total);
    let off = 0;
    for (const p of parts) {
      out.set(p, off);
      off += p.length;
    }
    return out;
  }
  throw new Error("Unsupported S3 response body");
}

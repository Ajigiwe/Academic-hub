/**
 * Shared PDF factories for scripts: generate structurally valid PDFs
 * (correct xref table) with no external tooling. Used by the upload
 * smoke tests and the demo-data prep script.
 */

/** Minimal but structurally valid N-page PDF (correct xref table). */
export function makePdf(
  pageCount: number,
  heading = "Question Paper",
  lines: string[] = [],
): Buffer {
  const enc = (s: string) => Buffer.from(s, "latin1");
  const chunks: Buffer[] = [];
  let offset = 0;
  const offsets: number[] = [];

  const push = (s: string) => {
    const b = enc(s);
    chunks.push(b);
    offset += b.length;
  };
  const beginObj = (n: number) => {
    offsets[n] = offset;
    push(`${n} 0 obj\n`);
  };

  push("%PDF-1.4\n");
  const kids = Array.from({ length: pageCount }, (_, i) => `${3 + i * 2} 0 R`).join(" ");
  const fontObjNum = 3 + pageCount * 2;

  beginObj(1);
  push(`<< /Type /Catalog /Pages 2 0 R >>\nendobj\n`);
  beginObj(2);
  push(`<< /Type /Pages /Kids [ ${kids} ] /Count ${pageCount} >>\nendobj\n`);

  for (let i = 0; i < pageCount; i++) {
    const contentNum = 4 + i * 2;
    beginObj(3 + i * 2);
    push(
      `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents ${contentNum} 0 R ` +
        `/Resources << /Font << /F1 ${fontObjNum} 0 R >> >> >>\nendobj\n`,
    );
    const pageLines = [
      heading,
      "End of Semester Examination",
      "",
      `Page ${i + 1} of ${pageCount}`,
      "",
      "Answer ALL questions. Time allowed: 2 hours.",
      "",
      ...lines,
    ];
    const text = pageLines
      .map((l, idx) => `BT /F1 ${idx === 0 ? 18 : 12} Tf 72 ${720 - idx * 22} Td (${l}) Tj ET`)
      .join("\n");
    beginObj(contentNum);
    push(`<< /Length ${text.length} >>\nstream\n${text}\nendstream\nendobj\n`);
  }

  beginObj(fontObjNum);
  push(`<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n`);

  const xrefStart = offset;
  let xref = `xref\n0 ${fontObjNum + 1}\n0000000000 65535 f \n`;
  for (let n = 1; n <= fontObjNum; n++) {
    xref += `${String(offsets[n]).padStart(10, "0")} 00000 n \n`;
  }
  push(xref);
  push(
    `trailer\n<< /Size ${fontObjNum + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`,
  );
  return Buffer.concat(chunks);
}

/** File parts need a plain ArrayBuffer (not a Node Buffer view). */
export function toFile(buf: Buffer, name: string): File {
  const ab = new ArrayBuffer(buf.length);
  new Uint8Array(ab).set(buf);
  return new File([ab], name, { type: "application/pdf" });
}

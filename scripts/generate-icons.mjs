// Regenerates Academic Hub PWA icons as PNGs from the same geometry the
// SVG icons use (AH monogram + waves, design units in a 120x132 grid).
// Uses @napi-rs/canvas — an existing app dependency — so no new packages.
//
// Usage: node scripts/generate-icons.mjs
import { createCanvas } from "@napi-rs/canvas";
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const outDir = join(root, "public", "icons");
mkdirSync(outDir, { recursive: true });

/**
 * Draw the AH monogram + waves in design units onto a context.
 * The design grid spans roughly x: 8-72, y: 22-120.
 */
function drawMonogram(ctx, s) {
  ctx.save();
  ctx.lineCap = "round";
  ctx.lineJoin = "round";
  ctx.strokeStyle = "#ffffff";
  const stroke = (x1, y1, x2, y2, w) => {
    ctx.beginPath();
    ctx.moveTo(x1 * s, y1 * s);
    ctx.lineTo(x2 * s, y2 * s);
    ctx.lineWidth = w * s;
    ctx.stroke();
  };
  const wave = (pts, c1, c2, w, color, alpha) => {
    ctx.strokeStyle = color;
    ctx.globalAlpha = alpha ?? 1;
    ctx.beginPath();
    ctx.moveTo(pts[0][0] * s, pts[0][1] * s);
    ctx.bezierCurveTo(c1[0] * s, c1[1] * s, c2[0] * s, c2[1] * s, pts[1][0] * s, pts[1][1] * s);
    ctx.lineWidth = w * s;
    ctx.stroke();
  };

  // A left leg
  stroke(40, 30, 16, 90, 10);
  // A right leg / H left stem (shared)
  stroke(40, 30, 40, 90, 10);
  // A crossbar
  stroke(24, 64, 44, 64, 8.5);
  // H right stem (taller)
  stroke(72, 22, 72, 90, 11);
  // H crossbar
  stroke(40, 58, 72, 58, 8.5);
  // waves
  wave([[8, 104], [62, 102]], [26, 97], [44, 111], 5, "#ffffff");
  wave([[18, 114], [70, 111]], [34, 108], [52, 120], 4, "#ffffff", 0.85);
  ctx.restore();
}

/**
 * Render a squared icon. `bg` is [top, bottom] for the gradient; pass
 * `rounded` to clip to an app-icon shape (false = full-bleed maskable).
 * `place` returns { scale, tx, ty } mapping the design grid into the canvas.
 */
function renderIcon(size, { bg, rounded = true, place }) {
  const canvas = createCanvas(size, size);
  const ctx = canvas.getContext("2d");

  if (rounded) {
    ctx.beginPath();
    ctx.roundRect(0, 0, size, size, size * 0.2);
    ctx.clip();
  }
  const grad = ctx.createLinearGradient(0, 0, size, size);
  grad.addColorStop(0, bg[0]);
  grad.addColorStop(1, bg[1]);
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, size, size);

  ctx.translate(place.tx, place.ty);
  drawMonogram(ctx, place.scale);
  return canvas;
}

// Place the 120x132 design grid so its content (x 8-72, y 22-120) is
// centered in the canvas. Content box per variant:
const placements = {
  "512": { scale: 512 / 128, tx: 512 / 2 - 40 * (512 / 128), ty: 512 / 2 - 71 * (512 / 128) },
  "192": { scale: 192 / 128, tx: 96 - 40 * (192 / 128), ty: 96 - 71 * (192 / 128) },
  "180": { scale: 180 / 128, tx: 90 - 40 * (180 / 128), ty: 90 - 71 * (180 / 128) },
};

// Design-grid center: x = (8+72)/2 = 40, y = (22+120)/2 = 71.
const files = [
  ["icon-512.png", renderIcon(512, { bg: ["#3b6df6", "#2563eb"], place: placements["512"] })],
  ["icon-192.png", renderIcon(192, { bg: ["#3b6df6", "#2563eb"], place: placements["192"] })],
  ["icon-maskable-512.png", renderIcon(512, { bg: ["#0b2d5b", "#0b2d5b"], rounded: false, place: placements["512"] })],
  ["apple-touch-icon.png", renderIcon(180, { bg: ["#2563eb", "#1d4ed8"], place: placements["180"] })],
];

for (const [name, canvas] of files) {
  writeFileSync(join(outDir, name), canvas.toBuffer("image/png"));
  console.log(`wrote public/icons/${name}`);
}
import type { FreeMaterial } from "@/lib/resources";
import { formatBytes } from "@/lib/resources";

const TYPE_LABEL: Record<string, string> = {
  PAST_QUESTION: "Past question",
  LECTURE_NOTES: "Lecture notes",
  SLIDES: "Slides",
  REVISION: "Revision",
  PRACTICE: "Practice",
};

export function FreeMaterialCard({ material }: { material: FreeMaterial }) {
  const file = material.files[0];
  return (
    <div className="card-padded flex flex-col gap-2.5">
      <div className="flex flex-wrap items-center gap-1.5">
        <span className="badge-brand font-semibold">{material.course.code}</span>
        <span className="badge-neutral">
          {TYPE_LABEL[material.type] ?? material.type}
        </span>
        <span className="badge-gold">Free</span>
      </div>
      <h3 className="line-clamp-2 font-semibold leading-snug text-neutral-900">
        {material.title}
      </h3>
      <p className="line-clamp-1 text-sm text-neutral-600">
        {material.programme.name} · Level {material.level} ·{" "}
        {material.semester === 2 ? "Second" : "First"} Semester
      </p>
      <div className="mt-auto flex items-end justify-between gap-2 pt-2">
        <p className="text-xs text-neutral-500">
          {file ? formatBytes(file.sizeBytes) : ""}
          {material.pageCount ? ` · ${material.pageCount} pages` : ""}
        </p>
        <a
          href={`/api/resources/${material.slug}/download`}
          className="btn-secondary btn-sm"
          download
        >
          Download
        </a>
      </div>
    </div>
  );
}
import Link from "next/link";
import type { SearchBundleItem } from "@/lib/resources";
import { formatPrice } from "@/lib/resources";

type CardBundle = Pick<
  SearchBundleItem,
  "id" | "slug" | "title" | "level" | "academicYear" | "pricePesewas"
> & {
  course: { code: string; title: string } | null;
  programme: { name: string } | null;
  solved?: boolean;
  _count: { resources: number };
};

export function BundleCard({ bundle }: { bundle: CardBundle }) {
  const paperCount = bundle._count?.resources ?? 0;
  return (
    <div className="card-padded group relative flex flex-col gap-2.5 overflow-hidden transition-all hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift">
      {/* Accent strip */}
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 h-0.5 origin-left scale-x-0 bg-gradient-to-r from-brand-600 to-gold-400 transition-transform duration-300 group-hover:scale-x-100"
      />

      <div className="flex flex-wrap items-center gap-1.5">
        <span className="badge-brand font-semibold">
          {bundle.course?.code ?? "Bundle"}
        </span>
        <span className="badge-neutral">Level {bundle.level}</span>
        <span className="badge-neutral">{bundle.academicYear}</span>
        {bundle.solved && (
          <span className="badge-success font-semibold">Solved</span>
        )}
      </div>

      <Link
        href={`/bundles/${bundle.slug}`}
        className="line-clamp-2 font-semibold leading-snug text-neutral-900 transition-colors hover:text-brand-800"
      >
        {bundle.title}
      </Link>

      <p className="line-clamp-1 text-sm text-neutral-600">
        {bundle.programme?.name ?? ""}
        {bundle.programme && bundle.course ? " · " : ""}
        {bundle.course?.title ?? ""}
      </p>

      <div className="mt-auto flex items-end justify-between gap-2 pt-2">
        <div>
          <p className="text-lg font-bold tracking-tight text-brand-800">
            {formatPrice(bundle.pricePesewas)}
          </p>
          <p className="text-[11px] font-medium text-neutral-500">
            {paperCount === 0
              ? "Papers coming soon"
              : `${paperCount} paper${paperCount === 1 ? "" : "s"} · one price`}
          </p>
        </div>
      </div>

      {paperCount > 0 && (
        <div className="mt-1 flex gap-2">
          <Link
            href={`/bundles/${bundle.slug}`}
            className="btn-primary flex-1 px-3 py-2 text-xs"
          >
            Buy bundle
          </Link>
          {/* Preview of the first paper's first page — public, no login. */}
          <Link
            href={`/bundles/${bundle.slug}#preview`}
            className="btn-secondary flex-1 px-3 py-2 text-xs"
          >
            <svg
              className="h-3.5 w-3.5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden
            >
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            Preview
          </Link>
        </div>
      )}
    </div>
  );
}

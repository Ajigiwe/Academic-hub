import Link from "next/link";

/**
 * Persistent buy bar pinned above the mobile bottom nav — the buy box in
 * the page flow sits below the papers list on phones, so this keeps the
 * price + CTA reachable from anywhere on the bundle page. Hidden on md+,
 * where the sticky sidebar buy box takes over.
 */
export function MobileBuyBar({
  href,
  price,
  papersLabel,
}: {
  href: string;
  price: string;
  papersLabel: string;
}) {
  return (
    // z-30: below the z-40 bottom nav so the nav stays on top; pb clears
    // the nav height (~56px) plus the iPhone home-indicator safe area.
    <div className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 border-t border-neutral-200 bg-white/95 px-4 py-2.5 shadow-[0_-2px_10px_rgb(16_24_40/0.06)] backdrop-blur-md md:hidden">
      <div className="mx-auto flex max-w-lg items-center gap-3">
        <div className="min-w-0">
          <p className="truncate text-base font-extrabold tracking-tight text-neutral-900">
            {price}
          </p>
          <p className="truncate text-[11px] text-neutral-500">{papersLabel}</p>
        </div>
        <Link href={href} className="btn-gold ml-auto shrink-0">
          Buy Bundle
        </Link>
      </div>
    </div>
  );
}

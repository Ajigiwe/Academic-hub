export const NAVY = "#0b2d5b";
export const BLUE = "#2563eb";
const SLATE = "#24417a";

/**
 * Academic Hub mark — the AH monogram + waves. Default colors are for
 * light backgrounds; pass `light` for dark surfaces (all-white glyph).
 */
export function LogoMark({ className, light = false }: { className?: string; light?: boolean }) {
  const body = light ? "#ffffff" : SLATE;
  const accent = light ? "#ffffff" : BLUE;
  const navy = light ? "#ffffff" : NAVY;
  return (
    <svg viewBox="0 0 120 132" fill="none" className={className} aria-hidden>
      <g strokeLinecap="round" strokeLinejoin="round">
        {/* A left leg */}
        <path d="M40 30 L16 90" stroke={body} strokeWidth={10} />
        {/* A right leg / H left stem (shared) */}
        <path d="M40 30 L40 90" stroke={body} strokeWidth={10} />
        {/* A crossbar (accent) */}
        <path d="M24 64 L44 64" stroke={accent} strokeWidth={8.5} />
        {/* H right stem (taller) */}
        <path d="M72 22 L72 90" stroke={navy} strokeWidth={11} />
        {/* H crossbar */}
        <path d="M40 58 L72 58" stroke={body} strokeWidth={8.5} />
        {/* waves */}
        <path d="M8 104 C26 97 44 111 62 102" stroke={accent} strokeWidth={5} />
        <path d="M18 114 C34 108 52 120 70 111" stroke={navy} strokeWidth={4} />
      </g>
    </svg>
  );
}

/**
 * Full logo lockup: mark + "Academic Hub" wordmark, optionally with the
 * "Past Questions. Smarter Preparation." tagline stacked beneath.
 */
export function Logo({
  className = "",
  light = false,
  tagline = false,
}: {
  className?: string;
  light?: boolean;
  tagline?: boolean;
}) {
  return (
    <span className={`flex items-center gap-2.5 ${className}`}>
      <LogoMark light={light} className="h-9 w-9 shrink-0" />
      <span className="flex flex-col leading-tight">
        <span className="text-[15px] font-bold tracking-tight">
          <span style={{ color: light ? "#ffffff" : NAVY }}>Academic</span>{" "}
          <span style={{ color: light ? "#ffffff" : BLUE }}>Hub</span>
        </span>
        {tagline && (
          <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
            Past Questions. Smarter Preparation.
          </span>
        )}
      </span>
    </span>
  );
}
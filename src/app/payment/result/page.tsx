import Link from "next/link";

export const dynamic = "force-dynamic";

const shells = {
  success: {
    icon: (
      <span className="grid h-16 w-16 place-items-center rounded-full bg-brand-50 ring-8 ring-brand-50/50">
        <svg className="h-8 w-8 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="M20 6 9 17l-5-5" />
        </svg>
      </span>
    ),
    tint: "from-brand-50/60 to-neutral-50",
  },
  cancelled: {
    icon: (
      <span className="grid h-16 w-16 place-items-center rounded-full bg-neutral-100 ring-8 ring-neutral-100/50">
        <svg className="h-8 w-8 text-neutral-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="M9 14 4 9l5-5" />
          <path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11" />
        </svg>
      </span>
    ),
    tint: "from-neutral-50 to-white",
  },
  failed: {
    icon: (
      <span className="grid h-16 w-16 place-items-center rounded-full bg-amber-50 ring-8 ring-amber-50/50">
        <svg className="h-8 w-8 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
          <path d="M12 9v4" />
          <path d="M12 17h.01" />
        </svg>
      </span>
    ),
    tint: "from-amber-50/50 to-neutral-50",
  },
} as const;

function Shell({
  status,
  title,
  reference,
  children,
  actions,
}: {
  status: keyof typeof shells;
  title: string;
  reference?: string;
  children: React.ReactNode;
  actions: React.ReactNode;
}) {
  const s = shells[status];
  return (
    <div className={`border-b border-neutral-200 bg-gradient-to-b ${s.tint}`}>
      <div className="container-page flex max-w-md flex-col items-center py-16 text-center">
        {s.icon}
        <h1 className="mt-5 text-2xl font-bold tracking-tight text-neutral-900">
          {title}
        </h1>
        {reference && (
          <p className="mt-2 rounded-full border border-neutral-200 bg-white px-3 py-1 font-mono text-xs text-neutral-500 shadow-sm">
            {reference}
          </p>
        )}
        <div className="mt-3 max-w-sm text-sm leading-relaxed text-neutral-600">
          {children}
        </div>
        <div className="mt-7 flex flex-wrap justify-center gap-3">{actions}</div>
      </div>
    </div>
  );
}

export default async function PaymentResultPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string; ref?: string; reason?: string }>;
}) {
  const { status, ref, reason } = await searchParams;

  if (status === "success") {
    return (
      <Shell
        status="success"
        title="Payment confirmed"
        reference={ref}
        actions={
          <>
            <Link href="/library" className="btn-primary">
              Go to My Library
            </Link>
            <Link href="/search" className="btn-secondary">
              Keep browsing
            </Link>
          </>
        }
      >
        <p>
          Your resources have been added to your library — ready to read on
          any device, anytime.
        </p>
      </Shell>
    );
  }

  if (status === "cancelled") {
    return (
      <Shell
        status="cancelled"
        title="Payment cancelled"
        reference={ref}
        actions={
          <Link href="/search" className="btn-primary">
            Browse resources
          </Link>
        }
      >
        <p>
          No money was taken. Your cart is saved — you can retry whenever you
          are ready.
        </p>
      </Shell>
    );
  }

  return (
    <Shell
      status="failed"
      title="We could not confirm your payment"
      reference={ref}
      actions={
        <>
          <Link href="/library" className="btn-primary">
            Check My Library
          </Link>
          <Link href="/help" className="btn-secondary">
            Contact support
          </Link>
        </>
      }
    >
      <p>
        {reason ??
          "If you were debited, don't worry — payments are reconciled automatically. Contact support with your order reference and we'll sort it out."}
      </p>
    </Shell>
  );
}

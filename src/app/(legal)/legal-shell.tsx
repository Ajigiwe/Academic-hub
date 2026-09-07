export function LegalShell({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="container-page max-w-3xl py-10 sm:py-14">
      <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
        {title}
      </h1>
      <p className="mt-1.5 text-xs uppercase tracking-wider text-neutral-400">
        Last updated: September 2026
      </p>
      <div className="prose-neutral mt-8 space-y-6 text-sm leading-relaxed text-neutral-700 [&_h2]:mt-8 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-neutral-900 [&_li]:ml-5 [&_li]:list-disc [&_p]:text-neutral-700">
        {children}
      </div>
    </div>
  );
}

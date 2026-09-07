import Link from "next/link";
import { LegalShell } from "../legal-shell";

export const metadata = { title: "Help & Support" };

const faqs = [
  {
    q: "How do I get access to a past question?",
    a: "Create a free account, find the resource, and pay with Mobile Money or card. Access is granted instantly after payment confirms and lives in My Library forever.",
  },
  {
    q: "My payment went through but I don't see the resource.",
    a: "Return to the site — verification happens automatically when you come back. If it still doesn't appear after a few minutes, contact support with your order number (found on your Account page).",
  },
  {
    q: "Can I download or print the documents?",
    a: "Documents are read inside the secure viewer to protect copyright, with your identity watermarked on each page. Downloads and printing are intentionally not available.",
  },
  {
    q: "Can I share my account with a friend?",
    a: "No. Accounts are personal, and sharing violates the Terms of Service. With prices from just a few cedis, it's cheaper to buy your own copy.",
  },
  {
    q: "I found an error in a paper. What do I do?",
    a: "Please report it — use the contact details below with the course code and year, and our team will verify and fix it.",
  },
];

export default function HelpPage() {
  return (
    <LegalShell title="Help & Support">
      <div className="space-y-3">
        {faqs.map((f) => (
          <details
            key={f.q}
            className="group rounded-xl border border-neutral-200 bg-white p-4 shadow-sm [&_summary::-webkit-details-marker]:hidden"
          >
            <summary className="flex cursor-pointer items-center justify-between gap-3 text-sm font-semibold text-neutral-900">
              {f.q}
              <svg
                className="h-4 w-4 shrink-0 text-neutral-400 transition-transform group-open:rotate-180"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden
              >
                <path d="m6 9 6 6 6-6" />
              </svg>
            </summary>
            <p className="mt-2 text-sm leading-relaxed text-neutral-600">{f.a}</p>
          </details>
        ))}
      </div>

      <div className="rounded-xl border border-brand-100 bg-brand-50 p-5">
        <h2 className="!mt-0">Still need help?</h2>
        <p>
          Email <span className="font-semibold">support@academicresourcehub.com</span>{" "}
          with your order number and a short description — we respond within
          one working day.
        </p>
        <Link href="/search" className="btn-primary mt-4">
          Browse the catalog
        </Link>
      </div>
    </LegalShell>
  );
}

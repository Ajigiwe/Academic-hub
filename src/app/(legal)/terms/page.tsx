import { LegalShell } from "../legal-shell";

export const metadata = { title: "Terms of Service" };

export default function TermsPage() {
  return (
    <LegalShell title="Terms of Service">
      <p>
        These terms govern your use of the Academic Resource Hub platform
        (&quot;the Platform&quot;), operated for a single partner institution
        during the pilot phase. By creating an account or purchasing a
        resource, you accept these terms.
      </p>

      <h2>1. Accounts</h2>
      <p>
        You must provide accurate registration details and keep your
        credentials confidential. Account sharing is prohibited — entitlements
        are personal to your account. We may suspend accounts engaged in fraud
        or abuse of the platform.
      </p>

      <h2>2. Purchases and access</h2>
      <p>
        Purchased resources are licensed to you for personal, non-commercial
        study. Access is granted through your in-platform library and does not
        expire for the lifetime of the platform unless a resource is withdrawn
        for legal reasons, in which case a refund is issued.
      </p>

      <h2>3. Acceptable use</h2>
      <ul>
        <li>
          Do not download, redistribute, resell, or share purchased documents
          outside the platform.
        </li>
        <li>
          Do not attempt to circumvent access controls, watermarking, or
          security measures.
        </li>
        <li>Do not misrepresent your identity or institution.</li>
      </ul>

      <h2>4. Copyright</h2>
      <p>
        Past examination papers and academic materials remain the property of
        their authors or institutions. The Platform distributes materials only
        under agreement with the owning institution. If you believe material
        infringes your rights, contact us through the Help page.
      </p>

      <h2>5. Liability</h2>
      <p>
        The Platform is provided &quot;as is&quot;. While we work to keep the
        catalog accurate and available, we are not liable for exam outcomes or
        indirect losses arising from use of the Platform.
      </p>

      <h2>6. Changes</h2>
      <p>
        We may update these terms as the platform grows. Material changes will
        be announced in-app. Continued use after changes take effect
        constitutes acceptance.
      </p>
    </LegalShell>
  );
}

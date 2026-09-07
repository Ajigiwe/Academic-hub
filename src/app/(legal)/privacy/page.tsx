import { LegalShell } from "../legal-shell";

export const metadata = { title: "Privacy Policy" };

export default function PrivacyPage() {
  return (
    <LegalShell title="Privacy Policy">
      <p>
        This policy explains what data Academic Resource Hub collects and how
        it is used. We collect the minimum needed to run a secure marketplace.
      </p>

      <h2>What we collect</h2>
      <ul>
        <li>
          <strong>Account data:</strong> name, email, institution programme,
          and a securely hashed password.
        </li>
        <li>
          <strong>Purchase data:</strong> orders, payments, and the resources
          you unlock.
        </li>
        <li>
          <strong>Security data:</strong> sign-in sessions and access logs
          that protect your account and detect fraud.
        </li>
      </ul>

      <h2>How it is used</h2>
      <p>
        Your data is used to operate your library, fulfil purchases, provide
        support, and keep the platform secure. We do not sell personal data.
        Purchase history may be shown to administrators of the platform for
        support and accounting purposes.
      </p>

      <h2>Watermarking</h2>
      <p>
        Documents you open are marked with your identity for leak deterrence.
        This marking is applied to the copy you read and is tied to your
        account.
      </p>

      <h2>Payments</h2>
      <p>
        Payments are processed by our payment provider (Mobile Money and cards
        in Ghana cedis). We never see or store your card PIN, Mobile Money PIN,
        or full card number.
      </p>

      <h2>Data retention and deletion</h2>
      <p>
        You can request account deletion at any time via the Help page. We
        retain transaction records where required for accounting and legal
        obligations, separated from your profile where possible.
      </p>
    </LegalShell>
  );
}

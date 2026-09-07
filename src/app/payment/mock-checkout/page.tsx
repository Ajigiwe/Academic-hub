import type { Metadata } from "next";
import { MockCheckoutForm } from "@/components/mock-checkout-form";

export const metadata: Metadata = { title: "Checkout (sandbox)" };

export default async function MockCheckoutPage({
  searchParams,
}: {
  searchParams: Promise<{ ref?: string; amount?: string }>;
}) {
  const { ref, amount } = await searchParams;

  if (!ref) {
    return (
      <div className="container-page py-16 text-center">
        <p className="text-neutral-600">Missing payment reference.</p>
      </div>
    );
  }

  return (
    <div className="container-page flex justify-center py-12">
      <MockCheckoutForm
        reference={ref}
        amountPesewas={Number(amount) || 0}
      />
    </div>
  );
}

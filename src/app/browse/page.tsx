import { redirect } from "next/navigation";

export default async function BrowsePage({
  searchParams,
}: {
  searchParams: Promise<{ sort?: string }>;
}) {
  const { sort } = await searchParams;
  const params = new URLSearchParams();
  if (sort) params.set("sort", sort);
  redirect(`/search${params.size ? `?${params.toString()}` : ""}`);
}

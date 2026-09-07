import { prisma } from "@/lib/db";
import { revalidatePath } from "next/cache";

export const dynamic = "force-dynamic";

async function setUserStatus(formData: FormData) {
  "use server";

  const { getCurrentUser } = await import("@/lib/auth");
  const admin = await getCurrentUser();
  if (!admin || admin.role !== "ADMIN") return;

  const userId = String(formData.get("userId") ?? "");
  const status = String(formData.get("status") ?? "");
  if (!userId || !["ACTIVE", "SUSPENDED"].includes(status)) return;
  if (userId === admin.id) return; // don't lock yourself out

  await prisma.user.update({
    where: { id: userId },
    data: { status: status as "ACTIVE" | "SUSPENDED" },
  });
  revalidatePath("/admin/students");
}

export default async function AdminStudentsPage() {
  const { getCurrentUser } = await import("@/lib/auth");
  const admin = await getCurrentUser();

  const students = await prisma.user.findMany({
    orderBy: { createdAt: "desc" },
    take: 200,
    include: {
      _count: { select: { orders: true, entitlements: true } },
    },
  });

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Students</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Suspended users cannot log in and lose viewer access immediately
        (enforced server-side on every session check).
      </p>

      <div className="card mt-5 md:overflow-x-auto">
        <table className="table-base table-responsive">
          <thead>
            <tr>
              <th>Student</th>
              <th>Purchases</th>
              <th>Library items</th>
              <th>Status</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {students.map((s) => (
              <tr key={s.id}>
                <td data-label="Student">
                  <div>
                    <p className="text-right font-medium">
                      {s.firstName} {s.lastName}
                      {s.role === "ADMIN" && (
                        <span className="badge-gold ml-2">admin</span>
                      )}
                    </p>
                    <p className="text-right text-xs text-neutral-500">{s.email}</p>
                  </div>
                </td>
                <td data-label="Purchases" className="tabular-nums">{s._count.orders}</td>
                <td data-label="Library items" className="tabular-nums">{s._count.entitlements}</td>
                <td data-label="Status">
                  <span
                    className={
                      s.status === "ACTIVE" ? "badge-success" : "badge-danger"
                    }
                  >
                    {s.status}
                  </span>
                </td>
                <td data-label="Joined" className="whitespace-nowrap text-neutral-600">
                  {s.createdAt.toLocaleDateString("en-GB")}
                </td>
                <td data-label="">
                  {admin && s.id !== admin.id && (
                    <form action={setUserStatus}>
                      <input type="hidden" name="userId" value={s.id} />
                      <input
                        type="hidden"
                        name="status"
                        value={s.status === "ACTIVE" ? "SUSPENDED" : "ACTIVE"}
                      />
                      <button
                        className={
                          s.status === "ACTIVE" ? "btn-danger btn-sm" : "btn-secondary btn-sm"
                        }
                      >
                        {s.status === "ACTIVE" ? "Suspend" : "Reactivate"}
                      </button>
                    </form>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

import { getAllProgrammesWithVisibility, programmeSettingKey } from "@/lib/settings";
import { getProgrammeContentCounts } from "@/lib/resources";
import {
  saveProgrammeSettingsAction,
  addProgrammeAction,
  deleteProgrammeAction,
} from "@/lib/admin-actions";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const programmes = await getAllProgrammesWithVisibility();
  const counts = await getProgrammeContentCounts();

  // Outcome of the last delete attempt, carried by deleteProgrammeAction's
  // redirect so the result is never a silent no-op.
  const sp = await searchParams;
  const notice = typeof sp.notice === "string" ? sp.notice : undefined;
  const noticeName = typeof sp.name === "string" ? sp.name : undefined;
  const noticeBundles = Number(sp.bundles ?? 0);
  const noticeResources = Number(sp.resources ?? 0);

  const noticeBanner =
    notice === "deleted" ? (
      <p className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {noticeName
          ? `Programme “${noticeName}” was deleted.`
          : "Programme deleted."}
      </p>
    ) : notice === "blocked" ? (
      <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Couldn’t delete {noticeName ? `“${noticeName}”` : "the programme"} — it
        still has {noticeBundles} bundle{noticeBundles === 1 ? "" : "s"} and{" "}
        {noticeResources} paper{noticeResources === 1 ? "" : "s"} attached. Move
        or remove that content first, or just toggle the track off above.
      </p>
    ) : notice === "denied" ? (
      <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Admin access required.
      </p>
    ) : notice === "missing" ? (
      <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        No programme was selected for deletion.
      </p>
    ) : null;

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Settings</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Platform-wide controls. Changes apply to the public site immediately.
      </p>

      {noticeBanner}

      <form
        action={saveProgrammeSettingsAction}
        className="card mt-5 max-w-2xl p-5"
      >
        <h2 className="text-base font-semibold text-neutral-900">Programmes</h2>
        <p className="mt-1 text-sm text-neutral-600">
          Students only see the programme tracks switched on below. Content
          under a hidden track stays in the catalogue but disappears from
          browsing, filters, and search until you re-enable it.
        </p>

        <div className="mt-4 space-y-2">
          {programmes.map((p) => (
            <label
              key={p.slug}
              className="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-neutral-200 bg-white px-4 py-3 transition-colors hover:border-brand-300"
            >
              <span className="min-w-0">
                <span className="block text-sm font-semibold text-neutral-900">
                  {p.name}
                </span>
                <span className="mt-0.5 block text-xs text-neutral-500">
                  {p.slug} · visible to students:{" "}
                  <span className={p.enabled ? "font-semibold text-green-700" : "font-semibold text-red-600"}>
                    {p.enabled ? "Yes" : "No"}
                  </span>
                </span>
              </span>
              <span className="relative inline-flex shrink-0">
                <input
                  type="checkbox"
                  name={programmeSettingKey(p.slug)}
                  defaultChecked={p.enabled}
                  className="peer sr-only"
                />
                <span className="h-6 w-11 rounded-full bg-neutral-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-brand-600 peer-checked:after:translate-x-5" />
              </span>
            </label>
          ))}
        </div>

        <div className="mt-5 flex items-center gap-3">
          <button type="submit" className="btn-primary">
            Save changes
          </button>
          <p className="text-xs text-neutral-500">
            Unchecking a programme hides it from students immediately.
          </p>
        </div>
      </form>

      {/* Add a new programme track */}
      <form action={addProgrammeAction} className="card mt-5 max-w-2xl p-5">
        <h2 className="text-base font-semibold text-neutral-900">
          Add a programme
        </h2>
        <p className="mt-1 text-sm text-neutral-600">
          New tracks appear in the student browse flow (programme → level →
          semester) as soon as they are saved and enabled.
        </p>
        <div className="mt-4 flex flex-wrap items-end gap-3">
          <div className="min-w-0 flex-1">
            <label className="label" htmlFor="new-programme-name">
              Full name
            </label>
            <input
              id="new-programme-name"
              name="name"
              className="input"
              placeholder="e.g. Bachelor of Science in IT"
              maxLength={120}
              required
            />
          </div>
          <button type="submit" className="btn-secondary">
            Add programme
          </button>
        </div>
        <p className="mt-2 text-xs text-neutral-500">
          The URL slug is generated from the name (e.g. “bachelor-of-science-in-it”).
        </p>
      </form>

      {/* Remove a programme track — one form per programme so every
          button names its target and can grey out content-ful tracks. */}
      <section className="card mt-5 max-w-2xl p-5">
        <h2 className="text-base font-semibold text-neutral-900">
          Remove a programme
        </h2>
        <p className="mt-1 text-sm text-neutral-600">
          A track can only be deleted once it has no bundles or papers
          attached. Tracks you merely want off the site can simply be
          toggled off above — content stays but is hidden from students.
        </p>
        {programmes.length === 0 ? (
          <p className="mt-4 text-sm text-neutral-500">No programmes to remove.</p>
        ) : (
          <div className="mt-4 space-y-2">
            {programmes.map((p) => {
              const count = counts.get(p.slug) ?? { bundles: 0, materials: 0 };
              const deletable = count.bundles === 0 && count.materials === 0;
              return (
                <div
                  key={p.slug}
                  className={`flex items-center justify-between gap-4 rounded-xl border px-4 py-3 ${
                    deletable
                      ? "border-neutral-200 bg-white"
                      : "border-neutral-100 bg-neutral-50"
                  }`}
                >
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-neutral-900">
                      {p.name}
                    </p>
                    <p className="mt-0.5 text-xs text-neutral-500">
                      {deletable
                        ? "No bundles or papers — safe to delete"
                        : `${count.bundles} bundle${count.bundles === 1 ? "" : "s"} · ${count.materials} paper${count.materials === 1 ? "" : "s"} attached`}
                    </p>
                  </div>
                  <form action={deleteProgrammeAction} className="shrink-0">
                    <input type="hidden" name="slug" value={p.slug} />
                    <button
                      type="submit"
                      className="btn-danger btn-sm"
                      disabled={!deletable}
                      title={
                        deletable
                          ? `Delete ${p.name} permanently`
                          : `${p.name} still has content — unpublish or move it first`
                      }
                    >
                      Delete
                    </button>
                  </form>
                </div>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
}

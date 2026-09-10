import { getAllProgrammesWithVisibility, programmeSettingKey } from "@/lib/settings";
import {
  saveProgrammeSettingsAction,
  addProgrammeAction,
  deleteProgrammeAction,
} from "@/lib/admin-actions";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  const programmes = await getAllProgrammesWithVisibility();

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Settings</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Platform-wide controls. Changes apply to the public site immediately.
      </p>

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

      {/* Remove a programme track */}
      <form action={deleteProgrammeAction} className="card mt-5 max-w-2xl p-5">
        <h2 className="text-base font-semibold text-neutral-900">
          Remove a programme
        </h2>
        <p className="mt-1 text-sm text-neutral-600">
          Deleting a programme is only possible when it has no bundles or
          resources attached. Tracks you merely want off the site can simply
          be toggled off above — content stays but is hidden from students.
        </p>
        {programmes.length === 0 ? (
          <p className="mt-4 text-sm text-neutral-500">No programmes to remove.</p>
        ) : (
          <div className="mt-4 flex flex-wrap items-end gap-3">
            <div className="min-w-0 flex-1">
              <label className="label" htmlFor="delete-programme-slug">
                Programme
              </label>
              <select
                id="delete-programme-slug"
                name="slug"
                className="input"
                required
              >
                {programmes.map((p) => (
                  <option key={p.slug} value={p.slug}>
                    {p.name} ({p.slug})
                  </option>
                ))}
              </select>
            </div>
            <button type="submit" className="btn-danger">
              Delete programme
            </button>
          </div>
        )}
      </form>
    </div>
  );
}

import { PROGRAMMES } from "@/lib/programmes";
import { getProgrammeSettings, programmeSettingKey } from "@/lib/settings";
import { saveProgrammeSettingsAction } from "@/lib/admin-actions";

export const dynamic = "force-dynamic";

export default async function AdminSettingsPage() {
  const flags = await getProgrammeSettings();

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
          {PROGRAMMES.map((p) => {
            const enabled = flags.get(p.slug) ?? true;
            return (
              <label
                key={p.slug}
                className="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-neutral-200 bg-white px-4 py-3 transition-colors hover:border-brand-300"
              >
                <span className="min-w-0">
                  <span className="block text-sm font-semibold text-neutral-900">
                    {p.name}
                  </span>
                  <span className="mt-0.5 block text-xs text-neutral-500">
                    {p.short} · visible to students:{" "}
                    <span className={enabled ? "font-semibold text-green-700" : "font-semibold text-red-600"}>
                      {enabled ? "Yes" : "No"}
                    </span>
                  </span>
                </span>
                <span className="relative inline-flex shrink-0">
                  <input
                    type="checkbox"
                    name={programmeSettingKey(p.slug)}
                    defaultChecked={enabled}
                    className="peer sr-only"
                  />
                  <span className="h-6 w-11 rounded-full bg-neutral-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-brand-600 peer-checked:after:translate-x-5" />
                </span>
              </label>
            );
          })}
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
    </div>
  );
}
<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Student notifications — the display side the Next.js app never built
 * (it created PURCHASE notifications but had no UI). Bell count + list,
 * strictly scoped to the signed-in user.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => Notification::where('user_id', $request->user()->id)->unread()->count(),
            'groups' => $this->groupByDay($notifications->getCollection()),
        ]);
    }

    /**
     * Open a notification: mark it read, then send the student where it
     * points. A notification with nowhere to go (an old row, or a system
     * notice) just comes back to the list with its unread dot cleared.
     */
    public function open(Request $request, Notification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        if ($notification->isUnread()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        $destination = $notification->destination();

        return $destination
            ? redirect()->to($destination)
            : redirect()->route('notifications.index');
    }

    /** Mark every unread notification read (the list's "mark all" action). */
    public function readAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * The list reads better with time on its side: Today / Yesterday /
     * a date. Grouping is presentation only, so it lives here rather than
     * in a query.
     *
     * @param  Collection<int, Notification>  $notifications
     * @return array<string, Collection<int, Notification>>  label => rows
     */
    private function groupByDay(Collection $notifications): array
    {
        return $notifications
            ->groupBy(function (Notification $notification) {
                $day = $notification->created_at?->toDateString();

                return match ($day) {
                    now()->toDateString() => 'Today',
                    now()->subDay()->toDateString() => 'Yesterday',
                    default => $notification->created_at?->format('j M Y') ?? 'Earlier',
                };
            })
            ->all();
    }
}

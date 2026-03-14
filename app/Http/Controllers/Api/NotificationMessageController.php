<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\MarkAllNotificationMessagesReadAction;
use App\Actions\Notifications\MarkNotificationMessageReadAction;
use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Http\Controllers\Controller;
use App\Models\NotificationMessage;
use App\Models\User;
use App\Support\Notifications\NotificationCatalog;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $family = $request->string('family', 'all')->toString();
        $status = $request->string('status', 'unread')->toString();
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $query = $user->notificationMessages()
            ->visibleInInbox()
            ->when($family !== 'all' && array_key_exists($family, NotificationCatalog::families()), fn ($builder) => $builder->where('family', $family))
            ->when($status === 'unread', fn ($builder) => $builder->whereNull('read_at'))
            ->when($status === 'read', fn ($builder) => $builder->whereNotNull('read_at'));

        $notifications = $query->paginate($perPage);

        return response()->json([
            'data' => collect($notifications->items())
                ->map(fn (NotificationMessage $message): array => $this->messageData($message))
                ->all(),
            'meta' => [
                'unread_count' => $user->notificationMessages()->visibleInInbox()->whereNull('read_at')->count(),
                'pagination' => [
                    'page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ],
        ]);
    }

    public function read(
        Request $request,
        string $message,
        MarkNotificationMessageReadAction $markNotificationMessageReadAction,
    ): JsonResponse {
        $notification = $markNotificationMessageReadAction->handle($this->currentUser($request), $message, $request);

        return response()->json([
            'message' => __('notifications.api.read_success'),
            'data' => $this->messageData($notification),
        ]);
    }

    public function readAll(Request $request, MarkAllNotificationMessagesReadAction $markAllNotificationMessagesReadAction): JsonResponse
    {
        $updated = $markAllNotificationMessagesReadAction->handle($this->currentUser($request), $request);

        return response()->json([
            'message' => __('notifications.api.read_all_success'),
            'data' => ['updated_count' => $updated],
        ]);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user->fresh() ?? $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageData(NotificationMessage $message): array
    {
        $family = $message->family;
        $trigger = $message->trigger;
        $priority = $message->priority;
        $occurredAt = $message->occurred_at;
        $readAt = $message->read_at;

        return [
            'id' => $message->id,
            'family' => $family instanceof NotificationFamily ? $family->value : (string) $family,
            'trigger' => $trigger instanceof NotificationTrigger ? $trigger->value : (string) $trigger,
            'title' => $message->title,
            'body' => $message->body,
            'action_url' => $message->action_url,
            'entity_type' => $message->entity_type,
            'entity_id' => $message->entity_id,
            'priority' => $priority instanceof NotificationPriority ? $priority->value : (string) $priority,
            'occurred_at' => $occurredAt instanceof CarbonInterface ? $occurredAt->toIso8601String() : null,
            'read_at' => $readAt instanceof CarbonInterface ? $readAt->toIso8601String() : null,
            'channels_attempted' => $message->channels_attempted ?? [],
            'meta' => $message->meta ?? [],
        ];
    }
}

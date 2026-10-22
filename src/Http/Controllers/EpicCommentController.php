<?php

namespace Projects13\Http\Controllers;

use Projects13\Models\Epic;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Projects13\Models\EpicComment;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Controllers\Controller;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Http\Requests\EpicCommentRequest;

class EpicCommentController extends Controller
{
    public function show(Epic $epic): JsonResponse
    {
        $this->authorize('viewAny', Epic::class);

        $comments = $epic->comments()
            ->with('user:id,name')
            ->latest()
            ->latest('id')
            ->limit(EpicListQuery::RECENT_COMMENTS_LIMIT)
            ->get()
            ->map(function (EpicComment $comment): array {
                $author = $comment->user;

                return [
                    'id' => $comment->id,
                    'author' => $author === null ? __('Deleted user') : $author->getAttribute('name'),
                    'dateTime' => $comment->created_at?->toIso8601String(),
                    'body' => $comment->body,
                ];
            })
            ->values();

        return response()->json(['comments' => $comments]);
    }

    public function store(EpicCommentRequest $request, Epic $epic): RedirectResponse
    {
        // The epic stays locked until the comment is written, so a delete that checks for comments
        // either runs first (and the epic is trashed here) or waits and then finds this comment.
        $commented = DB::transaction(static function () use ($request, $epic): bool {
            $lockedEpic = Epic::withTrashed()
                ->whereKey($epic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEpic->trashed()) {
                return false;
            }

            $comment = $lockedEpic->comments()->make($request->validated());
            $comment->user()->associate($request->user());
            $comment->save();

            return true;
        });

        if (! $commented) {
            return back(fallback: route('epics.index'))
                ->with('error', __('Cannot comment on a deleted epic.'));
        }

        return back(fallback: route('epics.index'))
            ->with('status', __('Comment added successfully.'))
            ->with('commented_epic_id', $epic->id);
    }
}

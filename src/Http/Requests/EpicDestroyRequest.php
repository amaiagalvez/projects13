<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\TrashDestroyRequest;

/**
 * @extends TrashDestroyRequest<Epic>
 */
final class EpicDestroyRequest extends TrashDestroyRequest
{
    /**
     * @return Epic
     */
    protected function findTrashed(): Model
    {
        return Epic::onlyTrashed()
            ->findOrFail((int) $this->route('epic'));
    }
}

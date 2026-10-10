<?php

namespace Projects13\Http\Requests;

use Customers13\Models\Customer;
use Basics13\Http\Requests\SearchableListRequest;

class TimelineListRequest extends SearchableListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }
}

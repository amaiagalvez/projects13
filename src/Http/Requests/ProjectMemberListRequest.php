<?php

namespace Projects13\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectMemberListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}

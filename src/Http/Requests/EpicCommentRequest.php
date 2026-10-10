<?php

namespace Projects13\Http\Requests;

use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class EpicCommentRequest extends FormRequest
{
    /**
     * Keep comment errors apart from the epic form errors shown in the same drawer.
     *
     * @var string
     */
    protected $errorBag = 'comment';

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (is_string($body)) {
            $this->merge(['body' => trim($body)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('comment', $this->route('epic')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.MaxLength::longText()],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
        ];
    }
}

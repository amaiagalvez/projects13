<?php

namespace Projects13\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
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

    public function authorize(): bool|Response
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $response = Gate::forUser($user)->inspect('comment', $this->route('epic'));

        return $response->allowed() ?: $response;
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

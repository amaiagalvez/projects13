<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Validation\Rule;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class PlanningListRequest extends FormRequest
{
    /**
     * The two ways the planning screen can lay out the very same rows. They are links rather than
     * panels, so the choice travels in the URL: it survives a reload and can be shared or
     * bookmarked. Anything else falls back to the first one.
     *
     * @var non-empty-list<string>
     */
    public const VIEWS = ['roadmap', 'timeline'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Project::class) ?? false;
    }

    /**
     * A layout is a link, not user data to be corrected: a stale or hand-edited `?view=` should land
     * on the roadmap, not bounce the user back with a validation error. Normalising before
     * validation keeps the whitelist in `rules()` as the single place that names the layouts.
     */
    protected function prepareForValidation(): void
    {
        if (! in_array($this->query('view'), self::VIEWS, true)) {
            $this->merge(['view' => self::VIEWS[0]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.MaxLength::string()],
            'view' => ['nullable', 'string', Rule::in(self::VIEWS)],
        ];
    }

    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    public function view(): string
    {
        $view = $this->string('view')->toString();

        return in_array($view, self::VIEWS, true) ? $view : self::VIEWS[0];
    }
}

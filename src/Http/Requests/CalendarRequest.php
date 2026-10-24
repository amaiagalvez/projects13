<?php

namespace Projects13\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    /**
     * The four ways the calendar lays out the same events. Links rather than panels, so the choice
     * travels in the URL together with the day and the visible calendars.
     *
     * @var non-empty-list<string>
     */
    public const VIEWS = ['month', 'week', 'day', 'year'];

    /**
     * The calendars the screen draws. `?calendars=` lists the visible ones; without it all of them
     * show, and `none` hides every one.
     *
     * @var non-empty-list<string>
     */
    public const CALENDARS = ['projects', 'epics', 'personal'];

    /**
     * No domain permission: the personal calendar belongs to every signed-in user, and what the
     * domain calendars draw is narrowed by their own policies in the query.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The state is a link, not user data to be corrected: a stale or hand-edited value lands on the
     * default instead of bouncing the user back with a validation error.
     */
    protected function prepareForValidation(): void
    {
        $values = [];

        if (! in_array($this->query('view'), self::VIEWS, true)) {
            $values['view'] = self::VIEWS[0];
        }

        $date = $this->query('date');

        if (! is_string($date) || CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d') === false
            || CarbonImmutable::createFromFormat('Y-m-d', $date)?->toDateString() !== $date) {
            $values['date'] = null;
        }

        $this->merge($values);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'calendars' => ['nullable', 'string'],
        ];
    }

    public function view(): string
    {
        $view = $this->string('view')->toString();

        return in_array($view, self::VIEWS, true) ? $view : self::VIEWS[0];
    }

    public function day(): CarbonImmutable
    {
        $date = $this->string('date')->toString();

        return $date === '' ? CarbonImmutable::today() : CarbonImmutable::parse($date)->startOfDay();
    }

    /**
     * @return list<string>
     */
    public function calendars(): array
    {
        if (! $this->filled('calendars')) {
            return self::CALENDARS;
        }

        $asked = explode(',', $this->string('calendars')->toString());

        return array_values(array_filter(self::CALENDARS, static fn (string $calendar): bool => in_array($calendar, $asked, true)));
    }
}

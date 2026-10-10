<x-basics13::forms.tracked-resource prefix="epic" context-field="_epic_form" id-field="_epic_id">
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\EpicRequest::class" field="name"
            test="epic-name-info" for="epic-name">
            {{ __('Name') }}
        </x-forms.field-label>
        <flux:input name="name" x-model="form.name" minlength="4"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required
            autofocus id="epic-name" data-test="epic-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\EpicRequest::class" field="project_id"
            test="epic-project-info" for="epic-project-id">
            {{ __('Project') }}
        </x-forms.field-label>
        <flux:select id="epic-project-id" name="project_id" x-model="form.project_id" required
            x-on:focus="searchProjects()" x-bind:aria-busy="projectOptionsLoading"
            data-test="epic-project">
            <option value="">{{ __('Select a project') }}</option>
            <template x-for="project in projectOptions" :key="project.id">
                <option x-bind:value="String(project.id)" x-text="project.text"></option>
            </template>
        </flux:select>
        <flux:error name="project_id" />
        <flux:text x-show="projectOptionsLoading" role="status" data-test="epic-project-loading">
            {{ __('Loading records...') }}
        </flux:text>
        <flux:text x-show="projectOptionsSearched && !projectOptionsLoading && !projectOptionsError && projectOptionsResultCount === 0"
            role="status" data-test="epic-project-empty">
            {{ __('No projects match your search.') }}
        </flux:text>
        <flux:text x-show="projectOptionsError" x-text="projectOptionsError" role="alert"
            data-test="epic-project-error" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\EpicRequest::class" field="start_date"
            test="epic-start-date-info" for="epic-start-date" :labels="['end_date' => __('End date')]">
            {{ __('Start date') }}
        </x-forms.field-label>
        <flux:input type="date" name="start_date" x-model="form.start_date"
            x-bind:required="form.end_date !== ''" id="epic-start-date" data-test="epic-start-date" />
        <flux:error name="start_date" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\EpicRequest::class" field="end_date"
            test="epic-end-date-info" for="epic-end-date" :labels="['start_date' => __('Start date')]">
            {{ __('End date') }}
        </x-forms.field-label>
        <flux:input id="epic-end-date" type="date" name="end_date" x-model="form.end_date"
            x-bind:min="window.addDays(form.start_date, 1)" data-test="epic-end-date" />
        <flux:error name="end_date" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\EpicRequest::class" field="notes"
            test="epic-notes-info" for="epic-notes">
            {{ __('Notes') }}
        </x-forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="epic-notes" data-test="epic-notes" />
        <flux:error name="notes" />
    </flux:field>

    <x-slot:after>
        <template x-if="form.method === 'PUT'">
            <section class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60 sm:p-5"
                aria-labelledby="epic-comments-heading" data-test="epic-comments">
                <flux:heading size="lg" id="epic-comments-heading">{{ __('Comments') }}
                </flux:heading>

                <form method="POST" x-bind:action="form.commentAction"
                    x-data="{ isSubmitting: false, isDirty: false }"
                    x-on:form-dirty-change="isDirty = $event.detail.isDirty"
                    x-on:submit="if (!isDirty || isSubmitting) { $event.preventDefault(); return; } isSubmitting = true"
                    data-track-changes class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="_comment_epic_id" x-bind:value="form.id">
                    <flux:field>
                        <x-forms.field-label :request="\Projects13\Http\Requests\EpicCommentRequest::class" field="body"
                            test="epic-comment-body-info" for="epic-comment-body">
                            {{ __('New comment') }}
                        </x-forms.field-label>
                        <flux:textarea name="body" x-model="form.commentBody" rows="3"
                            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}" required
                            id="epic-comment-body" data-test="epic-comment-body" />
                        @error('body', 'comment')
                            <flux:text class="text-sm text-red-600" role="alert">{{ $message }}
                            </flux:text>
                        @enderror
                    </flux:field>
                    <flux:field>
                        <x-forms.field-label :request="\Projects13\Http\Requests\EpicCommentRequest::class" field="notes"
                            test="epic-comment-notes-info" for="epic-comment-notes">
                            {{ __('Notes') }}
                        </x-forms.field-label>
                        <flux:textarea name="notes" x-model="form.commentNotes" rows="3"
                            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
                            id="epic-comment-notes" data-test="epic-comment-notes" />
                        @error('notes', 'comment')
                            <flux:text class="text-sm text-red-600" role="alert">{{ $message }}
                            </flux:text>
                        @enderror
                    </flux:field>
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Unsaved epic changes are discarded when adding a comment.') }}
                    </flux:text>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" size="sm"
                            icon="chat-bubble-left" disabled
                            x-bind:disabled="isSubmitting || !isDirty"
                            data-test="epic-comment-submit">
                            {{ __('Add comment') }}
                        </flux:button>
                    </div>
                </form>

                <p x-show="form.commentsLoading" class="text-sm text-zinc-500"
                    data-test="epic-comments-loading">{{ __('Loading records...') }}</p>

                <p x-show="form.commentsError" x-text="form.commentsError" role="alert"
                    class="text-sm text-red-600" data-test="epic-comments-error"></p>

                <p x-show="!form.commentsLoading && !form.commentsError && form.comments.length === 0"
                    class="text-sm text-zinc-500" data-test="epic-comments-empty">{{ __('No comments yet.') }}</p>

                <p x-show="!form.commentsLoading && !form.commentsError && form.commentsCount > form.comments.length"
                    class="text-xs text-zinc-500" data-test="epic-comments-truncated"
                    x-text="@js(__('Showing the latest :shown of :total comments.')).replace(':shown', form.comments.length).replace(':total', form.commentsCount)">
                </p>

                <ul class="flex flex-col gap-3"
                    x-show="!form.commentsLoading && !form.commentsError && form.comments.length > 0">
                    <template x-for="comment in form.comments" :key="comment.id">
                        <li class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
                            data-test="epic-comment">
                            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300"
                                    x-text="comment.author"></span>
                                <time x-bind:datetime="comment.dateTime"
                                    x-text="window.formatLocalDateTime(comment.dateTime, 'datetime')"
                                    data-test="epic-comment-written-at"></time>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm" x-text="comment.body"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </x-slot:after>
</x-basics13::forms.tracked-resource>

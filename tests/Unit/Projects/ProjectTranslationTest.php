<?php

namespace Projects13\Tests\Unit\Projects;

use Projects13\Tests\TestCase;

class ProjectTranslationTest extends TestCase
{
    public function test_project_labels_are_available_in_all_supported_locales(): void
    {
        $expected = [
            'eu' => ['Proiektuak', 'Proiektu berria', 'Editatu proiektua'],
            'es' => ['Proyectos', 'Nuevo proyecto', 'Editar proyecto'],
            'fr' => ['Projets', 'Nouveau projet', 'Modifier le projet'],
            'en' => ['Projects', 'New project', 'Edit project'],
        ];

        foreach ($expected as $locale => [$projects, $newProject, $editProject]) {
            app()->setLocale($locale);

            $this->assertSame($projects, __('Projects'));
            $this->assertSame($newProject, __('New project'));
            $this->assertSame($editProject, __('Edit project'));
        }
    }
}

<?php

namespace Projects13\Tests\Unit\Epics;

use Projects13\Tests\TestCase;

class EpicTranslationTest extends TestCase
{
    public function test_epic_labels_are_available_in_all_supported_locales(): void
    {
        $expected = [
            'eu' => ['Epikak', 'Epika berria', 'Editatu epika'],
            'es' => ['Épicas', 'Nueva épica', 'Editar épica'],
            'fr' => ['Épopées', 'Nouvelle épopée', 'Modifier l’épopée'],
            'en' => ['Epics', 'New epic', 'Edit epic'],
        ];

        foreach ($expected as $locale => [$epics, $newEpic, $editEpic]) {
            app()->setLocale($locale);

            $this->assertSame($epics, __('Epics'));
            $this->assertSame($newEpic, __('New epic'));
            $this->assertSame($editEpic, __('Edit epic'));
        }
    }
}

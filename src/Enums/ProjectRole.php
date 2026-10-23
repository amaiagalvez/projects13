<?php

namespace Projects13\Enums;

/**
 * What a member may do on a project when the user does not hold `projects.manage`: an owner does
 * everything, including managing the members; an editor edits the project and its epics; a viewer
 * only reads.
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function edits(): bool
    {
        return $this !== self::Viewer;
    }

    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}

<?php

namespace Projects13\Tests\Unit;

use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;

class ConfiguredUserTest extends TestCase
{
    public function test_comment_relationship_rejects_a_non_eloquent_auth_model(): void
    {
        config()->set('auth.providers.users.model', \stdClass::class);
        $this->expectException(\InvalidArgumentException::class);

        (new EpicComment)->user();
    }

    public function test_comment_factory_rejects_a_non_eloquent_auth_model(): void
    {
        config()->set('auth.providers.users.model', \stdClass::class);
        $this->expectException(\InvalidArgumentException::class);

        EpicComment::factory()->make();
    }
}

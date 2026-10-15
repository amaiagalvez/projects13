<?php

namespace Projects13\Contracts;

/**
 * Default implementation of all three project integration interfaces.
 * Used when no integration package is installed.
 */
final class DefaultProjectIntegration implements ProjectDisplayIntegration, ProjectQueryIntegration, ProjectValidationIntegration
{
    use ProjectDisplayIntegrationDefaults;
    use ProjectQueryIntegrationDefaults;
    use ProjectValidationIntegrationDefaults;
}

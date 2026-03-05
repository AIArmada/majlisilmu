<?php

namespace App\Support\Authz;

use AIArmada\FilamentAuthz\Models\AuthzScope;

final class MemberRoleScopes
{
    private const string SCOPEABLE_TYPE = 'member-role-scope';

    private const string INSTITUTION_SCOPEABLE_ID = '11111111-1111-4111-8111-111111111111';

    private const string SPEAKER_SCOPEABLE_ID = '22222222-2222-4222-8222-222222222222';

    private const string EVENT_SCOPEABLE_ID = '33333333-3333-4333-8333-333333333333';

    public function institution(): AuthzScope
    {
        return $this->ensure(self::INSTITUTION_SCOPEABLE_ID, 'Institution Members');
    }

    public function speaker(): AuthzScope
    {
        return $this->ensure(self::SPEAKER_SCOPEABLE_ID, 'Speaker Members');
    }

    public function event(): AuthzScope
    {
        return $this->ensure(self::EVENT_SCOPEABLE_ID, 'Event Members');
    }

    private function ensure(string $scopeableId, string $label): AuthzScope
    {
        return AuthzScope::query()->firstOrCreate(
            [
                'scopeable_type' => self::SCOPEABLE_TYPE,
                'scopeable_id' => $scopeableId,
            ],
            [
                'label' => $label,
            ],
        );
    }
}

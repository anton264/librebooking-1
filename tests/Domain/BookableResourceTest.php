<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Domain/namespace.php');

class BookableResourceTest extends TestBase
{
    public function testStartedSlotCutoffFollowsGlobalSettingByDefault()
    {
        $resource = BookableResource::CreateNew('Room', 1);

        $this->assertFalse($resource->AllowsStartedSlotBooking());
        $this->assertNull($resource->GetStartedSlotCutoffMinutes());
    }

    public function testStartedSlotCutoffIsStoredInAdditionalProperties()
    {
        $resource = BookableResource::CreateNew('Room', 1);
        $resource->SetStartedSlotCutoffMinutes(15);

        $loaded = BookableResource::CreateNew('Room', 1);
        $loaded->DeserializeProperties($resource->GetSerializedProperties());

        $this->assertTrue($loaded->AllowsStartedSlotBooking());
        $this->assertSame(15, $loaded->GetStartedSlotCutoffMinutes());
    }

    public function testLegacyPropertiesWithoutStartedSlotCutoffFollowGlobalSetting()
    {
        $resource = BookableResource::CreateNew('Room', 1);
        $resource->DeserializeProperties('{"MaxConcurrentReservations":2}');

        $this->assertNull($resource->GetStartedSlotCutoffMinutes());
        $this->assertSame(2, $resource->GetMaxConcurrentReservations());
    }

    public function testNegativeStartedSlotCutoffIsTreatedAsZero()
    {
        $resource = BookableResource::CreateNew('Room', 1);
        $resource->SetStartedSlotCutoffMinutes(-5);

        $this->assertSame(0, $resource->GetStartedSlotCutoffMinutes());
    }
}

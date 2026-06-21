<?php

namespace Tests\Unit;

use App\Services\IdpItemReviewWorkflow;
use PHPUnit\Framework\TestCase;

class IdpItemReviewWorkflowTest extends TestCase
{
    public function test_it_returns_configured_steps_in_order(): void
    {
        $owner = (object) [
            'supervisor_id_1' => 11,
            'supervisor_id_2' => 22,
            'supervisor_id_3' => 33,
        ];
        $workflow = new IdpItemReviewWorkflow();

        $this->assertSame([1, 2, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(1, $workflow->firstStep($owner));
        $this->assertSame(2, $workflow->nextStep($owner, 1));
        $this->assertSame(3, $workflow->nextStep($owner, 2));
        $this->assertNull($workflow->nextStep($owner, 3));
    }

    public function test_it_skips_empty_steps(): void
    {
        $owner = (object) [
            'supervisor_id_1' => 11,
            'supervisor_id_2' => null,
            'supervisor_id_3' => 33,
        ];
        $workflow = new IdpItemReviewWorkflow();

        $this->assertSame([1, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(3, $workflow->nextStep($owner, 1));
    }
}

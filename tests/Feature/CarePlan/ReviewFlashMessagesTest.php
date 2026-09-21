<?php

declare(strict_types=1);

namespace Tests\Feature\CarePlan;

use App\Livewire\CarePlan\CarePlanUpdate;
use App\Livewire\Components\FlashMessage;
use App\Livewire\Concerns\InteractsWithFlashMessages;
use App\Models\CarePlan;
use App\Repositories\CarePlanRepository;
use Livewire\Component;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ReviewFlashMessagesTest extends TestCase
{
    public function test_ajax_uses_the_mounted_toast_without_leaking_session_flash(): void
    {
        Livewire::test(ReviewFlashHarness::class)->call('notify')
            ->assertDispatchedTo(FlashMessage::class, 'flashMessage', ['message' => 'Check the draft', 'type' => 'warning']);
        $this->assertFalse(session()->has('warning'));
        Livewire::test(FlashMessage::class)->assertDontSee('Check the draft');
    }

    public function test_redirect_uses_session_without_dispatch_and_is_consumed_once(): void
    {
        Livewire::test(ReviewFlashHarness::class)->call('saveAndRedirect')
            ->assertRedirect('/')->assertNotDispatched('flashMessage');
        $this->assertSame('Saved', session('info'));
        Livewire::test(FlashMessage::class)->assertSee('Saved')->assertSet('type', 'info');
        Livewire::test(FlashMessage::class)->assertDontSee('Saved');
    }

    public function test_warning_and_repeated_messages_render_with_a_new_toast_key(): void
    {
        $toast = Livewire::test(FlashMessage::class);
        $toast->dispatch('flashMessage', ['message' => 'Check the draft', 'type' => 'warning'])
            ->assertSee('Check the draft')->assertSet('notificationId', 1);
        $toast->dispatch('flashMessage', ['message' => 'Check the draft', 'type' => 'warning'])
            ->assertSee('Check the draft')->assertSet('notificationId', 2);
    }

    public function test_delete_action_keeps_its_message_for_the_redirect(): void
    {
        foreach (['draft' => 'success', 'active' => 'error'] as $status => $type) {
            $plan = Mockery::mock(CarePlan::class)->makePartial();
            $plan->exists = true;
            $plan->status = $status;
            $plan->setRelation('encounter', null);
            $plan->shouldReceive('delete')->times($status === 'draft' ? 1 : 0)->andReturn(true);
            $component = new ReviewCarePlanDeleteHarness();
            $component->carePlan = $plan;
            $component->personId = 1;

            $component->delete(app(CarePlanRepository::class));

            $this->assertSame('persons.care-plans', $component->redirectedRoute);
            $this->assertSame([], $component->outcomes);
            $message = $status === 'draft'
                ? __('Чернетку плану лікування успішно видалено.')
                : __('Можна видаляти лише чернетки планів лікування.');
            $this->assertSame($message, session($type));
            Livewire::test(FlashMessage::class)->assertSee($message)->assertSet('type', $type);
        }
    }
}

class ReviewCarePlanDeleteHarness extends CarePlanUpdate
{
    public ?string $redirectedRoute = null;

    public array $outcomes = [];

    public function redirectRoute($name, $parameters = [], $absolute = true, $navigate = false)
    {
        $this->redirectedRoute = $name;
    }

    protected function flashOutcome(string $type, string $message): void
    {
        $this->outcomes[] = [$type, $message];
    }
}

class ReviewFlashHarness extends Component
{
    use InteractsWithFlashMessages;

    public function notify(): void
    {
        $this->flashOutcome('warning', 'Check the draft');
    }

    public function saveAndRedirect(): void
    {
        session()->flash('info', 'Saved');
        $this->redirect('/', navigate: true);
    }

    public function render(): string
    {
        return '<div>Notification test</div>';
    }
}

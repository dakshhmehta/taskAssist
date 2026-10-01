<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\RelationManagers\TasksRelationManager;
use App\Filament\Widgets\MyUpcomingTasks;
use App\Filament\Widgets\UserTasksLists;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCommentCountIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $assignee, string $title, ?string $dueDate = null): Task
    {
        return Task::create([
            'title' => $title,
            'assignee_id' => $assignee->id,
            'due_date' => $dueDate ?? now()->addDay()->format('Y-m-d H:i:s'),
            'estimate' => 60,
        ]);
    }

    private function addComments(Task $task, User $author, int $count): void
    {
        foreach (range(1, $count) as $i) {
            $task->filamentComments()->create([
                'subject_type' => $task->getMorphClass(),
                'comment' => "Comment {$i}",
                'user_id' => $author->id,
            ]);
        }
    }

    public function test_my_upcoming_tasks_widget_labels_the_comments_action_with_the_count(): void
    {
        $user = User::factory()->create();

        $withComments = $this->makeTask($user, 'Has comments');
        $this->addComments($withComments, $user, 3);

        $withoutComments = $this->makeTask($user, 'No comments');

        $this->actingAs($user);

        Livewire::test(MyUpcomingTasks::class)
            ->assertTableActionExists('comments')
            ->assertTableActionHasLabel('comments', 'Comment (3)', $withComments->id)
            ->assertTableActionHasLabel('comments', 'Comment (0)', $withoutComments->id);
    }

    public function test_user_tasks_list_widget_labels_the_comments_action_with_the_count(): void
    {
        $user = User::factory()->create();

        $withComments = $this->makeTask($user, 'Has comments', now()->subMinutes(5)->format('Y-m-d H:i:s'));
        $this->addComments($withComments, $user, 2);

        $withoutComments = $this->makeTask($user, 'No comments', now()->subMinutes(5)->format('Y-m-d H:i:s'));

        $this->actingAs($user);

        Livewire::test(UserTasksLists::class)
            ->assertTableActionExists('comments')
            ->assertTableActionHasLabel('comments', 'Comment (2)', $withComments->id)
            ->assertTableActionHasLabel('comments', 'Comment (0)', $withoutComments->id);
    }

    public function test_tasks_resource_list_labels_the_comments_action_with_the_count(): void
    {
        $user = User::factory()->create();

        $withComments = $this->makeTask($user, 'Has comments');
        $this->addComments($withComments, $user, 4);

        $withoutComments = $this->makeTask($user, 'No comments');

        $this->actingAs($user);

        Livewire::test(ListTasks::class)
            ->assertTableActionExists('comments')
            ->assertTableActionHasLabel('comments', 'Comment (4)', $withComments->id)
            ->assertTableActionHasLabel('comments', 'Comment (0)', $withoutComments->id);
    }

    public function test_tag_tasks_relation_manager_labels_the_comments_action_with_the_count(): void
    {
        $user = User::factory()->create();

        $task = $this->makeTask($user, 'Has comments');
        $this->addComments($task, $user, 2);

        $tag = Tag::findOrCreate('Web');
        $task->attachTag($tag);

        $this->actingAs($user);

        Livewire::test(TasksRelationManager::class, [
            'ownerRecord' => $tag,
            'pageClass' => EditTag::class,
        ])
            ->assertTableActionExists('comments')
            ->assertTableActionHasLabel('comments', 'Comment (2)', $task->id);
    }

    public function test_task_edit_page_labels_the_comments_action_with_the_count(): void
    {
        $user = User::factory()->create();

        $task = $this->makeTask($user, 'Has comments');
        $this->addComments($task, $user, 5);

        $this->actingAs($user);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertActionExists('comments')
            ->assertActionHasLabel('comments', 'Comment (5)');
    }
}

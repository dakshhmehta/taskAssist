<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Models\UserLeave;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListLeaves extends Tool
{
    protected string $title = 'List Leaves';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List leave records with the same scoping, filters, ordering and pagination as the Filament Leave resource. Non-admin users only see their own leaves. Also returns the leave codes and the CL balance for the selected user.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('Filter by user ID. Defaults to the logged-in user; admins may pass any user ID to list that user\'s leaves.'),
            'all_users' => $schema->boolean()->description('Admins only: list leaves for every user instead of a single user.'),
            'code' => $schema->string()->description('Filter by leave code (CL, PL, SL).'),
            'status' => $schema->string()->description('Filter by status: NEW, APPROVED or REJECTED.'),
            'from_date' => $schema->string()->description('Only include leaves starting on or after this date (Y-m-d).'),
            'to_date' => $schema->string()->description('Only include leaves starting on or before this date (Y-m-d).'),
            'page' => $schema->integer()->description('The page of results to return (defaults to 1)'),
            'per_page' => $schema->integer()->description('The number of leaves per page (defaults to 25, max 100)'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $authUser = Auth::user();

        if (! $authUser) {
            return Response::error('No authenticated user.');
        }

        $isAdmin = (bool) $authUser->is_admin;
        $perPage = (int) ($request->get('per_page') ?? 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;
        $page = max((int) ($request->get('page') ?? 1), 1);

        $userId = $request->get('user_id');
        $allUsers = (bool) $request->get('all_users') && $isAdmin;

        if (! $isAdmin) {
            // Mirror the Filament table query: non-admins are locked to their own leaves.
            $userId = $authUser->id;
            $allUsers = false;
        }

        $query = UserLeave::query()
            ->with(['user:id,name', 'approvedByUser:id,name'])
            ->when(! $allUsers && $userId, fn ($q) => $q->where('user_id', $userId))
            ->when($request->get('code'), fn ($q, $code) => $q->where('code', $code))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('from_date'), fn ($q, $date) => $q->whereDate('from_date', '>=', $date))
            ->when($request->get('to_date'), fn ($q, $date) => $q->whereDate('from_date', '<=', $date))
            ->orderBy('from_date', 'DESC');

        $leaves = $query->paginate($perPage, ['*'], 'page', $page);

        $leaveTypes = config('leave_types');

        return Response::json([
            'status' => 'success',
            'leave_types' => $leaveTypes,
            'cl_earliest_date' => UserLeave::earliestClDate()->toDateString(),
            'cl_advance_notice' => UserLeave::clAdvanceNoticeMessage(),
            'balance' => $this->balanceFor($userId, $allUsers),
            'leaves' => $leaves->getCollection()->map(fn (UserLeave $leave) => [
                'id' => $leave->id,
                'user_id' => $leave->user_id,
                'user_name' => $leave->user?->name,
                'code' => $leave->code,
                'status' => $leave->status,
                'from_date' => $leave->from_date?->format('Y-m-d'),
                'to_date' => $leave->to_date?->format('Y-m-d'),
                'half_day' => (bool) $leave->half_day,
                'leave_days' => $leave->leave_days,
                'remarks' => $leave->remarks,
                'admin_remarks' => $leave->admin_remarks,
                'approved_at' => $leave->approved_at?->format('Y-m-d H:i:s'),
                'approved_by' => $leave->approvedByUser?->name,
                'created_at' => $leave->created_at?->format('Y-m-d H:i:s'),
            ])->toArray(),
            'pagination' => [
                'current_page' => $leaves->currentPage(),
                'per_page' => $leaves->perPage(),
                'total' => $leaves->total(),
                'last_page' => $leaves->lastPage(),
                'has_more' => $leaves->hasMorePages(),
            ],
        ]);
    }

    /**
     * Leave balances for the scoped user, mirroring the CL availability shown in the form/table.
     */
    protected function balanceFor(?int $userId, bool $allUsers): array
    {
        if ($allUsers || ! $userId) {
            return [];
        }

        $user = User::find($userId);

        if (! $user) {
            return [];
        }

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'cl' => (float) $user->balance(['cl']),
        ];
    }
}

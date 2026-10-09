<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\BusinessResource;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(User::query(), $request, ['name', 'email']);
    }

    public function store(UserRequest $request): BusinessResource
    {
        return new BusinessResource(DB::transaction(fn () => User::create($request->validated())));
    }

    public function show(User $user): BusinessResource
    {
        return new BusinessResource($user);
    }

    public function update(UserRequest $request, User $user): BusinessResource
    {
        return DB::transaction(function () use ($request, $user): BusinessResource {
            $owners = User::query()->where('role', 'owner')->orderBy('id')->lockForUpdate()->get();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $data = $request->validated();
            if ($user->role === 'owner' && ($data['role'] ?? 'owner') !== 'owner' && $owners->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Minimal satu owner harus tetap tersedia.']);
            }
            if ($user->role === 'mekanik' && ($data['role'] ?? 'mekanik') !== 'mekanik' && WorkOrder::query()->where('mechanic_id', $user->id)->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw ValidationException::withMessages(['role' => 'Mekanik masih memiliki work order aktif.']);
            }
            $user->update($data);
            if (isset($data['password']) || isset($data['role'])) {
                $user->tokens()->delete();
            }

            return new BusinessResource($user->refresh());
        }, 3);
    }

    public function destroy(Request $request, User $user): Response
    {
        abort_if($request->user()->id === $user->id, 422, 'Akun yang sedang digunakan tidak dapat dihapus.');
        DB::transaction(function () use ($user): void {
            $owners = User::query()->where('role', 'owner')->orderBy('id')->lockForUpdate()->get();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->role === 'owner' && $owners->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Minimal satu owner harus tetap tersedia.']);
            }
            $user->tokens()->delete();
            $user->delete();
        }, 3);

        return response()->noContent();
    }
}

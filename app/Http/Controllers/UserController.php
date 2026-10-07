<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::orderBy('id')->paginate($request->integer('per_page', 15))->withQueryString()
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }
}

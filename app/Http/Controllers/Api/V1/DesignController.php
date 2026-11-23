<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDesignRequest;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DesignController extends Controller
{
    /**
     * List saved designs, most recently edited first.
     */
    public function index(): AnonymousResourceCollection
    {
        return DesignResource::collection(Design::query()->latest('updated_at')->latest('id')->get());
    }

    public function store(StoreDesignRequest $request): DesignResource
    {
        $design = Design::create($request->validated());

        return (new DesignResource($design))->withCalculation();
    }

    public function show(Design $design): DesignResource
    {
        return (new DesignResource($design))->withCalculation();
    }

    public function update(StoreDesignRequest $request, Design $design): DesignResource
    {
        $design->update($request->validated());

        return (new DesignResource($design))->withCalculation();
    }

    public function destroy(Design $design): Response
    {
        $design->delete();

        return response()->noContent();
    }
}

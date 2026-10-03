<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, private int $statusCode = 200)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'],
            'data' => [
                'user' => UserResource::make($this->resource['user']),
                'token' => $this->resource['token'],
            ],
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode($this->statusCode);
    }
}
